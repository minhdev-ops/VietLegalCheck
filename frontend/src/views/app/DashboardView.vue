<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import {
  ArrowRight,
  Bell,
  ClipboardCheck,
  FolderKanban,
  Plus,
  ScrollText,
  ShieldQuestion,
} from '@lucide/vue'
import PageHeader from '@/components/common/PageHeader.vue'
import UiCard from '@/components/ui/UiCard.vue'
import StatusChip from '@/components/ui/StatusChip.vue'
import UiSkeleton from '@/components/ui/UiSkeleton.vue'
import Callout from '@/components/ui/Callout.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import UiButton from '@/components/ui/UiButton.vue'
import { api, type ApiError } from '@/lib/api'
import { formatDate, formatNumber } from '@/lib/format'
import { useNotificationsStore } from '@/stores/notifications'
import type { Assessment, Project } from '@/types/models'

/** Project chạy thêm trường updated_at do sắp xếp `latest()` của backend. */
interface ProjectListItem extends Project {
  updated_at?: string
}

interface AssessmentListItem extends Assessment {
  created_at?: string
}

interface AssessmentDetail extends Assessment {
  findings?: Assessment['findings']
}

interface OrgSummary {
  id: string
  name: string
  is_personal: boolean
  projects_count?: number
  pivot?: { role: string }
}

type ChipMeta = { tone: 'neutral' | 'ok' | 'caution' | 'info' | 'expert' | 'danger'; label: string }

/** Trạng thái là cụm từ không phải từ đơn (DESIGN.md). */
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

const router = useRouter()
const notifications = useNotificationsStore()

const loading = ref(true)
const error = ref<string | null>(null)
const projects = ref<ProjectListItem[]>([])
const projectTotal = ref(0)
const assessmentsCount = ref(0)
const openObligations = ref(0)
const recentAssessments = ref<Array<AssessmentListItem & { project_name: string }>>([])
const latestByProject = ref<Record<string, AssessmentListItem | null>>({})
const workspace = ref<OrgSummary | null>(null)

const hasProjects = computed(() => projects.value.length > 0)

const stats = computed(() => [
  { label: 'Dự án', value: projectTotal.value, icon: FolderKanban },
  { label: 'Đánh giá', value: assessmentsCount.value, icon: ClipboardCheck },
  { label: 'Nghĩa vụ đang mở', value: openObligations.value, icon: ScrollText },
  { label: 'Thông báo chưa đọc', value: notifications.unread, icon: Bell },
])

const unreadItems = computed(() => notifications.items.filter((n) => !n.read_at).slice(0, 4))

async function load(): Promise<void> {
  loading.value = true
  error.value = null
  try {
    const [projectsPayload, orgsPayload] = await Promise.all([
      api.get<{ data: ProjectListItem[]; meta: { total: number } }>('/projects', { per_page: 50 }),
      api.get<{ data: OrgSummary[] }>('/organizations'),
    ])
    projects.value = projectsPayload.data
    projectTotal.value = projectsPayload.meta.total
    const orgs = orgsPayload.data
    workspace.value = orgs.find((o) => o.is_personal) ?? orgs[0] ?? null

    const perProject = await Promise.all(
      projects.value.map((project) =>
        api.get<{ data: AssessmentListItem[] }>(`/projects/${project.id}/assessments`),
      ),
    )
    let count = 0
    const flat: Array<AssessmentListItem & { project_name: string }> = []
    perProject.forEach((payload, index) => {
      const items = payload.data
      count += items.length
      latestByProject.value[projects.value[index].id] = items[0] ?? null
      items.forEach((assessment) =>
        flat.push({ ...assessment, project_name: projects.value[index].name }),
      )
    })
    assessmentsCount.value = count
    flat.sort((a, b) => (b.created_at ?? '').localeCompare(a.created_at ?? ''))
    recentAssessments.value = flat.slice(0, 5)

    const latestIds = Object.values(latestByProject.value).filter((a): a is AssessmentListItem => a !== null)
    const details = await Promise.all(
      latestIds.map((assessment) =>
        api
          .get<{ assessment: AssessmentDetail }>(
            `/projects/${assessment.project_id}/assessments/${assessment.id}`,
          )
          .catch(() => null),
      ),
    )
    openObligations.value = details.reduce((sum, payload) => {
      const findings = payload?.assessment?.findings ?? []
      return (
        sum +
        findings.filter((f) => f.applicability === 'APPLICABLE' && f.fulfillment !== 'EXPERT_VERIFIED').length
      )
    }, 0)

    if (!notifications.loaded) await notifications.load().catch(() => undefined)
  } catch (err) {
    error.value = (err as ApiError)?.message ?? 'Chưa tải được tổng quan. Vui lòng thử lại.'
  } finally {
    loading.value = false
  }
}

