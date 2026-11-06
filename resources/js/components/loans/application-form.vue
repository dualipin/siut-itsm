<script setup lang="ts">
import { ref, computed, onMounted, nextTick } from 'vue'

interface BenefitOption {
  id: number
  name: string
  collection_date: string
  collection_day: number
  collection_month: number
  calculation_method: string
  requires_document: boolean
  document_instructions?: string | null
}

interface FortnightOption {
  date: string
  label: string
  day: number
  month: number
  year: number
}

interface UserProfile {
  id: number
  name: string
  curp?: string | null
  category?: string | null
  phone?: string | null
  address?: string | null
  role: string
  is_active: boolean
}

interface EvidenceUploadState {
  sourceType: 'nomina' | 'benefit'
  benefitId?: number | null
  benefitName?: string
  file: File | null
  fileName: string
  stage: 'idle' | 'saving' | 'preparing' | 'analyzing' | 'comparing' | 'done' | 'error'
  stageText: string
  progress: number
  aiStatus: 'valido' | 'dudoso' | 'invalido' | 'fallo_tecnico' | null
  classification: string | null
  confidence: number | null
  observations: string | null
  comparisonNotes: string | null
  extractedFields: Record<string, any> | null
  storagePath?: string | null
  evidenceId?: number | null
}

const props = defineProps<{
  user: UserProfile
  isWindowOpen: boolean
  activeLoan?: any
  fortnights: FortnightOption[]
  benefits: BenefitOption[]
  storeUrl: string
  uploadUrl: string
  submitUrl: string
  signUrl: string
  portalLoansUrl: string
}>()

const currentStep = ref(1)
const isSubmitting = ref(false)
const errorMessage = ref<string | null>(null)
const successMessage = ref<string | null>(null)

// Draft state
const loanId = ref<number | null>(props.activeLoan?.id ?? null)
const folio = ref<string | null>(props.activeLoan?.folio ?? null)

// Step 1: Loan Configuration
const paymentType = ref<'nomina' | 'benefit' | 'mixed'>('nomina')
const requestedAmount = ref<number>(10000)
const monthlyRate = ref<number | null>(props.activeLoan?.monthly_rate ? Number(props.activeLoan.monthly_rate) : null)
const salaryCommitment = ref<number | null>(null)
const bankName = ref<string>('')
const bankAccount = ref<string>('')
const interbankClabe = ref<string>('')

// Nomina fields
const nominaAmount = ref<number>(10000)
const nominaFortnights = ref<number>(4)
const firstFortnightDate = ref<string>(props.fortnights[0]?.date ?? '')

// Benefits selection
interface SelectedBenefit {
  benefit_id: number
  name: string
  amount: number
  collection_date: string
}
const selectedBenefits = ref<SelectedBenefit[]>([])

// Evidence upload states per payment source
const evidences = ref<EvidenceUploadState[]>([])

// Step 3: Signature
const signaturePadCanvas = ref<HTMLCanvasElement | null>(null)
const signatureData = ref<string>('')
const isDrawing = ref(false)
const hasSigned = ref(false)
const efirmaMode = ref(false)


function getCsrfToken(): string {
  const meta = document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null
  return meta?.content ?? ''
}

// Initialize evidence slots whenever payment type or benefits change
function syncEvidenceSlots() {
  const newEvidences: EvidenceUploadState[] = []

  if (paymentType.value === 'nomina' || paymentType.value === 'mixed') {
    const existing = evidences.value.find(e => e.sourceType === 'nomina')
    newEvidences.push(existing ?? {
      sourceType: 'nomina',
      benefitId: null,
      benefitName: 'Recibo de nómina reciente',
      file: null,
      fileName: '',
      stage: 'idle',
      stageText: 'Pendiente de carga',
      progress: 0,
      aiStatus: null,
      classification: null,
      confidence: null,
      observations: null,
      comparisonNotes: null,
      extractedFields: null,
    })
  }

  if (paymentType.value === 'benefit' || paymentType.value === 'mixed') {
    for (const b of selectedBenefits.value) {
      const existing = evidences.value.find(e => e.sourceType === 'benefit' && e.benefitId === b.benefit_id)
      newEvidences.push(existing ?? {
        sourceType: 'benefit',
        benefitId: b.benefit_id,
        benefitName: b.name,
        file: null,
        fileName: '',
        stage: 'idle',
        stageText: 'Pendiente de carga',
        progress: 0,
        aiStatus: null,
        classification: null,
        confidence: null,
        observations: null,
        comparisonNotes: null,
        extractedFields: null,
      })
    }
  }

  evidences.value = newEvidences
}

