<script setup lang="ts">
/**
 * Nghĩa vụ: findings từ đánh giá gần nhất đã hoàn tất, kèm căn cứ pháp lý,
 * lọc theo mức áp dụng và sửa ghi chú trạng thái từng finding.
 */
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { Pencil } from '@lucide/vue'
import PageHeader from '@/components/common/PageHeader.vue'
import Callout from '@/components/ui/Callout.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import LegalCitation from '@/components/ui/LegalCitation.vue'
import StatusChip from '@/components/ui/StatusChip.vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiCard from '@/components/ui/UiCard.vue'
import UiDialog from '@/components/ui/UiDialog.vue'
import UiSelect from '@/components/ui/UiSelect.vue'
import UiSkeleton from '@/components/ui/UiSkeleton.vue'
import UiTable from '@/components/ui/UiTable.vue'
import UiTextarea from '@/components/ui/UiTextarea.vue'
import { useToastsStore } from '@/stores/toasts'
import { api, type ApiError } from '@/lib/api'
import type { Assessment, AssessmentFinding } from '@/types/models'
import {
  APPLICABILITY,
  ASSESSMENT_STATUS,
  FULFILLMENT,
  SEVERITY,
  fetchList,
  statusMeta,
  toSelectOptions,
} from './shared'

const route = useRoute()
const toasts = useToastsStore()
const id = computed(() => String(route.params.id))

const loading = ref(true)
const error = ref<ApiError | null>(null)
const assessment = ref<Assessment | null>(null)
const findings = ref<AssessmentFinding[]>([])
const filter = ref<string>('ALL')

const FILTER_OPTIONS = [
  { value: 'ALL', label: 'Tất cả' },
  { value: 'APPLICABLE', label: 'Có khả năng áp dụng' },
  { value: 'NEED_MORE_INFO', label: 'Cần bổ sung thông tin' },
  { value: 'NEED_EXPERT_REVIEW', label: 'Cần chuyên gia xác nhận' },
]

const filtered = computed(() =>
  filter.value === 'ALL' ? findings.value : findings.value.filter((f) => f.applicability === filter.value),
)

const counts = computed(() => ({
  APPLICABLE: findings.value.filter((f) => f.applicability === 'APPLICABLE').length,
  NEED_MORE_INFO: findings.value.filter((f) => f.applicability === 'NEED_MORE_INFO').length,
  NEED_EXPERT_REVIEW: findings.value.filter((f) => f.applicability === 'NEED_EXPERT_REVIEW').length,
}))

const columns = [
  { key: 'rule_code', label: 'Mã quy tắc', width: '12rem' },
  { key: 'title', label: 'Nghĩa vụ' },
  { key: 'applicability', label: 'Áp dụng', width: '11rem' },
  { key: 'severity', label: 'Cấp bách', width: '9rem' },
  { key: 'fulfillment', label: 'Thực hiện', width: '11rem' },
  { key: 'actions', label: '', align: 'right' as const, width: '6rem' },
]

type LegalBasisItem = NonNullable<AssessmentFinding['legal_basis']>[number]

function citationOf(row: Record<string, unknown>): LegalBasisItem | null {
  return (row.first_citation ?? null) as LegalBasisItem | null
}

const rows = computed(() =>
  filtered.value.map((finding) => ({
    id: finding.id,
    rule_code: finding.rule_code,
    title: finding.title,
    legal_group: finding.legal_group,
    applicability: finding.applicability,
    severity: finding.severity,
    fulfillment: finding.fulfillment,
    first_citation: (finding.legal_basis ?? [])[0] ?? null,
    finding,
  })),
)

async function load(): Promise<void> {
  loading.value = true
  error.value = null
  try {
    const assessments = await fetchList<Assessment>(`/projects/${id.value}/assessments`)
    const latest = assessments.find((item) => item.status === 'COMPLETED') ?? assessments[0] ?? null
    assessment.value = latest
    if (latest) {
      const payload = await api.get<{ assessment: Assessment }>(
        `/projects/${id.value}/assessments/${latest.id}`,
      )
      findings.value = payload.assessment.findings ?? []
    } else {
      findings.value = []
    }
  } catch (e) {
    error.value = e as ApiError
  } finally {
    loading.value = false
  }
}

/* ---- Sửa ghi chú / trạng thái thực hiện ---- */
const editing = ref<AssessmentFinding | null>(null)
const editOpen = ref(false)
const editFulfillment = ref<string>('')
const editNote = ref<string>('')
const editError = ref<string | null>(null)
const saving = ref(false)

function openEdit(finding: AssessmentFinding): void {
  editing.value = finding
  editFulfillment.value = finding.fulfillment
  editNote.value = (finding as AssessmentFinding & { status_note?: string | null }).status_note ?? ''
  editError.value = null
  editOpen.value = true
}

async function saveEdit(): Promise<void> {
  if (!editing.value || !assessment.value) return
  saving.value = true
  editError.value = null
  try {
    const payload = await api.patch<{ finding: AssessmentFinding }>(
      `/projects/${id.value}/assessments/${assessment.value.id}/findings/${editing.value.id}`,
      { fulfillment: editFulfillment.value, status_note: editNote.value || null },
    )
    const updated = payload.finding
    const index = findings.value.findIndex((item) => item.id === updated.id)
    if (index >= 0) findings.value[index] = updated
    editOpen.value = false
    toasts.push('Đã cập nhật ghi chú', undefined, 'ok')
  } catch (e) {
    editError.value = (e as ApiError).message
  } finally {
    saving.value = false
  }
}

