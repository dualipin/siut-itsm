<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useIslandForm } from '@/composables/useIslandForm'
import { MONTHLY_RATE_OPTIONS, type MonthlyRateValue } from '@/constants/loans'

// ── Props ─────────────────────────────────────────────────────────────────────
const props = defineProps<{
    benefits: Array<{
        id: number
        name: string
        collectionDay: number
        collectionMonth: number
        collectionDate: string   // 'YYYY-MM-DD' for the current year
    }>
}>()

// ── Form state ────────────────────────────────────────────────────────────────
type BenefitEntry = { benefit_id: number; amount: string; collection_date: string }

const form = useIslandForm({
    monthly_rate: '' as '' | MonthlyRateValue,
    payment_type: 'nomina' as 'nomina' | 'benefit' | 'mixed',
    nomina: {
        amount: '',
        fortnights: '',
        first_fortnight: '',
    },
    benefits: [] as BenefitEntry[],
})

// ── Fortnight computation ─────────────────────────────────────────────────────
function lastDayOfMonth(year: number, month: number): number {
    return new Date(year, month, 0).getDate()
}

/**
 * Advance one fortnight from the given date string (YYYY-MM-DD).
 * Fortnights land on the 15th or the last day of the month.
 */
function nextFortnight(dateStr: string): string {
    const [yyyy, mm, dd] = dateStr.split('-').map(Number)
    let year = yyyy, month = mm, day: number

    if (dd < 15) {
        day = 15
    } else if (dd === 15) {
        day = lastDayOfMonth(year, month)
    } else {
        // last day → move to 15th of next month
        month += 1
        if (month > 12) { month = 1; year += 1 }
        day = 15
    }

    return `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`
}

/**
 * Generate all valid fortnights between minDate (today+20) and Nov 30 of current year.
 */
const availableFortnights = computed(() => {
    const today = new Date()
    const minDate = new Date(today)
    minDate.setDate(today.getDate() + 20)

    const year = today.getFullYear()
    const cutoff = new Date(year, 10, 30) // Nov 30

    const result: Array<{ value: string; label: string }> = []
    const months = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov']

    for (let month = 1; month <= 11; month++) {
        const last = lastDayOfMonth(year, month)
        const candidates = [15, last]

        for (const day of candidates) {
            const d = new Date(year, month - 1, day)
            if (d >= minDate && d <= cutoff) {
                const yyyy = d.getFullYear()
                const mm = String(d.getMonth() + 1).padStart(2, '0')
                const ddStr = String(d.getDate()).padStart(2, '0')
                result.push({
                    value: `${yyyy}-${mm}-${ddStr}`,
                    label: `${ddStr} ${months[month - 1]} ${yyyy}`,
                })
            }
        }
    }

    return result
})

/**
 * Available fortnight counts from the selected first_fortnight to Nov 30.
 */
const availableFortnightCounts = computed(() => {
    const firstStr = form.data.nomina.first_fortnight
    if (!firstStr) return []

    const year = new Date().getFullYear()
    const cutoffStr = `${year}-11-30`

    const result: Array<{ value: number; label: string }> = []
    let current = firstStr
    let count = 0

    while (current <= cutoffStr) {
        count++
        result.push({
            value: count,
            label: count === 1 ? '1 quincena' : `${count} quincenas`,
        })
        current = nextFortnight(current)
    }

    return result
})

// Reset fortnights when first_fortnight changes
watch(
    () => form.data.nomina.first_fortnight,
    () => { form.data.nomina.fortnights = '' }
)

/**
 * The date of the last fortnight calculated from first_fortnight + fortnights count.
 */
const lastFortnightDate = computed(() => {
    const firstStr = form.data.nomina.first_fortnight
    const count = Number(form.data.nomina.fortnights)
    if (!firstStr || !count) return ''

    let current = firstStr
    for (let i = 1; i < count; i++) {
        current = nextFortnight(current)
    }

    const [yyyy, mm, dd] = current.split('-').map(Number)
    const months = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic']
    return `${String(dd).padStart(2, '0')} ${months[mm - 1]} ${yyyy}`
})

// ── Payment method helpers ─────────────────────────────────────────────────────
const showNomina = computed(() =>
    form.data.payment_type === 'nomina' || form.data.payment_type === 'mixed'
)

const showBenefits = computed(() =>
    form.data.payment_type === 'benefit' || form.data.payment_type === 'mixed'
)

