<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { Download, FileText } from '@lucide/vue'
import PageHeader from '@/components/common/PageHeader.vue'
import UiCard from '@/components/ui/UiCard.vue'
import StatusChip from '@/components/ui/StatusChip.vue'
import UiSkeleton from '@/components/ui/UiSkeleton.vue'
import Callout from '@/components/ui/Callout.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import UiTable from '@/components/ui/UiTable.vue'
import UiButton from '@/components/ui/UiButton.vue'
import { api, getToken, type ApiError } from '@/lib/api'
import { formatDate } from '@/lib/format'
import { useToastsStore } from '@/stores/toasts'
import type { Project, Report } from '@/types/models'

type ChipMeta = { tone: 'neutral' | 'ok' | 'caution' | 'info' | 'expert' | 'danger'; label: string }

const REPORT_STATUS: Record<string, ChipMeta> = {
  QUEUED: { tone: 'info', label: 'Đang tạo' },
  COMPLETED: { tone: 'ok', label: 'Sẵn sàng tải' },
  FAILED: { tone: 'danger', label: 'Lỗi tạo báo cáo' },
}

function meta(status: string): ChipMeta {
  return REPORT_STATUS[status] ?? { tone: 'neutral', label: status }
}

interface ReportRow extends Report {
  project_id: string
  project_name: string
}

const toasts = useToastsStore()

const rows = ref<ReportRow[]>([])
const loading = ref(true)
const error = ref<ApiError | null>(null)
const downloading = ref<Record<string, boolean>>({})

const columns = [
  { key: 'project_name', label: 'Dự án' },
  { key: 'as_of_date', label: 'Ngày đánh giá' },
  { key: 'status', label: 'Trạng thái' },
  { key: 'sha256', label: 'Mã băm (X-Report-Hash)' },
  { key: 'created_at', label: 'Ngày tạo' },
  { key: 'actions', label: '', align: 'right' as const },
]

function shortHash(hash: string | null): string {
  return hash ? hash.slice(0, 12) : '—'
}

async function load(): Promise<void> {
  loading.value = true
  error.value = null
  try {
    const projectsPayload = await api.get<{ data: Project[] }>('/projects', { per_page: 50 })
    const grouped = await Promise.all(
      projectsPayload.data.map((project) =>
        api
          .get<{ data: Report[] }>(`/projects/${project.id}/reports`)
          .then((payload) => ({ project, reports: payload.data }))
          .catch(() => ({ project, reports: [] as Report[] })),
      ),
    )
    rows.value = grouped
      .flatMap(({ project, reports }) =>
        reports.map((report) => ({ ...report, project_id: project.id, project_name: project.name })),
      )
      .sort((a, b) => b.created_at.localeCompare(a.created_at))
  } catch (err) {
    error.value = err as ApiError
    rows.value = []
  } finally {
    loading.value = false
  }
}

async function onDownload(row: ReportRow): Promise<void> {
  if (!row.sha256 || downloading.value[row.id]) return
  downloading.value[row.id] = true
  try {
    const token = getToken()
    const response = await fetch(`/api/v1/projects/${row.project_id}/reports/${row.id}/download`, {
      headers: { ...(token ? { Authorization: `Bearer ${token}` } : {}) },
    })
    if (!response.ok) {
      const text = await response.text().catch(() => '')
      const payload = text ? JSON.parse(text) : null
      throw { code: 'DOWNLOAD', message: payload?.error?.message ?? 'Tải báo cáo không thành công.' } as ApiError
    }
    const hash = response.headers.get('X-Report-Hash')
    const blob = await response.blob()
    const url = URL.createObjectURL(blob)
    const anchor = document.createElement('a')
    anchor.href = url
    anchor.download = `bao-cao-${row.project_name}.pdf`
    document.body.appendChild(anchor)
    anchor.click()
    anchor.remove()
    URL.revokeObjectURL(url)
    toasts.push('Đã tải báo cáo', hash ? `Mã băm: ${hash}` : undefined, 'ok')
  } catch (err) {
    toasts.push('Tải báo cáo không thành công', (err as ApiError)?.message, 'danger')
  } finally {
    downloading.value[row.id] = false
  }
}

onMounted(() => {
  void load()
})
</script>

<template>
  <div>
    <PageHeader
      title="Báo cáo"
      subtitle="Báo cáo đánh giá của mọi dự án, kèm mã băm kiểm chứng và liên kết tải xuống PDF."
    />

    <UiCard padding="none">
      <Callout
        v-if="error"
        tone="danger"
        icon="warning"
        :title="error.message ?? 'Chưa tải được danh sách báo cáo.'"
        action-label="Thử lại"
        class="m-5"
        @action="load()"
      />
      <UiSkeleton v-else-if="loading" :lines="6" class="p-5" />
      <EmptyState
        v-else-if="rows.length === 0"
        :icon="FileText"
        title="Chưa có báo cáo nào"
        body="Báo cáo được tạo sau khi hoàn thành đánh giá dự án và sẽ liệt kê tại đây."
        class="m-5"
      />
      <template v-else>
        <UiTable
          :columns="columns"
          :rows="rows as unknown as Array<Record<string, unknown>>"
          class="px-5"
        >
          <template #cell-project_name="{ row }">
            <span class="font-medium text-ink">{{ (row as unknown as ReportRow).project_name }}</span>
          </template>
          <template #cell-as_of_date="{ row }">
            <span class="tnum text-ink-muted">{{ formatDate((row as unknown as ReportRow).as_of_date) }}</span>
          </template>
          <template #cell-status="{ row }">
            <StatusChip v-bind="meta((row as unknown as ReportRow).status)" />
          </template>
          <template #cell-sha256="{ row }">
            <span
              v-if="(row as unknown as ReportRow).sha256"
              class="tnum font-mono text-[12px] text-ink-muted"
              :title="(row as unknown as ReportRow).sha256 ?? ''"
            >
              {{ shortHash((row as unknown as ReportRow).sha256) }}
            </span>
            <span v-else class="text-ink-faint">—</span>
          </template>
          <template #cell-created_at="{ row }">
            <span class="tnum text-ink-muted">{{ formatDate((row as unknown as ReportRow).created_at) }}</span>
          </template>
          <template #cell-actions="{ row }">
            <UiButton
              size="sm"
              variant="secondary"
              :disabled="!(row as unknown as ReportRow).sha256"
              :loading="downloading[(row as unknown as ReportRow).id]"
              @click="onDownload(row as unknown as ReportRow)"
            >
              <Download class="size-4" aria-hidden="true" /> Tải xuống
            </UiButton>
          </template>
        </UiTable>
      </template>
    </UiCard>
  </div>
</template>
