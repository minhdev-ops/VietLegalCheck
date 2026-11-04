<script setup lang="ts">
/**
 * Tổng quan dự án: hồ sơ rút gọn, thẻ hoạt động kèm lý do, đánh giá gần nhất
 * và các chỉ số đếm nhanh liên kết tới các tab khác.
 */
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { FileText, Scale, ScanLine } from '@lucide/vue'
import PageHeader from '@/components/common/PageHeader.vue'
import Callout from '@/components/ui/Callout.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import StatusChip from '@/components/ui/StatusChip.vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiCard from '@/components/ui/UiCard.vue'
import UiSkeleton from '@/components/ui/UiSkeleton.vue'
import { api, type ApiError } from '@/lib/api'
import { formatDate } from '@/lib/format'
import type { Assessment, AssessmentFinding, ProjectProfile } from '@/types/models'
import {
  ASSESSMENT_STATUS,
  OVERALL_STATUS,
  fetchList,
  statusMeta,
  useProject,
} from './shared'

const route = useRoute()
const router = useRouter()
const { project } = useProject()

const id = computed(() => String(route.params.id))

interface ProjectTag {
  tag_code: string
  reason: string | null
  confidence: number | null
  assigned_by: string | null
}
interface TagCatalog {
  code: string
  name_vi: string
  group: string
}

const profile = ref<ProjectProfile | null>(null)
const tags = ref<ProjectTag[]>([])
const tagNames = ref<Record<string, string>>({})
const latest = ref<Assessment | null>(null)
const findings = ref<AssessmentFinding[]>([])
const counts = ref({ documents: 0, procedures: 0, scans: 0 })
const loading = ref(true)
const error = ref<ApiError | null>(null)

async function load(): Promise<void> {
  loading.value = true
  error.value = null
  try {
    const idValue = id.value
    const [tagsPayload, profilePayload, assessments] = await Promise.all([
      api.get<{ tags: ProjectTag[]; available: TagCatalog[] }>(`/projects/${idValue}/tags`),
      api.get<{ profile: ProjectProfile }>(`/projects/${idValue}/profile`),
      fetchList<Assessment>(`/projects/${idValue}/assessments`),
    ])
    profile.value = profilePayload.profile ?? null
    tags.value = tagsPayload.tags ?? []
    tagNames.value = Object.fromEntries((tagsPayload.available ?? []).map((tag) => [tag.code, tag.name_vi]))
    latest.value = assessments[0] ?? null
    if (latest.value) {
      const detail = await api.get<{ assessment: Assessment }>(
        `/projects/${idValue}/assessments/${latest.value.id}`,
      )
      findings.value = detail.assessment.findings ?? []
    }
    const [documents, procedures, scans] = await Promise.all([
      fetchList<{ id: string }>(`/projects/${idValue}/documents`),
      fetchList<{ id: string }>(`/projects/${idValue}/procedures`),
      fetchList<{ id: string }>(`/projects/${idValue}/scans`),
    ])
    counts.value = { documents: documents.length, procedures: procedures.length, scans: scans.length }
  } catch (e) {
    error.value = e as ApiError
  } finally {
    loading.value = false
  }
}

const overallMeta = computed(() =>
  latest.value?.overall_status ? statusMeta(OVERALL_STATUS, latest.value.overall_status) : null,
)
const latestMeta = computed(() =>
  latest.value ? statusMeta(ASSESSMENT_STATUS, latest.value.status) : null,
)
const applicableCount = computed(() => findings.value.filter((f) => f.applicability === 'APPLICABLE').length)
const needInfoCount = computed(() => findings.value.filter((f) => f.applicability === 'NEED_MORE_INFO').length)
const expertCount = computed(() => findings.value.filter((f) => f.applicability === 'NEED_EXPERT_REVIEW').length)
const completedSteps = computed(() => profile.value?.completed_step ?? 0)

function go(name: string): void {
  router.push({ name, params: { id: id.value } })
}

function tagName(code: string): string {
  return tagNames.value[code] ?? code
}

