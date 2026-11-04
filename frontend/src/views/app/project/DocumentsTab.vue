<script setup lang="ts">
/**
 * Tài liệu: tải lên nhiều file (giới hạn 20MB / loại file), danh sách tài liệu
 * với tải xuống theo hash và xoá file.
 */
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { Download, Trash2, UploadCloud } from '@lucide/vue'
import PageHeader from '@/components/common/PageHeader.vue'
import Callout from '@/components/ui/Callout.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import StatusChip from '@/components/ui/StatusChip.vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiCard from '@/components/ui/UiCard.vue'
import UiSelect from '@/components/ui/UiSelect.vue'
import UiSkeleton from '@/components/ui/UiSkeleton.vue'
import UiTable from '@/components/ui/UiTable.vue'
import { useToastsStore } from '@/stores/toasts'
import { api, getToken, type ApiError, upload } from '@/lib/api'
import { formatBytes } from '@/lib/format'
import type { DocumentFile } from '@/types/models'
import { DOCUMENT_SCAN_STATUS, fetchList, shortHash, statusMeta } from './shared'

const route = useRoute()
const toasts = useToastsStore()
const id = computed(() => String(route.params.id))

const loading = ref(true)
const error = ref<ApiError | null>(null)
const files = ref<DocumentFile[]>([])
const category = ref('OTHER')
const uploading = ref(false)
const uploadError = ref<string | null>(null)
const fileInput = ref<HTMLInputElement | null>(null)
const selected = ref<File[]>([])

const MAX_SIZE = 20 * 1024 * 1024
const ACCEPTED = [
  'application/pdf',
  'image/png',
  'image/jpeg',
  'image/webp',
  'application/msword',
  'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
  'application/vnd.ms-excel',
  'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
  'text/plain',
]

const CATEGORY_OPTIONS = [
  { value: 'OTHER', label: 'Khác' },
  { value: 'LICENSE', label: 'Giấy phép' },
  { value: 'CONTRACT', label: 'Hợp đồng' },
  { value: 'EVIDENCE', label: 'Bằng chứng' },
  { value: 'POLICY', label: 'Chính sách' },
]

const columns = [
  { key: 'original_name', label: 'Tên file' },
  { key: 'category', label: 'Nhóm', width: '9rem' },
  { key: 'size', label: 'Dung lượng', numeric: true, width: '8rem' },
  { key: 'scan_status', label: 'Kiểm tra', width: '9rem' },
  { key: 'checksum_sha256', label: 'Băm SHA-256', width: '12rem' },
  { key: 'created_at', label: 'Tải lên', width: '9rem' },
  { key: 'actions', label: '', align: 'right' as const, width: '9rem' },
]

async function load(): Promise<void> {
  loading.value = true
  error.value = null
  try {
    files.value = await fetchList<DocumentFile>(`/projects/${id.value}/documents`)
  } catch (e) {
    error.value = e as ApiError
  } finally {
    loading.value = false
  }
}

function pickFiles(event: Event): void {
  const input = event.target as HTMLInputElement
  selected.value = input.files ? Array.from(input.files) : []
  uploadError.value = null
}

function validate(files: File[]): string | null {
  for (const file of files) {
    if (file.size > MAX_SIZE) return `File "${file.name}" vượt giới hạn 20MB.`
    if (!ACCEPTED.includes(file.type) && !/\.(pdf|png|jpe?g|webp|docx?|xlsx?|txt)$/i.test(file.name)) {
      return `File "${file.name}" không thuộc loại được phép tải lên.`
    }
  }
  return null
}

async function uploadFiles(): Promise<void> {
  if (!selected.value.length) {
    uploadError.value = 'Chọn ít nhất một file để tải lên.'
    return
  }
  const invalid = validate(selected.value)
  if (invalid) {
    uploadError.value = invalid
    return
  }
  uploading.value = true
  uploadError.value = null
  try {
    for (const file of selected.value) {
      await upload(`/projects/${id.value}/documents`, file, { category: category.value })
    }
    toasts.push('Đã tải lên tài liệu', `${selected.value.length} file đã được lưu.`, 'ok')
    selected.value = []
    if (fileInput.value) fileInput.value.value = ''
    await load()
  } catch (e) {
    uploadError.value = (e as ApiError).message
  } finally {
    uploading.value = false
  }
}

async function download(file: DocumentFile): Promise<void> {
  try {
    const response = await fetch(`/api/v1/projects/${id.value}/documents/${file.id}/download`, {
      headers: { Authorization: `Bearer ${getToken() ?? ''}` },
    })
    if (!response.ok) throw new Error('Tải xuống không thành công.')
    const blob = await response.blob()
    const link = document.createElement('a')
    link.href = URL.createObjectURL(blob)
    link.download = file.original_name
    link.click()
    URL.revokeObjectURL(link.href)
  } catch (e) {
    toasts.push('Tải xuống lỗi', (e as Error).message, 'danger')
  }
}

