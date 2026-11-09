<?php

namespace App\Filament\Resources\Loans\Pages;

use App\Enums\LoanPaymentType;
use App\Filament\Forms\Components\SignaturePad;
use App\Filament\Resources\Loans\LoanResource;
use App\Models\Benefit;
use App\Models\Loan;
use App\Models\LoanEvidence;
use App\Models\User;
use App\Services\LoanCalendarService;
use App\Services\LoanDocumentAIService;
use App\Services\LoanService;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\HasWizard;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ApplyLoan extends CreateRecord
{
    use HasWizard;

    protected static string $resource = LoanResource::class;

    protected static ?string $title = 'Nueva Solicitud de Préstamo';

    protected static ?string $breadcrumb = 'Nueva Solicitud';

    protected static bool $canCreateAnother = false;

    public function mount(): void
    {
        if (! app(LoanCalendarService::class)->isApplicationWindowOpen()) {
            Notification::make()
                ->title('Periodo de solicitudes cerrado')
                ->body('El periodo de recepción de solicitudes de préstamos ha concluido por este año (fecha límite: 30 de noviembre).')
                ->warning()
                ->persistent()
                ->send();

            $this->redirect(ListLoans::getUrl());

            return;
        }

        parent::mount();

        /** @var User $user */
        $user = auth()->user();
        $bankDetails = app(LoanService::class)->latestBankDetails($user);

        $fillData = [
            ...$bankDetails,
            'signature_type' => 'drawn',
            'signature_data' => null,
            'signed_terms_hash' => null,
            'accept_terms' => false,
        ];

        $token = request()->query('simulation');
        if (is_string($token)) {
            /** @var array{payment_type: string, nomina?: array{amount: float, fortnights: int}, benefits?: array<int, array{benefit_id: int, amount: float}>}|null $simulation */
            $simulation = Cache::get('loan_application_'.$token);
            if ($simulation !== null) {
                $fillData = [
                    ...$fillData,
                    'payment_type' => $simulation['payment_type'],
                    'nomina_amount' => $simulation['nomina']['amount'] ?? null,
                    'nomina_fortnights' => $simulation['nomina']['fortnights'] ?? null,
                    'benefits' => $simulation['benefits'] ?? [],
                ];
            }
        }

        $this->form->fill($fillData);
        $this->syncBenefitEvidenceRows();
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
                ->afterValidation(function (): void {
                    $this->syncBenefitEvidenceRows();
                })
                ->schema([
                    Select::make('payment_type')
                        ->label('Forma de Pago')
                        ->options(collect(LoanPaymentType::cases())->mapWithKeys(
                            fn (LoanPaymentType $type): array => [$type->value => $type->getLabel()]
                        ))
                        ->live()
                        ->afterStateUpdated(function (Set $set): void {
                            $this->syncBenefitEvidenceRows();
                            $this->invalidateSignature($set);
                        })
                        ->required(),
                    Grid::make(2)
                        ->schema([
                            TextInput::make('nomina_amount')
                                ->label('Monto por Nómina')
                                ->numeric()
                                ->minValue(1)
                                ->live(onBlur: true)
                                ->afterStateUpdated(function (Set $set): void {
                                    $this->invalidateSignature($set);
                                })
                                ->visible(fn (callable $get): bool => in_array($get('payment_type'), ['nomina', 'mixed'], true))
                                ->required(fn (callable $get): bool => in_array($get('payment_type'), ['nomina', 'mixed'], true)),
                            Select::make('nomina_fortnights')
                                ->label('Número de Quincenas')
                                ->options(fn (): array => collect($calendar->getEligibleFortnights())
                                    ->keys()
                                    ->mapWithKeys(fn (int $index): array => [
                                        $index + 1 => ($index + 1).' '.($index === 0 ? 'quincena' : 'quincenas'),
                                    ])
                                    ->all())
                                ->live()
                                ->afterStateUpdated(function (Set $set): void {
                                    $this->invalidateSignature($set);
                                })
                                ->visible(fn (callable $get): bool => in_array($get('payment_type'), ['nomina', 'mixed'], true))
                                ->required(fn (callable $get): bool => in_array($get('payment_type'), ['nomina', 'mixed'], true)),
                        ]),
                    Repeater::make('benefits')
                        ->label('Prestaciones')
                        ->schema([
                            Select::make('benefit_id')
                                ->label('Prestación')
                                ->options($benefits)
                                ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                ->live()
                                ->afterStateUpdated(function (Set $set): void {
                                    $this->syncBenefitEvidenceRows();
                                    $this->invalidateSignature($set);
                                })
                                ->required(),
                            TextInput::make('amount')
                                ->label('Monto')
                                ->numeric()
                                ->minValue(1)
                                ->live(onBlur: true)
                                ->afterStateUpdated(function (Set $set): void {
                                    $this->invalidateSignature($set);
                                })
                                ->required(),
                        ])
                        ->columns(2)
                        ->live()
                        ->minItems(fn (callable $get): int => in_array($get('payment_type'), ['benefit', 'mixed'], true) ? 1 : 0)
                        ->afterStateUpdated(function (Set $set): void {
                            $this->syncBenefitEvidenceRows();
                            $this->invalidateSignature($set);
                        })
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
                                ->maxLength(100)
                                ->live(onBlur: true)
                                ->afterStateUpdated(function (Set $set): void {
                                    $this->invalidateSignature($set);
                                })
                                ->required(),
                            TextInput::make('bank_account')
                                ->label('Cuenta')
                                ->maxLength(50)
                                ->live(onBlur: true)
                                ->afterStateUpdated(function (Set $set): void {
                                    $this->invalidateSignature($set);
                                })
                                ->requiredWithout('interbank_clabe'),
                            TextInput::make('interbank_clabe')
                                ->label('CLABE Interbancaria')
                                ->maxLength(18)
                                ->live(onBlur: true)
                                ->afterStateUpdated(function (Set $set): void {
                                    $this->invalidateSignature($set);
                                })
                                ->requiredWithout('bank_account')
                                ->regex('/^\d{1,18}$/')
                                ->extraInputAttributes([
                                    'maxlength' => '18',
                                    'inputmode' => 'numeric',
                                    'pattern' => '[0-9]{1,18}',
                                    'oninput' => "this.value = this.value.replace(/\\D/g, '').slice(0, 18)",
                                ])
                                ->helperText('Captura la cuenta o la CLABE; la CLABE admite hasta 18 dígitos.'),
                        ]),
                ]),
            Step::make('Evidencias')
                ->icon(Heroicon::DocumentArrowUp)
                ->description('Archivos privados requeridos por el flujo')
                ->beforeValidation(function (): void {
                    $this->syncBenefitEvidenceRows();
                })
                ->afterValidation(function (): void {
                    $this->ensureEvidenceCanProceed($this->form->getRawState());
                })
                ->schema([
                    Section::make('Comprobante de Nómina')
                        ->icon(Heroicon::DocumentText)
                        ->schema([
                            FileUpload::make('nomina_evidence')
                                ->label('Archivo del Comprobante')
                                ->disk('local')
                                ->directory('loan-evidence-poc')
                                ->visibility('private')
                                ->live()
                                ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp'])
                                ->imageResizeMode('contain')
                                ->imageResizeTargetWidth('1920')
                                ->imageResizeTargetHeight('1920')
                                ->imageResizeUpscale(false)
                                ->maxSize(20480)
                                ->afterStateUpdated(function (Set $set, mixed $state): void {
                                    $set('nomina_ai_status', $this->evaluateEvidenceState($state, 'nomina'));
                                })
                                ->required(fn (callable $get): bool => in_array($get('payment_type'), ['nomina', 'mixed'], true)),
                            Html::make(fn (Get $get): HtmlString => $this->renderEvidenceStatus((string) $get('nomina_ai_status'))),
                            Hidden::make('nomina_ai_status')
                                ->default('pendiente')
                                ->dehydrated(),
                        ])
                        ->visible(fn (callable $get): bool => in_array($get('payment_type'), ['nomina', 'mixed'], true)),
                    Repeater::make('benefit_evidences')
                        ->label('Comprobantes de Prestaciones')
                        ->addable(false)
                        ->deletable(false)
                        ->reorderable(false)
                        ->collapsible(false)
                        ->itemLabel(function (array $state, ?string $key = null): string {
                            $name = $state['benefit_name'] ?? ($key ? data_get($this->data, "benefit_evidences.{$key}.benefit_name") : null);

                            return $name ? "Comprobante de Prestación: {$name}" : 'Comprobante de Prestación';
                        })
                        ->schema([
                            Hidden::make('benefit_name'),
                            Hidden::make('benefit_id')
                                ->required(),
                            FileUpload::make('file')
                                ->label('Archivo del Comprobante')
                                ->disk('local')
                                ->directory('loan-evidence-poc')
                                ->visibility('private')
                                ->live()
                                ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp'])
                                ->imageResizeMode('contain')
                                ->imageResizeTargetWidth('1920')
                                ->imageResizeTargetHeight('1920')
                                ->imageResizeUpscale(false)
                                ->maxSize(20480)
                                ->afterStateUpdated(function (Set $set, callable $get, mixed $state): void {
                                    $set(
                                        'ai_status',
                                        $this->evaluateEvidenceState(
                                            $state,
                                            'benefit',
                                            $get('benefit_id') ? (int) $get('benefit_id') : null,
                                        ),
                                    );
                                })
                                ->required(),
                            Html::make(fn (Get $get): HtmlString => $this->renderEvidenceStatus((string) $get('ai_status'))),
                            Hidden::make('ai_status')
                                ->default('pendiente')
                                ->dehydrated(),
                        ])
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
                            Html::make(fn (Get $get): HtmlString => $this->renderTermsSummary($get)),
                            Radio::make('signature_type')
                                ->label('Modalidad de Firma')
                                ->options([
                                    'drawn' => 'Firma autógrafa digitalizada (trazo manuscrito)',
                                    'efirma' => 'e.firma del SAT (Firma electrónica avanzada)',
                                ])
                                ->descriptions([
                                    'drawn' => 'Dibuje su firma en el recuadro digital tal como aparece en su credencial de elector (INE/IFE).',
                                    'efirma' => 'Identificación mediante certificados digitales del SAT (mecanismo preparado para Fase 2).',
                                ])
                                ->default('drawn')
                                ->live()
                                ->required()
                                ->rule(fn (): \Closure => function (string $attribute, mixed $value, \Closure $fail): void {
                                    if ($value === 'efirma') {
                                        $fail('La modalidad con e.firma está en preparación para la Fase 2. Selecciona firma autógrafa digitalizada para poder enviar tu solicitud en esta fase.');
                                    }
                                }),
                            SignaturePad::make('signature_data')
                                ->label('Trazo de la Firma')
                                ->helperText('Realice su firma dentro del recuadro usando su ratón, lápiz óptico o pantalla táctil. Este trazo se estampará en su solicitud, pagaré y anuencia de descuento.')
                                ->required(fn (callable $get): bool => ($get('signature_type') ?? 'drawn') === 'drawn')
                                ->visible(fn (callable $get): bool => ($get('signature_type') ?? 'drawn') === 'drawn'),
                            Section::make('Mecanismo de e.firma (SAT)')
                                ->icon(Heroicon::ShieldCheck)
                                ->description('Estructura para la recepción y verificación de certificados digitales del SAT.')
                                ->visible(fn (callable $get): bool => $get('signature_type') === 'efirma')
                                ->schema([
                                    Html::make(fn (): HtmlString => new HtmlString(
                                        '<div class="flex items-start gap-3 rounded-xl border border-info-200 bg-info-50 p-4 text-info-800 dark:border-info-900/50 dark:bg-info-950/20 dark:text-info-200">'
                                        .'<div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-white/80 font-bold dark:bg-info-900">i</div>'
                                        .'<div>'
                                        .'<p class="font-semibold">Mecanismo preparado para adopción (Fase 2)</p>'
                                        .'<p class="mt-1 text-sm leading-5">La infraestructura para recepción y validación de e.firma (.cer, .key y contraseña del SAT) se encuentra preparada. De conformidad con las políticas de la Fase 1, el trazo de firma autógrafa digitalizada es obligatorio para tramitar y enviar su solicitud en este momento.</p>'
                                        .'</div>'
                                        .'</div>'
                                    )),
                                    Grid::make(2)
                                        ->schema([
                                            TextInput::make('efirma_cert_mock')
                                                ->label('Certificado (.cer)')
                                                ->placeholder('ejemplo_rfc.cer')
                                                ->disabled()
                                                ->helperText('Archivo de certificado digital expedido por el SAT (mecanismo preparado)'),
                                            TextInput::make('efirma_key_mock')
                                                ->label('Clave privada (.key)')
                                                ->placeholder('ejemplo_rfc.key')
                                                ->disabled()
                                                ->helperText('Archivo de clave privada protegida (mecanismo preparado)'),
                                        ]),
                                    TextInput::make('efirma_password_mock')
                                        ->label('Contraseña de clave privada')
                                        ->password()
                                        ->placeholder('••••••••••••')
                                        ->disabled()
                                        ->helperText('Contraseña de acceso a su clave privada'),
                                    Html::make(fn (): HtmlString => new HtmlString(
                                        '<div class="rounded-lg bg-amber-50 p-3 text-xs text-amber-800 dark:bg-amber-950/20 dark:text-amber-200 border border-amber-200 dark:border-amber-900/50">'
                                        .'<strong>Nota:</strong> Para completar y enviar esta solicitud ahora, por favor seleccione arriba la opción <em>"Firma autógrafa digitalizada"</em>.'
                                        .'</div>'
                                    )),
                                ]),
                            Toggle::make('accept_terms')
                                ->label('Acepto los términos, importes y condiciones de esta solicitud')
                                ->helperText('Al marcar esta casilla y firmar, autorizo de forma voluntaria e irrevocable los descuentos correspondientes.')
                                ->accepted()
                                ->required(),
                            Hidden::make('signed_terms_hash')
                                ->dehydrated(),
                        ]),
                ]),
        ];
    }

    protected function handleRecordCreation(array $data): Model
    {
        /** @var User $user */
        $user = auth()->user();
        $this->ensureEvidenceCanProceed($data);

        if (($data['signature_type'] ?? 'drawn') === 'efirma') {
            Notification::make()
                ->title('Modalidad no disponible para envío')
                ->body('La modalidad con e.firma está en preparación para la Fase 2. Selecciona firma autógrafa digitalizada para enviar tu solicitud.')
                ->danger()
                ->persistent()
                ->send();

            throw ValidationException::withMessages([
                'signature_type' => 'Selecciona firma autógrafa digitalizada para poder enviar tu solicitud en esta fase.',
            ]);
        }

        if (empty($data['signature_data']) || ! str_starts_with((string) $data['signature_data'], 'data:image/')) {
            Notification::make()
                ->title('Firma obligatoria')
                ->body('Debes plasmar tu firma en el recuadro para poder enviar la solicitud.')
                ->danger()
                ->persistent()
                ->send();

            throw ValidationException::withMessages([
                'signature_data' => 'Debes plasmar tu firma en el recuadro para poder enviar la solicitud.',
            ]);
        }

        $currentTermsHash = $this->calculateFormTermsHash($data);
        if (! empty($data['signed_terms_hash']) && ! hash_equals($data['signed_terms_hash'], $currentTermsHash)) {
            $this->invalidateSignature();

            Notification::make()
                ->title('Términos modificados')
                ->body('Los términos de la solicitud cambiaron después de haber firmado. Debes firmar nuevamente.')
                ->danger()
                ->persistent()
                ->send();

            throw ValidationException::withMessages([
                'signature_data' => 'Los términos de la solicitud cambiaron después de haber firmado. Debes firmar nuevamente.',
            ]);
        }

        $paymentType = LoanPaymentType::from((string) $data['payment_type']);
        $benefits = $data['benefits'] ?? [];

        $loan = app(LoanService::class)->createDraft($user, [
            'payment_type' => $paymentType->value,
            'requested_amount' => $this->calculateRequestedAmount($data),
            'nomina_amount' => $data['nomina_amount'] ?? null,
            'nomina_fortnights' => $data['nomina_fortnights'] ?? null,
            'bank_name' => $data['bank_name'] ?? null,
            'bank_account' => $data['bank_account'] ?? null,
            'interbank_clabe' => $data['interbank_clabe'] ?? null,
            'benefits' => $benefits,
        ]);

        $this->persistEvidence($loan, $data);
        app(LoanService::class)->signLoan($loan, (string) $data['signature_data'], 'drawn');
        app(LoanService::class)->submitLoan($loan, $user);

        Notification::make()
            ->title('Solicitud enviada')
            ->body("La solicitud {$loan->folio} fue enviada para revisión.")
            ->success()
            ->send();

        return $loan->fresh();
    }

    public function invalidateSignature(?Set $set = null): void
    {
        $hadSignature = ! empty(data_get($this->data, 'signature_data')) || ! empty(data_get($this->data, 'accept_terms'));

        if ($set) {
            $set('signature_data', null);
            $set('signed_terms_hash', null);
            $set('accept_terms', false);
        }

        data_set($this, 'data.signature_data', null);
        data_set($this, 'data.signed_terms_hash', null);
        data_set($this, 'data.accept_terms', false);

        if ($hadSignature) {
            Notification::make()
                ->title('Firma invalidada')
                ->body('Los términos de la solicitud fueron modificados. Deberás firmar nuevamente la solicitud con los importes actualizados.')
                ->warning()
                ->send();
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function calculateFormTermsHash(array $data): string
    {
        $benefits = collect($data['benefits'] ?? [])
            ->filter(fn (array $b): bool => ! empty($b['benefit_id']))
            ->sortBy('benefit_id')
            ->map(fn (array $b): array => [
                'benefit_id' => (int) $b['benefit_id'],
                'amount' => (float) ($b['amount'] ?? 0),
            ])
            ->values()
            ->all();

        $payload = [
            'payment_type' => (string) ($data['payment_type'] ?? ''),
            'nomina_amount' => (float) ($data['nomina_amount'] ?? 0),
            'nomina_fortnights' => (int) ($data['nomina_fortnights'] ?? 0),
            'bank_name' => (string) ($data['bank_name'] ?? ''),
            'bank_account' => (string) ($data['bank_account'] ?? ''),
            'interbank_clabe' => (string) ($data['interbank_clabe'] ?? ''),
            'benefits' => $benefits,
        ];

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    protected function renderTermsSummary(callable $get): HtmlString
    {
        $paymentType = $get('payment_type');
        $nominaAmount = (float) ($get('nomina_amount') ?? 0);
        $nominaFortnights = (int) ($get('nomina_fortnights') ?? 0);
        $benefits = collect($get('benefits') ?? [])->filter(fn (array $b): bool => ! empty($b['benefit_id']));
        $bankName = $get('bank_name') ?: 'No especificado';
        $accountOrClabe = $get('bank_account') ?: ($get('interbank_clabe') ?: 'No especificado');

        $totalRequested = 0.0;
        if (in_array($paymentType, ['nomina', 'mixed'], true)) {
            $totalRequested += $nominaAmount;
        }
        if (in_array($paymentType, ['benefit', 'mixed'], true)) {
            $totalRequested += $benefits->sum(fn (array $b): float => (float) ($b['amount'] ?? 0));
        }

        $typeLabel = match ($paymentType) {
            'nomina' => 'Vía Nómina Quincenal',
            'benefit' => 'Vía Prestación',
            'mixed' => 'Mixta (Nómina y Prestaciones)',
            default => 'Por definir',
        };

        $html = '<div class="rounded-xl border border-gray-200 bg-gray-50/60 p-4 text-xs dark:border-gray-800 dark:bg-gray-800/30 space-y-2.5">';
        $html .= '<div class="flex items-center justify-between border-b border-gray-200/80 pb-2 dark:border-gray-700/60">';
        $html .= '<span class="font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider text-[11px]">Resumen de Términos a Aceptar</span>';
        $html .= '<span class="font-bold text-sm text-gray-900 dark:text-white">$ '.number_format($totalRequested, 2).'</span>';
        $html .= '</div>';

        $html .= '<div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-gray-600 dark:text-gray-400">';
        $html .= '<div>Modalidad: <strong class="text-gray-900 dark:text-white">'.$typeLabel.'</strong></div>';
        $html .= '<div>Depósito: <strong class="text-gray-900 dark:text-white">'.$bankName.' ('.$accountOrClabe.')</strong></div>';

        if (in_array($paymentType, ['nomina', 'mixed'], true)) {
            $html .= '<div class="sm:col-span-2">Descuento nómina: <strong class="text-gray-900 dark:text-white">$ '.number_format($nominaAmount, 2).'</strong> en <strong class="text-gray-900 dark:text-white">'.$nominaFortnights.' quincenas</strong></div>';
        }

        if (in_array($paymentType, ['benefit', 'mixed'], true) && $benefits->isNotEmpty()) {
            $benefitNames = Benefit::whereIn('id', $benefits->pluck('benefit_id'))->pluck('name', 'id');
            $html .= '<div class="sm:col-span-2">Prestaciones comprometidas: ';
            $items = $benefits->map(fn ($b) => ($benefitNames->get((int) $b['benefit_id'], 'Prestación')).': $'.number_format((float) ($b['amount'] ?? 0), 2))->join(', ');
            $html .= '<strong class="text-gray-900 dark:text-white">'.$items.'</strong></div>';
        }

        $html .= '</div>';
        $html .= '</div>';

        return new HtmlString($html);
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
            $file = $this->extractEvidenceFile($data['nomina_evidence']);
            $path = is_string($file) ? $file : $this->prepareEvidencePath($file);
            if ($path) {
                $this->createEvidence($loan, $path, 'nomina');
            }
        }

        foreach ($data['benefit_evidences'] ?? [] as $evidenceData) {
            if (! empty($evidenceData['file']) && ! empty($evidenceData['benefit_id'])) {
                $file = $this->extractEvidenceFile($evidenceData['file']);
                $path = is_string($file) ? $file : $this->prepareEvidencePath($file);
                if ($path) {
                    $this->createEvidence(
                        $loan,
                        $path,
                        'benefit',
                        (int) $evidenceData['benefit_id'],
                    );
                }
            }
        }
    }

    public function syncBenefitEvidenceRows(?array $selectedBenefitState = null): void
    {
        $rawState = $this->form->getRawState();
        $paymentType = $rawState['payment_type'] ?? null;

        if (! in_array($paymentType, ['benefit', 'mixed'], true)) {
            data_set($this, 'data.benefit_evidences', []);
            $this->form->clearCachedChildSchemas();

            return;
        }

        $existingKeyByBenefitId = [];
        $existingRowsByBenefitId = [];
        foreach ($rawState['benefit_evidences'] ?? [] as $key => $evidence) {
            if (! empty($evidence['benefit_id'])) {
                $benefitId = (int) $evidence['benefit_id'];
                $existingKeyByBenefitId[$benefitId] = $key;
                $existingRowsByBenefitId[$benefitId] = $evidence;
            }
        }

        $benefitsSource = $selectedBenefitState ?? $rawState['benefits'] ?? [];
        $selectedBenefits = collect($benefitsSource)
            ->filter(fn (array $benefit): bool => ! empty($benefit['benefit_id']))
            ->pluck('benefit_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();

        $names = Benefit::whereIn('id', $selectedBenefits)->pluck('name', 'id');

        $syncedRows = [];
        foreach ($selectedBenefits as $benefitId) {
            $key = (string) ($existingKeyByBenefitId[$benefitId] ?? Str::uuid());
            $existing = $existingRowsByBenefitId[$benefitId] ?? [];

            $syncedRows[$key] = [
                'benefit_id' => $benefitId,
                'benefit_name' => $names->get($benefitId, 'Prestación seleccionada'),
                'file' => $existing['file'] ?? null,
                'ai_status' => $existing['ai_status'] ?? 'pendiente',
            ];
        }

        data_set($this, 'data.benefit_evidences', $syncedRows);
        $this->form->clearCachedChildSchemas();
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
            ...$this->evaluateEvidenceForPersistence($path, $sourceType, $benefitId),
        ]);
    }

    private function evaluateEvidenceForForm(string $path, string $sourceType, ?int $benefitId = null): string
    {
        return $this->evaluateEvidenceForPersistence($path, $sourceType, $benefitId)['ai_status']->value;
    }

    private function evaluateEvidenceState(mixed $state, string $sourceType, ?int $benefitId = null): string
    {
        $rawFile = $this->extractEvidenceFile($state);
        $path = $this->prepareEvidencePath($rawFile);
        if (! $path) {
            return 'Pendiente de análisis';
        }

        $status = $this->evaluateEvidenceForForm($path, $sourceType, $benefitId);

        if ($rawFile instanceof TemporaryUploadedFile || $rawFile instanceof UploadedFile) {
            Storage::disk('local')->delete($path);
        }

        return $status;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function ensureEvidenceCanProceed(array $data): void
    {
        $evidences = [];

        if (! empty($data['nomina_evidence'])) {
            $evidences[] = [
                'file' => $data['nomina_evidence'],
                'source_type' => 'nomina',
                'field' => 'nomina_evidence',
                'status' => (string) ($data['nomina_ai_status'] ?? 'pendiente'),
            ];
        }

        foreach ($data['benefit_evidences'] ?? [] as $index => $evidenceData) {
            if (! empty($evidenceData['file']) && ! empty($evidenceData['benefit_id'])) {
                $evidences[] = [
                    'file' => $evidenceData['file'],
                    'source_type' => 'benefit',
                    'benefit_id' => (int) $evidenceData['benefit_id'],
                    'field' => "benefit_evidences.{$index}.file",
                    'status' => (string) ($evidenceData['ai_status'] ?? 'pendiente'),
                ];
            }
        }

        foreach ($evidences as $evidence) {
            $status = $evidence['status'];

            if (in_array($status, ['valido', 'fallo_tecnico'], true)) {
                continue;
            }

            if (in_array($status, ['invalido', 'dudoso'], true)) {
                Notification::make()
                    ->title('No puedes continuar todavía')
                    ->body($this->evidenceBlockingMessage($status))
                    ->danger()
                    ->persistent()
                    ->send();

                throw new Halt;
            }

            $rawFile = $this->extractEvidenceFile($evidence['file']);
            $path = $this->prepareEvidencePath($rawFile);

            if (! $path) {
                continue;
            }

            $result = $this->evaluateEvidenceForPersistence(
                $path,
                $evidence['source_type'],
                $evidence['benefit_id'] ?? null,
            );

            if ($rawFile instanceof TemporaryUploadedFile || $rawFile instanceof UploadedFile) {
                Storage::disk('local')->delete($path);
            }

            if (in_array($result['ai_status']->value, ['valido', 'fallo_tecnico'], true)) {
                continue;
            }

            Notification::make()
                ->title('No puedes continuar todavía')
                ->body($this->evidenceBlockingMessage($result['ai_status']->value))
                ->danger()
                ->persistent()
                ->send();

            throw new Halt;
        }
    }

    private function evidenceBlockingMessage(string $status): string
    {
        return match ($status) {
            'invalido' => 'Este comprobante no corresponde al documento solicitado. Regresa al paso “Evidencias”, selecciona un archivo legible y vigente del trabajador, y vuelve a cargarlo.',
            'dudoso' => 'No fue posible confirmar este comprobante con suficiente certeza. Revisa que el nombre y el concepto correspondan a tu solicitud, y vuelve a cargar un documento más claro.',
            default => 'No fue posible revisar este comprobante en este momento. Conserva el archivo y vuelve a intentarlo en unos minutos.',
        };
    }

    private function renderEvidenceStatus(string $status): HtmlString
    {
        $content = match ($status) {
            'valido' => [
                'classes' => 'border-success-200 bg-success-50 text-success-800',
                'icon' => '✓',
                'title' => 'Comprobante listo',
                'body' => 'El documento corresponde a esta solicitud y puede continuar.',
            ],
            'invalido' => [
                'classes' => 'border-danger-200 bg-danger-50 text-danger-800',
                'icon' => '!',
                'title' => 'Necesitas reemplazar este comprobante',
                'body' => 'El archivo no corresponde al documento solicitado. Carga un comprobante laboral legible, vigente y a nombre del trabajador.',
            ],
            'dudoso' => [
                'classes' => 'border-warning-200 bg-warning-50 text-warning-800',
                'icon' => '?',
                'title' => 'Revisión pendiente',
                'body' => 'No se pudo confirmar con claridad. Verifica el nombre, el concepto y la legibilidad antes de continuar.',
            ],
            'fallo_tecnico' => [
                'classes' => 'border-info-200 bg-info-50 text-info-800',
                'icon' => 'i',
                'title' => 'Se revisará manualmente',
                'body' => 'La revisión automática no está disponible. Puedes continuar; un revisor verificará este comprobante antes de aprobar la solicitud.',
            ],
            default => [
                'classes' => 'border-gray-200 bg-gray-50 text-gray-700',
                'icon' => '•',
                'title' => 'Documento pendiente',
                'body' => 'Carga el comprobante para revisar que sea el indicado.',
            ],
        };

        return new HtmlString(
            '<div class="flex items-start gap-3 rounded-xl border p-4 '.$content['classes'].'">'
            .'<div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-white/80 font-bold">'.$content['icon'].'</div>'
            .'<div><p class="font-semibold">'.$content['title'].'</p><p class="mt-1 text-sm leading-5">'.$content['body'].'</p></div>'
            .'</div>',
        );
    }

    private function extractEvidenceFile(mixed $state): mixed
    {
        if (is_array($state)) {
            return Arr::first($state);
        }

        return $state;
    }

    private function prepareEvidencePath(mixed $state): ?string
    {
        $file = $this->extractEvidenceFile($state);

        if (is_string($file) && $file !== '') {
            return $file;
        }

        if ($file instanceof TemporaryUploadedFile || $file instanceof UploadedFile) {
            return $file->store('loan-evidence-poc', 'local');
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function evaluateEvidenceForPersistence(string $path, string $sourceType, ?int $benefitId = null): array
    {
        /** @var User $user */
        $user = auth()->user();
        $benefit = $benefitId ? Benefit::find($benefitId) : null;
        $result = app(LoanDocumentAIService::class)->evaluate($path, $sourceType, $user, $benefit);

        return [
            'ai_status' => $result->aiStatus,
            'ai_classification' => $result->classification,
            'ai_confidence' => $result->confidence,
            'ai_model' => $result->model,
            'ai_prompt_version' => $result->promptVersion,
            'extracted_data' => $result->extractedFields,
            'comparison_data' => $result->comparison,
            'manual_review_status' => 'pending',
        ];
    }
}
