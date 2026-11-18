<?php

namespace App\Filament\Resources\Loans\Pages;

use App\Enums\LoanOperationalState;
use App\Filament\Resources\Loans\LoanResource;
use App\Models\Loan;
use App\Models\User;
use App\Services\LoanCalendarService;
use App\Services\LoanOperationalStateService;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;

class ListLoans extends ListRecords
{
    protected static string $resource = LoanResource::class;

    public function content(Schema $schema): Schema
    {
        $stateService = app(LoanOperationalStateService::class);

        return $schema
            ->components([
                Callout::make('Servicio de préstamos temporalmente fuera de servicio')
                    ->description(fn (): string => $stateService->publicSuspensionMessage())
                    ->danger()
                    ->icon(Heroicon::ExclamationTriangle)
                    ->visible(fn (): bool => $stateService->isTemporarilyOutOfService()),
                $this->getTabsContentComponent(),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE),
                EmbeddedTable::make(),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER),
            ]);
    }

    protected function getHeaderActions(): array
    {
        $calendarService = app(LoanCalendarService::class);
        $stateService = app(LoanOperationalStateService::class);

        return [
            Action::make('manageOperationalState')
                ->label(fn (): string => 'Estado: '.$stateService->effectiveState()->getLabel())
                ->icon(Heroicon::Cog6Tooth)
                ->color(fn (): string|array|null => $stateService->effectiveState()->getColor())
                ->authorize('manageOperationalState', Loan::class)
                ->modalHeading('Control Operativo de Préstamos')
                ->modalDescription('Configura el estado operativo vigente para el Simulador Público y el Portal de Préstamos.')
                ->modalSubmitActionLabel('Guardar estado operativo')
                ->schema([
                    Select::make('state')
                        ->label('Estado operativo')
                        ->options(LoanOperationalState::class)
                        ->default(fn (): string => $stateService->current()->state->value)
                        ->live()
                        ->required(),

                    Placeholder::make('cierre_anio_preview')
                        ->label('Año fiscal destino')
                        ->content(fn (): string => 'Al activar Cierre de año, el año fiscal objetivo se fijará automáticamente en '.(now()->year + 1).'.')
                        ->visible(function (Get $get): bool {
                            $state = $get('state');
                            $value = $state instanceof LoanOperationalState ? $state->value : $state;

                            return $value === LoanOperationalState::CierreAnio->value;
                        }),

                    Textarea::make('suspension_message')
                        ->label('Mensaje público de suspensión (opcional)')
                        ->placeholder(LoanOperationalStateService::DEFAULT_SUSPENSION_MESSAGE)
                        ->helperText('Si se deja vacío, se mostrará el mensaje institucional predeterminado en el Simulador y en el Portal.')
                        ->rows(3)
                        ->maxLength(500)
                        ->default(fn (): ?string => $stateService->current()->suspension_message)
                        ->visible(function (Get $get): bool {
                            $state = $get('state');
                            $value = $state instanceof LoanOperationalState ? $state->value : $state;

                            return $value === LoanOperationalState::TemporalmenteFueraDeServicio->value;
                        }),

                    Placeholder::make('audit_info')
                        ->label('Última actualización')
                        ->content(function () use ($stateService): string {
                            $setting = $stateService->current();
                            if (! $setting->exists || $setting->state_changed_at === null) {
                                return 'Sin cambios registrados (estado inicial Activo — ejercicio '.($setting->target_fiscal_year ?? now()->year).').';
                            }

                            $actorName = $setting->updatedBy?->full_name ?? 'Sistema';

                            return sprintf(
                                '%s el %s (año fiscal objetivo: %d)',
                                $actorName,
                                $setting->state_changed_at->format('d/m/Y H:i'),
                                $setting->target_fiscal_year,
                            );
                        }),
                ])
                ->action(function (array $data) use ($stateService): void {
                    /** @var User $user */
                    $user = auth()->user();

                    $state = $data['state'] instanceof LoanOperationalState
                        ? $data['state']
                        : LoanOperationalState::from((string) $data['state']);

                    $setting = $stateService->updateState(
                        $user,
                        $state,
                        $data['suspension_message'] ?? null,
                    );

                    Notification::make()
                        ->title('Estado operativo actualizado')
                        ->body("El módulo de préstamos quedó en estado {$setting->state->getLabel()} (año fiscal {$setting->target_fiscal_year}).")
                        ->success()
                        ->send();
                }),

            Action::make('createLoan')
                ->label('Nueva Solicitud')
                ->icon(Heroicon::Plus)
                ->color('primary')
                ->disabled(fn (): bool => ! $calendarService->isApplicationWindowOpen())
                ->tooltip(fn (): ?string => $calendarService->isApplicationWindowOpen() ? null : $calendarService->applicationClosedReason())
                ->url(fn (): ?string => $calendarService->isApplicationWindowOpen() ? ApplyLoan::getUrl() : null),
        ];
    }
}