onMounted(load)
</script>

<template>
  <div>
    <PageHeader title="Tổng quan" subtitle="Ảnh chụp nhanh hồ sơ, phân loại và kết quả đánh giá gần nhất.">
      <template #actions>
        <UiButton variant="secondary" size="sm" @click="go('project-wizard')">Khai báo hồ sơ</UiButton>
        <UiButton variant="primary" size="sm" @click="go('project-obligations')">Xem nghĩa vụ</UiButton>
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
      <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <!-- Hồ sơ rút gọn -->
        <UiCard title="Hồ sơ rút gọn" description="Thông tin cơ bản về dự án đã khai báo.">
          <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
            <div>
              <dt class="text-[11px] font-semibold uppercase tracking-[0.06em] text-ink-muted">Loại sản phẩm</dt>
              <dd class="mt-0.5 text-[13.5px] text-ink">{{ project?.product_type ?? '—' }}</dd>
            </div>
            <div>
              <dt class="text-[11px] font-semibold uppercase tracking-[0.06em] text-ink-muted">Nền tảng</dt>
              <dd class="mt-0.5 text-[13.5px] text-ink">{{ project?.platform ?? '—' }}</dd>
            </div>
            <div class="sm:col-span-2">
              <dt class="text-[11px] font-semibold uppercase tracking-[0.06em] text-ink-muted">Địa chỉ mạng</dt>
              <dd class="mt-0.5 truncate text-[13.5px] text-ink">{{ project?.url ?? '—' }}</dd>
            </div>
            <div class="sm:col-span-2">
              <dt class="text-[11px] font-semibold uppercase tracking-[0.06em] text-ink-muted">Thị trường</dt>
              <dd class="mt-0.5 text-[13.5px] text-ink">
                <span v-if="project?.markets?.length">{{ project.markets.join(', ') }}</span>
                <span v-else>—</span>
              </dd>
            </div>
            <div class="sm:col-span-2">
              <dt class="text-[11px] font-semibold uppercase tracking-[0.06em] text-ink-muted">Tiến độ khai báo</dt>
              <dd class="mt-1.5 flex items-center gap-2">
                <StatusChip tone="info" :label="`Bước ${completedSteps} đã lưu`" />
                <StatusChip v-if="profile?.status === 'COMPLETED'" tone="ok" label="Đã hoàn tất" />
              </dd>
            </div>
          </dl>
          <template #footer>
            <UiButton variant="quiet" size="sm" @click="go('project-wizard')">Mở bản khai</UiButton>
          </template>
        </UiCard>

        <!-- Thẻ hoạt động -->
        <UiCard title="Thẻ hoạt động" description="Phân loại theo câu trả lời khai báo, kèm lý do áp dụng.">
          <EmptyState
            v-if="!tags.length"
            title="Chưa có thẻ hoạt động"
            body="Hoàn tất bản khai để hệ thống phân loại nghĩa vụ áp dụng."
            action-label="Khai báo hồ sơ"
            @action="go('project-wizard')"
          />
          <ul v-else class="flex flex-col divide-y divide-line">
            <li v-for="tag in tags" :key="tag.tag_code" class="py-2.5 first:pt-0 last:pb-0">
              <div class="flex flex-wrap items-center gap-2">
                <span class="rounded-sm bg-surface-2 px-1.5 py-0.5 text-[11px] font-bold uppercase tracking-[0.04em] text-ink">
                  {{ tag.tag_code }}
                </span>
                <span class="text-[13.5px] font-medium text-ink">{{ tagName(tag.tag_code) }}</span>
                <span v-if="tag.confidence !== null" class="tnum ml-auto text-xs text-ink-muted">
                  {{ Math.round(tag.confidence * 100) }}%
                </span>
              </div>
              <p v-if="tag.reason" class="mt-1 text-[12.5px] text-ink-muted">{{ tag.reason }}</p>
            </li>
          </ul>
        </UiCard>
      </div>

      <!-- Đánh giá gần nhất -->
      <UiCard class="mt-6" title="Đánh giá gần nhất" description="Kết quả lần đánh giá mới nhất theo ngày ghi nhận.">
        <EmptyState
          v-if="!latest"
          title="Chưa có đánh giá nào"
          body="Chạy đánh giá để phân loại các nghĩa vụ pháp lý áp dụng cho dự án."
          action-label="Xem lịch sử đánh giá"
          @action="go('project-history')"
        />
        <div v-else class="flex flex-col gap-4">
          <div class="flex flex-wrap items-center gap-2">
            <StatusChip v-if="latestMeta" :tone="latestMeta.tone" :label="latestMeta.label" />
            <StatusChip v-if="overallMeta" :tone="overallMeta.tone" :label="overallMeta.label" />
            <span class="tnum ml-auto text-[13px] text-ink-muted">Ngày ghi nhận {{ formatDate(latest.as_of_date) }}</span>
          </div>
          <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div class="rounded-md border border-line bg-surface-2/60 px-4 py-3">
              <p class="tnum text-2xl font-semibold text-ink">{{ applicableCount }}</p>
              <p class="mt-0.5 text-[12.5px] text-ink-muted">Nghĩa vụ có khả năng áp dụng</p>
            </div>
            <div class="rounded-md border border-line bg-surface-2/60 px-4 py-3">
              <p class="tnum text-2xl font-semibold text-ink">{{ needInfoCount }}</p>
              <p class="mt-0.5 text-[12.5px] text-ink-muted">Cần bổ sung thông tin</p>
            </div>
            <div class="rounded-md border border-line bg-surface-2/60 px-4 py-3">
              <p class="tnum text-2xl font-semibold text-ink">{{ expertCount }}</p>
              <p class="mt-0.5 text-[12.5px] text-ink-muted">Cần chuyên gia xác nhận</p>
            </div>
          </div>
        </div>
        <template v-if="latest" #footer>
          <UiButton variant="quiet" size="sm" @click="go('project-obligations')">Xem danh sách nghĩa vụ</UiButton>
          <UiButton variant="quiet" size="sm" @click="go('project-history')">Lịch sử đánh giá</UiButton>
        </template>
      </UiCard>

      <!-- Chỉ số nhanh -->
      <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <button
          class="group flex items-start gap-3 rounded-lg border border-line bg-surface p-4 text-left transition-colors hover:bg-surface-2"
          @click="go('project-documents')"
        >
          <FileText class="mt-0.5 size-5 shrink-0 text-ink-muted" aria-hidden="true" />
          <span class="min-w-0">
            <span class="tnum block text-2xl font-semibold text-ink">{{ counts.documents }}</span>
            <span class="block text-[12.5px] text-ink-muted group-hover:text-ink">Tài liệu đã tải lên</span>
          </span>
        </button>
        <button
          class="group flex items-start gap-3 rounded-lg border border-line bg-surface p-4 text-left transition-colors hover:bg-surface-2"
          @click="go('project-procedures')"
        >
          <Scale class="mt-0.5 size-5 shrink-0 text-ink-muted" aria-hidden="true" />
          <span class="min-w-0">
            <span class="tnum block text-2xl font-semibold text-ink">{{ counts.procedures }}</span>
            <span class="block text-[12.5px] text-ink-muted group-hover:text-ink">Thủ tục đang theo dõi</span>
          </span>
        </button>
        <button
          class="group flex items-start gap-3 rounded-lg border border-line bg-surface p-4 text-left transition-colors hover:bg-surface-2"
          @click="go('project-scan')"
        >
          <ScanLine class="mt-0.5 size-5 shrink-0 text-ink-muted" aria-hidden="true" />
          <span class="min-w-0">
            <span class="tnum block text-2xl font-semibold text-ink">{{ counts.scans }}</span>
            <span class="block text-[12.5px] text-ink-muted group-hover:text-ink">Lần quét trang</span>
          </span>
        </button>
      </div>
    </template>
  </div>
</template>
