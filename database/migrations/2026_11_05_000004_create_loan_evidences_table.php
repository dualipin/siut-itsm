<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_evidences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('loans')->cascadeOnDelete();
            $table->string('source_type'); // 'nomina' or 'benefit'
            $table->foreignId('benefit_id')->nullable()->constrained('benefits')->nullOnDelete();
            $table->string('file_path');
            $table->string('file_hash', 64);
            $table->string('file_name');
            $table->string('mime_type');
            $table->unsignedBigInteger('file_size');
            $table->string('ai_status')->default('dudoso'); // 'valido', 'dudoso', 'invalido', 'fallo_tecnico'
            $table->string('ai_classification')->nullable(); // 'recibo_nomina', 'documento_prestacion', 'ilegible', 'invalido'
            $table->decimal('ai_confidence', 4, 2)->nullable();
            $table->string('ai_model')->nullable();
            $table->string('ai_prompt_version')->nullable();
            $table->json('extracted_data')->nullable();
            $table->json('comparison_data')->nullable();
            $table->string('manual_review_status')->default('pending'); // 'pending', 'approved', 'rejected'
            $table->text('manual_review_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_evidences');
    }
};
