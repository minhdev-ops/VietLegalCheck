<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { Bell, CheckCheck } from '@lucide/vue'
import PageHeader from '@/components/common/PageHeader.vue'
import UiCard from '@/components/ui/UiCard.vue'
import StatusChip from '@/components/ui/StatusChip.vue'
import UiSkeleton from '@/components/ui/UiSkeleton.vue'
import Callout from '@/components/ui/Callout.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import UiButton from '@/components/ui/UiButton.vue'
import type { ApiError } from '@/lib/api'
import { formatDateTime } from '@/lib/format'
import { useNotificationsStore } from '@/stores/notifications'
import { useToastsStore } from '@/stores/toasts'

type FilterValue = 'all' | 'unread'

const FILTERS: Array<{ value: FilterValue; label: string }> = [
  { value: 'all', label: 'Tất cả' },
  { value: 'unread', label: 'Chưa đọc' },
]

const notifications = useNotificationsStore()
const toasts = useToastsStore()

const filter = ref<FilterValue>('all')
const loading = ref(false)
const error = ref<string | null>(null)
const marking = ref<Record<string, boolean>>({})
const markingAll = ref(false)

const filtered = computed(() =>
  filter.value === 'unread' ? notifications.items.filter((n) => !n.read_at) : notifications.items,
)

async function load(): Promise<void> {
  loading.value = true
  error.value = null
  try {
    await notifications.load()
  } catch (err) {
    error.value = (err as ApiError)?.message ?? 'Chưa tải được thông báo.'
  } finally {
    loading.value = false
  }
}

async function onMarkRead(id: string): Promise<void> {
  if (marking.value[id]) return
  marking.value[id] = true
  try {
    await notifications.markRead(id)
  } catch (err) {
    toasts.push('Chưa đánh dấu được thông báo', (err as ApiError)?.message, 'danger')
  } finally {
    marking.value[id] = false
  }
}

async function onMarkAllRead(): Promise<void> {
  if (markingAll.value || notifications.unread === 0) return
  markingAll.value = true
  try {
    await notifications.markAllRead()
    toasts.push('Đã đánh dấu tất cả là đã đọc', undefined, 'ok')
  } catch (err) {
    toasts.push('Chưa đánh dấu được tất cả thông báo', (err as ApiError)?.message, 'danger')
  } finally {
    markingAll.value = false
  }
}

onMounted(() => {
  void load()
})
</script>

<template>
  <div>
    <PageHeader
      title="Thông báo"
      subtitle="Mỗi đánh giá hoàn thành, nghĩa vụ đến hạn hoặc lời mời tham gia tổ chức đều xuất hiện tại đây."
    >
      <template #actions>
        <UiButton
          variant="secondary"
          :loading="markingAll"
          :disabled="notifications.unread === 0"
          @click="onMarkAllRead"
        >
          <CheckCheck class="size-4" aria-hidden="true" /> Đánh dấu tất cả đã đọc
        </UiButton>
      </template>
    </PageHeader>

    <UiCard padding="none">
      <template #header>
        <div class="flex items-center gap-2">
          <button
            v-for="option in FILTERS"
            :key="option.value"
            type="button"
            class="rounded-pill px-3 py-1 text-[12px] font-semibold uppercase tracking-[0.06em] transition-colors"
            :class="
              filter === option.value
                ? 'bg-accent/10 text-accent'
                : 'text-ink-muted hover:bg-surface-2 hover:text-ink'
            "
            @click="filter = option.value"
          >
            {{ option.label }}
            <span v-if="option.value === 'unread' && notifications.unread > 0" class="tnum">({{ notifications.unread }})</span>
          </button>
        </div>
      </template>

      <Callout
        v-if="error"
        tone="danger"
        icon="warning"
        :title="error"
        action-label="Thử lại"
        class="m-5"
        @action="load()"
      />
      <UiSkeleton v-else-if="loading" :lines="5" class="p-5" />
      <EmptyState
        v-else-if="filtered.length === 0"
        :icon="Bell"
        :title="filter === 'unread' ? 'Không có thông báo chưa đọc' : 'Chưa có thông báo nào'"
        body="Thông báo mới sẽ xuất hiện khi có đánh giá hoàn thành hoặc nghĩa vụ đến hạn."
        class="m-5"
      />
      <ul v-else class="px-5 py-2">
        <li
          v-for="item in filtered"
          :key="item.id"
          class="flex items-start gap-4 border-b border-line py-4 last:border-0"
        >
          <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
              <h3 class="text-[13.5px] font-semibold text-ink">{{ item.title }}</h3>
              <StatusChip v-if="!item.read_at" tone="info" label="Chưa đọc" />
            </div>
            <p class="mt-1 text-[13px] text-ink-muted">{{ item.body }}</p>
            <p class="tnum mt-1.5 text-[11.5px] text-ink-faint">{{ formatDateTime(item.created_at) }}</p>
          </div>
          <UiButton
            v-if="!item.read_at"
            size="sm"
            variant="secondary"
            :loading="marking[item.id]"
            @click="onMarkRead(item.id)"
          >
            Đánh dấu đã đọc
          </UiButton>
        </li>
      </ul>
    </UiCard>
  </div>
</template>
