<script setup lang="ts">
import { RouterView, RouterLink } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()

const NAV = [
  { label: 'Tổng quan', to: '/admin' },
  { label: 'Văn bản pháp luật', to: '/admin/van-ban' },
  { label: 'Bộ quy tắc', to: '/admin/quy-tac' },
  { label: 'Thủ tục hành chính', to: '/admin/thu-tuc' },
  { label: 'Hàng đợi chuyên gia', to: '/admin/chuyen-gia' },
  { label: 'Nhật ký hoạt động', to: '/admin/nhat-ky' },
  { label: 'Người dùng & tổ chức', to: '/admin/nguoi-dung' },
  { label: 'Cấu hình hệ thống', to: '/admin/cau-hinh' },
] as const
</script>

<template>
  <div class="flex min-h-dvh bg-paper">
    <aside class="sticky top-0 flex h-dvh w-[248px] shrink-0 flex-col border-r border-line bg-surface-2">
      <div class="flex h-16 items-center gap-2.5 border-b border-line px-5">
        <span class="flex size-8 items-center justify-center rounded-md bg-seal/10 text-seal">
          <span class="text-[15px] font-bold">§</span>
        </span>
        <div class="leading-tight">
          <p class="text-[14px] font-semibold tracking-[-0.01em] text-ink">Quản trị pháp lý</p>
          <p class="text-[10.5px] font-medium uppercase tracking-[0.08em] text-ink-faint">{{ auth.user?.email }}</p>
        </div>
      </div>
      <nav class="flex flex-col gap-0.5 p-3">
        <RouterLink
          v-for="item in NAV"
          :key="item.to"
          :to="item.to"
          class="rounded-md px-3 py-2 text-[13.5px] font-medium text-ink-muted transition-colors hover:bg-surface-3 hover:text-ink"
          active-class="!bg-accent/8 !text-accent"
        >
          {{ item.label }}
        </RouterLink>
      </nav>
      <div class="mt-auto border-t border-line p-3">
        <RouterLink to="/app" class="block rounded-md px-3 py-2 text-[13.5px] font-medium text-ink-muted hover:bg-surface-3 hover:text-ink">
          ← Về không gian làm việc
        </RouterLink>
      </div>
    </aside>
    <main class="mx-auto w-full max-w-[1240px] flex-1 px-4 py-6 sm:px-6 lg:py-8">
      <RouterView />
    </main>
  </div>
</template>
