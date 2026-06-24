<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tables = ['leads', 'tickets', 'contacts', 'tasks'];
        
        foreach ($tables as $tableName) {
            Schema::connection('tenant')->table($tableName, function (Blueprint $table) {
                $table->json('custom_data')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = ['leads', 'tickets', 'contacts', 'tasks'];
        
        foreach ($tables as $tableName) {
            Schema::connection('tenant')->table($tableName, function (Blueprint $table) {
                $table->dropColumn('custom_data');
            });
        }
    }
};