onMounted(load)

const FULFILLMENT_OPTIONS = toSelectOptions(FULFILLMENT)
</script>

<template>
  <div>
    <PageHeader
      title="Nghĩa vụ pháp lý"
      subtitle="Danh sách nghĩa vụ được phân loại từ đánh giá gần nhất, kèm căn cứ pháp lý."
    >
      <template #actions>
        <StatusChip
          v-if="assessment"
          :tone="statusMeta(ASSESSMENT_STATUS, assessment.status).tone"
          :label="`${statusMeta(ASSESSMENT_STATUS, assessment.status).label} · ${assessment.as_of_date}`"
        />
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
      <div class="mb-4 flex flex-wrap items-center gap-3">
        <label class="flex items-center gap-2 text-[13px] text-ink-muted">
          <span>Lọc theo mức áp dụng</span>
          <UiSelect
            v-model="filter"
            :options="FILTER_OPTIONS"
            wrapper-class="w-56"
          />
        </label>
        <span class="tnum ml-auto text-[13px] text-ink-muted">{{ rows.length }} nghĩa vụ</span>
      </div>

      <UiCard padding="none">
        <EmptyState
          v-if="!findings.length"
          title="Chưa có nghĩa vụ nào"
          body="Chạy đánh giá để hệ thống phân loại các nghĩa vụ áp dụng cho dự án."
          action-label="Xem lịch sử đánh giá"
          @action="$router.push({ name: 'project-history', params: { id } })"
        />
        <EmptyState
          v-else-if="!rows.length"
          title="Không có nghĩa vụ phù hợp"
          body="Không có nghĩa vụ nào ở mức áp dụng đã chọn. Đổi bộ lọc để xem lại."
          action-label="Xem tất cả"
          @action="filter = 'ALL'"
        />
        <UiTable
          v-else
          :columns="columns"
          :rows="rows"
        >
          <template #cell-rule_code="{ value }">
            <span class="tnum font-semibold text-ink">{{ value }}</span>
          </template>
          <template #cell-title="{ row }">
            <div class="flex flex-col gap-2 py-1">
              <span class="text-[13.5px] font-medium text-ink">{{ row.title as string }}</span>
              <LegalCitation
                v-if="citationOf(row)"
                :citation="String(citationOf(row)?.citation ?? '')"
                :document-number="citationOf(row)?.document_number"
                :document-title="citationOf(row)?.document_title"
                :effective-from="citationOf(row)?.effective_from"
                :official-url="citationOf(row)?.official_url"
                :quote="citationOf(row)?.content"
                class="max-w-prose"
              />
              <span v-else class="text-[12.5px] text-ink-faint">Chưa có căn cứ pháp lý đi kèm</span>
              <span v-if="row.legal_group" class="text-[11.5px] uppercase tracking-[0.04em] text-ink-muted">
                {{ row.legal_group as string }}
              </span>
            </div>
          </template>
          <template #cell-applicability="{ value }">
            <StatusChip
              :tone="statusMeta(APPLICABILITY, String(value)).tone"
              :label="statusMeta(APPLICABILITY, String(value)).label"
            />
          </template>
          <template #cell-severity="{ value }">
            <StatusChip
              :tone="statusMeta(SEVERITY, String(value)).tone"
              :label="statusMeta(SEVERITY, String(value)).label"
            />
          </template>
          <template #cell-fulfillment="{ value }">
            <StatusChip
              :tone="statusMeta(FULFILLMENT, String(value)).tone"
              :label="statusMeta(FULFILLMENT, String(value)).label"
            />
          </template>
          <template #cell-actions="{ row }">
            <UiButton variant="quiet" size="sm" @click="openEdit(row.finding as AssessmentFinding)">
              <Pencil class="size-3.5" aria-hidden="true" />
              Sửa ghi chú
            </UiButton>
          </template>
        </UiTable>
      </UiCard>

      <div v-if="findings.length" class="mt-4 flex flex-wrap gap-2">
        <StatusChip v-for="key in Object.keys(counts)" :key="key" :tone="statusMeta(APPLICABILITY, key).tone" :label="`${statusMeta(APPLICABILITY, key).label}: ${counts[key as keyof typeof counts]}`" plain />
      </div>
    </template>

    <UiDialog
      :open="editOpen"
      title="Sửa ghi chú nghĩa vụ"
      :description="editing?.title"
      size="md"
      @update:open="editOpen = $event"
    >
      <div class="flex flex-col gap-4">
        <UiSelect v-model="editFulfillment" :options="FULFILLMENT_OPTIONS" label="Mức thực hiện" />
        <UiTextarea
          v-model="editNote"
          label="Ghi chú"
          hint="Mô tả tình trạng xử lý, tối đa 1000 ký tự."
          :rows="4"
        />
        <Callout v-if="editing?.recommendation" tone="info" icon="info" title="Khuyến nghị">
          {{ editing.recommendation }}
        </Callout>
        <Callout v-if="editError" tone="danger" icon="warning" :title="editError" />
      </div>
      <template #footer>
        <UiButton variant="secondary" :disabled="saving" @click="editOpen = false">Đóng</UiButton>
        <UiButton variant="primary" :loading="saving" @click="saveEdit()">Lưu ghi chú</UiButton>
      </template>
    </UiDialog>
  </div>
</template>
