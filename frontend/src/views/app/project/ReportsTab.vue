<script setup lang="ts">
/**
 * Báo cáo: danh sách báo cáo PDF theo đánh giá, tạo báo cáo từ assessment đã hoàn tất
 * và tải xuống kèm hiển thị băm SHA-256.
 */
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { Download, FileCheck2 } from '@lucide/vue'
import PageHeader from '@/components/common/PageHeader.vue'
import Callout from '@/components/ui/Callout.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import StatusChip from '@/components/ui/StatusChip.vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiCard from '@/components/ui/UiCard.vue'
import UiDialog from '@/components/ui/UiDialog.vue'
import UiSelect from '@/components/ui/UiSelect.vue'
import UiSkeleton from '@/components/ui/UiSkeleton.vue'
import UiTable from '@/components/ui/UiTable.vue'
import { useToastsStore } from '@/stores/toasts'
import { api, getToken, type ApiError } from '@/lib/api'
import { formatDate } from '@/lib/format'
import type { Assessment, Report } from '@/types/models'
import { REPORT_STATUS, fetchList, shortHash, statusMeta } from './shared'

const route = useRoute()
const toasts = useToastsStore()
const id = computed(() => String(route.params.id))

const loading = ref(true)
const error = ref<ApiError | null>(null)
const reports = ref<Report[]>([])
const assessments = ref<Assessment[]>([])

const columns = [
  { key: 'as_of_date', label: 'Ngày ghi nhận', width: '10rem' },
  { key: 'status', label: 'Trạng thái', width: '10rem' },
  { key: 'sha256', label: 'Băm báo cáo', width: '13rem' },
  { key: 'created_at', label: 'Tạo lúc', width: '10rem' },
  { key: 'actions', label: '', align: 'right' as const, width: '9rem' },
]

async function load(): Promise<void> {
  loading.value = true
  error.value = null
  try {
    const [items, latest] = await Promise.all([
      fetchList<Report>(`/projects/${id.value}/reports`),
      fetchList<Assessment>(`/projects/${id.value}/assessments`),
    ])
    reports.value = items
    assessments.value = latest
  } catch (e) {
    error.value = e as ApiError
  } finally {
    loading.value = false
  }
}

/* ---- Tạo báo cáo ---- */
const createOpen = ref(false)
const chosen = ref('')
const creating = ref(false)
const createError = ref<string | null>(null)

const completedOptions = computed(() =>
  assessments.value
    .filter((assessment) => assessment.status === 'COMPLETED')
    .map((assessment) => ({ value: assessment.id, label: `Đánh giá ${formatDate(assessment.as_of_date)}` })),
)

function openCreate(): Promise<void> | void {
  if (!completedOptions.value.length) {
    toasts.push('Chưa có đánh giá hoàn tất', 'Cần chạy đánh giá trước khi tạo báo cáo.', 'caution')
    return
  }
  chosen.value = completedOptions.value[0].value
  createError.value = null
  createOpen.value = true
}

async function submitCreate(): Promise<void> {
  if (!chosen.value) {
    createError.value = 'Chọn đánh giá để xuất báo cáo.'
    return
  }
  creating.value = true
  createError.value = null
  try {
    await api.post(`/projects/${id.value}/assessments/${chosen.value}/reports`, {})
    toasts.push('Đã tạo yêu cầu xuất báo cáo', 'Báo cáo sẽ sẵn sàng tải xuống khi hoàn tất.', 'info')
    createOpen.value = false
    await load()
  } catch (e) {
    createError.value = (e as ApiError).message
  } finally {
    creating.value = false
  }
}

/* ---- Tải xuống ---- */
const downloading = ref<string | null>(null)

async function download(report: Report): Promise<void> {
  downloading.value = report.id
  try {
    const response = await fetch(`/api/v1/projects/${id.value}/reports/${report.id}/download`, {
      headers: { Authorization: `Bearer ${getToken() ?? ''}` },
    })
    if (!response.ok) throw new Error('Báo cáo chưa hoàn tất hoặc tải xuống không thành công.')
    const blob = await response.blob()
    const link = document.createElement('a')
    link.href = URL.createObjectURL(blob)
    link.download = `bao-cao-${report.as_of_date}.pdf`
    link.click()
    URL.revokeObjectURL(link.href)
    toasts.push('Đã tải xuống báo cáo', undefined, 'ok')
  } catch (e) {
    toasts.push('Tải xuống lỗi', (e as Error).message, 'danger')
  } finally {
    downloading.value = null
  }
}

onMounted(load)
</script>

<template>
  <div>
    <PageHeader
      title="Báo cáo"
      subtitle="Báo cáo PDF rà soát nghĩa vụ theo đánh giá, kèm băm kiểm tra toàn vẹn."
    >
      <template #actions>
        <UiButton variant="primary" @click="openCreate()">
          <FileCheck2 class="size-4" aria-hidden="true" />
          Tạo báo cáo
        </UiButton>
      </template>
    </PageHeader>

    <UiCard padding="none">
      <UiSkeleton v-if="loading" :lines="4" class="p-5" />
      <Callout
        v-else-if="error"
        tone="danger"
        icon="warning"
        :title="error.message"
        action-label="Thử lại"
        class="m-5"
        @action="load()"
      />
      <EmptyState
        v-else-if="!reports.length"
        title="Chưa có báo cáo nào"
        body="Tạo báo cáo từ một lần đánh giá đã hoàn tất để xuất bản PDF rà soát."
        action-label="Tạo báo cáo"
        class="m-5"
        @action="openCreate()"
      />
      <UiTable v-else :columns="columns" :rows="reports.map((report) => ({ ...report, sha256: report.sha256 }))">
        <template #cell-status="{ value }">
          <StatusChip :tone="statusMeta(REPORT_STATUS, String(value)).tone" :label="statusMeta(REPORT_STATUS, String(value)).label" />
        </template>
        <template #cell-sha256="{ value }">
          <code class="tnum text-[11.5px] text-ink-muted" :title="String(value)">{{ shortHash(value as string) }}</code>
        </template>
        <template #cell-actions="{ row }">
          <div class="flex justify-end">
            <UiButton
              variant="quiet"
              size="sm"
              :loading="downloading === (row as unknown as Report).id"
              :disabled="!(row as unknown as Report).sha256"
              @click="download(row as unknown as Report)"
            >
              <Download class="size-3.5" aria-hidden="true" />
              Tải xuống
            </UiButton>
          </div>
        </template>
      </UiTable>
    </UiCard>

    <UiDialog
      :open="createOpen"
      title="Tạo báo cáo"
      description="Xuất báo cáo PDF từ một lần đánh giá đã hoàn tất."
      size="sm"
      @update:open="createOpen = $event"
    >
      <form class="flex flex-col gap-4" @submit.prevent="submitCreate()">
        <UiSelect v-model="chosen" :options="completedOptions" label="Đánh giá" />
        <Callout v-if="createError" tone="danger" icon="warning" :title="createError" />
      </form>
      <template #footer>
        <UiButton variant="secondary" :disabled="creating" @click="createOpen = false">Đóng</UiButton>
        <UiButton variant="primary" :loading="creating" @click="submitCreate()">Tạo báo cáo</UiButton>
      </template>
    </UiDialog>
  </div>
</template>
