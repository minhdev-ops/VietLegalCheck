<script setup lang="ts">
/**
 * Quét trang: khởi tạo quét LIGHT/DEEP (DEEP yêu cầu xác minh tên miền),
 * theo dõi danh sách lần quét, xem chi tiết trang tìm thấy và huỷ quét đang chạy.
 */
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { ExternalLink, Eye, ScanLine, XCircle } from '@lucide/vue'
import PageHeader from '@/components/common/PageHeader.vue'
import Callout from '@/components/ui/Callout.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import StatusChip from '@/components/ui/StatusChip.vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiCard from '@/components/ui/UiCard.vue'
import UiDialog from '@/components/ui/UiDialog.vue'
import UiInput from '@/components/ui/UiInput.vue'
import UiSelect from '@/components/ui/UiSelect.vue'
import UiSkeleton from '@/components/ui/UiSkeleton.vue'
import UiTable from '@/components/ui/UiTable.vue'
import { useToastsStore } from '@/stores/toasts'
import { api, type ApiError } from '@/lib/api'
import { formatDateTime } from '@/lib/format'
import type { DomainVerification, Scan, ScanPage } from '@/types/models'
import { SCAN_STATUS, SEVERITY, VERIFICATION_STATUS, fetchList, statusMeta } from './shared'

const route = useRoute()
const toasts = useToastsStore()
const id = computed(() => String(route.params.id))

const loading = ref(true)
const error = ref<ApiError | null>(null)
const scans = ref<Scan[]>([])
const url = ref('https://')
const mode = ref<'LIGHT' | 'DEEP'>('LIGHT')
const submitting = ref(false)
const formError = ref<string | null>(null)

const domain = computed(() => {
  try {
    return new URL(url.value).hostname.replace(/^www\./, '')
  } catch {
    return ''
  }
})

const verification = ref<DomainVerification | null>(null)
const verifying = ref(false)
const checking = ref(false)

const activeModes = computed(() => scans.value.filter((scan) => scan.status === 'QUEUED' || scan.status === 'RUNNING'))

let refreshTimer: ReturnType<typeof setInterval> | undefined

function scanChip(status: string) {
  return statusMeta(SCAN_STATUS, status)
}
function verificationChip(status: string) {
  return statusMeta(VERIFICATION_STATUS, status)
}
function severityChip(status: string) {
  return statusMeta(SEVERITY, status)
}

async function load(): Promise<void> {
  loading.value = true
  error.value = null
  try {
    scans.value = await fetchList<Scan>(`/projects/${id.value}/scans`)
  } catch (e) {
    error.value = e as ApiError
  } finally {
    loading.value = false
  }
}

async function startVerification(): Promise<void> {
  if (!domain.value) {
    formError.value = 'Nhập địa chỉ mạng hợp lệ trước khi xác minh.'
    return
  }
  verifying.value = true
  formError.value = null
  try {
    const payload = await api.post<{ verification: DomainVerification }>(
      `/projects/${id.value}/domain-verifications`,
      { domain: domain.value },
    )
    verification.value = payload.verification
    toasts.push('Đã tạo yêu cầu xác minh', 'Làm theo hướng dẫn rồi bấm kiểm tra.', 'info')
  } catch (e) {
    formError.value = (e as ApiError).message
  } finally {
    verifying.value = false
  }
}

async function checkVerification(): Promise<void> {
  if (!domain.value || !verification.value) return
  checking.value = true
  formError.value = null
  try {
    const payload = await api.get<{ verification: DomainVerification; verified: boolean }>(
      `/projects/${id.value}/domain-verifications/${encodeURIComponent(verification.value.domain)}/check`,
    )
    verification.value = payload.verification
    if (payload.verified) toasts.push('Đã xác minh tên miền', undefined, 'ok')
    else toasts.push('Chưa xác minh được', 'Kiểm tra lại bản ghi hoặc thẻ meta đã đặt.', 'caution')
  } catch (e) {
    formError.value = (e as ApiError).message
  } finally {
    checking.value = false
  }
}

