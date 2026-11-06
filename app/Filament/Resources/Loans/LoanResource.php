<?php

namespace App\Filament\Resources\Loans;

use App\Enums\LoanStatus;
use App\Filament\Resources\Loans\Pages\ApplyLoan;
use App\Filament\Resources\Loans\Pages\ListLoans;
use App\Filament\Resources\Loans\Pages\ReviewLoanDocuments;
use App\Filament\Resources\Loans\Pages\ViewLoan;
use App\Filament\Resources\Loans\Tables\LoansTable;
use App\Models\Loan;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class LoanResource extends Resource
{
    protected static ?string $model = Loan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Banknotes;

    protected static ?string $recordTitleAttribute = 'folio';

    protected static ?string $modelLabel = 'Préstamo';

    protected static ?string $pluralModelLabel = 'Préstamos';

    protected static ?string $navigationLabel = 'Préstamos';

    protected static ?string $breadcrumb = 'Préstamos';

    protected static \UnitEnum|string|null $navigationGroup = 'Administración';

    public static function getNavigationBadge(): ?string
    {
        /** @var User|null $user */
        $user = auth()->user();

        if (! $user || ! $user->isLeaderOrAdmin()) {
            return null;
        }

        $count = static::getModel()::query()
            ->whereIn('status', [LoanStatus::Enviada, LoanStatus::EnRevision])
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->is_active;
    }

    public static function canCreate(): bool
    {
        return (bool) auth()->user()?->is_active;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->isLeaderOrAdmin() ?? false;
    }

    public static function table(Table $table): Table
    {
        return LoansTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLoans::route('/'),
            'apply' => ApplyLoan::route('/solicitud-prestamo'),
            'view' => ViewLoan::route('/{record}'),
            'review-documents' => ReviewLoanDocuments::route('/{record}/review-documents'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ])
            ->with(['user'])
            ->withExists('evidences');

        /** @var User|null $user */
        $user = auth()->user();

        if ($user && $user->isAgremiado()) {
            $query->where('user_id', $user->id);
        }

        return $query;
    }
}
