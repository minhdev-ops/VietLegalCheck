<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { FolderKanban, Plus, Search } from '@lucide/vue'
import PageHeader from '@/components/common/PageHeader.vue'
import UiCard from '@/components/ui/UiCard.vue'
import UiInput from '@/components/ui/UiInput.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import Pagination from '@/components/ui/Pagination.vue'
import StatusChip from '@/components/ui/StatusChip.vue'
import UiSkeleton from '@/components/ui/UiSkeleton.vue'
import UiTable from '@/components/ui/UiTable.vue'
import Callout from '@/components/ui/Callout.vue'
import UiButton from '@/components/ui/UiButton.vue'
import { api, type ApiError } from '@/lib/api'
import { formatDate } from '@/lib/format'
import type { Assessment, Project } from '@/types/models'

interface ProjectListItem extends Project {
  updated_at?: string
}

interface AssessmentListItem extends Assessment {
  created_at?: string
}

type ChipMeta = { tone: 'neutral' | 'ok' | 'caution' | 'info' | 'expert' | 'danger'; label: string }

const PROJECT_STATUS: Record<string, ChipMeta> = {
  DRAFT: { tone: 'neutral', label: 'Bản nháp' },
  ACTIVE: { tone: 'ok', label: 'Đang hoạt động' },
  ARCHIVED: { tone: 'neutral', label: 'Đã lưu trữ' },
}

const ASSESSMENT_STATUS: Record<string, ChipMeta> = {
  QUEUED: { tone: 'info', label: 'Đang chờ' },
  RUNNING: { tone: 'caution', label: 'Đang đánh giá' },
  COMPLETED: { tone: 'ok', label: 'Đã hoàn thành' },
  CANCELLED: { tone: 'neutral', label: 'Đã huỷ' },
  FAILED: { tone: 'danger', label: 'Không thành công' },
}

function meta(map: Record<string, ChipMeta>, status: string): ChipMeta {
  return map[status] ?? { tone: 'neutral', label: status }
}

const PER_PAGE = 10
const router = useRouter()

const q = ref('')
const page = ref(1)
const items = ref<ProjectListItem[]>([])
const total = ref(0)
const lastPage = ref(1)
const loading = ref(false)
const error = ref<ApiError | null>(null)
const loadingLatest = ref(false)
const latest = ref<Record<string, AssessmentListItem | null>>({})

const columns = [
  { key: 'name', label: 'Dự án' },
  { key: 'status', label: 'Trạng thái' },
  { key: 'latest', label: 'Đánh giá gần nhất' },
  { key: 'created_at', label: 'Ngày tạo' },
]

async function fetchProjects(): Promise<void> {
  loading.value = true
  error.value = null
  try {
    const payload = await api.get<{ data: ProjectListItem[]; meta: { total: number; last_page: number } }>(
      '/projects',
      { page: page.value, per_page: PER_PAGE, q: q.value.trim() || undefined },
    )
    items.value = payload.data
    total.value = payload.meta.total
    lastPage.value = payload.meta.last_page
    await fetchLatestAssessments()
  } catch (err) {
    error.value = err as ApiError
    items.value = []
  } finally {
    loading.value = false
  }
}

/** Lấy đánh giá mới nhất của từng dự án trong trang hiện tại (hiện as_of_date gần nhất). */
async function fetchLatestAssessments(): Promise<void> {
  latest.value = {}
  if (items.value.length === 0) return
  loadingLatest.value = true
  try {
    const results = await Promise.all(
      items.value.map((project) =>
        api.get<{ data: AssessmentListItem[] }>(`/projects/${project.id}/assessments`).catch(() => null),
      ),
    )
    results.forEach((payload, index) => {
      latest.value[items.value[index].id] = payload?.data?.[0] ?? null
    })
  } finally {
    loadingLatest.value = false
  }
}


function onRowClick(row: Record<string, unknown>): void {
  void router.push({ name: 'project-overview', params: { id: String(row.id) } })
}

onMounted(() => {
  void fetchProjects()
})
let timer: ReturnType<typeof setTimeout> | undefined
watch(q, () => {
  clearTimeout(timer)
  timer = setTimeout(() => {
    if (page.value > 1) page.value = 1
    else void fetchProjects()
  }, 300)
})

watch(page, () => {
  void fetchProjects()
})
</script>

<template>
  <div>
    <PageHeader title="Dự án của tôi" subtitle="Toàn bộ dự án trong không gian làm việc, kèm trạng thái và đợt đánh giá gần nhất.">
      <template #actions>
        <UiButton variant="primary" @click="router.push({ name: 'project-new' })">
          <Plus class="size-4" aria-hidden="true" /> Tạo dự án mới
        </UiButton>
      </template>
    </PageHeader>

    <UiCard padding="none">
      <template #header>
        <div class="flex items-center gap-2">
          <Search class="size-4 shrink-0 text-ink-faint" aria-hidden="true" />
          <UiInput v-model="q" wrapper-class="max-w-sm" placeholder="Tìm theo tên hoặc mô tả dự án…" />
        </div>
      </template>

      <Callout
        v-if="error"
        tone="danger"
        icon="warning"
        :title="error.message ?? 'Chưa tải được danh sách dự án.'"
        action-label="Thử lại"
        class="m-5"
        @action="fetchProjects()"
      />
      <UiSkeleton v-else-if="loading" :lines="6" class="p-5" />
      <EmptyState
        v-else-if="items.length === 0"
        :icon="FolderKanban"
        title="Chưa có dự án nào"
        :body="q.trim() ? 'Không tìm thấy dự án theo từ khoá này. Thử từ khoá khác hoặc tạo dự án mới.' : 'Tạo dự án đầu tiên để khai báo hồ sơ tác động và đánh giá nghĩa vụ pháp lý.'"
        action-label="Tạo dự án mới"
        class="m-5"
        @action="router.push({ name: 'project-new' })"
      />
      <template v-else>
        <UiTable :columns="columns" :rows="items as unknown as Array<Record<string, unknown>>" :on-row-click="onRowClick" class="px-5">
          <template #cell-name="{ row }">
            <div class="min-w-0">
              <p class="truncate font-medium text-ink">{{ (row as unknown as ProjectListItem).name }}</p>
              <p v-if="(row as unknown as ProjectListItem).description" class="mt-0.5 truncate text-[12px] text-ink-muted">
                {{ (row as unknown as ProjectListItem).description }}
              </p>
            </div>
          </template>
          <template #cell-status="{ row }">
            <StatusChip v-bind="meta(PROJECT_STATUS, (row as unknown as ProjectListItem).status)" />
          </template>
          <template #cell-latest="{ row }">
            <div v-if="loadingLatest" class="tnum text-[12px] text-ink-faint">Đang tải…</div>
            <template v-else-if="latest[(row as unknown as ProjectListItem).id]">
              <p class="tnum text-[13px] text-ink">{{ formatDate(latest[(row as unknown as ProjectListItem).id]?.as_of_date) }}</p>
              <StatusChip v-bind="meta(ASSESSMENT_STATUS, latest[(row as unknown as ProjectListItem).id]?.status ?? '')" class="mt-0.5" />
            </template>
            <span v-else class="text-[13px] text-ink-faint">Chưa có đánh giá</span>
          </template>
          <template #cell-created_at="{ row }">
            <span class="tnum text-ink-muted">{{ formatDate((row as unknown as ProjectListItem).created_at) }}</span>
          </template>
        </UiTable>
        <Pagination
          :page="page"
          :last-page="lastPage"
          :total="total"
          class="px-5"
          @change="page = $event"
        />
      </template>
    </UiCard>
  </div>
</template>
