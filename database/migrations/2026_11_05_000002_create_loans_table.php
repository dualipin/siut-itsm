<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->string('folio')->unique()->index();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('borrador')->index();
            $table->string('payment_type')->index(); // nomina, benefit, mixed
            $table->decimal('requested_amount', 12, 2);
            $table->decimal('approved_amount', 12, 2)->nullable();
            $table->decimal('monthly_rate', 5, 2)->nullable(); // e.g. 6.00, 7.50, 8.00, 9.50 — defined by admin upon approval
            $table->decimal('salary_commitment_amount', 12, 2)->nullable();
            $table->decimal('nomina_amount', 12, 2)->nullable();
            $table->unsignedInteger('nomina_fortnights')->nullable();
            $table->date('first_fortnight_date')->nullable();

            // Signature
            $table->mediumText('signature_data')->nullable();
            $table->string('signature_type')->default('drawn');
            $table->string('signature_hash')->nullable();
            $table->dateTime('signed_at')->nullable();
            $table->dateTime('submitted_at')->nullable();

            // Review
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('waitlist_reason')->nullable();
            $table->boolean('documents_generated')->default(false);
            $table->json('pdf_generation_errors')->nullable();

            // Disbursement
            $table->date('disbursed_at')->nullable();
            $table->decimal('disbursed_amount', 12, 2)->nullable();
            $table->string('disbursement_method')->nullable(); // transferencia, cheque, efectivo
            $table->string('disbursement_reference')->nullable();
            $table->foreignId('disbursed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('disbursement_receipt_path')->nullable();

            // Bank details for applicant
            $table->string('bank_name')->nullable();
            $table->string('bank_account')->nullable();
            $table->string('interbank_clabe', 18)->nullable();

            // Active loan exception audit
            $table->boolean('exception_active_loan')->default(false);
            $table->foreignId('exception_authorized_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
