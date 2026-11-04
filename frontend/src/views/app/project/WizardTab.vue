<script setup lang="ts">
/**
 * Bản khai 8 bước (Stepper): mô hình kinh doanh, nền tảng, dữ liệu cá nhân,
 * thanh toán, nội dung người dùng, thương mại điện tử, xuất nhập khẩu, hoàn thành.
 * Câu hỏi sinh từ bộ câu hỏi đang áp dụng (GET /projects/{id}/profile), gom vào
 * từng bước theo nhóm + mã câu hỏi; nháp tự lưu vào localStorage sau mỗi thay đổi
 * và được khôi phục khi tải lại trang. next/hoàn tất đẩy hồ sơ lên API
 * (PUT /projects/{id}/profile).
 */
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { CheckCircle2 } from '@lucide/vue'
import Callout from '@/components/ui/Callout.vue'
import CheckOption from '@/components/ui/CheckOption.vue'
import StatusChip from '@/components/ui/StatusChip.vue'
import Stepper from '@/components/ui/Stepper.vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiCard from '@/components/ui/UiCard.vue'
import UiInput from '@/components/ui/UiInput.vue'
import UiSelect from '@/components/ui/UiSelect.vue'
import UiSkeleton from '@/components/ui/UiSkeleton.vue'
import UiTextarea from '@/components/ui/UiTextarea.vue'
import { useToastsStore } from '@/stores/toasts'
import { api, type ApiError } from '@/lib/api'
import type { ProjectProfile, Question, QuestionSet } from '@/types/models'

interface WizardStep {
  label: string
  short: string
  questions: Question[]
}

const route = useRoute()
const router = useRouter()
const toasts = useToastsStore()

const id = computed(() => String(route.params.id))
const DRAFT_KEY = computed(() => `vlc.wizard.${id.value}`)

const PAYMENT_CODES = ['handles_payments']
const CONTENT_CODES = ['allows_user_content', 'publishes_editorial_content']
const ECOMMERCE_CODES = ['sells_online', 'is_intermediary']
const SCOPE_CODES = ['markets', 'serves_public_sector']

const STEP_RULES: Array<{ label: string; short: string; pick: (question: Question) => boolean }> = [
  {
    label: 'Mô hình kinh doanh',
    short: 'Mô hình',
    pick: (question) =>
      question.section === 'MÔ HÌNH HOẠT ĐỘNG' &&
      !PAYMENT_CODES.includes(question.code) &&
      !CONTENT_CODES.includes(question.code) &&
      !ECOMMERCE_CODES.includes(question.code) &&
      !SCOPE_CODES.includes(question.code),
  },
  { label: 'Nền tảng', short: 'Nền tảng', pick: (question) => question.section === 'KỸ THUẬT' },
  { label: 'Dữ liệu cá nhân', short: 'Dữ liệu', pick: (question) => question.section === 'DỮ LIỆU' },
  { label: 'Thanh toán', short: 'Thanh toán', pick: (question) => PAYMENT_CODES.includes(question.code) },
  { label: 'Nội dung người dùng', short: 'Nội dung', pick: (question) => CONTENT_CODES.includes(question.code) },
  { label: 'Thương mại điện tử', short: 'TM điện tử', pick: (question) => ECOMMERCE_CODES.includes(question.code) },
  { label: 'Xuất nhập khẩu', short: 'Xuất nhập', pick: (question) => SCOPE_CODES.includes(question.code) },
  { label: 'Hoàn thành', short: 'Hoàn tất', pick: () => false },
]

const loading = ref(true)
const error = ref<ApiError | null>(null)
const saving = ref(false)
const running = ref(false)
const profile = ref<ProjectProfile | null>(null)
const questionSet = ref<QuestionSet | null>(null)
const answers = ref<Record<string, unknown>>({})
const current = ref(0)
const stepErrors = ref<string[]>([])

/**
 * 8 bước cố định theo spec. Câu hỏi không khớp nhóm riêng (section/mã chưa biết)
 * được gom vào bước đầu tiên để không rơi khỏi bản khai.
 */
