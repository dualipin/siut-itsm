<?php

namespace App\Filament\Resources\Loans\Schemas;

use App\Models\Loan;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

class LoanInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.loans.loan-detail-view')
                ->columnSpanFull()
                ->viewData(static function (Loan $record): array {
                    $record->loadMissing([
                        'user',
                        'reviewer',
                        'disburser',
                        'loanBenefits.benefit',
                        'evidences.benefit',
                        'evidences.reviewer',
                        'amortizationRows',
                        'financialSummary',
                        'generatedDocuments.generator',
                        'statusHistories.user',
                    ]);

                    return [
                        'loan' => $record,
                        'user' => $record->user,
                        'evidences' => $record->evidences,
                        'amortizationRows' => $record->amortizationRows,
                        'financialSummary' => $record->financialSummary,
                        'generatedDocs' => $record->generatedDocuments->groupBy(fn ($d) => $d->document_type->value),
                        'statusHistories' => $record->statusHistories,
                        'canReview' => auth()->user()?->isLeaderOrAdmin() ?? false,
                    ];
                }),
        ]);
    }
}
