<script setup lang="ts">
import { ref } from 'vue'
import {
  BookOpen,
  ClipboardList,
  FileText,
  Gavel,
  Inbox,
  Scale,
  Settings,
  ShieldCheck,
  Users,
} from '@lucide/vue'
import PageHeader from '@/components/common/PageHeader.vue'
import UiCard from '@/components/ui/UiCard.vue'
import UiSkeleton from '@/components/ui/UiSkeleton.vue'
import Callout from '@/components/ui/Callout.vue'
import { api, type ApiError } from '@/lib/api'
import type { DashboardStats } from './shared'

const stats = ref<DashboardStats | null>(null)
const loading = ref(false)
const error = ref<ApiError | null>(null)

async function load(): Promise<void> {
  loading.value = true
  error.value = null
  try {
    stats.value = await api.get<DashboardStats>('/admin/dashboard')
  } catch (e) {
    error.value = e as ApiError
  } finally {
    loading.value = false
  }
}
load()

const STAT_CARDS = [
  { key: 'pending_rules', label: 'Quy tắc chờ duyệt', to: '/admin/quy-tac', icon: Scale },
  { key: 'unverified_documents', label: 'Văn bản chưa kiểm chứng', to: '/admin/van-ban', icon: FileText },
  { key: 'open_expert_reviews', label: 'Đánh giá chuyên gia đang mở', to: '/admin/chuyen-gia', icon: ShieldCheck },
] as const

const QUICK_LINKS = [
  { label: 'Văn bản pháp luật', body: 'Số hiệu, trích yếu, điều khoản và trạng thái hiệu lực.', to: '/admin/van-ban', icon: FileText },
  { label: 'Bộ quy tắc', body: 'Phiên bản quy tắc, duyệt và ca kiểm thử.', to: '/admin/quy-tac', icon: Gavel },
  { label: 'Thủ tục hành chính', body: 'Quy trình, cơ quan tiếp nhận và kết quả xử lý.', to: '/admin/thu-tuc', icon: ClipboardList },
  { label: 'Hàng đợi chuyên gia', body: 'Yêu cầu cần chuyên gia pháp lý xác nhận.', to: '/admin/chuyen-gia', icon: Inbox },
  { label: 'Nhật ký hoạt động', body: 'Truy vết thao tác của người dùng trên hệ thống.', to: '/admin/nhat-ky', icon: BookOpen },
  { label: 'Người dùng & tổ chức', body: 'Tài khoản và vai trò hệ thống.', to: '/admin/nguoi-dung', icon: Users },
  { label: 'Cấu hình hệ thống', body: 'Kho cấu hình key–value phục vụ vận hành.', to: '/admin/cau-hinh', icon: Settings },
] as const
</script>

<template>
  <div>
    <PageHeader
      title="Tổng quan quản trị"
      subtitle="Các đầu việc cần юрист/admin xử lý: quy tắc chờ duyệt, văn bản chưa kiểm chứng, hàng đợi chuyên gia."
    />

    <Callout
      v-if="error"
      tone="danger"
      icon="warning"
      :title="error.message"
      action-label="Tải lại"
      @action="load()"
    />

    <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-3">
      <template v-if="loading">
        <UiCard v-for="card in STAT_CARDS" :key="card.key">
          <UiSkeleton :lines="2" />
        </UiCard>
      </template>
      <RouterLink v-else v-for="card in STAT_CARDS" :key="card.key" :to="card.to">
        <UiCard class="transition-colors hover:bg-surface-2">
          <div class="flex items-center gap-3 text-ink-muted">
            <component :is="card.icon" class="size-4" aria-hidden="true" />
            <span class="text-[13px] font-medium">{{ card.label }}</span>
          </div>
          <p class="tnum mt-3 text-[2rem] font-semibold leading-none tracking-[-0.02em] text-ink">
            {{ stats ? (stats[card.key] ?? 0) : 0 }}
          </p>
          <p class="mt-3 text-[12.5px] font-medium text-accent">Xem danh sách</p>
        </UiCard>
      </RouterLink>
    </div>

    <h2 class="mt-10 text-[15px] font-semibold tracking-[-0.005em] text-ink">Link nhanh</h2>
    <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <RouterLink
        v-for="link in QUICK_LINKS"
        :key="link.to"
        :to="link.to"
        class="rounded-lg border border-line bg-surface p-4 transition-colors hover:bg-surface-2"
      >
        <div class="flex items-center gap-2.5 text-ink">
          <component :is="link.icon" class="size-4 shrink-0 text-accent" aria-hidden="true" />
          <span class="text-[14px] font-semibold">{{ link.label }}</span>
        </div>
        <p class="mt-1.5 text-[13px] text-ink-muted">{{ link.body }}</p>
      </RouterLink>
    </div>
  </div>
</template>
