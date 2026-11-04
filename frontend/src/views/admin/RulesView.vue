<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { Gavel, Search } from '@lucide/vue'
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
import { RULE_STATUS, statusMeta, usePaginated } from './shared'
import type { Rule } from './shared'

const router = useRouter()

const params = ref<{ q: string }>({ q: '' })
const { items, total, page, lastPage, loading, error, load } = usePaginated<Rule>('/admin/rules', params)

const columns = [
  { key: 'code', label: 'Mã quy tắc' },
  { key: 'title', label: 'Tên quy tắc' },
  { key: 'version', label: 'Phiên bản', numeric: true },
  { key: 'status', label: 'Trạng thái phiên bản' },
  { key: 'failing_tests', label: 'Ca kiểm thử lỗi', numeric: true },
  { key: 'updated_at', label: 'Cập nhật' },
]

const rows = computed(() =>
  items.value.map((rule) => ({
    id: rule.id,
    code: rule.code,
    title: rule.title,
    version: `v${rule.current_version}`,
    status: rule.status,
    failing_tests: rule.failing_tests,
    total_tests: rule.total_tests,
    updated_at: rule.updated_at,
  })),
)

function open(row: Record<string, unknown>): void {
  router.push({ name: 'admin-rule', params: { id: String(row.id) } })
}
</script>

<template>
  <div>
    <PageHeader
      title="Bộ quy tắc"
      subtitle="Quản lý phiên bản quy tắc nghiệp vụ: duyệt bản nháp, chạy ca kiểm thử và áp dụng."
    />

    <UiCard padding="none">
      <template #header>
        <div class="flex items-center gap-2">
          <Search class="size-4 shrink-0 text-ink-faint" aria-hidden="true" />
          <UiInput v-model="params.q" wrapper-class="max-w-sm" placeholder="Tìm theo mã hoặc tên quy tắc…" />
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
        :icon="Gavel"
        title="Chưa có quy tắc nào"
        body="Quy tắc đánh giá xuất hiện ở đây sau khi được tạo cho lĩnh vực nghiệp vụ."
        class="m-5"
      />
      <template v-else>
        <UiTable :columns="columns" :rows="rows" :on-row-click="open" class="px-5">
          <template #cell-status="{ value }">
            <StatusChip v-bind="statusMeta(RULE_STATUS, String(value))" />
          </template>
          <template #cell-failing_tests="{ row }">
            <span v-if="Number(row.failing_tests) > 0" class="tnum font-semibold text-seal">
              {{ row.failing_tests }} / {{ row.total_tests }} lỗi
            </span>
            <span v-else class="tnum text-ink-muted">{{ row.total_tests }} ca</span>
          </template>
          <template #cell-updated_at="{ value }">
            <span class="tnum text-ink-muted">{{ formatDate(value as string) }}</span>
          </template>
        </UiTable>
        <Pagination :page="page" :last-page="lastPage" :total="total" class="px-5" @change="page = $event" />
      </template>
    </UiCard>
  </div>
</template>
