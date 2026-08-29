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
        Schema::create('transparency_records', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('summary')->nullable();

            $table->integer('fiscal_year')->index();
            $table->string('period')->index();

            $table->text('observations')->nullable();

            $table->string('type')->index();
            $table->string('status')->index();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transparency_records');
    }
};