const steps = computed<WizardStep[]>(() => {
  const questions = questionSet.value?.questions ?? []
  const mapped = STEP_RULES.map((rule) => ({
    label: rule.label,
    short: rule.short,
    questions: questions.filter((question) => rule.pick(question)),
  }))
  const assigned: Record<string, true> = {}
  for (const step of mapped) {
    for (const question of step.questions) assigned[question.code] = true
  }
  mapped[0].questions = [
    ...mapped[0].questions,
    ...questions.filter((question) => !assigned[question.code]),
  ]
  return mapped
})

const totalSteps = computed(() => steps.value.length)
const currentStep = computed(() => steps.value[current.value])
const isLast = computed(() => current.value === totalSteps.value - 1)
const requiredTotal = computed(() =>
  (questionSet.value?.questions ?? []).filter((question) => question.importance === 'REQUIRED').length,
)
const answeredRequiredCount = computed(() =>
  (questionSet.value?.questions ?? []).filter(
    (question) => question.importance === 'REQUIRED' && !isUnanswered(question),
  ).length,
)
const progress = computed(() =>
  requiredTotal.value === 0 ? 0 : Math.round((answeredRequiredCount.value / requiredTotal.value) * 100),
)
const answeredCount = computed(() => Object.keys(answers.value).length)

function isUnanswered(question: Question): boolean {
  const value = answers.value[question.code]
  if (value === undefined || value === null) return true
  if (Array.isArray(value)) return value.length === 0
  return String(value).trim() === ''
}

/** Validate từng bước: câu REQUIRED phải có câu trả lời. */
function validateStep(index: number): string[] {
  const step = steps.value[index]
  if (!step) return []
  return step.questions
    .filter((question) => question.importance === 'REQUIRED' && isUnanswered(question))
    .map((question) => question.prompt)
}

async function load(): Promise<void> {
  loading.value = true
  error.value = null
  try {
    const payload = await api.get<{ profile: ProjectProfile; question_set: QuestionSet }>(
      `/projects/${id.value}/profile`,
    )
    profile.value = payload.profile ?? null
    questionSet.value = payload.question_set ?? null
    answers.value = { ...(profile.value?.answers ?? {}) }
    const draft = readDraft()
    if (draft) {
      answers.value = { ...answers.value, ...draft.answers }
      current.value = Math.min(draft.step, Math.max(totalSteps.value - 1, 0))
      toasts.push('Đã khôi phục bản nháp', undefined, 'info', 4000)
    } else {
      current.value = Math.min(profile.value?.completed_step ?? 0, Math.max(totalSteps.value - 1, 0))
    }
  } catch (e) {
    error.value = e as ApiError
  } finally {
    loading.value = false
  }
}

interface DraftPayload {
  answers: Record<string, unknown>
  step: number
  savedAt: number
}

function readDraft(): DraftPayload | null {
  try {
    const raw = localStorage.getItem(DRAFT_KEY.value)
    if (!raw) return null
    const parsed = JSON.parse(raw) as DraftPayload
    if (!parsed || typeof parsed.savedAt !== 'number' || !parsed.answers) return null
    return parsed
  } catch {
    return null
  }
}

function saveDraft(): void {
  const payload: DraftPayload = { answers: answers.value, step: current.value, savedAt: Date.now() }
  try {
    localStorage.setItem(DRAFT_KEY.value, JSON.stringify(payload))
  } catch {
    /* localStorage không khả dụng (chế độ riêng tư) — bản nháp chỉ ở phía máy chủ. */
  }
}

async function saveProfile(status: 'DRAFT' | 'COMPLETED' = 'DRAFT'): Promise<boolean> {
  saving.value = true
  stepErrors.value = []
  try {
    const payload = await api.put<{ profile: ProjectProfile }>(`/projects/${id.value}/profile`, {
      answers: answers.value,
      completed_step: current.value,
      status,
    })
    profile.value = payload.profile ?? profile.value
    if (status === 'COMPLETED') localStorage.removeItem(DRAFT_KEY.value)
    return true
  } catch (e) {
    stepErrors.value = [(e as ApiError).message]
    return false
  } finally {
    saving.value = false
  }
}

