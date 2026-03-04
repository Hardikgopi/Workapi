<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TenantTokenMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $companyName = $request->header('X-Company-Name');
        $token = $request->bearerToken();

        if (!$companyName) {
            return response()->json(['message' => 'X-Company-Name header is required'], 400);
        }

        if (!$token) {
            return response()->json(['message' => 'Bearer token is required'], 401);
        }

        // 1️⃣ Connect to the tenant database
        $dbName = 'tenant_' . preg_replace('/\W+/', '_', strtolower($companyName));
        
        // Check if database exists (optional but safer)
        // For simplicity, we assume it exists or will fail on connection
        
        config(['database.connections.tenant.database' => $dbName]);
        DB::purge('tenant');
        DB::reconnect('tenant');

        // 2️⃣ Verify the token
        $hashedToken = hash('sha256', $token);
        
        $tokenRecord = DB::connection('tenant')
            ->table('personal_access_tokens')
            ->where('token', $hashedToken)
            ->first();

        if (!$tokenRecord) {
            return response()->json(['message' => 'Invalid or expired token'], 401);
        }

        // 3️⃣ Get the user and attach to request
        $user = DB::connection('tenant')
            ->table('users')
            ->where('id', $tokenRecord->tokenable_id)
            ->first();

        if (!$user) {
            return response()->json(['message' => 'User not found'], 401);
        }

        // Manually set the user on the request if needed, or just pass along
        $request->merge(['tenant_user' => $user]);

        return $next($request);
    }
}
