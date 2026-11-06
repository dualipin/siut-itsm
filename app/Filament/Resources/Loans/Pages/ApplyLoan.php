<?php

namespace App\Filament\Resources\Loans\Pages;

use App\Enums\LoanAiStatus;
use App\Enums\LoanPaymentType;
use App\Filament\Resources\Loans\LoanResource;
use App\Models\Loan;
use App\Models\LoanEvidence;
use App\Models\User;
use App\Services\LoanCalendarService;
use App\Services\LoanService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\HasWizard;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ApplyLoan extends CreateRecord
{
    use HasWizard;

    protected static string $resource = LoanResource::class;

    protected static ?string $title = 'Nueva Solicitud de Préstamo';

    protected static ?string $breadcrumb = 'Nueva Solicitud';

    protected static bool $canCreateAnother = false;

    public function mount(): void
    {
        parent::mount();

        $token = request()->query('simulation');
        if (! is_string($token)) {
            return;
        }

        /** @var array{payment_type: string, monthly_rate: float, nomina?: array{amount: float, fortnights: int, first_fortnight: string}, benefits?: array<int, array{benefit_id: int, amount: float}>}|null $simulation */
        $simulation = Cache::get('loan_application_'.$token);
        if ($simulation === null) {
            return;
        }

        $this->form->fill([
            'payment_type' => $simulation['payment_type'],
            'monthly_rate' => $simulation['monthly_rate'],
            'nomina_amount' => $simulation['nomina']['amount'] ?? null,
            'nomina_fortnights' => $simulation['nomina']['fortnights'] ?? null,
            'first_fortnight_date' => $simulation['nomina']['first_fortnight'] ?? null,
            'benefits' => $simulation['benefits'] ?? [],
        ]);
    }

    public function getSteps(): array
    {
        $calendar = app(LoanCalendarService::class);
        $benefits = $calendar->getEligibleBenefits()
            ->mapWithKeys(fn (array $benefit): array => [$benefit['id'] => $benefit['name']])
            ->all();

        return [
            Step::make('Simulación')
                ->icon(Heroicon::Calculator)
                ->description('Captura los mismos parámetros del simulador')
                ->schema([
                    Select::make('payment_type')
                        ->label('Forma de Pago')
                        ->options(collect(LoanPaymentType::cases())->mapWithKeys(
                            fn (LoanPaymentType $type): array => [$type->value => $type->getLabel()]
                        ))
                        ->live()
                        ->required(),
                    Select::make('monthly_rate')
                        ->label('Tasa de Interés Mensual')
                        ->options([
                            6 => '6.00%',
                            7.5 => '7.50%',
                            8 => '8.00%',
                            9.5 => '9.50%',
                        ])
                        ->required(),
                    Grid::make(3)
                        ->schema([
                            TextInput::make('nomina_amount')
                                ->label('Monto por Nómina')
                                ->numeric()
                                ->minValue(1)
                                ->visible(fn (callable $get): bool => in_array($get('payment_type'), ['nomina', 'mixed'], true))
                                ->required(fn (callable $get): bool => in_array($get('payment_type'), ['nomina', 'mixed'], true)),
                            TextInput::make('nomina_fortnights')
                                ->label('Número de Quincenas')
                                ->numeric()
                                ->integer()
                                ->minValue(1)
                                ->visible(fn (callable $get): bool => in_array($get('payment_type'), ['nomina', 'mixed'], true))
                                ->required(fn (callable $get): bool => in_array($get('payment_type'), ['nomina', 'mixed'], true)),
                            DatePicker::make('first_fortnight_date')
                                ->label('Primera Quincena')
                                ->minDate(now()->addDays(20))
                                ->maxDate(now()->setMonth(11)->setDay(30))
                                ->visible(fn (callable $get): bool => in_array($get('payment_type'), ['nomina', 'mixed'], true))
                                ->required(fn (callable $get): bool => in_array($get('payment_type'), ['nomina', 'mixed'], true)),
                        ]),
                    Repeater::make('benefits')
                        ->label('Prestaciones')
                        ->schema([
                            Select::make('benefit_id')
                                ->label('Prestación')
                                ->options($benefits)
                                ->distinct()
                                ->required(),
                            TextInput::make('amount')
                                ->label('Monto')
                                ->numeric()
                                ->minValue(1)
                                ->required(),
                        ])
                        ->columns(2)
                        ->minItems(fn (callable $get): int => in_array($get('payment_type'), ['benefit', 'mixed'], true) ? 1 : 0)
                        ->visible(fn (callable $get): bool => in_array($get('payment_type'), ['benefit', 'mixed'], true)),
                ]),
            Step::make('Datos Bancarios')
                ->icon(Heroicon::BuildingLibrary)
                ->description('Datos para la eventual transferencia')
                ->schema([
                    Grid::make(3)
                        ->schema([
                            TextInput::make('bank_name')
                                ->label('Banco')
                                ->maxLength(100),
                            TextInput::make('bank_account')
                                ->label('Cuenta')
                                ->maxLength(50),
                            TextInput::make('interbank_clabe')
                                ->label('CLABE Interbancaria')
                                ->length(18)
                                ->numeric(),
                        ]),
                ]),
            Step::make('Evidencias')
                ->icon(Heroicon::DocumentArrowUp)
                ->description('Archivos privados requeridos por el flujo')
                ->schema([
                    FileUpload::make('nomina_evidence')
                        ->label('Comprobante de Nómina')
                        ->disk('local')
                        ->directory('loan-evidence-poc')
                        ->visibility('private')
                        ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                        ->maxSize(5120)
                        ->required(fn (callable $get): bool => in_array($get('payment_type'), ['nomina', 'mixed'], true))
                        ->visible(fn (callable $get): bool => in_array($get('payment_type'), ['nomina', 'mixed'], true)),
                    Repeater::make('benefit_evidences')
                        ->label('Comprobantes de Prestaciones')
                        ->schema([
                            Select::make('benefit_id')
                                ->label('Prestación')
                                ->options($benefits)
                                ->required(),
                            FileUpload::make('file')
                                ->label('Comprobante')
                                ->disk('local')
                                ->directory('loan-evidence-poc')
                                ->visibility('private')
                                ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                                ->maxSize(5120)
                                ->required(),
                        ])
                        ->columns(2)
                        ->minItems(fn (callable $get): int => in_array($get('payment_type'), ['benefit', 'mixed'], true) ? 1 : 0)
                        ->visible(fn (callable $get): bool => in_array($get('payment_type'), ['benefit', 'mixed'], true)),
                ]),
            Step::make('Firma y Envío')
                ->icon(Heroicon::CheckCircle)
                ->description('Confirma los términos actuales y envía la solicitud')
                ->schema([
                    Section::make('Confirmación')
                        ->description('La aceptación se registra contra los términos capturados y queda invalidada si cambian.')
                        ->schema([
                            Toggle::make('accept_terms')
                                ->label('Acepto los términos, importes y condiciones de esta solicitud')
                                ->accepted()
                                ->required(),
                        ]),
                ]),
        ];
    }

    protected function handleRecordCreation(array $data): Model
    {
        /** @var User $user */
        $user = auth()->user();
        $paymentType = LoanPaymentType::from((string) $data['payment_type']);
        $benefits = $data['benefits'] ?? [];

        $loan = app(LoanService::class)->createDraft($user, [
            'payment_type' => $paymentType->value,
            'requested_amount' => $this->calculateRequestedAmount($data),
            'monthly_rate' => (float) $data['monthly_rate'],
            'nomina_amount' => $data['nomina_amount'] ?? null,
            'nomina_fortnights' => $data['nomina_fortnights'] ?? null,
            'first_fortnight_date' => $data['first_fortnight_date'] ?? null,
            'bank_name' => $data['bank_name'] ?? null,
            'bank_account' => $data['bank_account'] ?? null,
            'interbank_clabe' => $data['interbank_clabe'] ?? null,
            'benefits' => $benefits,
        ]);

        $this->persistEvidence($loan, $data);
        app(LoanService::class)->signLoan($loan, 'filament-accepted-'.now()->toIso8601String());
        app(LoanService::class)->submitLoan($loan, $user);

        Notification::make()
            ->title('Solicitud enviada')
            ->body("La solicitud {$loan->folio} fue enviada para revisión.")
            ->success()
            ->send();

        return $loan->fresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function calculateRequestedAmount(array $data): float
    {
        $nominaAmount = (float) ($data['nomina_amount'] ?? 0);
        $benefitsAmount = collect($data['benefits'] ?? [])
            ->sum(fn (array $benefit): float => (float) ($benefit['amount'] ?? 0));

        return $nominaAmount + $benefitsAmount;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function persistEvidence(Loan $loan, array $data): void
    {
        if (! empty($data['nomina_evidence'])) {
            $this->createEvidence($loan, (string) $data['nomina_evidence'], 'nomina');
        }

        foreach ($data['benefit_evidences'] ?? [] as $evidenceData) {
            if (! empty($evidenceData['file']) && ! empty($evidenceData['benefit_id'])) {
                $this->createEvidence(
                    $loan,
                    (string) $evidenceData['file'],
                    'benefit',
                    (int) $evidenceData['benefit_id'],
                );
            }
        }
    }

    private function createEvidence(Loan $loan, string $path, string $sourceType, ?int $benefitId = null): void
    {
        $disk = Storage::disk('local');
        $fullPath = $disk->path($path);

        if (! is_file($fullPath)) {
            throw ValidationException::withMessages([
                'evidence' => 'No fue posible verificar uno de los archivos adjuntos.',
            ]);
        }

        LoanEvidence::create([
            'loan_id' => $loan->id,
            'source_type' => $sourceType,
            'benefit_id' => $benefitId,
            'file_path' => $path,
            'file_hash' => hash_file('sha256', $fullPath),
            'file_name' => basename($path),
            'mime_type' => $disk->mimeType($path) ?: 'application/octet-stream',
            'file_size' => $disk->size($path),
            'ai_status' => LoanAiStatus::Dudoso,
            'manual_review_status' => 'pending',
        ]);
    }
}
