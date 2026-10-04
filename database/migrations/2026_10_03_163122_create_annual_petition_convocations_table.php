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
        Schema::create('annual_petition_convocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('annual_petition_id')
                ->constrained('annual_petitions')
                ->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('annual_petition_convocations');
    }
};