function openProject(id: string): void {
  void router.push({ name: 'project-overview', params: { id } })
}

onMounted(() => {
  void load()
})
</script>

<template>
  <div>
    <PageHeader
      title="Tổng quan"
      subtitle="Số dự án, đánh giá và nghĩa vụ pháp lý đang mở trong không gian làm việc của bạn."
    >
      <template #actions>
        <UiButton variant="secondary" @click="router.push({ name: 'assistant' })">
          <ShieldQuestion class="size-4" aria-hidden="true" /> Hỏi trợ lý pháp lý
        </UiButton>
        <UiButton variant="primary" @click="router.push({ name: 'project-new' })">
          <Plus class="size-4" aria-hidden="true" /> Tạo dự án
        </UiButton>
      </template>
    </PageHeader>

    <Callout
      v-if="error"
      tone="danger"
      icon="warning"
      :title="error"
      action-label="Thử lại"
      class="mb-6"
      @action="load()"
    />
    <template v-else>
      <div v-if="loading" class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <UiCard v-for="index in 4" :key="index">
          <UiSkeleton :lines="2" />
        </UiCard>
      </div>
      <div v-else class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <UiCard v-for="stat in stats" :key="stat.label">
          <div class="flex items-center gap-3">
            <span class="flex size-9 shrink-0 items-center justify-center rounded-md bg-surface-2 text-ink-muted">
              <component :is="stat.icon" class="size-5" aria-hidden="true" />
            </span>
            <div class="min-w-0">
              <p class="tnum text-[22px] font-semibold leading-none text-ink">{{ formatNumber(stat.value) }}</p>
              <p class="mt-1 text-[10.5px] font-semibold uppercase tracking-[0.06em] text-ink-muted">
                {{ stat.label }}
              </p>
            </div>
          </div>
        </UiCard>
      </div>

      <UiCard v-if="!loading && !error" class="mt-4">
        <div class="flex flex-wrap items-center justify-between gap-x-6 gap-y-2">
          <div class="min-w-0">
            <h3 class="text-[15px] font-semibold leading-tight tracking-[-0.005em] text-ink">
              Không gian làm việc
            </h3>
            <p class="mt-1 truncate text-[13px] text-ink-muted">
              {{ workspace?.name ?? '—' }}{{ workspace?.projects_count !== undefined ? ` · ${workspace.projects_count} dự án` : '' }}
            </p>
          </div>
          <div class="flex items-center gap-2">
            <StatusChip
              v-if="workspace"
              :tone="workspace.is_personal ? 'neutral' : 'info'"
              :label="workspace.is_personal ? 'Gói cá nhân' : 'Tổ chức'"
            />
            <RouterLink
              to="/app/to-chuc"
              class="inline-flex items-center gap-1 text-[13px] font-medium text-accent hover:underline underline-offset-2"
            >
              Quản lý <ArrowRight class="size-3.5" aria-hidden="true" />
            </RouterLink>
          </div>
        </div>
      </UiCard>

      <div v-if="loading" class="mt-4 grid gap-4 lg:grid-cols-3">
        <UiCard v-for="index in 3" :key="index">
          <UiSkeleton :lines="5" />
        </UiCard>
      </div>
      <div v-else-if="!error" class="mt-4 grid gap-4 lg:grid-cols-3">
        <UiCard title="Dự án gần đây" class="min-w-0">
          <EmptyState
            v-if="!hasProjects"
            :icon="FolderKanban"
            title="Chưa có dự án nào"
            body="Tạo dự án đầu tiên để bắt đầu khai báo hồ sơ và đánh giá nghĩa vụ pháp lý."
            action-label="Tạo dự án"
            class="-mx-5 -my-1 border-0 bg-transparent"
            @action="router.push({ name: 'project-new' })"
          />
          <ul v-else class="-mx-5">
            <li
              v-for="project in projects.slice(0, 5)"
              :key="project.id"
              class="flex cursor-pointer items-center justify-between gap-4 border-b border-line px-5 py-3 transition-colors last:border-0 hover:bg-surface-2"
              @click="openProject(project.id)"
            >
              <div class="min-w-0">
                <p class="truncate text-[13.5px] font-medium text-ink">{{ project.name }}</p>
                <p class="tnum mt-0.5 truncate text-[11.5px] text-ink-muted">
                  Đánh giá gần nhất: {{ formatDate(latestByProject[project.id]?.as_of_date) }}
                </p>
              </div>
              <StatusChip v-bind="meta(PROJECT_STATUS, project.status)" />
            </li>
          </ul>
        </UiCard>

        <UiCard title="Đánh giá mới" class="min-w-0">
          <EmptyState
            v-if="recentAssessments.length === 0"
            :icon="ClipboardCheck"
            title="Chưa có đánh giá nào"
            body="Đánh giá sẽ xuất hiện sau khi hoàn thành khai báo hồ sơ dự án."
            class="-mx-5 -my-1 border-0 bg-transparent"
          />
          <ul v-else class="-mx-5">
            <li
              v-for="assessment in recentAssessments"
              :key="assessment.id"
              class="flex cursor-pointer items-center justify-between gap-4 border-b border-line px-5 py-3 transition-colors last:border-0 hover:bg-surface-2"
              @click="openProject(assessment.project_id)"
            >
              <div class="min-w-0">
                <p class="truncate text-[13.5px] font-medium text-ink">{{ assessment.project_name }}</p>
                <p class="tnum mt-0.5 text-[11.5px] text-ink-muted">{{ formatDate(assessment.as_of_date) }}</p>
              </div>
              <StatusChip v-bind="meta(ASSESSMENT_STATUS, assessment.status)" />
            </li>
          </ul>
        </UiCard>

        <UiCard class="min-w-0">
          <template #header>
            <div class="flex min-w-0 flex-1 items-center justify-between gap-4">
              <h3 class="text-[15px] font-semibold leading-tight tracking-[-0.005em] text-ink">Thông báo</h3>
              <RouterLink
                to="/app/thong-bao"
                class="inline-flex shrink-0 items-center gap-1 text-[13px] font-medium text-accent hover:underline underline-offset-2"
              >
                Tất cả <ArrowRight class="size-3.5" aria-hidden="true" />
              </RouterLink>
            </div>
          </template>
          <EmptyState
            v-if="unreadItems.length === 0"
            :icon="Bell"
            title="Không có thông báo mới"
            body="Mỗi đánh giá hoàn thành hoặc nghĩa vụ đến hạn sẽ được thông báo tại đây."
            class="-mx-5 -my-1 border-0 bg-transparent"
          />
          <ul v-else class="-mx-5">
            <li
              v-for="item in unreadItems"
              :key="item.id"
              class="border-b border-line px-5 py-3 last:border-0"
            >
              <div class="flex items-center gap-2">
                <span class="size-1.5 shrink-0 rounded-full bg-seal" aria-hidden="true" />
                <p class="truncate text-[13.5px] font-medium text-ink">{{ item.title }}</p>
              </div>
              <p class="mt-0.5 truncate pl-3.5 text-[11.5px] text-ink-muted">{{ item.body }}</p>
            </li>
          </ul>
        </UiCard>
      </div>
    </template>
  </div>
</template>