const canStart = computed(() => {
  if (!url.value || mode.value !== 'DEEP') return true
  return verification.value?.status === 'VERIFIED' && verification.value.domain === domain.value
})

async function startScan(): Promise<void> {
  submitting.value = true
  formError.value = null
  try {
    await api.post(`/projects/${id.value}/scans`, { url: url.value, mode: mode.value })
    toasts.push('Đã bắt đầu quét', mode.value === 'DEEP' ? 'Quét sâu đang thu thập các trang.' : undefined, 'info')
    await load()
    if (activeModes.value.length) scheduleRefresh()
  } catch (e) {
    formError.value = (e as ApiError).message
  } finally {
    submitting.value = false
  }
}

/* Tự làm mới danh sách khi có quét đang chạy. */
function scheduleRefresh(): void {
  clearInterval(refreshTimer)
  refreshTimer = setInterval(async () => {
    if (!activeModes.value.length) {
      clearInterval(refreshTimer)
      return
    }
    await load()
  }, 5_000)
}

/* ---- Chi tiết lần quét ---- */
const detail = ref<Scan | null>(null)
const detailOpen = ref(false)
const detailLoading = ref(false)
const pages = ref<ScanPage[]>([])

async function openDetail(scan: Scan): Promise<void> {
  detailOpen.value = true
  detailLoading.value = true
  detail.value = scan
  pages.value = []
  try {
    const payload = await api.get<{ scan: Scan }>(`/projects/${id.value}/scans/${scan.id}`)
    detail.value = payload.scan
    pages.value = payload.scan.pages ?? []
  } catch (e) {
    toasts.push('Không tải được chi tiết', (e as ApiError).message, 'danger')
  } finally {
    detailLoading.value = false
  }
}

async function cancelScan(scan: Scan): Promise<void> {
  try {
    await api.post(`/projects/${id.value}/scans/${scan.id}/cancel`)
    toasts.push('Đã huỷ lần quét', undefined, 'ok')
    await load()
  } catch (e) {
    toasts.push('Huỷ không thành công', (e as ApiError).message, 'danger')
  }
}

const detailColumns = [
  { key: 'url', label: 'Trang' },
  { key: 'http_status', label: 'HTTP', numeric: true },
  { key: 'title', label: 'Tiêu đề' },
  { key: 'fetch_error', label: 'Lỗi' },
]

onMounted(async () => {
  await load()
  if (activeModes.value.length) scheduleRefresh()
})
onBeforeUnmount(() => clearInterval(refreshTimer))

const MODE_OPTIONS = [
  { value: 'LIGHT', label: 'Quét nhanh (LIGHT)' },
  { value: 'DEEP', label: 'Quét sâu (DEEP)' },
]
</script>

