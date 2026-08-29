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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transparency_documents');
    }
};
