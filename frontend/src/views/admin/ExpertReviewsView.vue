<script setup lang="ts">
import { computed, ref } from 'vue'
import { Inbox, Search } from '@lucide/vue'
import PageHeader from '@/components/common/PageHeader.vue'
import UiCard from '@/components/ui/UiCard.vue'
import UiInput from '@/components/ui/UiInput.vue'
import UiSelect from '@/components/ui/UiSelect.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import Pagination from '@/components/ui/Pagination.vue'
import StatusChip from '@/components/ui/StatusChip.vue'
import UiSkeleton from '@/components/ui/UiSkeleton.vue'
import UiTable from '@/components/ui/UiTable.vue'
import Callout from '@/components/ui/Callout.vue'
import { formatDateTime } from '@/lib/format'
import {
  REVIEW_STATUS,
  statusMeta,
  toSelectOptions,
  usePaginated,
} from './shared'
import type { ExpertReview } from './shared'

const params = ref<{ q: string; status: string }>({ q: '', status: '' })
const { items, total, page, lastPage, loading, error, load } = usePaginated<ExpertReview>(
  '/admin/expert-reviews',
  params,
)

const columns = [
  { key: 'title', label: 'Yêu cầu' },
  { key: 'reason', label: 'Lý do' },
  { key: 'status', label: 'Trạng thái' },
  { key: 'assignee', label: 'Chuyên gia phụ trách' },
  { key: 'created_at', label: 'Tạo lúc' },
]

const rows = computed(() =>
  items.value.map((review) => ({
    title: review.title,
    item_type: review.item_type,
    reason: review.reason ?? '—',
    status: review.status,
    assignee: review.assignee ?? 'Chưa phân công',
    created_at: review.created_at,
  })),
)
</script>

<template>
  <div>
    <PageHeader
      title="Hàng đợi chuyên gia"
      subtitle="Các phát phẩm pháp lý cần chuyên gia xác nhận trước khi chốt kết quả đánh giá."
    />

    <UiCard padding="none">
      <template #header>
        <div class="flex flex-wrap items-center gap-3">
          <div class="flex items-center gap-2">
            <Search class="size-4 shrink-0 text-ink-faint" aria-hidden="true" />
            <UiInput v-model="params.q" placeholder="Tìm theo tiêu đề…" />
          </div>
          <UiSelect
            v-model="params.status"
            :options="[{ value: '', label: 'Tất cả trạng thái' }, ...toSelectOptions(REVIEW_STATUS)]"
            wrapper-class="w-56"
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
      <UiSkeleton v-else-if="loading" :lines="4" class="p-5" />
      <EmptyState
        v-else-if="rows.length === 0"
        :icon="Inbox"
        title="Không có yêu cầu nào trong hàng đợi"
        body="Thay đổi bộ lọc trạng thái hoặc đợi các đánh giá mới có phát phẩm cần chuyên gia xác nhận."
        class="m-5"
      />
      <template v-else>
        <UiTable :columns="columns" :rows="rows" class="px-5">
          <template #cell-status="{ value }">
            <StatusChip v-bind="statusMeta(REVIEW_STATUS, String(value))" />
          </template>
          <template #cell-created_at="{ value }">
            <span class="tnum text-ink-muted">{{ formatDateTime(value as string | null) }}</span>
          </template>
        </UiTable>
        <Pagination :page="page" :last-page="lastPage" :total="total" class="px-5" @change="page = $event" />
      </template>
    </UiCard>
  </div>
</template>
