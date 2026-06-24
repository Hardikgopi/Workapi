<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Tenant;
use App\Services\TenantManager;
use App\Services\AuthService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

class AuthController extends Controller
{
    protected $tenantManager;
    protected $authService;

    public function __construct(TenantManager $tenantManager, AuthService $authService)
    {
        $this->tenantManager = $tenantManager;
        $this->authService = $authService;
    }

    /**
     * Product Owner creates a tenant + admin
     */
    public function createCustomerCompany(Request $request)
    {
        $request->validate([
            'company_name' => 'required|string|unique:tenants,name',
            'admin_name' => 'required|string',
            'admin_email' => 'required|email',
            'admin_password' => 'required|string|min:6',
        ]);

        // 1. Create global tenant record
        $tenant = Tenant::create(['name' => $request->company_name]);

        // 2. Create tenant database and run migrations via Service
        $this->tenantManager->createTenant($request->company_name);

        // 3. Create the admin user in the new tenant database
        $adminId = $this->authService->registerTenantUser([
            'name' => $request->admin_name,
            'email' => $request->admin_email,
            'password' => $request->admin_password,
            'role' => 'admin',
        ]);

        return response()->json([
            'message' => 'Tenant and Admin created successfully',
            'tenant_id' => $tenant->id,
            'admin_id' => $adminId
        ]);
    }

    /**
     * Tenant Login
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'company_name' => 'required|string'
        ]);

        // Switch to the correct tenant database
        $this->tenantManager->switchToTenant($request->company_name);

        // Authenticate user
        $authData = $this->authService->loginTenantUser($request->email, $request->password);

        if (!$authData) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        return response()->json([
            'message' => 'Login successful',
            'user' => $authData['user'],
            'token' => $authData['token'],
            'company_name' => $request->company_name
        ]);
    }

    /**
     * Register (Add Employee)
     */
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'email' => 'required|email',
            'password' => 'required|string|min:6',
            'role' => 'required|string',
        ]);

        $userId = $this->authService->registerTenantUser($request->all());

        return response()->json([
            'message' => 'User registered successfully',
            'user_id' => $userId
        ]);
    }

    /**
     * Logout
     */
    public function logout(Request $request)
    {
        if ($token = $request->bearerToken()) {
            $this->authService->revokeTenantToken($token);
        }

        return response()->json(['message' => 'Logged out successfully']);
    }

    /**
     * Update FCM Token
     */
    public function updateFcmToken(Request $request)
    {
        $request->validate([
            'fcm_token' => 'required|string',
        ]);

        $user = $request->attributes->get('tenant_user');

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        DB::connection('tenant')
            ->table('users')
            ->where('id', $user->id)
            ->update(['fcm_token' => $request->fcm_token]);

        return response()->json(['message' => 'FCM token updated successfully']);
    }

    /**
     * List all tenant users (for dropdowns like Assigned To)
     */
    public function listUsers(Request $request)
    {
        $users = DB::connection('tenant')
            ->table('users')
            ->select('id', 'name', 'email', 'role')
            ->orderBy('name')
            ->get();

        return response()->json($users);
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required'
        ]);

        if ($request->email !== 'gopinathm577@gmail.com') {
            return response()->json([
                'message' => 'Unauthorized email'
            ], 403);
        }

        $storedOtp = cache()->get('owner_otp');

        if (!$storedOtp || $storedOtp != $request->otp) {
            return response()->json([
                'message' => 'Invalid OTP'
            ], 400);
        }

        cache()->forget('owner_otp');

        return response()->json([
            'message' => 'OTP verified'
        ]);
    }
    public function sendOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email'
        ]);

        $allowedEmail = 'gopinathm577@gmail.com';

        if ($request->email !== $allowedEmail) {
            return response()->json([
                'message' => 'Unauthorized email address'
            ], 403);
        }

        $otp = rand(100000, 999999);

        cache()->put('owner_otp', $otp, now()->addMinutes(5));

        // Send email
        Mail::raw("Your OTP is: $otp", function ($message) use ($allowedEmail) {
            $message->to($allowedEmail)
                    ->subject('Owner Login OTP');
        });

        return response()->json([
            'message' => 'OTP sent successfully'
        ]);
    }
}