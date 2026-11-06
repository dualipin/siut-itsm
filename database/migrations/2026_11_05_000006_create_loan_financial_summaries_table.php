<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_financial_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('loans')->cascadeOnDelete();
            $table->decimal('total_principal', 12, 2);
            $table->decimal('total_interest', 12, 2);
            $table->decimal('total_payment', 12, 2);
            $table->date('disbursement_date');
            $table->unsignedInteger('fortnights')->nullable();
            $table->date('first_fortnight')->nullable();
            $table->date('last_fortnight')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_financial_summaries');
    }
};
