<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table): void {
            $table->unsignedSmallInteger('fiscal_year')->nullable()->index()->after('folio');
        });

        DB::table('loans')
            ->whereNull('fiscal_year')
            ->select(['id', 'first_fortnight_date', 'created_at'])
            ->orderBy('id')
            ->chunkById(200, function (Collection $rows): void {
                foreach ($rows as $row) {
                    $sourceDate = $row->first_fortnight_date ?? $row->created_at;
                    $fiscalYear = $sourceDate !== null
                        ? Carbon::parse((string) $sourceDate)->year
                        : now()->year;

                    DB::table('loans')
                        ->where('id', $row->id)
                        ->update(['fiscal_year' => $fiscalYear]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table): void {
            $table->dropIndex(['fiscal_year']);
            $table->dropColumn('fiscal_year');
        });
    }
};