function goNext(): void {
  const missing = validateStep(current.value)
  if (missing.length) {
    stepErrors.value = missing
    toasts.push('Cần trả lời các câu hỏi bắt buộc', missing[0], 'caution', 5000)
    return
  }
  stepErrors.value = []
  if (current.value < totalSteps.value - 1) current.value += 1
  saveDraft()
  void saveProfile()
}

function goPrev(): void {
  stepErrors.value = []
  if (current.value > 0) current.value -= 1
  saveDraft()
}

function onSelect(index: number): void {
  if (index > current.value) {
    // Chỉ cho phép nhảy tới bước đã hoàn thành.
    const missing = validateStep(current.value)
    if (missing.length) {
      stepErrors.value = missing
      toasts.push('Cần trả lời các câu hỏi bắt buộc', missing[0], 'caution', 5000)
      return
    }
  }
  stepErrors.value = []
  current.value = index
  saveDraft()
}

async function finish(): Promise<void> {
  const missing = steps.value.flatMap((_, index) => validateStep(index))
  if (missing.length) {
    stepErrors.value = missing
    toasts.push('Bản khai chưa hoàn tất', missing[0], 'caution', 5000)
    return
  }
  if (!(await saveProfile('COMPLETED'))) return
  toasts.push('Đã lưu hồ sơ', 'Hồ sơ đã hoàn tất, có thể chạy đánh giá.', 'ok')
}

async function runAssessment(): Promise<void> {
  running.value = true
  try {
    if (!(await saveProfile('COMPLETED'))) return
    await api.post(`/projects/${id.value}/assessments`, {})
    toasts.push('Đã tạo đánh giá', 'Đang phân loại nghĩa vụ áp dụng.', 'info')
    await router.push({ name: 'project-obligations', params: { id: id.value } })
  } catch (e) {
    toasts.push('Chạy đánh giá không thành công', (e as ApiError).message, 'danger')
  } finally {
    running.value = false
  }
}

watch(answers, () => saveDraft(), { deep: true })

onMounted(load)
</script>

