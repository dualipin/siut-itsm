<?php

namespace App\Filament\Widgets;

use App\Enums\LoanStatus;
use App\Filament\Resources\Loans\LoanResource;
use App\Filament\Resources\UserRequests\UserRequestResource;
use App\Models\Loan;
use App\Models\User;
use App\Services\LoanOperationalStateService;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;

class AgremiadoWelcomeWidget extends Widget
{
    protected string $view = 'filament.widgets.agremiado-welcome-widget';

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return auth()->user()?->isAgremiado() ?? false;
    }

    /**
     * @return array{
     *     userName: string,
     *     primaryAction: array{
     *         label: string,
     *         description: ?string,
     *         url: ?string,
     *         icon: BackedEnum,
     *         color: string,
     *         unavailable: bool,
     *     },
     *     secondaryAction: array{
     *         label: string,
     *         url: string,
     *         icon: BackedEnum,
     *     },
     * }
     */
    protected function getViewData(): array
    {
        /** @var User|null $user */
        $user = auth()->user();

        $draftLoan = $user !== null
            ? Loan::forUser($user)->where('status', LoanStatus::Borrador)->latest('id')->first()
            : null;

        $operationalStateService = app(LoanOperationalStateService::class);
        $isOutOfService = $operationalStateService->isTemporarilyOutOfService();

        $primaryAction = match (true) {
            $isOutOfService => [
                'label' => 'Préstamos no disponibles',
                'description' => 'Temporalmente fuera de servicio',
                'url' => null,
                'icon' => Heroicon::ExclamationTriangle,
                'color' => 'danger',
                'unavailable' => true,
            ],
            $draftLoan !== null => [
                'label' => 'Continuar borrador de préstamo',
                'description' => null,
                'url' => LoanResource::getUrl('view', ['record' => $draftLoan]),
                'icon' => Heroicon::DocumentText,
                'color' => 'warning',
                'unavailable' => false,
            ],
            default => [
                'label' => 'Solicitar préstamo',
                'description' => null,
                'url' => LoanResource::getUrl('apply'),
                'icon' => Heroicon::Banknotes,
                'color' => 'primary',
                'unavailable' => false,
            ],
        };

        return [
            'userName' => $user?->name ?? '',
            'primaryAction' => $primaryAction,
            'secondaryAction' => [
                'label' => 'Nueva solicitud sindical',
                'url' => UserRequestResource::getUrl('create'),
                'icon' => Heroicon::ClipboardDocumentList,
            ],
        ];
    }
}