// ── Available benefits (only future collection dates) ─────────────────────────
const availableBenefits = computed(() => {
    const today = new Date().toISOString().slice(0, 10)
    return props.benefits.filter(b => b.collectionDate > today)
})

// ── Selected benefit IDs tracking ─────────────────────────────────────────────
const selectedBenefitIds = ref<Set<number>>(new Set())

function toggleBenefit(benefit: typeof props.benefits[0]) {
    if (selectedBenefitIds.value.has(benefit.id)) {
        selectedBenefitIds.value.delete(benefit.id)
        form.data.benefits = form.data.benefits.filter(b => b.benefit_id !== benefit.id)
    } else {
        selectedBenefitIds.value.add(benefit.id)
        form.data.benefits.push({
            benefit_id: benefit.id,
            amount: '',
            collection_date: benefit.collectionDate,
        })
    }
}

function isBenefitSelected(id: number): boolean {
    return selectedBenefitIds.value.has(id)
}

// ── Loan total computed in real time ──────────────────────────────────────────
const loanTotal = computed(() => {
    const nominaAmt = parseFloat(form.data.nomina.amount) || 0
    const benefitsAmt = form.data.benefits.reduce((sum, b) => sum + (parseFloat(b.amount) || 0), 0)
    return nominaAmt + benefitsAmt
})

const loanTotalFormatted = computed(() =>
    loanTotal.value.toLocaleString('es-MX', { style: 'currency', currency: 'MXN' })
)

// ── Submit ────────────────────────────────────────────────────────────────────
const pdfOpened = ref(false)

const submit = async () => {
    pdfOpened.value = false

    await form.post('/api/simulador-prestamo/simulate', {
        onSuccess: (data: { pdf_url?: string }) => {
            if (data.pdf_url) {
                window.open(data.pdf_url, '_blank', 'noopener')
                pdfOpened.value = true
            }
        },
    })
}

// ── Integer-only input helper ──────────────────────────────────────────────────
function setIntegerField(e: Event, setter: (value: string) => void) {
    const input = e.target as HTMLInputElement
    const digits = input.value.replace(/\D/g, '')
    input.value = digits
    setter(digits)
}

// ── Field error helper ─────────────────────────────────────────────────────────
function fieldError(key: string): string | undefined {
    const errors = form.errors as Record<string, string[] | undefined>
    return errors[key]?.[0]
}
</script>