<template>
  <div>
    <PageHeader title="Bản khai" subtitle="Khai báo đặc điểm dự án để phân loại nghĩa vụ pháp lý áp dụng.">
      <template #actions>
        <StatusChip v-if="profile?.status === 'COMPLETED'" tone="ok" label="Đã hoàn tất" />
        <span v-else class="tnum text-[13px] text-ink-muted">Đã trả lời {{ answeredCount }} câu hỏi</span>
      </template>
    </PageHeader>

    <UiSkeleton v-if="loading" :lines="6" />
    <Callout
      v-else-if="error"
      tone="danger"
      icon="warning"
      :title="error.message"
      action-label="Thử lại"
      @action="load()"
    />
    <template v-else>
      <Callout
        v-if="!questionSet?.questions?.length"
        tone="info"
        icon="info"
        title="Chưa có bộ câu hỏi áp dụng"
      >
        Vui lòng liên hệ quản trị để xuất bản bộ câu hỏi khai báo.
      </Callout>
      <template v-else>
        <div class="mb-6 overflow-x-auto pb-2">
          <Stepper :steps="steps" :current="current" @select="onSelect" />
        </div>

        <Callout v-if="stepErrors.length" tone="caution" icon="warning" title="Cần hoàn tất các câu hỏi bắt buộc" class="mb-4">
          <ul class="list-disc pl-4">
            <li v-for="(prompt, index) in stepErrors" :key="index">{{ prompt }}</li>
          </ul>
        </Callout>

        <!-- Bước hoàn thành: rà soát -->
        <UiCard v-if="isLast" title="Hoàn thành" description="Rà soát câu trả lời trước khi chạy đánh giá.">
          <div class="flex flex-col gap-4">
            <div class="flex flex-wrap items-center gap-3">
              <StatusChip
                :tone="answeredRequiredCount === requiredTotal ? 'ok' : 'caution'"
                :label="`${answeredRequiredCount}/${requiredTotal} câu bắt buộc đã trả lời`"
              />
              <span class="tnum text-[13px] text-ink-muted">Tiến độ {{ progress }}%</span>
            </div>
            <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
              <div v-for="step in steps.slice(0, -1)" :key="step.label">
                <dt class="text-[11px] font-semibold uppercase tracking-[0.06em] text-ink-muted">
                  {{ step.label }}
                </dt>
                <dd class="mt-0.5 text-[13.5px] text-ink">
                  {{ step.questions.filter((question) => !isUnanswered(question)).length }} /
                  {{ step.questions.length }} câu đã trả lời
                </dd>
              </div>
            </dl>
          </div>
          <template #footer>
            <UiButton variant="secondary" :disabled="saving || running" @click="goPrev()">Quay lại</UiButton>
            <UiButton variant="primary" :loading="saving" :disabled="running" @click="finish()">
              <CheckCircle2 class="size-4" aria-hidden="true" />
              Hoàn tất bản khai
            </UiButton>
            <UiButton variant="primary" :loading="running" :disabled="saving" @click="runAssessment()">
              Chạy đánh giá ngay
            </UiButton>
          </template>
        </UiCard>

        <!-- Bước nội dung: câu hỏi -->
        <UiCard v-else :title="currentStep.label" :description="`Bước ${current + 1} / ${totalSteps}`">
          <div class="flex flex-col gap-5">
            <Callout
              v-if="!currentStep.questions.length"
              tone="neutral"
              icon="none"
              title="Không có câu hỏi cho bước này"
            >
              Bước này chưa có câu hỏi trong bộ câu hỏi đang áp dụng — tiếp tục sang bước kế tiếp.
            </Callout>
            <div v-for="question in currentStep.questions" :key="question.code" class="flex flex-col gap-2">
              <div>
                <label class="text-[13.5px] font-medium text-ink">
                  {{ question.prompt }}
                  <span v-if="question.importance === 'REQUIRED'" class="text-seal">*</span>
                </label>
                <p v-if="question.help" class="mt-0.5 text-[12.5px] text-ink-muted">{{ question.help }}</p>
              </div>

              <!-- BOOL: có/không -->
              <div v-if="question.type === 'BOOL'" class="grid grid-cols-2 gap-2 sm:max-w-md">
                <CheckOption
                  :model-value="(answers[question.code] as string | null) ?? null"
                  value="yes"
                  label="Có"
                  type="radio"
                  :name="question.code"
                  @update:model-value="answers[question.code] = $event"
                />
                <CheckOption
                  :model-value="(answers[question.code] as string | null) ?? null"
                  value="no"
                  label="Không"
                  type="radio"
                  :name="question.code"
                  @update:model-value="answers[question.code] = $event"
                />
              </div>

              <!-- SINGLE: lựa chọn đơn -->
              <UiSelect
                v-else-if="question.type === 'SINGLE'"
                :model-value="(answers[question.code] as string) ?? ''"
                :options="question.options ?? []"
                placeholder="Chọn một lựa chọn"
                wrapper-class="sm:max-w-md"
                @update:model-value="answers[question.code] = $event"
              />

              <!-- MULTI: checkbox -->
              <div v-else-if="question.type === 'MULTI'" class="flex flex-col gap-2">
                <CheckOption
                  v-for="option in question.options ?? []"
                  :key="String(option.value)"
                  :model-value="(answers[question.code] as Array<string>) ?? []"
                  :value="option.value"
                  :label="option.label"
                  type="checkbox"
                  @update:model-value="answers[question.code] = $event"
                />
              </div>

              <!-- TEXT / NUMBER -->
              <UiTextarea
                v-else-if="question.type === 'TEXT'"
                :model-value="(answers[question.code] as string) ?? ''"
                :rows="3"
                @update:model-value="answers[question.code] = $event"
              />
              <UiInput
                v-else
                :model-value="(answers[question.code] as string | number) ?? ''"
                inputmode="numeric"
                @update:model-value="answers[question.code] = $event"
              />
            </div>
          </div>
          <template #footer>
            <UiButton variant="secondary" :disabled="current === 0 || saving" @click="goPrev()">Quay lại</UiButton>
            <UiButton variant="primary" :loading="saving" @click="goNext()">Tiếp tục</UiButton>
          </template>
        </UiCard>
      </template>
    </template>
  </div>
</template>
