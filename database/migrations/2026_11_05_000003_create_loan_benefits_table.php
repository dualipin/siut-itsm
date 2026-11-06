<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_benefits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('loans')->cascadeOnDelete();
            $table->foreignId('benefit_id')->constrained('benefits')->restrictOnDelete();
            $table->decimal('requested_amount', 12, 2);
            $table->decimal('approved_amount', 12, 2)->nullable();
            $table->date('collection_date');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_benefits');
    }
};
