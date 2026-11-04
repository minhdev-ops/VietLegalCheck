<script setup lang="ts">
import { computed, ref } from 'vue'
import { ClipboardList, Search } from '@lucide/vue'
import PageHeader from '@/components/common/PageHeader.vue'
import UiCard from '@/components/ui/UiCard.vue'
import UiInput from '@/components/ui/UiInput.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import Pagination from '@/components/ui/Pagination.vue'
import StatusChip from '@/components/ui/StatusChip.vue'
import UiSkeleton from '@/components/ui/UiSkeleton.vue'
import UiTable from '@/components/ui/UiTable.vue'
import Callout from '@/components/ui/Callout.vue'
import { formatDate } from '@/lib/format'
import type { AdministrativeProcedure } from '@/types/models'
import { usePaginated } from './shared'

const params = ref<{ q: string }>({ q: '' })
const { items, total, page, lastPage, loading, error, load } = usePaginated<AdministrativeProcedure>(
  '/admin/procedures',
  params,
)

const columns = [
  { key: 'code', label: 'Mã thủ tục' },
  { key: 'name', label: 'Tên thủ tục' },
  { key: 'authority', label: 'Cơ quan tiếp nhận' },
  { key: 'processing_time', label: 'Thời hạn xử lý' },
  { key: 'fee', label: 'Phí, lệ phí' },
  { key: 'verified', label: 'Kiểm chứng' },
]

const rows = computed(() =>
  items.value.map((procedure) => ({
    code: procedure.code,
    name: procedure.name,
    authority: procedure.authority,
    processing_time: procedure.processing_time,
    fee: procedure.fee,
    verified_stale: procedure.verified_stale,
    last_verified_at: procedure.last_verified_at,
    portal_url: procedure.portal_url,
  })),
)
</script>

<template>
  <div>
    <PageHeader
      title="Thủ tục hành chính"
      subtitle="Danh mục thủ tục: cơ quan tiếp nhận, thời hạn xử lý và tình trạng kiểm chứng."
    />

    <UiCard padding="none">
      <template #header>
        <div class="flex items-center gap-2">
          <Search class="size-4 shrink-0 text-ink-faint" aria-hidden="true" />
          <UiInput v-model="params.q" wrapper-class="max-w-sm" placeholder="Tìm theo mã hoặc tên thủ tục…" />
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
      <UiSkeleton v-else-if="loading" :lines="4" class="p-5" />
      <EmptyState
        v-else-if="rows.length === 0"
        :icon="ClipboardList"
        title="Chưa có thủ tục nào"
        body="Danh mục thủ tục hành chính sẽ xuất hiện ở đây sau khi được biên mục."
        class="m-5"
      />
      <template v-else>
        <UiTable :columns="columns" :rows="rows" class="px-5">
          <template #cell-verified="{ row }">
            <div class="flex items-center gap-2">
              <StatusChip
                v-if="row.verified_stale"
                tone="caution"
                :label="`Cần kiểm tra lại · ${formatDate(row.last_verified_at as string | null)}`"
              />
              <StatusChip v-else tone="ok" :label="`Đã kiểm chứng · ${formatDate(row.last_verified_at as string | null)}`" />
            </div>
          </template>
        </UiTable>
        <Pagination :page="page" :last-page="lastPage" :total="total" class="px-5" @change="page = $event" />
      </template>
    </UiCard>
  </div>
</template>