// Benefit selection handlers
function toggleBenefit(benefit: BenefitOption) {
  const idx = selectedBenefits.value.findIndex(b => b.benefit_id === benefit.id)
  if (idx > -1) {
    selectedBenefits.value.splice(idx, 1)
  } else {
    selectedBenefits.value.push({
      benefit_id: benefit.id,
      name: benefit.name,
      amount: 5000,
      collection_date: benefit.collection_date,
    })
  }
  syncAmounts()
  syncEvidenceSlots()
}

function isBenefitSelected(benefitId: number): boolean {
  return selectedBenefits.value.some(b => b.benefit_id === benefitId)
}

function syncAmounts() {
  if (paymentType.value === 'nomina') {
    nominaAmount.value = requestedAmount.value
    selectedBenefits.value = []
  } else if (paymentType.value === 'benefit') {
    nominaAmount.value = 0
    if (selectedBenefits.value.length === 1) {
      selectedBenefits.value[0].amount = requestedAmount.value
    }
  } else if (paymentType.value === 'mixed') {
    const benefitsSum = selectedBenefits.value.reduce((acc, b) => acc + (b.amount || 0), 0)
    nominaAmount.value = Math.max(0, requestedAmount.value - benefitsSum)
  }
}

const totalConfigured = computed(() => {
  let total = 0
  if (paymentType.value === 'nomina' || paymentType.value === 'mixed') {
    total += Number(nominaAmount.value) || 0
  }
  if (paymentType.value === 'benefit' || paymentType.value === 'mixed') {
    total += selectedBenefits.value.reduce((acc, b) => acc + (Number(b.amount) || 0), 0)
  }
  return total
})

const isStep1Valid = computed(() => {
  if (!requestedAmount.value || requestedAmount.value < 100) return false
  if (paymentType.value === 'nomina') {
    return nominaAmount.value > 0 && nominaFortnights.value >= 1 && !!firstFortnightDate.value
  }
  if (paymentType.value === 'benefit') {
    return selectedBenefits.value.length > 0 && totalConfigured.value === requestedAmount.value
  }
  if (paymentType.value === 'mixed') {
    return (
      nominaAmount.value > 0 &&
      nominaFortnights.value >= 1 &&
      !!firstFortnightDate.value &&
      selectedBenefits.value.length > 0 &&
      totalConfigured.value === requestedAmount.value
    )
  }
  return false
})

const areAllEvidencesUploaded = computed(() => {
  if (evidences.value.length === 0) return false
  return evidences.value.every(e => e.aiStatus !== null && e.stage === 'done')
})

const isUploadingAny = computed(() => {
  return evidences.value.some(e => ['saving', 'preparing', 'analyzing', 'comparing'].includes(e.stage))
})

// Save draft to backend
async function saveDraft(): Promise<boolean> {
  errorMessage.value = null
  isSubmitting.value = true

  const payload: any = {
    loan_id: loanId.value,
    payment_type: paymentType.value,
    requested_amount: requestedAmount.value,
    monthly_rate: monthlyRate.value,
    salary_commitment_amount: salaryCommitment.value,
    bank_name: bankName.value,
    bank_account: bankAccount.value,
    interbank_clabe: interbankClabe.value,
  }

  if (paymentType.value === 'nomina' || paymentType.value === 'mixed') {
    payload.nomina_amount = nominaAmount.value
    payload.nomina_fortnights = nominaFortnights.value
    payload.first_fortnight_date = firstFortnightDate.value
  }

  if (paymentType.value === 'benefit' || paymentType.value === 'mixed') {
    payload.benefits = selectedBenefits.value.map(b => ({
      benefit_id: b.benefit_id,
      amount: b.amount,
    }))
  }

  try {
    const res = await fetch(props.storeUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': getCsrfToken(),
      },
      body: JSON.stringify(payload),
    })

    const json = await res.json()
    if (!res.ok) {
      const firstErr = json.errors ? Object.values(json.errors)[0]?.[0] : json.message
      errorMessage.value = firstErr || 'Error al guardar la solicitud.'
      isSubmitting.value = false
      return false
    }

    loanId.value = json.loan.id
    folio.value = json.loan.folio
    isSubmitting.value = false
    return true
  } catch (err: any) {
    errorMessage.value = 'Error de conexión al guardar el borrador.'
    isSubmitting.value = false
    return false
  }
}