const deleting = ref<string | null>(null)

async function remove(file: DocumentFile): Promise<void> {
  if (!confirm(`Xoá file "${file.original_name}"? Hành động không thể hoàn tác.`)) return
  deleting.value = file.id
  try {
    await api.del(`/projects/${id.value}/documents/${file.id}`)
    toasts.push('Đã xoá tài liệu', undefined, 'ok')
    await load()
  } catch (e) {
    toasts.push('Xoá không thành công', (e as ApiError).message, 'danger')
  } finally {
    deleting.value = null
  }
}

onMounted(load)
</script>

<template>
  <div>
    <PageHeader
      title="Tài liệu"
      subtitle="Tài liệu dự án: giấy phép, hợp đồng, bằng chứng và chính sách đôi bên."
    />

    <UiCard title="Tải lên" description="Mỗi file tối đa 20MB; chấp nhận PDF, ảnh và văn bản phổ biến.">
      <form class="flex flex-col gap-4" @submit.prevent="uploadFiles()">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
          <div class="sm:col-span-2">
            <label class="mb-1.5 block text-[13px] font-medium text-ink">Chọn file</label>
            <input
              ref="fileInput"
              type="file"
              multiple
              accept=".pdf,.png,.jpg,.jpeg,.webp,.doc,.docx,.xls,.xlsx,.txt"
              class="block w-full text-[13px] text-ink-muted file:mr-3 file:h-9 file:rounded-md file:border-0 file:bg-accent file:px-4 file:text-sm file:font-medium file:text-white"
              @change="pickFiles"
            />
            <p v-if="selected.length" class="mt-1.5 text-[12.5px] text-ink-muted">
              Đã chọn {{ selected.length }} file:
              <span v-for="file in selected" :key="file.name" class="text-ink">{{ file.name }} ({{ formatBytes(file.size) }}); </span>
            </p>
          </div>
          <UiSelect v-model="category" :options="CATEGORY_OPTIONS" label="Nhóm tài liệu" />
        </div>
        <Callout v-if="uploadError" tone="danger" icon="warning" :title="uploadError" />
        <div class="flex justify-end">
          <UiButton variant="primary" :loading="uploading" :disabled="!selected.length" type="submit">
            <UploadCloud class="size-4" aria-hidden="true" />
            Tải lên {{ selected.length ? `(${selected.length})` : '' }}
          </UiButton>
        </div>
      </form>
    </UiCard>

    <UiCard class="mt-6" padding="none">
      <template #header>
        <div class="flex items-center justify-between gap-4">
          <div>
            <h3 class="text-[15px] font-semibold leading-tight tracking-[-0.005em] text-ink">Danh sách tài liệu</h3>
            <p class="mt-1 text-[13px] text-ink-muted">Tải xuống kèm băm SHA-256 kiểm tra toàn vẹn.</p>
          </div>
          <span class="tnum text-[13px] text-ink-muted">{{ files.length }} file</span>
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
        v-else-if="!files.length"
        title="Chưa có tài liệu nào"
        body="Tải lên giấy phép, hợp đồng hoặc bằng chứng để đính kèm vào hồ sơ dự án."
        action-label="Chọn file"
        class="m-5"
        @action="fileInput?.click()"
      />
      <UiTable v-else :columns="columns" :rows="files.map((file) => ({ ...file }))">
        <template #cell-original_name="{ value }">
          <span class="text-[13.5px] font-medium text-ink">{{ value }}</span>
        </template>
        <template #cell-size="{ value }">
          <span class="tnum text-[13px] text-ink-muted">{{ formatBytes(value as number) }}</span>
        </template>
        <template #cell-scan_status="{ value }">
          <StatusChip
            :tone="statusMeta(DOCUMENT_SCAN_STATUS, String(value)).tone"
            :label="statusMeta(DOCUMENT_SCAN_STATUS, String(value)).label"
          />
        </template>
        <template #cell-checksum_sha256="{ value }">
          <code class="tnum text-[11.5px] text-ink-muted" :title="String(value)">{{ shortHash(value as string) }}</code>
        </template>
        <template #cell-actions="{ row }">
          <div class="flex justify-end gap-1">
            <UiButton variant="quiet" size="sm" @click="download(row as unknown as DocumentFile)">
              <Download class="size-3.5" aria-hidden="true" />
              Tải xuống
            </UiButton>
            <UiButton
              variant="quiet"
              size="sm"
              :loading="deleting === (row as unknown as DocumentFile).id"
              @click="remove(row as unknown as DocumentFile)"
            >
              <Trash2 class="size-3.5" aria-hidden="true" />
              Xoá
            </UiButton>
          </div>
        </template>
      </UiTable>
    </UiCard>
  </div>
</template>
