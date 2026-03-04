<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Tenant;
use App\Services\TenantManager;
use App\Services\AuthService;
use Illuminate\Support\Facades\DB;

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
}