<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('benefits', function (Blueprint $table) {
            $table->string('calculation_method')->default('compound_interest')->after('collection_month');
            $table->boolean('requires_document')->default(true)->after('calculation_method');
            $table->string('document_instructions')->nullable()->after('requires_document');
        });
    }

    public function down(): void
    {
        Schema::table('benefits', function (Blueprint $table) {
            $table->dropColumn(['calculation_method', 'requires_document', 'document_instructions']);
        });
    }
};