// Navigation between steps
async function goToStep2() {
  if (!isStep1Valid.value) return
  const saved = await saveDraft()
  if (saved) {
    syncEvidenceSlots()
    currentStep.value = 2
  }
}

async function goToStep3() {
  if (!areAllEvidencesUploaded.value) return
  currentStep.value = 3
  await nextTick()
  initCanvas()
}

// Synchronous blocking evidence upload
async function handleFileUpload(evState: EvidenceUploadState, event: Event) {
  const target = event.target as HTMLInputElement
  const file = target.files?.[0]
  if (!file) return

  // 1. Client-side local check
  if (file.size > 5 * 1024 * 1024) {
    evState.stage = 'error'
    evState.stageText = 'El archivo supera el límite de 5 MB.'
    evState.observations = 'El archivo excede el tamaño máximo permitido de 5 MB.'
    return
  }

  evState.file = file
  evState.fileName = file.name
  evState.stage = 'saving'
  evState.stageText = '1/5 Guardando archivo...'
  evState.progress = 20

  const formData = new FormData()
  formData.append('file', file)
  formData.append('source_type', evState.sourceType)
  if (evState.benefitId) {
    formData.append('benefit_id', String(evState.benefitId))
  }
  if (loanId.value) {
    formData.append('loan_id', String(loanId.value))
  }

  setTimeout(() => {
    if (evState.stage === 'saving') {
      evState.stage = 'preparing'
      evState.stageText = '2/5 Preparando documento...'
      evState.progress = 40
    }
  }, 400)

  setTimeout(() => {
    if (evState.stage === 'preparing') {
      evState.stage = 'analyzing'
      evState.stageText = '3/5 Analizando con IA...'
      evState.progress = 65
    }
  }, 800)

  try {
    const res = await fetch(props.uploadUrl, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'X-CSRF-TOKEN': getCsrfToken(),
      },
      body: formData,
    })

    evState.stage = 'comparing'
    evState.stageText = '4/5 Comparando datos con perfil...'
    evState.progress = 90

    const json = await res.json()
    if (!res.ok) {
      evState.stage = 'error'
      evState.stageText = json.errors?.file?.[0] || json.message || 'Error al validar el archivo.'
      evState.observations = evState.stageText
      return
    }

    const ai = json.ai_result
    evState.stage = 'done'
    evState.stageText = '5/5 Análisis finalizado'
    evState.progress = 100
    evState.aiStatus = ai.ai_status
    evState.classification = ai.classification
    evState.confidence = ai.confidence
    evState.observations = ai.observations
    evState.extractedFields = ai.extracted_fields
    evState.comparisonNotes = ai.comparison?.notes ?? null
    evState.storagePath = json.storage?.path
    if (json.evidence?.id) {
      evState.evidenceId = json.evidence.id
    }
  } catch (err: any) {
    evState.stage = 'error'
    evState.stageText = 'Fallo de conexión durante la validación.'
    evState.aiStatus = 'fallo_tecnico'
    evState.observations = 'No fue posible conectar con el servidor. Se requerirá revisión manual.'
  }
}

