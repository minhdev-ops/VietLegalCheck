<script setup lang="ts">
import { RouterLink } from 'vue-router'
import PublicShell from './PublicShell.vue'
import UiButton from '@/components/ui/UiButton.vue'

const LINKS = [
  { to: '/huong-dan', label: 'Hướng dẫn sử dụng' },
  { to: '/van-ban', label: 'Tra cứu văn bản pháp luật' },
  { to: '/dang-nhap', label: 'Đăng nhập' },
] as const
</script>

<template>
  <PublicShell>
    <div class="mx-auto flex max-w-[34rem] flex-col px-4 py-24 text-center sm:px-6">
      <p class="tnum text-balance font-serif text-[clamp(] font-semibold leading-none tracking-[-0.02em] text-ink">
        404
      </p>
      <h1 class="mt-6 text-[1.125rem] font-semibold leading-snug tracking-[-0.01em] text-ink">
        Không tìm thấy trang
      </h1>
      <p class="mx-auto mt-3 measure text-[13.5px] leading-relaxed text-ink-muted">
        Địa chỉ không tồn tại hoặc đã bị di chuyển. Quay lại trang chủ hoặc tiếp tục với các trang công khai.
      </p>
      <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
        <RouterLink to="/">
          <UiButton variant="primary">Về trang chủ</UiButton>
        </RouterLink>
        <RouterLink
          v-for="link in LINKS"
          :key="link.to"
          :to="link.to"
          class="inline-flex h-9 items-center rounded-md border border-line-strong bg-surface px-4 text-sm font-medium text-ink transition-colors hover:bg-surface-2"
        >
          {{ link.label }}
        </RouterLink>
      </div>
    </div>
  </PublicShell>
</template>
