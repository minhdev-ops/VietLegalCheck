<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { Search } from '@lucide/vue'
import PublicShell from './PublicShell.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import UiInput from '@/components/ui/UiInput.vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiTable from '@/components/ui/UiTable.vue'
import UiSkeleton from '@/components/ui/UiSkeleton.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import Callout from '@/components/ui/Callout.vue'
import StatusChip from '@/components/ui/StatusChip.vue'
import Pagination from '@/components/ui/Pagination.vue'
import { api, type ApiError } from '@/lib/api'
import { formatDate } from '@/lib/format'
import type { LegalDocument } from '@/types/models'

interface DocumentsResponse {
  data: LegalDocument[]
  as_of_date: string
  meta: { page: number; last_page: number; total: number }
}

const router = useRouter()

const q = ref('')
const asOf = ref(new Date().toISOString().slice(0, 10))
const documents = ref<LegalDocument[]>([])
const asOfFromApi = ref('')
const page = ref(1)
const lastPage = ref(1)
const total = ref(0)
const loading = ref(false)
const error = ref<string | null>(null)

const COLUMNS = [
  { key: 'doc_number', label: 'Số hiệu', width: '12rem' },
  { key: 'title', label: 'Đầu đề' },
  { key: 'issuer', label: 'Cơ quan ban hành', width: '14rem' },
  { key: 'issued_at', label: 'Ngày ban hành', width: '9rem' },
  { key: 'status', label: 'Hiệu lực', width: '10rem' },
]

type EffectTone = 'ok' | 'info' | 'neutral'

function isoDay(value: string | null | undefined): string | null {
  return value ? value.slice(0, 10) : null
}

function effectiveness(row: Record<string, unknown>): { label: string; tone: EffectTone } {
  const ref = isoDay(asOfFromApi.value) ?? asOf.value
  const from = isoDay(row.effective_from as string | null)
  const to = isoDay(row.effective_to as string | null)
  if (from && ref < from) return { label: 'Chưa có hiệu lực', tone: 'info' }
  if (to && ref > to) return { label: 'Hết hiệu lực', tone: 'neutral' }
  return { label: 'Đang có hiệu lực', tone: 'ok' }
}

async function fetchDocuments(): Promise<void> {
  loading.value = true
  error.value = null
  try {
    const payload = await api.get<DocumentsResponse>('/legal/documents', {
      q: q.value.trim(),
      as_of_date: asOf.value,
      page: page.value,
    })
    documents.value = payload.data
    asOfFromApi.value = payload.as_of_date
    page.value = payload.meta.page
    lastPage.value = payload.meta.last_page
    total.value = payload.meta.total
  } catch (err) {
    error.value = (err as ApiError).message ?? 'Không tải được danh sách văn bản.'
  } finally {
    loading.value = false
  }
}

function onSearch(): void {
  page.value = 1
  void fetchDocuments()
}

function clearFilters(): void {
  q.value = ''
  page.value = 1
  void fetchDocuments()
}

function openDocument(row: Record<string, unknown>): void {
  void router.push({
    name: 'legal-document',
    params: { id: String(row.id) },
    query: { as_of_date: asOfFromApi.value || asOf.value },
  })
}

onMounted(() => {
  void fetchDocuments()
})
</script>

<template>
  <PublicShell>
    <div class="mx-auto max-w-[1240px] px-4 py-10 sm:px-6">
      <PageHeader
        title="Tra cứu văn bản pháp luật"
        subtitle="Dữ liệu văn bản công khai, lọc theo ngày hiệu lực. Mỗi dòng dẫn đến chi tiết điều khoản."
      />

      <form
        class="flex flex-wrap items-end gap-4 rounded-lg border border-line bg-surface p-5"
        @submit.prevent="onSearch"
      >
        <UiInput
          v-model="q"
          label="Từ khoá"
          placeholder="Số hiệu hoặc đầu đề văn bản…"
          wrapper-class="min-w-[16rem] flex-1"
        />
        <div class="flex flex-col gap-1.5">
          <label class="text-[13px] font-medium text-ink" for="as-of">Hiệu lực tại ngày</label>
          <input
            id="as-of"
            v-model="asOf"
            type="date"
            class="h-9 rounded-md border border-line-strong bg-surface px-3 text-sm text-ink focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/20"
          />
        </div>
        <UiButton type="submit" variant="primary">
          <Search class="size-4" aria-hidden="true" /> Tra cứu
        </UiButton>
        <UiButton type="button" variant="ghost" @click="clearFilters">Xóa lọc</UiButton>
      </form>

      <p v-if="asOfFromApi" class="tnum mt-4 text-[12.5px] text-ink-muted">
        Hiệu lực tính tại ngày {{ formatDate(asOfFromApi) }} · {{ total }} văn bản
      </p>

      <div class="mt-4">
        <UiSkeleton v-if="loading" :lines="6" />
        <Callout
          v-else-if="error"
          tone="caution"
          icon="warning"
          title="Chưa tải được danh sách văn bản"
          action-label="Thử lại"
          @action="fetchDocuments()"
        >
          {{ error }}
        </Callout>
        <EmptyState
          v-else-if="documents.length === 0"
          title="Chưa tìm thấy văn bản"
          body="Thử bớt từ khoá hoặc đổi ngày hiệu lực, rồi tra cứu lại."
          action-label="Xóa lọc"
          @action="clearFilters()"
        />
        <template v-else>
          <UiTable :columns="COLUMNS" :rows="documents as unknown as Record<string, unknown>[]" :on-row-click="openDocument">
            <template #cell-doc_number="{ row }">
              <span class="tnum font-medium text-ink">{{ row.doc_number }}</span>
            </template>
            <template #cell-title="{ row }">
              <span class="text-ink">{{ row.title }}</span>
            </template>
            <template #cell-issuer="{ row }">
              <span class="text-ink-muted">{{ row.issuer ?? '—' }}</span>
            </template>
            <template #cell-issued_at="{ row }">
              <span class="tnum text-ink-muted">{{ formatDate(row.issued_at as string | null) }}</span>
            </template>
            <template #cell-status="{ row }">
              <StatusChip
                :tone="effectiveness(row).tone"
                :label="effectiveness(row).label"
              />
            </template>
          </UiTable>
          <Pagination
            :page="page"
            :last-page="lastPage"
            :total="total"
            @change="(next) => { page = next; void fetchDocuments() }"
          />
        </template>
      </div>
    </div>
  </PublicShell>
</template>