// Canvas drawn signature
function initCanvas() {
  const canvas = signaturePadCanvas.value
  if (!canvas) return
  const ctx = canvas.getContext('2d')
  if (!ctx) return

  ctx.lineWidth = 2.5
  ctx.lineCap = 'round'
  ctx.lineJoin = 'round'
  ctx.strokeStyle = '#000000'

  function getPos(e: MouseEvent | TouchEvent) {
    const rect = canvas.getBoundingClientRect()
    const clientX = 'touches' in e ? e.touches[0].clientX : e.clientX
    const clientY = 'touches' in e ? e.touches[0].clientY : e.clientY
    return {
      x: clientX - rect.left,
      y: clientY - rect.top,
    }
  }

  canvas.onmousedown = (e) => {
    isDrawing.value = true
    const pos = getPos(e)
    ctx.beginPath()
    ctx.moveTo(pos.x, pos.y)
  }

  canvas.onmousemove = (e) => {
    if (!isDrawing.value) return
    const pos = getPos(e)
    ctx.lineTo(pos.x, pos.y)
    ctx.stroke()
    hasSigned.value = true
  }

  window.onmouseup = () => {
    if (isDrawing.value) {
      isDrawing.value = false
      signatureData.value = canvas.toDataURL('image/png')
    }
  }

  canvas.ontouchstart = (e) => {
    e.preventDefault()
    isDrawing.value = true
    const pos = getPos(e)
    ctx.beginPath()
    ctx.moveTo(pos.x, pos.y)
  }

  canvas.ontouchmove = (e) => {
    e.preventDefault()
    if (!isDrawing.value) return
    const pos = getPos(e)
    ctx.lineTo(pos.x, pos.y)
    ctx.stroke()
    hasSigned.value = true
  }

  canvas.ontouchend = () => {
    if (isDrawing.value) {
      isDrawing.value = false
      signatureData.value = canvas.toDataURL('image/png')
    }
  }
}

function clearSignature() {
  const canvas = signaturePadCanvas.value
  if (!canvas) return
  const ctx = canvas.getContext('2d')
  ctx?.clearRect(0, 0, canvas.width, canvas.height)
  signatureData.value = ''
  hasSigned.value = false
}

// Final submission
async function submitLoanApplication() {
  if (!loanId.value) return
  errorMessage.value = null
  isSubmitting.value = true

  // 1. Save signature if captured
  if (signatureData.value) {
    try {
      const signRes = await fetch(props.signUrl.replace('__LOAN_ID__', String(loanId.value)), {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': getCsrfToken(),
        },
        body: JSON.stringify({ signature_data: signatureData.value }),
      })
      if (!signRes.ok) {
        const j = await signRes.json()
        errorMessage.value = j.message || 'Error al guardar la firma.'
        isSubmitting.value = false
        return
      }
    } catch {
      errorMessage.value = 'Fallo de red al registrar la firma.'
      isSubmitting.value = false
      return
    }
  }

  // 2. Submit loan
  try {
    const res = await fetch(props.submitUrl.replace('__LOAN_ID__', String(loanId.value)), {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'X-CSRF-TOKEN': getCsrfToken(),
      },
    })
    const json = await res.json()
    if (!res.ok) {
      errorMessage.value = json.message || 'Error al enviar la solicitud.'
      isSubmitting.value = false
      return
    }

    successMessage.value = '¡Solicitud enviada con éxito! Redirigiendo al portal...'
    setTimeout(() => {
      window.location.href = props.portalLoansUrl
    }, 1500)
  } catch (err: any) {
    errorMessage.value = 'Error al enviar la solicitud. Por favor verifique su conexión.'
    isSubmitting.value = false
  }
}

onMounted(() => {
  syncAmounts()
  syncEvidenceSlots()
})
</script>

