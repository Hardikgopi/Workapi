<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Log;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    { 
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $firebaseCredentials = env('FIREBASE_CREDENTIALS');

        if (!$firebaseCredentials) {
            return;
        }

        $credentialsPath = storage_path('app/firebase-credentials.json');

        try {
            if (!file_exists($credentialsPath) || file_get_contents($credentialsPath) !== $firebaseCredentials) {
                file_put_contents($credentialsPath, $firebaseCredentials);
            }

            putenv("GOOGLE_APPLICATION_CREDENTIALS={$credentialsPath}");
            $_ENV['GOOGLE_APPLICATION_CREDENTIALS'] = $credentialsPath;
            $_SERVER['GOOGLE_APPLICATION_CREDENTIALS'] = $credentialsPath;
        } catch (\Throwable $e) {
            Log::error('Unable to prepare Firebase credentials file', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
