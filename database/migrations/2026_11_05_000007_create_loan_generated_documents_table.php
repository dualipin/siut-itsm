<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_generated_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('loans')->cascadeOnDelete();
            $table->string('document_type'); // solicitud, pagare, anuencia, corrida_financiera
            $table->unsignedInteger('version')->default(1);
            $table->string('file_path');
            $table->string('file_hash', 64);
            $table->unsignedBigInteger('file_size')->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('generated_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_generated_documents');
    }
};