<template>
    <div class="min-h-screen min-w-0 w-full flex flex-col overflow-x-hidden bg-base-200">

        <!-- ===== Header ===== -->
        <header class="bg-base-300 border-b border-base-300">
            <div class="max-w-3xl w-full min-w-0 mx-auto px-4 sm:px-6 py-6 sm:py-10">
                <div class="flex items-center gap-2 mb-2 sm:mb-4">
                    <span class="text-sm uppercase tracking-[.18em] text-base-content/60 font-semibold">
                        Sindicato Único de Trabajadores
                    </span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-semibold tracking-tight text-base-content">
                    Simulador de Préstamo
                </h1>
                <p class="mt-2 text-base text-base-content/60">
                    Calcula tu corrida financiera y tabla de amortización sin compromiso.
                </p>
            </div>
        </header>

        <!-- ===== Disclaimer ===== -->
        <div class="bg-warning/10 border-b border-warning/30">
            <div class="max-w-3xl w-full min-w-0 mx-auto px-4 sm:px-6 py-3 flex items-center gap-2 text-sm text-warning-content">
                <svg class="w-4 h-4 shrink-0 text-warning" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                </svg>
                <span>Esta simulación es únicamente informativa.</span>
            </div>
        </div>

        <main class="flex-1">
            <div class="max-w-3xl w-full min-w-0 mx-auto px-4 sm:px-6 py-6 sm:py-10 space-y-6 sm:space-y-10">

                <!-- ===== Feedback alerts ===== -->
                <div v-if="pdfOpened && form.recentlySuccessful" role="status" class="alert alert-success rounded-box flex items-center gap-3">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M9 12l2 2 4-4"/><circle cx="12" cy="12" r="10"/>
                    </svg>
                    <span class="text-sm">Simulación completada. El PDF se abrió en una pestaña nueva.</span>
                </div>

                <div v-if="form.statusType === 'error'" role="alert" class="alert alert-error rounded-box flex items-center gap-3">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                    </svg>
                    <span class="text-sm">{{ form.statusMessage }}</span>
                </div>

                <!-- ===== 01 — Forma de pago ===== -->
                <section>
                    <h2 class="flex items-center gap-2 text-base font-semibold text-base-content/70 uppercase tracking-wide mb-3">
                        <!-- credit-card icon -->
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5z"/>
                        </svg>
                        01 — Forma de pago
                    </h2>
                    <div class="border-t border-base-300 pt-4 sm:pt-6 space-y-5">

                        <!-- Payment type cards -->
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3" role="group" aria-label="Forma de pago">
                            <label
                                v-for="opt in [
                                    { value: 'nomina', label: 'Nómina', desc: 'Descuento directo de nómina en quincenas regulares.' },
                                    { value: 'benefit', label: 'Prestación', desc: 'Se descuenta de tus prestaciones anuales.' },
                                    { value: 'mixed', label: 'Mixto', desc: 'Combina quincenas de nómina y prestaciones.' },
                                ]"
                                :key="opt.value"
                                class="cursor-pointer border rounded-xl p-4 transition-all"
                                :class="form.data.payment_type === opt.value
                                    ? 'border-primary bg-primary/5 ring-2 ring-primary'
                                    : 'border-base-300 bg-base-100 hover:border-base-content/30'"
                            >
                                <input
                                    type="radio"
                                    name="payment_type"
                                    class="sr-only"
                                    :value="opt.value"
                                    v-model="form.data.payment_type"
                                />
                                <p class="text-base font-semibold">{{ opt.label }}</p>
                                <p class="text-sm text-base-content/60 mt-1">{{ opt.desc }}</p>
                            </label>
                        </div>

                        <!-- Nómina sub-section -->
                        <fieldset v-if="showNomina" class="min-w-0 bg-base-100 border border-base-300 rounded-box overflow-hidden">
                            <div class="px-4 sm:px-5 py-2.5 border-b border-base-300 bg-base-200/60">
                                <legend class="text-sm font-semibold uppercase tracking-widest text-base-content/70">
                                    Nómina quincenal
                                </legend>
                            </div>
                            <div class="p-4 sm:p-5 grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-5">

                                <!-- Nomina amount -->
                                <div class="form-control sm:col-span-2">
                                    <label class="label py-1" for="nomina-amount">
                                        <span class="label-text text-sm uppercase tracking-wider text-base-content/60 font-semibold">
                                            Monto nómina ($)
                                        </span>
                                    </label>
                                    <input
                                        id="nomina-amount"
                                        :value="form.data.nomina.amount"
                                        type="number"
                                        inputmode="numeric"
                                        min="1"
                                        step="1"
                                        placeholder="Ej. 10,000"
                                        @input="(e: Event) => setIntegerField(e, v => form.data.nomina.amount = v)"
                                        class="input input-bordered w-full text-base min-h-12"
                                        :class="{ 'input-error': fieldError('nomina.amount') }"
                                        :aria-invalid="Boolean(fieldError('nomina.amount'))"
                                        :aria-describedby="fieldError('nomina.amount') ? 'nomina-amount-error' : 'nomina-amount-hint'"
                                    />
                                    <p id="nomina-amount-hint" class="label-text-alt text-base-content/60 mt-1 text-sm">

                                    </p>
                                    <p v-if="fieldError('nomina.amount')" id="nomina-amount-error" class="label-text-alt text-error mt-1 text-sm">
                                        {{ fieldError('nomina.amount') }}
                                    </p>
                                </div>

                                <!-- First fortnight -->
                                <div class="form-control">
                                    <label class="label py-1" for="first-fortnight">
                                        <span class="label-text text-sm uppercase tracking-wider text-base-content/60 font-semibold">
                                            Primera quincena
                                        </span>
                                    </label>
                                    <select
                                        id="first-fortnight"
                                        v-model="form.data.nomina.first_fortnight"
                                        class="select select-bordered w-full text-base min-h-[48px]"
                                        :class="{ 'select-error': fieldError('nomina.first_fortnight') }"
                                        :aria-invalid="Boolean(fieldError('nomina.first_fortnight'))"
                                    >
                                        <option value="" disabled>Selecciona…</option>
                                        <option
                                            v-for="f in availableFortnights"
                                            :key="f.value"
                                            :value="f.value"
                                        >{{ f.label }}</option>
                                    </select>
                                    <p v-if="fieldError('nomina.first_fortnight')" class="label-text-alt text-error mt-1 text-sm">
                                        {{ fieldError('nomina.first_fortnight') }}
                                    </p>
                                    <p v-if="availableFortnights.length === 0" class="label-text-alt text-warning mt-1 text-sm">
                                        No hay quincenas disponibles (límite: 30 Nov {{ new Date().getFullYear() }}).
                                    </p>
                                </div>

                                <!-- Number of fortnights -->
                                <div class="form-control">
                                    <label class="label py-1" for="fortnights">
                                        <span class="label-text text-sm uppercase tracking-wider text-base-content/60 font-semibold">
                                            Número de quincenas
                                        </span>
                                    </label>
                                    <select
                                        id="fortnights"
                                        v-model="form.data.nomina.fortnights"
                                        class="select select-bordered w-full text-base min-h-[48px]"
                                        :class="{ 'select-error': fieldError('nomina.fortnights') }"
                                        :aria-invalid="Boolean(fieldError('nomina.fortnights'))"
                                        :disabled="!form.data.nomina.first_fortnight"
                                    >
                                        <option value="" disabled>
                                            {{ form.data.nomina.first_fortnight ? 'Selecciona…' : 'Elige primero la quincena inicial' }}
                                        </option>
                                        <option
                                            v-for="opt in availableFortnightCounts"
                                            :key="opt.value"
                                            :value="opt.value"
                                        >{{ opt.label }}</option>
                                    </select>
                                    <p v-if="fieldError('nomina.fortnights')" class="label-text-alt text-error mt-1 text-sm">
                                        {{ fieldError('nomina.fortnights') }}
                                    </p>
                                    <p v-if="lastFortnightDate" class="label-text-alt text-base-content/60 mt-1 text-sm">
                                        Última quincena: {{ lastFortnightDate }}
                                    </p>
                                </div>
                            </div>
                        </fieldset>

                        <!-- Prestaciones sub-section -->
                        <fieldset v-if="showBenefits" class="min-w-0 bg-base-100 border border-base-300 rounded-box overflow-hidden">
                            <div class="px-4 sm:px-5 py-2.5 border-b border-base-300 bg-base-200/60">
                                <legend class="text-sm font-semibold uppercase tracking-widest text-base-content/70">
                                    Prestaciones
                                </legend>
                            </div>
                            <div class="p-4 sm:p-5">
                                <p v-if="availableBenefits.length === 0" class="text-sm text-base-content/60">
                                    No hay prestaciones disponibles para el año en curso.
                                </p>
                                <div v-else class="space-y-2">
                                    <label
                                        v-for="benefit in availableBenefits"
                                        :key="benefit.id"
                                        class="flex flex-col cursor-pointer p-3 rounded-lg border border-transparent transition-colors"
                                        :class="isBenefitSelected(benefit.id)
                                            ? 'bg-primary/10 border-primary'
                                            : 'hover:bg-base-200'"
                                    >
                                        <div class="flex items-center gap-3">
                                            <input
                                                type="checkbox"
                                                class="checkbox checkbox-primary"
                                                :checked="isBenefitSelected(benefit.id)"
                                                @change="toggleBenefit(benefit)"
                                                :aria-label="benefit.name"
                                            />
                                            <span class="flex-1 text-base">{{ benefit.name }}</span>
                                            <span class="text-sm text-base-content/60">
                                                {{ new Date(benefit.collectionDate + 'T00:00:00').toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' }) }}
                                            </span>
                                        </div>
                                        <!-- Per-benefit amount field -->
                                        <div
                                            v-if="isBenefitSelected(benefit.id)"
                                            class="mt-3 ml-9"
                                        >
                                            <input
                                                type="number"
                                                inputmode="numeric"
                                                min="1"
                                                step="1"
                                                placeholder="Monto ($)"
                                                class="input input-bordered input-sm w-full max-w-xs text-base min-h-[48px]"
                                                :class="{ 'input-error': fieldError(`benefits.${form.data.benefits.findIndex(b => b.benefit_id === benefit.id)}.amount`) }"
                                                :value="form.data.benefits.find(b => b.benefit_id === benefit.id)?.amount ?? ''"
                                                @input="(e: Event) => {
                                                    const idx = form.data.benefits.findIndex(b => b.benefit_id === benefit.id)
                                                    if (idx !== -1) setIntegerField(e, v => form.data.benefits[idx].amount = v)
                                                }"
                                            />
                                        </div>
                                    </label>
                                </div>
                                <p v-if="fieldError('benefits')" class="text-error text-sm mt-2">
                                    {{ fieldError('benefits') }}
                                </p>
                            </div>
                        </fieldset>
                    </div>
                </section>

                <!-- ===== 02 — Tasa de interés ===== -->
                <section>
                    <h2 class="flex items-center gap-2 text-base font-semibold text-base-content/70 uppercase tracking-wide mb-3">
                        <!-- percent icon -->
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 14.25l6-6m4.5-3.493V21.75l-3.75-1.5-3.75 1.5-3.75-1.5-3.75 1.5V4.757c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0 1 11.186 0c1.1.128 1.907 1.077 1.907 2.185zM9.75 9h.008v.008H9.75V9zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0zm4.125 4.5h.008v.008h-.008V13.5zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0z"/>
                        </svg>
                        02 — Tipo de trabajador solicitante
                    </h2>
                    <div class="border-t border-base-300 pt-4 sm:pt-6">
                        <fieldset
                            class="py-4 px-2 rounded-xl bg-base-200/50 border"
                            :class="fieldError('monthly_rate') ? 'border-error' : 'border-base-200'"
                            :aria-describedby="fieldError('monthly_rate') ? 'monthly-rate-error' : undefined"
                        >
                            <legend class="sr-only">Tipo de trabajador solicitante</legend>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2" role="radiogroup" aria-label="Tasa de interés mensual">
                                <label
                                    v-for="rate in MONTHLY_RATE_OPTIONS"
                                    :key="rate.value"
                                    class="flex items-center justify-between btn py-6 px-4 border border-base-300"
                                    :class="form.data.monthly_rate === rate.value
                                        ? 'border-primary bg-primary/5'
                                        : 'border-base-200 hover:border-base-content/30'"
                                >
                                    <span class="text-sm font-medium text-base-content/80">{{ rate.label }}</span>
                                    <span class="flex items-center gap-2">
                                        <span class="text-sm font-bold text-primary">{{ rate.value }} %</span>
                                        <input
                                            v-model="form.data.monthly_rate"
                                            type="radio"
                                            name="monthly_rate"
                                            :value="rate.value"
                                            class="radio radio-primary radio-sm"
                                            :aria-label="`${rate.value} % — ${rate.label}`"
                                        />
                                    </span>
                                </label>
                            </div>
                        </fieldset>
                        <p v-if="fieldError('monthly_rate')" id="monthly-rate-error" class="label-text-alt text-error mt-1 text-sm">
                            {{ fieldError('monthly_rate') }}
                        </p>
                        <p v-else class="label-text-alt text-base-content/60 mt-1 text-sm">
                            Selecciona la tasa correspondiente a tu situación.
                        </p>
                    </div>
                </section>

                <!-- ===== Submit ===== -->
                <section class="border-t-2 border-base-content/80 pt-6 space-y-4">

                    <!-- Loan total summary -->
                    <div v-if="loanTotal > 0" class="rounded-xl bg-base-100 border border-base-300 px-5 py-4 flex items-center justify-between">
                        <span class="text-base text-base-content/70 font-medium">Total del préstamo:</span>
                        <span class="text-xl font-semibold text-primary">{{ loanTotalFormatted }}</span>
                    </div>
                    <div v-else class="alert alert-warning text-sm">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                        </svg>
                        Ingresa al menos un monto para simular.
                    </div>

                    <p class="text-sm text-base-content/60 leading-relaxed">
                        Al simular, se generará un PDF con la corrida financiera y la tabla de amortización.
                        <strong>Este documento es únicamente informativo.</strong>
                    </p>
                    <button
                        type="submit"
                        class="btn btn-primary w-full sm:w-auto sm:px-10 min-h-13 text-base font-medium"
                        :disabled="form.processing"
                        @click.prevent="submit"
                    >
                        <span v-if="form.processing" class="loading loading-spinner loading-xs" aria-hidden="true"></span>
                        {{ form.processing ? 'Calculando…' : 'Simular préstamo' }}
                    </button>
                </section>

            </div>
        </main>

        <footer class="border-t border-base-300 mt-8 bg-base-100">
            <div class="max-w-3xl w-full min-w-0 mx-auto px-4 sm:px-6 py-4 sm:py-6 text-sm text-base-content/50 flex justify-between items-center">
                <span>OST SIUT ITSM</span>
                <span>Simulador</span>
            </div>
        </footer>
    </div>
</template>
