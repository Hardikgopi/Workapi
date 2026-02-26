<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Artisan;
use App\Models\Tenant;
use App\Models\User;

class AuthController extends Controller
{
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

        // 1️⃣ Create tenant record in main DB
        $tenant = Tenant::create(['name' => $request->company_name]);

        // 2️⃣ Generate tenant database name
        $dbName = 'tenant_' . preg_replace('/\W+/', '_', strtolower($tenant->name));

        // 3️⃣ Create DB on Aiven
        DB::statement("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        // 4️⃣ Configure dynamic tenant connection
        config([
            'database.connections.tenant.host' => env('TENANT_DB_HOST'),
            'database.connections.tenant.port' => env('TENANT_DB_PORT'),
            'database.connections.tenant.username' => env('TENANT_DB_USERNAME'),
            'database.connections.tenant.password' => env('TENANT_DB_PASSWORD'),
            'database.connections.tenant.database' => $dbName,
        ]);

        // 5️⃣ Create tenant users table dynamically
        DB::connection('tenant')->statement("
            CREATE TABLE IF NOT EXISTS users (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                role VARCHAR(50) NOT NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL
            )
        ");

        // 6️⃣ Insert admin user in tenant DB
        $adminId = DB::connection('tenant')->table('users')->insertGetId([
            'name' => $request->admin_name,
            'email' => $request->admin_email,
            'password' => Hash::make($request->admin_password),
            'role' => 'admin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Tenant created & admin added',
            'tenant_id' => $tenant->id,
            'tenant_db' => $dbName,
            'admin_id' => $adminId
        ]);
    }

    /**
     * Tenant Admin adds employee
     */
    public function register(Request $request)
    {
        $request->validate([
            'name'=>'required|string',
            'email'=>'required|email',
            'password'=>'required|string|min:6',
            'role'=>'required|in:admin,user',
            'company_name'=>'required|string'
        ]);

        $dbName = 'tenant_' . preg_replace('/\W+/', '_', strtolower($request->company_name));
        config(['database.connections.tenant.database' => $dbName]);

        $userId = DB::connection('tenant')->table('users')->insertGetId([
            'name'=>$request->name,
            'email'=>$request->email,
            'password'=>Hash::make($request->password),
            'role'=>$request->role,
            'created_at'=>now(),
            'updated_at'=>now()
        ]);

        return response()->json(['message'=>'Employee added','user_id'=>$userId]);
    }

    /**
     * Tenant login
     */
    public function login(Request $request)
    {
        $request->validate([
            'email'=>'required|email',
            'password'=>'required|string',
            'company_name'=>'required|string'
        ]);

        $dbName = 'tenant_' . preg_replace('/\W+/', '_', strtolower($request->company_name));
        config(['database.connections.tenant.database'=>$dbName]);

        $user = DB::connection('tenant')->table('users')
                    ->where('email', $request->email)->first();

        if(!$user || !Hash::check($request->password,$user->password)) {
            return response()->json(['message'=>'Invalid credentials'],401);
        }

        $token = $user->id.'_token_'.now()->timestamp;

        return response()->json([
            'message'=>'Login successful',
            'token'=>$token,
            'user'=>[
                'id'=>$user->id,
                'name'=>$user->name,
                'email'=>$user->email,
                'role'=>$user->role
            ]
        ]);
    }

    /**
     * Logout
     */
    public function logout(Request $request)
    {
        return response()->json(['message'=>'Logged out successfully']);
    }
}