<template>
  <div class="space-y-6">
    <!-- Deadline warning if closed -->
    <div v-if="!isWindowOpen" class="alert alert-error shadow-lg">
      <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      <div>
        <h3 class="font-bold">Periodo de Solicitudes Cerrado</h3>
        <div class="text-sm">El periodo de recepción de solicitudes de préstamos para el ciclo actual concluyó el 30 de noviembre. No es posible generar nuevas solicitudes en este momento.</div>
      </div>
    </div>

    <!-- Active loan banner if user already has an active loan -->
    <div v-else-if="activeLoan && activeLoan.status !== 'borrador'" class="card bg-base-100 shadow-xl border border-warning/30">
      <div class="card-body">
        <div class="flex items-start gap-4">
          <div class="p-3 bg-warning/20 rounded-xl text-warning">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <div class="flex-1">
            <h2 class="card-title text-base sm:text-lg">Ya cuentas con una solicitud activa</h2>
            <p class="text-sm text-base-content/70 mt-1">
              Tienes la solicitud <strong>{{ activeLoan.folio }}</strong> en estado <strong>{{ activeLoan.status }}</strong>. Por política del sindicato, solo se permite una solicitud activa simultánea por agremiado.
            </p>
            <div class="card-actions justify-end mt-4">
              <a :href="portalLoansUrl" class="btn btn-primary btn-sm">Ver mis solicitudes</a>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Main wizard -->
    <div v-else class="card bg-base-100 shadow-xl border border-base-300">
      <!-- Steps Indicator -->
      <div class="bg-base-200/50 p-4 border-b border-base-300">
        <ul class="steps steps-horizontal w-full text-xs sm:text-sm">
          <li class="step" :class="{ 'step-primary': currentStep >= 1 }">1. Condiciones</li>
          <li class="step" :class="{ 'step-primary': currentStep >= 2 }">2. Documentos e IA</li>
          <li class="step" :class="{ 'step-primary': currentStep >= 3 }">3. Firma y Envío</li>
        </ul>
      </div>

      <div class="card-body p-4 sm:p-8">
        <!-- Error alert -->
        <div v-if="errorMessage" class="alert alert-error text-sm mb-4">
          <span>{{ errorMessage }}</span>
        </div>
        <div v-if="successMessage" class="alert alert-success text-sm mb-4">
          <span>{{ successMessage }}</span>
        </div>

        <!-- ========================================== -->
        <!-- STEP 1: Loan Configuration                -->
        <!-- ========================================== -->
        <div v-if="currentStep === 1" class="space-y-6">
          <div class="border-b border-base-300 pb-3">
            <h2 class="text-lg font-bold text-base-content">Paso 1: Parámetros del Préstamo</h2>
            <p class="text-xs text-base-content/60">Define el monto, la forma de pago y los plazos deseados.</p>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Monto Solicitado -->
            <div class="form-control">
              <label class="label"><span class="label-text font-semibold">Monto Total Solicitado ($ MXN)</span></label>
              <input
                v-model.number="requestedAmount"
                type="number"
                min="100"
                step="500"
                class="input input-bordered w-full font-bold text-lg"
                @input="syncAmounts"
              />
            </div>

            <!-- Tasa de Interés (Determinada por administración) -->
            <div class="form-control justify-end">
              <div class="p-3 rounded-xl bg-base-200/60 border border-base-300 flex items-start gap-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-primary shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div class="text-xs">
                  <span class="font-bold text-base-content block">Tasa de Interés</span>
                  <span class="text-base-content/70">
                    La tasa aplicable (6.0% a 9.5% mensual) es asignada y dictaminada por la administración del sindicato según reglamento al evaluar tu solicitud.
                  </span>
                </div>
              </div>
            </div>
          </div>

          <!-- Forma de Pago -->
          <div class="form-control">
            <label class="label"><span class="label-text font-semibold">Forma de Pago</span></label>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
              <label
                class="border rounded-xl p-4 cursor-pointer flex flex-col items-center text-center transition-all"
                :class="paymentType === 'nomina' ? 'border-primary bg-primary/10 shadow-sm' : 'border-base-300 hover:border-base-content/30'"
              >
                <input type="radio" value="nomina" v-model="paymentType" class="radio radio-primary radio-sm mb-2" @change="syncAmounts(); syncEvidenceSlots();" />
                <span class="font-bold text-sm">Solo Nómina</span>
                <span class="text-xs text-base-content/60 mt-1">Descuentos quincenales (Método alemán)</span>
              </label>

              <label
                class="border rounded-xl p-4 cursor-pointer flex flex-col items-center text-center transition-all"
                :class="paymentType === 'benefit' ? 'border-primary bg-primary/10 shadow-sm' : 'border-base-300 hover:border-base-content/30'"
              >
                <input type="radio" value="benefit" v-model="paymentType" class="radio radio-primary radio-sm mb-2" @change="syncAmounts(); syncEvidenceSlots();" />
                <span class="font-bold text-sm">Solo Prestación(es)</span>
                <span class="text-xs text-base-content/60 mt-1">Interés compuesto a la fecha de cobro</span>
              </label>

              <label
                class="border rounded-xl p-4 cursor-pointer flex flex-col items-center text-center transition-all"
                :class="paymentType === 'mixed' ? 'border-primary bg-primary/10 shadow-sm' : 'border-base-300 hover:border-base-content/30'"
              >
                <input type="radio" value="mixed" v-model="paymentType" class="radio radio-primary radio-sm mb-2" @change="syncAmounts(); syncEvidenceSlots();" />
                <span class="font-bold text-sm">Combinación Mixta</span>
                <span class="text-xs text-base-content/60 mt-1">Nómina + una o varias prestaciones</span>
              </label>
            </div>
          </div>

          <!-- Subsección Nómina -->
          <div v-if="paymentType === 'nomina' || paymentType === 'mixed'" class="bg-base-200/40 p-4 rounded-xl space-y-4 border border-base-300">
            <h3 class="font-bold text-sm text-primary flex items-center gap-2">
              <span>📅</span> Configuración de Nómina Quincenal
            </h3>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
              <div class="form-control">
                <label class="label"><span class="label-text text-xs">Monto a amortizar por Nómina</span></label>
                <input v-model.number="nominaAmount" type="number" min="1" class="input input-bordered input-sm" :disabled="paymentType === 'nomina'" />
              </div>

              <div class="form-control">
                <label class="label"><span class="label-text text-xs">Primera Quincena Disponible (Tolerancia 20 días)</span></label>
                <select v-model="firstFortnightDate" class="select select-bordered select-sm">
                  <option v-for="f in fortnights" :key="f.date" :value="f.date">{{ f.label }}</option>
                </select>
              </div>

              <div class="form-control">
                <label class="label"><span class="label-text text-xs">Número de Quincenas</span></label>
                <input v-model.number="nominaFortnights" type="number" min="1" max="24" class="input input-bordered input-sm" />
              </div>
            </div>

            <div class="form-control">
              <label class="label"><span class="label-text text-xs">Compromiso Salarial Quincenal Deseado ($ opcional)</span></label>
              <input v-model.number="salaryCommitment" type="number" placeholder="Ej. 1500.00" class="input input-bordered input-sm max-w-xs" />
            </div>
          </div>

          <!-- Subsección Prestaciones -->
          <div v-if="paymentType === 'benefit' || paymentType === 'mixed'" class="bg-base-200/40 p-4 rounded-xl space-y-4 border border-base-300">
            <h3 class="font-bold text-sm text-info flex items-center gap-2">
              <span>🎁</span> Prestaciones Elegibles para el Ciclo
            </h3>

            <div v-if="benefits.length === 0" class="text-xs text-base-content/60">
              No hay prestaciones disponibles para amortizar en las fechas restantes del año.
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div
                v-for="b in benefits"
                :key="b.id"
                class="border rounded-xl p-3 flex flex-col justify-between transition-all"
                :class="isBenefitSelected(b.id) ? 'border-info bg-info/10' : 'border-base-300 bg-base-100'"
              >
                <div class="flex items-start justify-between">
                  <div>
                    <span class="font-bold text-sm block">{{ b.name }}</span>
                    <span class="text-xs text-base-content/60">Cobro: {{ b.collection_date }}</span>
                  </div>
                  <input
                    type="checkbox"
                    :checked="isBenefitSelected(b.id)"
                    @change="toggleBenefit(b)"
                    class="checkbox checkbox-info checkbox-sm"
                  />
                </div>

                <div v-if="isBenefitSelected(b.id)" class="mt-3 pt-2 border-t border-info/20">
                  <label class="text-xs font-semibold block mb-1">Monto para esta prestación ($):</label>
                  <input
                    type="number"
                    min="1"
                    class="input input-bordered input-xs w-full"
                    :value="selectedBenefits.find(sb => sb.benefit_id === b.id)?.amount"
                    @input="(e: any) => {
                      const val = Number(e.target.value) || 0
                      const item = selectedBenefits.find(sb => sb.benefit_id === b.id)
                      if (item) item.amount = val
                      syncAmounts()
                    }"
                  />
                </div>
              </div>
            </div>

            <div v-if="paymentType === 'mixed'" class="text-xs text-right font-semibold" :class="totalConfigured === requestedAmount ? 'text-success' : 'text-error'">
              Total configurado: ${{ totalConfigured.toLocaleString() }} de ${{ requestedAmount.toLocaleString() }}
            </div>
          </div>

          <!-- Datos Bancarios -->
          <div class="bg-base-200/20 p-4 rounded-xl space-y-3 border border-base-300">
            <h3 class="font-bold text-sm text-base-content flex items-center gap-2">
              <span>🏦</span> Datos de Depósito del Solicitante
            </h3>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
              <div class="form-control">
                <label class="label"><span class="label-text text-xs">Banco</span></label>
                <input v-model="bankName" type="text" placeholder="Ej. BBVA, Banorte" class="input input-bordered input-sm" />
              </div>
              <div class="form-control">
                <label class="label"><span class="label-text text-xs">Número de Cuenta</span></label>
                <input v-model="bankAccount" type="text" placeholder="Número de cuenta" class="input input-bordered input-sm" />
              </div>
              <div class="form-control">
                <label class="label"><span class="label-text text-xs">CLABE Interbancaria (18 dígitos)</span></label>
                <input v-model="interbankClabe" type="text" maxlength="18" placeholder="18 dígitos" class="input input-bordered input-sm font-mono" />
              </div>
            </div>
          </div>

          <!-- Navigation -->
          <div class="flex justify-end pt-4">
            <button
              type="button"
              class="btn btn-primary btn-sm sm:btn-md"
              :disabled="!isStep1Valid || isSubmitting"
              @click="goToStep2"
            >
              <span v-if="isSubmitting" class="loading loading-spinner loading-xs"></span>
              Siguiente: Subir Documentos
            </button>
          </div>
        </div>

        <!-- ========================================== -->
        <!-- STEP 2: Evidence Upload & AI Verification  -->
        <!-- ========================================== -->
        <div v-else-if="currentStep === 2" class="space-y-6">
          <div class="border-b border-base-300 pb-3">
            <h2 class="text-lg font-bold text-base-content">Paso 2: Validación Documental con IA</h2>
            <p class="text-xs text-base-content/60">
              Cada fuente de amortización requiere su comprobante propio. El análisis es síncrono y honesto; valida que el titular y datos coincidan con tu registro.
            </p>
          </div>

          <div class="space-y-4">
            <div
              v-for="(ev, index) in evidences"
              :key="index"
              class="border rounded-2xl p-4 sm:p-5 bg-base-100 shadow-sm space-y-3"
              :class="{
                'border-success/50 bg-success/5': ev.aiStatus === 'valido',
                'border-warning/50 bg-warning/5': ev.aiStatus === 'dudoso' || ev.aiStatus === 'fallo_tecnico',
                'border-error/50 bg-error/5': ev.aiStatus === 'invalido',
                'border-base-300': !ev.aiStatus
              }"
            >
              <div class="flex items-center justify-between">
                <div>
                  <span class="badge badge-sm badge-neutral font-mono uppercase">{{ ev.sourceType }}</span>
                  <h3 class="font-bold text-base mt-1">{{ ev.benefitName }}</h3>
                </div>

                <!-- Status Badge -->
                <div>
                  <span v-if="ev.aiStatus === 'valido'" class="badge badge-success gap-1 text-xs">
                    ✓ Válido por IA
                  </span>
                  <span v-else-if="ev.aiStatus === 'dudoso'" class="badge badge-warning gap-1 text-xs">
                    ⚠ Dudoso (Revisión manual)
                  </span>
                  <span v-else-if="ev.aiStatus === 'fallo_tecnico'" class="badge badge-warning gap-1 text-xs">
                    ⚙ Fallo IA (Revisión manual)
                  </span>
                  <span v-else-if="ev.aiStatus === 'invalido'" class="badge badge-error gap-1 text-xs">
                    ✕ Inválido
                  </span>
                  <span v-else class="badge badge-ghost text-xs">Sin archivo</span>
                </div>
              </div>

              <!-- Upload input & progress -->
              <div class="pt-2">
                <div v-if="ev.stage === 'idle' || ev.stage === 'error' || ev.stage === 'done'" class="flex items-center gap-3">
                  <input
                    type="file"
                    accept=".pdf,.png,.jpg,.jpeg,.webp"
                    class="file-input file-input-bordered file-input-sm w-full max-w-md"
                    :disabled="isUploadingAny"
                    @change="(e) => handleFileUpload(ev, e)"
                  />
                  <span v-if="ev.fileName" class="text-xs text-base-content/60 truncate">{{ ev.fileName }}</span>
                </div>

                <!-- Progress bar during active validation stages -->
                <div v-else class="space-y-2 py-2">
                  <div class="flex items-center justify-between text-xs font-semibold">
                    <span class="text-primary">{{ ev.stageText }}</span>
                    <span>{{ ev.progress }}%</span>
                  </div>
                  <progress class="progress progress-primary w-full" :value="ev.progress" max="100"></progress>
                </div>
              </div>

              <!-- AI observations & notes -->
              <div v-if="ev.observations" class="text-xs p-3 rounded-xl bg-base-200/60 text-base-content/80">
                <strong>Observaciones:</strong> {{ ev.observations }}
              </div>
            </div>
          </div>

          <div class="flex justify-between pt-4">
            <button type="button" class="btn btn-ghost btn-sm" @click="currentStep = 1">← Volver al Paso 1</button>
            <button
              type="button"
              class="btn btn-primary btn-sm sm:btn-md"
              :disabled="!areAllEvidencesUploaded || isUploadingAny"
              @click="goToStep3"
            >
              Siguiente: Firmar Solicitud
            </button>
          </div>
        </div>

        <!-- ========================================== -->
        <!-- STEP 3: Drawn Signature & Submission       -->
        <!-- ========================================== -->
        <div v-else-if="currentStep === 3" class="space-y-6">
          <div class="border-b border-base-300 pb-3">
            <h2 class="text-lg font-bold text-base-content">Paso 3: Firma del Solicitante y Envío</h2>
            <p class="text-xs text-base-content/60">
              La firma dibujada es obligatoria. Se vinculará de forma criptográfica a los términos de tu préstamo.
            </p>
          </div>

          <!-- Summary card -->
          <div class="bg-base-200/50 p-4 rounded-xl space-y-2 text-xs sm:text-sm border border-base-300">
            <h4 class="font-bold text-primary">Resumen de la Solicitud</h4>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 pt-1">
              <div><span class="text-base-content/60 block">Solicitante:</span> <strong>{{ user.name }}</strong></div>
              <div><span class="text-base-content/60 block">Monto:</span> <strong>${{ requestedAmount.toLocaleString() }}</strong></div>
              <div><span class="text-base-content/60 block">Tasa:</span> <strong>{{ monthlyRate ? monthlyRate + "% mensual" : "Por definir por administración" }}</strong></div>
              <div><span class="text-base-content/60 block">Forma de Pago:</span> <strong>{{ paymentType }}</strong></div>
            </div>
          </div>

          <!-- Signature Pad -->
          <div class="space-y-2">
            <div class="flex items-center justify-between">
              <label class="label-text font-bold">Dibuja tu firma en el recuadro:</label>
              <button type="button" class="btn btn-ghost btn-xs text-error" @click="clearSignature">Limpiar Firma</button>
            </div>

            <div class="border-2 border-dashed border-base-300 rounded-xl p-2 bg-white flex justify-center">
              <canvas
                ref="signaturePadCanvas"
                width="500"
                height="160"
                class="touch-none cursor-crosshair w-full max-w-lg h-40 bg-white"
              ></canvas>
            </div>
            <p class="text-xs text-base-content/60 italic text-center">Usa el mouse o el dedo en pantallas táctiles para firmar.</p>
          </div>

          <!-- Prepared e.firma section -->
          <div class="border border-base-300 p-4 rounded-xl bg-base-200/30 flex items-center justify-between">
            <div>
              <span class="font-bold text-xs block">Firma Electrónica Avanzada (e.firma SAT)</span>
              <span class="text-xs text-base-content/60">Integración preparada para fases posteriores.</span>
            </div>
            <span class="badge badge-sm badge-ghost">Preparada</span>
          </div>

          <div class="flex justify-between pt-4">
            <button type="button" class="btn btn-ghost btn-sm" @click="currentStep = 2">← Volver a Documentos</button>
            <button
              type="button"
              class="btn btn-success btn-sm sm:btn-md text-white font-bold"
              :disabled="!hasSigned || isSubmitting"
              @click="submitLoanApplication"
            >
              <span v-if="isSubmitting" class="loading loading-spinner loading-xs"></span>
              Enviar Solicitud Definitiva
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
