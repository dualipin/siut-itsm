<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('transparency_documents');

        Schema::table('transparency_records', function (Blueprint $table) {
            $table->string('reference_number')->nullable()->unique()->after('name');
            $table->date('document_date')->nullable()->index()->after('period');
            $table->string('period')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('transparency_records')
            ->whereNull('period')
            ->update(['period' => 'Anual']);

        Schema::table('transparency_records', function (Blueprint $table) {
            $table->dropUnique(['reference_number']);
            $table->dropIndex(['document_date']);
            $table->dropColumn(['reference_number', 'document_date']);
            $table->string('period')->nullable(false)->change();
        });

        Schema::create('transparency_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transparency_record_id')->constrained('transparency_records')->cascadeOnDelete();
            $table->string('name');
            $table->date('published_at')->nullable()->index();
            $table->boolean('is_public')->default(true)->index();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }
};
