<script setup lang="ts">
/**
 * Vỏ bọc dự án: PageHeader + tab điều hướng 8 tab + khe RouterView.
 * Nút "Chạy đánh giá" tạo assessment (202 trả job) rồi theo dõi tiến độ tác vụ.
 */
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  PROJECT_STATUS,
  fetchList,
  provideProject,
  statusMeta,
} from './project/shared'
import StatusChip from '@/components/ui/StatusChip.vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiDialog from '@/components/ui/UiDialog.vue'
import UiSkeleton from '@/components/ui/UiSkeleton.vue'
import UiTabs from '@/components/ui/UiTabs.vue'
import Callout from '@/components/ui/Callout.vue'
import { useToastsStore } from '@/stores/toasts'
import { api, type ApiError } from '@/lib/api'
import type { Assessment, BackgroundJob, Project } from '@/types/models'

const route = useRoute()
const router = useRouter()
const toasts = useToastsStore()

const project = ref<Project | null>(null)
const loading = ref(true)
const error = ref<ApiError | null>(null)
const assessmentRunning = ref(false)

async function load(): Promise<void> {
  loading.value = true
  error.value = null
  try {
    const payload = await api.get<{ project: Project }>(`/projects/${String(route.params.id)}`)
    project.value = payload.project
  } catch (e) {
    error.value = e as ApiError
    project.value = null
  } finally {
    loading.value = false
  }
}

provideProject({ project, loading, error, reload: load })

const status = computed(() =>
  project.value ? statusMeta(PROJECT_STATUS, project.value.status) : null,
)

const TABS = [
  { value: 'project-overview', label: 'Tổng quan' },
  { value: 'project-wizard', label: 'Khai báo' },
  { value: 'project-obligations', label: 'Nghĩa vụ' },
  { value: 'project-scan', label: 'Quét trang' },
  { value: 'project-documents', label: 'Tài liệu' },
  { value: 'project-procedures', label: 'Thủ tục' },
  { value: 'project-history', label: 'Lịch sử' },
  { value: 'project-reports', label: 'Báo cáo' },
] as const

const activeTab = computed(() => (route.name ?? 'project-overview') as string)

function onTabChange(value: string): void {
  if (value !== route.name) router.push({ name: value, params: { id: String(route.params.id) } })
}

/* ---- Tạo đánh giá mới (POST /projects/{id}/assessments) ---- */
const assessDialog = ref(false)
const asOfDate = ref(new Date().toISOString().slice(0, 10))
const assessError = ref<string | null>(null)
let pollTimer: ReturnType<typeof setInterval> | undefined

function openAssessDialog(): void {
  asOfDate.value = new Date().toISOString().slice(0, 10)
  assessError.value = null
  assessDialog.value = true
}

async function submitAssessment(): Promise<void> {
  assessError.value = null
  assessmentRunning.value = true
  try {
    const created = await api.post<{ assessment_id: string; job_id: string }>(
      `/projects/${String(route.params.id)}/assessments`,
      { as_of_date: asOfDate.value || undefined },
    )
    assessDialog.value = false
    toasts.push('Đã tạo đánh giá', 'Đang phân loại nghĩa vụ áp dụng, vui lòng đợi.', 'info')
    await pollJob(created.job_id)
    await load()
    await router.push({ name: 'project-obligations', params: { id: String(route.params.id) } })
  } catch (e) {
    assessError.value = (e as ApiError).message
  } finally {
    assessmentRunning.value = false
  }
}

async function pollJob(jobId: string): Promise<void> {
  let job: BackgroundJob | null = null
  const deadline = Date.now() + 120_000
  await new Promise<void>((resolve) => {
    pollTimer = setInterval(async () => {
      try {
        const payload = await api.get<{ job: BackgroundJob }>(`/jobs/${jobId}`)
        job = payload.job
        if (payload.job.status === 'SUCCEEDED' || payload.job.status === 'FAILED' || payload.job.status === 'CANCELLED') {
          clearInterval(pollTimer)
          resolve()
        }
      } catch {
        clearInterval(pollTimer)
        resolve()
      }
      if (Date.now() > deadline) {
        clearInterval(pollTimer)
        resolve()
      }
    }, 2_000)
  })
  const outcome = job as BackgroundJob | null
  if (outcome?.status === 'SUCCEEDED') {
    const finished = (await fetchList<Assessment>(`/projects/${String(route.params.id)}/assessments`))[0]
    toasts.push('Đánh giá hoàn tất', finished ? `Xem nghĩa vụ tại đánh giá ${finished.as_of_date}.` : undefined, 'ok')
  } else if (outcome?.status === 'FAILED') {
    toasts.push('Đánh giá lỗi', outcome.last_error ?? 'Tác vụ kết thúc không thành công.', 'danger')
  }
}

onBeforeUnmount(() => clearInterval(pollTimer))

watch(() => route.params.id, load, { immediate: true })

</script>

<template>
  <div>
    <template v-if="loading">
      <UiSkeleton :lines="2" class="mb-6" />
    </template>
    <Callout
      v-else-if="error"
      tone="danger"
      icon="warning"
      :title="error.message"
      action-label="Thử lại"
      @action="load()"
    />
    <template v-else-if="project">
      <PageHeader :title="project.name" :subtitle="project.description ?? undefined">
        <template #actions>
          <div class="flex items-center gap-2">
            <StatusChip v-if="status" :tone="status.tone" :label="status.label" />
            <UiButton variant="primary" :loading="assessmentRunning" @click="openAssessDialog()">
              <Play v-if="!assessmentRunning" class="size-4" aria-hidden="true" />
              Chạy đánh giá
            </UiButton>
          </div>
        </template>
      </PageHeader>

      <div class="sticky top-0 z-10 -mx-1 mb-6 bg-paper/90 px-1 py-1 backdrop-blur-md">
        <UiTabs :model-value="activeTab" :tabs="[...TABS]" @update:model-value="onTabChange($event as string)" />
      </div>

      <RouterView />
    </template>

    <UiDialog
      :open="assessDialog"
      title="Chạy đánh giá mới"
      description="Đánh giá phân loại các nghĩa vụ pháp lý áp dụng theo hồ sơ và câu trả lời khai báo."
      size="sm"
      @update:open="assessDialog = $event"
    >
      <form class="flex flex-col gap-4" @submit.prevent="submitAssessment()">
        <label class="flex flex-col gap-1.5">
          <span class="text-[13px] font-medium text-ink">Ngày ghi nhận</span>
          <input
            v-model="asOfDate"
            type="date"
            required
            class="h-9 w-full rounded-md border border-line-strong bg-surface px-3 text-sm text-ink focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/20"
          />
          <span class="text-xs text-ink-muted">Để trống để lấy ngày hiện tại.</span>
        </label>
        <Callout v-if="assessError" tone="danger" icon="warning" :title="assessError" />
        <div v-if="assessmentRunning" class="flex items-center gap-2 text-[13px] text-ink-muted">
          <Loader2 class="size-4 animate-spin" aria-hidden="true" />
          Đang phân loại nghĩa vụ…
        </div>
      </form>
      <template #footer>
        <UiButton variant="secondary" :disabled="assessmentRunning" @click="assessDialog = false">Đóng</UiButton>
        <UiButton variant="primary" :loading="assessmentRunning" @click="submitAssessment()">Chạy đánh giá</UiButton>
      </template>
    </UiDialog>
  </div>
</template>
