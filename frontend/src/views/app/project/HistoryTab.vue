<script setup lang="ts">
/**
 * Lịch sử đánh giá: timeline các lần đánh giá, nhật ký trạng thái và xem chi tiết
 * findings của mỗi lần trong dialog.
 */
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { Eye } from '@lucide/vue'
import PageHeader from '@/components/common/PageHeader.vue'
import Callout from '@/components/ui/Callout.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import StatusChip from '@/components/ui/StatusChip.vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiCard from '@/components/ui/UiCard.vue'
import UiDialog from '@/components/ui/UiDialog.vue'
import UiSkeleton from '@/components/ui/UiSkeleton.vue'
import { useToastsStore } from '@/stores/toasts'
import { api, type ApiError } from '@/lib/api'
import { formatDate, formatDateTime } from '@/lib/format'
import type { Assessment, AssessmentFinding } from '@/types/models'
import {
  APPLICABILITY,
  ASSESSMENT_STATUS,
  OVERALL_STATUS,
  SEVERITY,
  fetchList,
  statusMeta,
} from './shared'

const route = useRoute()
const toasts = useToastsStore()
const id = computed(() => String(route.params.id))

const loading = ref(true)
const error = ref<ApiError | null>(null)
const assessments = ref<Assessment[]>([])

async function load(): Promise<void> {
  loading.value = true
  error.value = null
  try {
    assessments.value = await fetchList<Assessment>(`/projects/${id.value}/assessments`)
  } catch (e) {
    error.value = e as ApiError
  } finally {
    loading.value = false
  }
}

/* ---- Chi tiết findings trong dialog ---- */
const detailOpen = ref(false)
const detailLoading = ref(false)
const detail = ref<Assessment | null>(null)
const detailFindings = ref<AssessmentFinding[]>([])

async function openDetail(assessment: Assessment): Promise<void> {
  detail.value = assessment
  detailFindings.value = []
  detailOpen.value = true
  detailLoading.value = true
  try {
    const payload = await api.get<{ assessment: Assessment }>(
      `/projects/${id.value}/assessments/${assessment.id}`,
    )
    detailFindings.value = payload.assessment.findings ?? []
  } catch (e) {
    toasts.push('Không tải được chi tiết', (e as ApiError).message, 'danger')
  } finally {
    detailLoading.value = false
  }
}

function findingCounts(list: AssessmentFinding[]): Record<string, number> {
  return list.reduce(
    (acc, finding) => {
      acc[finding.applicability] = (acc[finding.applicability] ?? 0) + 1
      return acc
    },
    {} as Record<string, number>,
  )
}

onMounted(load)
</script>

<template>
  <div>
    <PageHeader
      title="Lịch sử đánh giá"
      subtitle="Các lần đánh giá theo ngày ghi nhận, kèm nhật ký trạng thái và kết quả chi tiết."
    />

    <UiSkeleton v-if="loading" :lines="5" />
    <Callout
      v-else-if="error"
      tone="danger"
      icon="warning"
      :title="error.message"
      action-label="Thử lại"
      @action="load()"
    />
    <EmptyState
      v-else-if="!assessments.length"
      title="Chưa có đánh giá nào"
      body="Chạy đánh giá để ghi nhận lần đầu tiên và theo dõi sự thay đổi theo thời gian."
      action-label="Chuyển đến nghĩa vụ"
      @action="$router.push({ name: 'project-obligations', params: { id } })"
    />
    <ol v-else class="flex flex-col gap-4">
      <li v-for="assessment in assessments" :key="assessment.id">
        <UiCard>
          <div class="flex flex-col gap-3">
            <div class="flex flex-wrap items-center gap-2">
              <span class="tnum text-[13px] font-semibold text-ink">{{ formatDate(assessment.as_of_date) }}</span>
              <StatusChip
                :tone="statusMeta(ASSESSMENT_STATUS, assessment.status).tone"
                :label="statusMeta(ASSESSMENT_STATUS, assessment.status).label"
              />
              <StatusChip
                v-if="assessment.overall_status"
                :tone="statusMeta(OVERALL_STATUS, assessment.overall_status).tone"
                :label="statusMeta(OVERALL_STATUS, assessment.overall_status).label"
              />
              <UiButton variant="quiet" size="sm" class="ml-auto" @click="openDetail(assessment)">
                <Eye class="size-3.5" aria-hidden="true" />
                Xem chi tiết
              </UiButton>
            </div>

            <!-- Nhật ký trạng thái -->
            <ul class="flex flex-col gap-1.5 text-[12.5px] text-ink-muted">
              <li class="flex flex-wrap items-center gap-2">
                <span class="size-1.5 rounded-full bg-info" aria-hidden="true" />
                <span class="tnum">{{ formatDateTime(assessment.started_at) }}</span>
                <span>Bắt đầu chạy đánh giá</span>
              </li>
              <li v-if="assessment.completed_at" class="flex flex-wrap items-center gap-2">
                <span class="size-1.5 rounded-full bg-ok" aria-hidden="true" />
                <span class="tnum">{{ formatDateTime(assessment.completed_at) }}</span>
                <span>Hoàn tất, phiên bản bộ câu hỏi {{ assessment.question_set_version }}</span>
              </li>
            </ul>

            <div class="flex flex-wrap items-center gap-3 border-t border-line pt-3">
              <span class="text-[12.5px] text-ink-muted">
                Quy tắc áp dụng: <span class="tnum font-medium text-ink">{{ assessment.rule_set_snapshot?.length ?? 0 }}</span>
              </span>
            </div>
          </div>
        </UiCard>
      </li>
    </ol>

    <UiDialog
      :open="detailOpen"
      :title="detail ? `Đánh giá ngày ${formatDate(detail.as_of_date)}` : 'Chi tiết đánh giá'"
      :description="detail ? statusMeta(ASSESSMENT_STATUS, detail.status).label : undefined"
      size="lg"
      @update:open="detailOpen = $event"
    >
      <UiSkeleton v-if="detailLoading" :lines="5" />
      <div v-else class="flex flex-col gap-5">
        <div class="flex flex-wrap gap-2">
          <StatusChip
            v-for="(count, key) in findingCounts(detailFindings)"
            :key="key"
            :tone="statusMeta(APPLICABILITY, String(key)).tone"
            :label="`${statusMeta(APPLICABILITY, String(key)).label}: ${count}`"
            plain
          />
          <span v-if="!detailFindings.length" class="text-[13px] text-ink-muted">Không có finding nào trong lần đánh giá này.</span>
        </div>
        <ul class="flex flex-col divide-y divide-line">
          <li v-for="finding in detailFindings" :key="finding.id" class="py-3 first:pt-0 last:pb-0">
            <div class="flex flex-wrap items-center gap-2">
              <StatusChip :tone="statusMeta(SEVERITY, finding.severity).tone" :label="statusMeta(SEVERITY, finding.severity).label" />
              <StatusChip :tone="statusMeta(APPLICABILITY, finding.applicability).tone" :label="statusMeta(APPLICABILITY, finding.applicability).label" />
              <span class="tnum text-[12.5px] text-ink-muted">{{ finding.rule_code }}</span>
            </div>
            <p class="mt-1.5 text-[13.5px] font-medium text-ink">{{ finding.title }}</p>
            <p v-if="finding.explanation" class="mt-1 text-[12.5px] text-ink-muted">{{ finding.explanation }}</p>
          </li>
        </ul>
      </div>
      <template #footer>
        <UiButton variant="secondary" @click="detailOpen = false">Đóng</UiButton>
      </template>
    </UiDialog>
  </div>
</template>
