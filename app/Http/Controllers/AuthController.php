<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\Tenant;

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
        $tenant = Tenant::create([
            'name' => $request->company_name
        ]);

        // 2️⃣ Generate tenant database name
        $dbName = 'tenant_' . preg_replace('/\W+/', '_', strtolower($tenant->name));

        // 3️⃣ Create tenant database
        DB::statement("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        // 4️⃣ Set tenant connection dynamically
        config([
            'database.connections.tenant.database' => $dbName,
        ]);

        DB::purge('tenant');
        DB::reconnect('tenant');

        // 5️⃣ Create users table inside tenant DB
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

        // 6️⃣ Insert admin user
        $adminId = DB::connection('tenant')->table('users')->insertGetId([
            'name' => $request->admin_name,
            'email' => $request->admin_email,
            'password' => Hash::make($request->admin_password),
            'role' => 'admin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Tenant created successfully',
            'tenant_id' => $tenant->id,
            'tenant_database' => $dbName,
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

        $dbName = 'tenant_' . preg_replace('/\W+/', '_', strtolower($request->company_name));

        config(['database.connections.tenant.database' => $dbName]);
        DB::purge('tenant');
        DB::reconnect('tenant');

        $user = DB::connection('tenant')
                    ->table('users')
                    ->where('email', $request->email)
                    ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        return response()->json([
            'message' => 'Login successful',
            'user' => $user
        ]);
    }

    /**
     * Logout
     */
    public function logout()
    {
        return response()->json(['message' => 'Logged out successfully']);
    }
}