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
        Schema::create('inquiries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('guest_name', 150)->nullable();
            $table->string('guest_email', 255)->nullable();
            $table->string('title', 255);
            $table->string('slug', 255)->unique();
            $table->text('body');
            $table->string('category', 100)->nullable()->index();
            $table->boolean('is_public')->default(false)->index();
            $table->string('status', 30)->default('pending')->index();
            $table->unsignedInteger('views_count')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inquiries');
    }
};
