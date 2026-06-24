<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection('tenant')->create('notes', function (Blueprint $table) {
            $table->id();
            $table->text('content');
            $table->string('related_type')->nullable(); // lead, ticket, contact, task
            $table->unsignedBigInteger('related_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable(); // tenant user id
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('notes');
    }
};
