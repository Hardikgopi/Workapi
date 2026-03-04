<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthService
{
    /**
     * Authenticate a user in a specific tenant and generate a manual token.
     */
    public function loginTenantUser(string $email, string $password): ?array
    {
        $user = DB::connection('tenant')
            ->table('users')
            ->where('email', $email)
            ->first();

        if (!$user || !Hash::check($password, $user->password)) {
            return null;
        }

        $token = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $token);

        DB::connection('tenant')->table('personal_access_tokens')->insert([
            'tokenable_type' => 'App\Models\User',
            'tokenable_id' => $user->id,
            'name' => 'auth_token',
            'token' => $hashedToken,
            'abilities' => json_encode(['*']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'user' => $user,
            'token' => $token
        ];
    }

    /**
     * Register a new user in the current tenant connection.
     */
    public function registerTenantUser(array $data): int
    {
        return DB::connection('tenant')->table('users')->insertGetId([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => $data['role'] ?? 'employee',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Revoke a token in the current tenant connection.
     */
    public function revokeTenantToken(string $token): void
    {
        $hashedToken = hash('sha256', $token);
        
        DB::connection('tenant')
            ->table('personal_access_tokens')
            ->where('token', $hashedToken)
            ->delete();
    }
}
