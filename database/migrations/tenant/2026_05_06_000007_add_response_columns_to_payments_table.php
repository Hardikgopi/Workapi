<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection('tenant')->table('payments', function (Blueprint $table) {
            $table->unsignedBigInteger('amount_paid')->default(0)->after('amount');
            $table->unsignedBigInteger('amount_due')->default(0)->after('amount_paid');
            $table->timestamp('cancelled_at')->nullable()->after('paid_at');
            $table->timestamp('expired_at')->nullable()->after('cancelled_at');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('payments', function (Blueprint $table) {
            $table->dropColumn(['amount_paid', 'amount_due', 'cancelled_at', 'expired_at']);
        });
    }
};

