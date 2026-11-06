<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_efirma_signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->unique()->constrained('loans')->cascadeOnDelete();
            $table->longText('certificate');
            $table->string('certificate_serial', 64);
            $table->string('rfc', 13);
            $table->string('curp', 18);
            $table->string('signer_name');
            $table->timestamp('valid_from');
            $table->timestamp('valid_to');
            $table->text('original_chain');
            $table->text('seal');
            $table->string('algorithm', 32)->default('RSA-SHA256');
            $table->timestamp('signed_at');
            $table->timestamp('validated_at');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_efirma_signatures');
    }
};
