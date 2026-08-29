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
        Schema::create('themes', function (Blueprint $table) {
            $table->integer('id')->primary()->default(1)->unique();
            $table->text('color_base_100');
            $table->text('color_base_200');
            $table->text('color_base_300');
            $table->text('color_base_content');
            $table->text('color_primary');
            $table->text('color_primary_content');
            $table->text('color_secondary');
            $table->text('color_secondary_content');
            $table->text('color_accent');
            $table->text('color_accent_content');
            $table->text('color_neutral');
            $table->text('color_neutral_content');
            $table->text('color_info');
            $table->text('color_info_content');
            $table->text('color_success');
            $table->text('color_success_content');
            $table->text('color_warning');
            $table->text('color_warning_content');
            $table->text('color_error');
            $table->text('color_error_content');
            $table->decimal('radius_box');
            $table->decimal('radius_selector');
            $table->decimal('radius_field');
            $table->decimal('size_field', 3, 2);
            $table->decimal('size_selector', 3, 2);
            $table->integer('border_width');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('themes');
    }
};
