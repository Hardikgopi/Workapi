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
        Schema::connection('tenant')->create('custom_fields', function (Blueprint $table) {
            $table->id();
            $table->string('module'); // e.g. 'leads', 'tickets', 'contacts'
            $table->string('name'); // internal key: e.g. 'industry_type' 
            $table->string('label'); // UI label: e.g. 'Industry Type'
            $table->string('ui_type'); // text, number, select, date, checkbox, radio, file
            $table->json('options')->nullable(); // JSON array of options if select/radio
            $table->boolean('is_required')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('custom_fields');
    }
};
