<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { Plus, Search } from '@lucide/vue'
import PageHeader from '@/components/common/PageHeader.vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiCard from '@/components/ui/UiCard.vue'
import UiDialog from '@/components/ui/UiDialog.vue'
import UiInput from '@/components/ui/UiInput.vue'
import UiSelect from '@/components/ui/UiSelect.vue'
import Pagination from '@/components/ui/Pagination.vue'
import StatusChip from '@/components/ui/StatusChip.vue'
import UiTable from '@/components/ui/UiTable.vue'
import Callout from '@/components/ui/Callout.vue'
import { useToastsStore } from '@/stores/toasts'
import { api, type ApiError } from '@/lib/api'
import { formatDate } from '@/lib/format'
import type { LegalDocument } from '@/types/models'
import {
  DOC_TYPE_OPTIONS,
  DOCUMENT_STATUS,
  emptyToNull,
  statusMeta,
  toSelectOptions,
  usePaginated,
} from './shared'

const router = useRouter()
const toasts = useToastsStore()

const params = ref<{ q: string }>({ q: '' })
const { items, total, page, lastPage, error, load, reload } = usePaginated<LegalDocument>(
  '/admin/legal-documents',
  params,
)

const columns = [
  { key: 'doc_number', label: 'Số hiệu' },
  { key: 'title', label: 'Trích yếu' },
  { key: 'doc_type', label: 'Loại văn bản' },
  { key: 'issuer', label: 'Cơ quan ban hành' },
  { key: 'effective_from', label: 'Hiệu lực từ' },
  { key: 'status', label: 'Trạng thái' },
]

const rows = computed(() =>
  items.value.map((doc) => ({
    id: doc.id,
    doc_number: doc.doc_number,
    title: doc.title,
    doc_type: doc.doc_type,
    issuer: doc.issuer ?? '—',
    effective_from: doc.effective_from,
    status: doc.status,
    verified_stale: doc.verified_stale,
  })),
)

function open(row: Record<string, unknown>): void {
  router.push({ name: 'admin-document', params: { id: String(row.id) } })
}

/* Tạo văn bản mới */
const dialog = ref(false)
const submitting = ref(false)
const dialogError = ref<string | null>(null)
const form = ref({
  doc_number: '',
  title: '',
  doc_type: DOC_TYPE_OPTIONS[1],
  issuer: '',
  issued_at: '',
  effective_from: '',
  effective_to: '',
  status: 'IN_EFFECT',
  official_url: '',
  notes: '',
})

function openDialog(): void {
  form.value = {
    doc_number: '',
    title: '',
    doc_type: DOC_TYPE_OPTIONS[1],
    issuer: '',
    issued_at: '',
    effective_from: '',
    effective_to: '',
    status: 'IN_EFFECT',
    official_url: '',
    notes: '',
  }
  dialogError.value = null
  dialog.value = true
}

async function submit(): Promise<void> {
  if (!form.value.doc_number || !form.value.title) {
    dialogError.value = 'Số hiệu và trích yếu là bắt buộc.'
    return
  }
  submitting.value = true
  dialogError.value = null
  try {
    await api.post('/admin/legal-documents', {
      doc_number: form.value.doc_number,
      title: form.value.title,
      doc_type: form.value.doc_type,
      issuer: emptyToNull(form.value.issuer),
      issued_at: emptyToNull(form.value.issued_at),
      effective_from: emptyToNull(form.value.effective_from),
      effective_to: emptyToNull(form.value.effective_to),
      status: form.value.status,
      official_url: emptyToNull(form.value.official_url),
      notes: emptyToNull(form.value.notes),
    })
    toasts.push('Đã tạo văn bản pháp luật', undefined, 'ok')
    dialog.value = false
    reload()
  } catch (e) {
    dialogError.value = (e as ApiError).message
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div>
    <PageHeader
      title="Văn bản pháp luật"
      subtitle="Sổ đăng ký văn bản: số hiệu, trích yếu, hiệu lực và điều khoản căn cứ pháp lý."
    >
      <template #actions>
        <UiButton variant="primary" @click="openDialog">
          <Plus class="size-4" aria-hidden="true" />
          Tạo văn bản
        </UiButton>
      </template>
    </PageHeader>

    <UiCard padding="none">
      <template #header>
        <div class="flex items-center gap-2">
          <Search class="size-4 shrink-0 text-ink-faint" aria-hidden="true" />
          <UiInput
            v-model="params.q"
            wrapper-class="max-w-sm"
            placeholder="Tìm theo số hiệu hoặc trích yếu…"
          />
        </div>
      </template>

      <Callout
        v-if="error"
        tone="danger"
        icon="warning"
        :title="error.message"
        action-label="Thử lại"
        class="m-5"
        @action="load()"
      />
      <template v-else>
        <UiTable
          :columns="columns"
          :rows="rows"
          :on-row-click="open"
          class="px-5"
        >
          <template #cell-effective_from="{ value }">
            <span class="tnum">{{ formatDate(value as string | null) }}</span>
          </template>
          <template #cell-status="{ row }">
            <div class="flex items-center gap-2">
              <StatusChip v-bind="statusMeta(DOCUMENT_STATUS, String(row.status))" />
              <span v-if="row.verified_stale" class="text-[11.5px] font-medium text-caution">cần kiểm tra lại</span>
            </div>
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

    <UiDialog
      :open="dialog"
      title="Tạo văn bản pháp luật"
      description="Nhập số hiệu và trích yếu; các trường khác có thể bổ sung sau."
      size="lg"
      @update:open="dialog = $event"
    >
      <form class="grid grid-cols-1 gap-4 sm:grid-cols-2" @submit.prevent="submit()">
        <UiInput v-model="form.doc_number" label="Số hiệu" required placeholder="123/2025/QH15" />
        <UiSelect v-model="form.doc_type" label="Loại văn bản" :options="DOC_TYPE_OPTIONS.map((o) => ({ value: o, label: o }))" />
        <UiInput v-model="form.title" label="Trích yếu" required wrapper-class="sm:col-span-2" />
        <UiInput v-model="form.issuer" label="Cơ quan ban hành" placeholder="Quốc hội, Chính phủ…" />
        <UiSelect v-model="form.status" label="Trạng thái" :options="toSelectOptions(DOCUMENT_STATUS)" />
        <UiInput v-model="form.issued_at" label="Ngày ban hành" hint="Định dạng YYYY-MM-DD" />
        <UiInput v-model="form.effective_from" label="Hiệu lực từ" hint="Định dạng YYYY-MM-DD" />
        <UiInput v-model="form.effective_to" label="Hiệu lực đến" hint="Định dạng YYYY-MM-DD" />
        <UiInput v-model="form.official_url" label="URL nguồn chính thức" placeholder="https://" />
        <UiInput v-model="form.notes" label="Ghi chú" wrapper-class="sm:col-span-2" />
        <p v-if="dialogError" class="text-xs text-seal sm:col-span-2" role="alert">{{ dialogError }}</p>
      </form>
      <template #footer>
        <UiButton variant="secondary" @click="dialog = false">Huỷ</UiButton>
        <UiButton variant="primary" :loading="submitting" @click="submit()">Tạo văn bản</UiButton>
      </template>
    </UiDialog>
  </div>
</template>
