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
        Schema::create('petition_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('annual_petition_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('curp', 18)->index();
            $table->text('agremiado_name')->nullable();
            $table->text('proposal');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('petition_requests');
    }
};
