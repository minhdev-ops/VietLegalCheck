<script setup lang="ts">
import { computed, ref } from 'vue'
import { BookOpen } from '@lucide/vue'
import PageHeader from '@/components/common/PageHeader.vue'
import UiCard from '@/components/ui/UiCard.vue'
import UiSelect from '@/components/ui/UiSelect.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import Pagination from '@/components/ui/Pagination.vue'
import StatusChip from '@/components/ui/StatusChip.vue'
import UiSkeleton from '@/components/ui/UiSkeleton.vue'
import UiTable from '@/components/ui/UiTable.vue'
import Callout from '@/components/ui/Callout.vue'
import { formatDateTime } from '@/lib/format'
import {
  AUDIT_ACTION,
  statusMeta,
  toSelectOptions,
  usePaginated,
} from './shared'
import type { AuditEntry } from './shared'

const params = ref<{ action: string }>({ action: '' })
const { items, total, page, lastPage, loading, error, load } = usePaginated<AuditEntry>(
  '/admin/audit',
  params,
  20,
)

const columns = [
  { key: 'created_at', label: 'Thời gian' },
  { key: 'actor', label: 'Người thực hiện' },
  { key: 'action', label: 'Hành động' },
  { key: 'resource', label: 'Tài nguyên' },
  { key: 'summary', label: 'Diễn giải' },
  { key: 'ip', label: 'Địa chỉ IP' },
]

const rows = computed(() =>
  items.value.map((entry) => ({
    created_at: entry.created_at,
    actor: entry.actor ?? 'Hệ thống',
    action: entry.action,
    resource: entry.resource_type ? `${entry.resource_type}${entry.resource_id ? ' · ' + entry.resource_id : ''}` : '—',
    summary: entry.summary ?? '—',
    ip: entry.ip ?? '—',
  })),
)
</script>

<template>
  <div>
    <PageHeader
      title="Nhật ký hoạt động"
      subtitle="Truy vết thao tác của người dùng: tạo, cập nhật, duyệt và xác minh trên hệ thống."
    />

    <UiCard padding="none">
      <template #header>
        <UiSelect
          v-model="params.action"
          :options="[{ value: '', label: 'Tất cả hành động' }, ...toSelectOptions(AUDIT_ACTION)]"
          wrapper-class="w-56"
        />
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
      <UiSkeleton v-else-if="loading" :lines="6" class="p-5" />
      <EmptyState
        v-else-if="rows.length === 0"
        :icon="BookOpen"
        title="Chưa có bản ghi nào"
        body="Không có hoạt động nào khớp với bộ lọc hiện tại trong nhật ký hệ thống."
        class="m-5"
      />
      <template v-else>
        <UiTable :columns="columns" :rows="rows" class="px-5">
          <template #cell-created_at="{ value }">
            <span class="tnum text-ink-muted">{{ formatDateTime(value as string | null) }}</span>
          </template>
          <template #cell-action="{ value }">
            <StatusChip v-bind="statusMeta(AUDIT_ACTION, String(value))" />
          </template>
        </UiTable>
        <Pagination :page="page" :last-page="lastPage" :total="total" class="px-5" @change="page = $event" />
      </template>
    </UiCard>
  </div>
</template>