<template>
  <div>
    <PageHeader
      title="Quét trang"
      subtitle="Quét trang để phát hiện vấn đề pháp lý hiển thị trên website đang vận hành."
    />

    <UiCard title="Khởi tạo quét" description="Chế độ quét sâu yêu cầu xác minh quyền sở hữu tên miền.">
      <form class="flex flex-col gap-4" @submit.prevent="startScan()">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
          <UiInput
            v-model="url"
            label="Địa chỉ trang"
            placeholder="https://example.com"
            wrapper-class="sm:col-span-2"
            required
          />
          <UiSelect v-model="mode" :options="MODE_OPTIONS" label="Chế độ quét" />
        </div>

        <Callout
          v-if="mode === 'DEEP' && !canStart"
          tone="caution"
          icon="warning"
          title="Cần xác minh quyền sở hữu tên miền"
        >
          Quét sâu chỉ áp dụng cho tên miền đã xác minh. Tạo yêu cầu xác minh cho
          <strong class="font-semibold">{{ domain || '—' }}</strong>, làm theo hướng dẫn rồi kiểm tra lại.
        </Callout>
        <Callout
          v-if="mode === 'DEEP' && canStart && verification"
          tone="ok"
          icon="info"
          title="Tên miền đã xác minh"
        >
          {{ verification.domain }} — có thể bắt đầu quét sâu.
        </Callout>
        <Callout v-if="formError" tone="danger" icon="warning" :title="formError" />

        <div v-if="mode === 'DEEP' && !canStart" class="flex flex-col gap-3 rounded-md border border-line bg-surface-2/60 p-4">
          <div v-if="!verification" class="flex flex-wrap items-center gap-2">
            <span class="text-[13px] text-ink-muted">Chưa có yêu cầu xác minh cho tên miền này.</span>
            <UiButton variant="secondary" size="sm" :loading="verifying" @click="startVerification()">
              Tạo yêu cầu xác minh
            </UiButton>
          </div>
          <div v-else class="flex flex-col gap-3">
            <div class="flex flex-wrap items-center gap-2">
              <StatusChip
                :tone="verificationChip(verification.status).tone"
                :label="verificationChip(verification.status).label"
              />
              <span class="tnum text-[12.5px] text-ink-muted">Phương pháp {{ verification.method === 'META_TAG' ? 'thẻ meta' : 'bản ghi DNS TXT' }}</span>
              <UiButton variant="secondary" size="sm" :loading="checking" @click="checkVerification()">
                Kiểm tra xác minh
              </UiButton>
            </div>
            <div class="flex flex-col gap-1.5">
              <span class="text-[12.5px] font-medium text-ink">Việc cần làm</span>
              <p class="text-[12.5px] text-ink-muted">{{ verification.instruction }}</p>
              <code class="w-fit rounded-sm bg-surface-3 px-2 py-1 text-[12px] text-ink">{{ verification.token }}</code>
            </div>
          </div>
        </div>

        <div class="flex justify-end">
          <UiButton variant="primary" :loading="submitting" :disabled="!canStart || !url" type="submit">
            <ScanLine class="size-4" aria-hidden="true" />
            Bắt đầu quét
          </UiButton>
        </div>
      </form>
    </UiCard>

    <UiCard class="mt-6" padding="none">
      <template #header>
        <div class="flex items-center justify-between gap-4">
          <div>
            <h3 class="text-[15px] font-semibold leading-tight tracking-[-0.005em] text-ink">Lịch sử quét</h3>
            <p class="mt-1 text-[13px] text-ink-muted">Các lần quét đã thực hiện, mới nhất trước.</p>
          </div>
          <span v-if="activeModes.length" class="tnum text-[13px] text-ink-muted">{{ activeModes.length }} đang chạy</span>
        </div>
      </template>

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
        v-else-if="!scans.length"
        title="Chưa có lần quét nào"
        body="Khởi tạo quét để thu thập các trang và phát hiện vấn đề pháp lý."
        class="m-5"
      />
      <UiTable
        v-else
        :columns="[
          { key: 'target_url', label: 'Địa chỉ' },
          { key: 'mode', label: 'Chế độ', width: '9rem' },
          { key: 'status', label: 'Trạng thái', width: '10rem' },
          { key: 'page_count', label: 'Số trang', numeric: true, width: '7rem' },
          { key: 'finished_at', label: 'Hoàn tất', width: '9rem' },
          { key: 'actions', label: '', align: 'right', width: '11rem' },
        ]"
        :rows="scans.map((scan) => ({ ...scan }))"
      >
        <template #cell-target_url="{ value }">
          <a
            :href="String(value)"
            target="_blank"
            rel="noopener noreferrer"
            class="inline-flex items-center gap-1 font-medium text-accent hover:underline underline-offset-2"
          >
            {{ value }}
            <ExternalLink class="size-3" aria-hidden="true" />
          </a>
        </template>
        <template #cell-status="{ value }">
          <StatusChip :tone="scanChip(String(value)).tone" :label="scanChip(String(value)).label" />
        </template>
        <template #cell-actions="{ row }">
          <div class="flex justify-end gap-1">
            <UiButton variant="quiet" size="sm" @click="openDetail(row as unknown as Scan)">
              <Eye class="size-3.5" aria-hidden="true" />
              Chi tiết
            </UiButton>
            <UiButton
              v-if="row.status === 'QUEUED' || row.status === 'RUNNING'"
              variant="quiet"
              size="sm"
              @click="cancelScan(row as unknown as Scan)"
            >
              <XCircle class="size-3.5" aria-hidden="true" />
              Huỷ
            </UiButton>
          </div>
        </template>
      </UiTable>
    </UiCard>

    <UiDialog
      :open="detailOpen"
      :title="detail ? `Lần quét ${detail.target_url}` : 'Chi tiết lần quét'"
      :description="detail ? `${detail.mode} · ${formatDateTime(detail.started_at)}` : undefined"
      size="lg"
      @update:open="detailOpen = $event"
    >
      <UiSkeleton v-if="detailLoading" :lines="4" />
      <div v-else-if="detail" class="flex flex-col gap-5">
        <div class="flex flex-wrap items-center gap-2">
          <StatusChip :tone="scanChip(detail.status).tone" :label="scanChip(detail.status).label" />
          <span class="tnum text-[13px] text-ink-muted">{{ pages.length }} trang thu thập</span>
        </div>

        <Callout v-if="detail.blocked_reason" tone="danger" icon="warning" title="Lần quét bị chặn">
          {{ detail.blocked_reason }}
        </Callout>
        <Callout v-if="detail.error" tone="danger" icon="warning" title="Lỗi khi quét">
          {{ detail.error }}
        </Callout>
        <Callout v-if="pages.length > 50" tone="caution" icon="warning" title="Khối lượng trang lớn">
          Lần quét thu thập {{ pages.length }} trang. Xem xét giới hạn phạm vi quét để giảm tải hệ thống.
        </Callout>

        <section>
          <h4 class="mb-2 text-[11px] font-semibold uppercase tracking-[0.06em] text-ink-muted">Trang tìm thấy</h4>
          <EmptyState v-if="!pages.length" title="Không có trang nào" body="Lần quét không thu thập được trang." />
          <UiTable v-else :columns="detailColumns" :rows="pages.map((page) => ({ ...page }))">
            <template #cell-url="{ value }">
              <span class="truncate text-[12.5px] text-ink">{{ value }}</span>
            </template>
            <template #cell-fetch_error="{ value }">
              <span class="text-[12.5px] text-ink-muted">{{ value ?? '—' }}</span>
            </template>
          </UiTable>
        </section>

        <section>
          <h4 class="mb-2 text-[11px] font-semibold uppercase tracking-[0.06em] text-ink-muted">Cảnh báo pháp lý</h4>
          <EmptyState v-if="!detail.findings?.length" title="Không có cảnh báo" body="Chưa phát hiện vấn đề pháp lý trên các trang đã quét." />
          <ul v-else class="flex flex-col divide-y divide-line">
            <li v-for="finding in detail.findings" :key="finding.code" class="py-2.5 first:pt-0 last:pb-0">
              <div class="flex flex-wrap items-center gap-2">
                <StatusChip :tone="severityChip(finding.severity).tone" :label="severityChip(finding.severity).label" />
                <span class="text-[13.5px] font-medium text-ink">{{ finding.title }}</span>
                <code class="rounded-sm bg-surface-3 px-1.5 py-0.5 text-[11px] text-ink">{{ finding.code }}</code>
              </div>
              <p class="mt-1 text-[12.5px] text-ink-muted">{{ finding.detail }}</p>
              <a
                v-if="finding.evidence_url"
                :href="finding.evidence_url"
                target="_blank"
                rel="noopener noreferrer"
                class="mt-1 inline-flex items-center gap-1 text-[12.5px] font-medium text-accent hover:underline underline-offset-2"
              >
                Trang chứa bằng chứng <ExternalLink class="size-3" aria-hidden="true" />
              </a>
            </li>
          </ul>
        </section>
      </div>
      <template #footer>
        <UiButton variant="secondary" @click="detailOpen = false">Đóng</UiButton>
      </template>
    </UiDialog>
  </div>
</template>
