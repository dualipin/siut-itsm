<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Login by CURP is case-insensitive because CURPs are stored uppercase.
     * Existing rows are normalized; the column stays nullable for legacy users.
     */
    public function up(): void
    {
        DB::table('users')->where('curp', '')->update(['curp' => null]);

        DB::table('users')
            ->whereNotNull('curp')
            ->update(['curp' => DB::raw('UPPER(TRIM(curp))')]);
    }

    /**
     * The original casing cannot be recovered; normalization is intentionally irreversible.
     */
    public function down(): void
    {
        //
    }
};
