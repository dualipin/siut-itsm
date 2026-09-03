<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('transparency_records')->where('type', 'financiero')->update(['type' => 'FINANCIERO']);
        DB::table('transparency_records')->where('type', 'otro')->update(['type' => 'OTRO']);
        DB::table('transparency_records')->where('type', 'acta')->update(['type' => 'MINUTAS']);
        DB::table('transparency_records')->where('type', 'convenio')->update(['type' => 'LEGAL']);
        DB::table('transparency_records')->where('type', 'normativo')->update(['type' => 'ADMINISTRATIVO']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('transparency_records')->where('type', 'FINANCIERO')->update(['type' => 'financiero']);
        DB::table('transparency_records')->where('type', 'OTRO')->update(['type' => 'otro']);
        DB::table('transparency_records')->where('type', 'MINUTAS')->update(['type' => 'acta']);
        DB::table('transparency_records')->where('type', 'LEGAL')->update(['type' => 'convenio']);
        DB::table('transparency_records')->where('type', 'ADMINISTRATIVO')->update(['type' => 'normativo']);
    }
};
