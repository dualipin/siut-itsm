<?php

use App\Enums\LoanOperationalState;
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
        Schema::create('loan_operational_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('state')->default(LoanOperationalState::Activo->value);
            $table->unsignedSmallInteger('target_fiscal_year');
            $table->text('suspension_message')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('state_changed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loan_operational_settings', function (Blueprint $table): void {
            $table->dropForeign(['updated_by']);
        });

        Schema::dropIfExists('loan_operational_settings');
    }
};
