<script setup lang="ts">
import { computed, ref } from 'vue'
import { Search, Users } from '@lucide/vue'
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
import { ROLE_STATUS, statusMeta, usePaginated } from './shared'
import type { AdminUser } from './shared'

const params = ref<{ q: string }>({ q: '' })
const { items, total, page, lastPage, loading, error, load } = usePaginated<AdminUser>(
  '/admin/users',
  params,
)

const columns = [
  { key: 'name', label: 'Họ tên' },
  { key: 'email', label: 'Email' },
  { key: 'system_role', label: 'Vai trò hệ thống' },
  { key: 'email_verified', label: 'Xác thực email' },
  { key: 'organization', label: 'Tổ chức' },
  { key: 'created_at', label: 'Ngày tạo' },
]

const rows = computed(() =>
  items.value.map((user) => ({
    name: user.name,
    email: user.email,
    system_role: user.system_role,
    email_verified: user.email_verified_at,
    organization: user.organization ?? '—',
    created_at: user.created_at,
  })),
)
</script>

<template>
  <div>
    <PageHeader
      title="Người dùng & tổ chức"
      subtitle="Tài khoản truy cập hệ thống cùng vai trò: người dùng, chuyên gia pháp lý, quản trị."
    />

    <UiCard padding="none">
      <template #header>
        <div class="flex items-center gap-2">
          <Search class="size-4 shrink-0 text-ink-faint" aria-hidden="true" />
          <UiInput v-model="params.q" wrapper-class="max-w-sm" placeholder="Tìm theo họ tên hoặc email…" />
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
      <UiSkeleton v-else-if="loading" :lines="5" class="p-5" />
      <EmptyState
        v-else-if="rows.length === 0"
        :icon="Users"
        title="Chưa có người dùng nào"
        body="Tài khoản sẽ xuất hiện ở đây sau khi đăng ký hoặc được quản trị tạo."
        class="m-5"
      />
      <template v-else>
        <UiTable :columns="columns" :rows="rows" class="px-5">
          <template #cell-system_role="{ value }">
            <StatusChip v-bind="statusMeta(ROLE_STATUS, String(value))" />
          </template>
          <template #cell-email_verified="{ value }">
            <StatusChip v-if="value" tone="ok" label="Đã xác thực" />
            <StatusChip v-else tone="caution" label="Chưa xác thực" />
          </template>
          <template #cell-created_at="{ value }">
            <span class="tnum text-ink-muted">{{ formatDate(value as string | null) }}</span>
          </template>
        </UiTable>
        <Pagination :page="page" :last-page="lastPage" :total="total" class="px-5" @change="page = $event" />
      </template>
    </UiCard>
  </div>
</template>
