<?php

namespace App\Filament\Widgets;

use App\Enums\DocumentStatus;
use App\Enums\LoanStatus;
use App\Enums\RequestStatus;
use App\Enums\UserDocumentType;
use App\Filament\Pages\Messages;
use App\Filament\Pages\Profile;
use App\Filament\Resources\Loans\LoanResource;
use App\Filament\Resources\UserRequests\UserRequestResource;
use App\Models\ConversationMessage;
use App\Models\Loan;
use App\Models\User;
use App\Models\UserRequest;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class AgremiadoStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return auth()->user()?->isAgremiado() ?? false;
    }

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return [];
        }

        return [
            $this->buildDocumentsStat($user),
            $this->buildLoanStat($user),
            $this->buildRequestsStat($user),
            $this->buildMessagesStat($user),
        ];
    }

    protected function buildDocumentsStat(User $user): Stat
    {
        $user->loadMissing('media');

        $documentTypes = UserDocumentType::cases();
        $validDocumentsCount = collect($documentTypes)
            ->filter(fn (UserDocumentType $type): bool => $user->getDocumentStatus($type) === DocumentStatus::Valid)
            ->count();
        $overallStatus = $user->getOverallDocumentStatus();

        return Stat::make('Expediente documental', "{$validDocumentsCount}/".count($documentTypes))
            ->icon(Heroicon::OutlinedDocumentCheck)
            ->description($overallStatus->getLabel())
            ->descriptionIcon($overallStatus->getIcon())
            ->descriptionColor($overallStatus->getColor())
            ->url(Profile::getUrl());
    }

    protected function buildLoanStat(User $user): Stat
    {
        $activeLoan = Loan::forUser($user)->active()->latest('id')->first();

        if (! $activeLoan) {
            return Stat::make('Préstamo activo', 'Sin préstamo activo')
                ->icon(Heroicon::OutlinedBanknotes)
                ->description('Consulta o solicita un préstamo')
                ->descriptionColor('gray')
                ->url(LoanResource::getUrl('index'));
        }

        $loanAmount = (float) ($activeLoan->disbursed_amount ?? $activeLoan->approved_amount ?? $activeLoan->requested_amount);
        $description = "{$activeLoan->folio} · \$".number_format($loanAmount, 2);

        if ($activeLoan->status === LoanStatus::Desembolsada) {
            $nextPayment = $activeLoan->amortizationRows()
                ->whereDate('date', '>=', now()->toDateString())
                ->reorder('date')
                ->orderBy('period')
                ->first();

            if ($nextPayment) {
                $description .= ' · Próx. pago: '.$nextPayment->date->format('d/m/Y').' ($'.number_format((float) $nextPayment->payment, 2).')';
            }
        }

        return Stat::make('Préstamo activo', $activeLoan->status->getLabel() ?? '')
            ->icon(Heroicon::OutlinedBanknotes)
            ->description($description)
            ->descriptionColor($activeLoan->status->getColor())
            ->url(LoanResource::getUrl('view', ['record' => $activeLoan]));
    }

    protected function buildRequestsStat(User $user): Stat
    {
        $inProgressCount = UserRequest::forUser($user->id)
            ->whereIn('status', [RequestStatus::Pending, RequestStatus::UnderReview])
            ->count();

        return Stat::make('Solicitudes en curso', (string) $inProgressCount)
            ->icon(Heroicon::OutlinedClipboardDocumentList)
            ->description($inProgressCount === 0 ? 'Sin solicitudes pendientes' : 'Pendientes o en revisión')
            ->descriptionColor($inProgressCount > 0 ? 'warning' : 'gray')
            ->url(UserRequestResource::getUrl('index'));
    }

    protected function buildMessagesStat(User $user): Stat
    {
        $unreadCount = ConversationMessage::query()
            ->join('conversation_participants', 'conversation_participants.conversation_id', '=', 'conversation_messages.conversation_id')
            ->where('conversation_participants.user_id', $user->id)
            ->where('conversation_messages.sender_id', '!=', $user->id)
            ->where(function (Builder $query): void {
                $query->whereNull('conversation_participants.last_read_at')
                    ->orWhereColumn('conversation_messages.created_at', '>', 'conversation_participants.last_read_at');
            })
            ->count();

        return Stat::make('Mensajes sin leer', (string) $unreadCount)
            ->icon(Heroicon::OutlinedChatBubbleLeftRight)
            ->description($unreadCount === 0 ? 'Bandeja al día' : 'Por leer en tus conversaciones')
            ->descriptionColor($unreadCount > 0 ? 'info' : 'gray')
            ->url(Messages::getUrl());
    }
}
