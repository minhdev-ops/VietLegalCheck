<script setup lang="ts">
</script>
<template>
  <div class="min-h-dvh bg-paper">
    <header class="border-b border-line bg-surface/80" style="backdrop-filter: blur(12px)">
      <div class="mx-auto flex h-16 max-w-[1240px] items-center justify-between gap-6 px-4 sm:px-6">
        <RouterLink to="/" class="flex items-center gap-2.5">
          <span class="flex size-8 items-center justify-center rounded-md bg-seal/10 text-seal">
            <span class="text-[15px] font-bold">§</span>
          </span>
          <span class="text-[15px] font-semibold tracking-[-0.01em] text-ink">VietLegal Check</span>
        </RouterLink>
        <nav class="flex items-center gap-1 text-[13.5px] font-medium text-ink-muted">
          <RouterLink to="/huong-dan" class="rounded-md px-3 py-2 hover:bg-surface-2 hover:text-ink">Hướng dẫn</RouterLink>
          <RouterLink to="/van-ban" class="rounded-md px-3 py-2 hover:bg-surface-2 hover:text-ink">Tra cứu văn bản</RouterLink>
          <RouterLink
            to="/dang-nhap"
            class="ml-2 rounded-md bg-accent px-4 py-2 text-white transition-colors hover:bg-accent-strong"
          >
            Đăng nhập
          </RouterLink>
        </nav>
      </div>
    </header>
    <main>
      <slot />
    </main>
    <footer class="border-t border-line bg-surface-2">
      <div class="mx-auto max-w-[1240px] px-4 py-8 text-[12.5px] text-ink-muted sm:px-6">
        <p class="measure">
          VietLegal Check là công cụ hỗ trợ đánh giá nghĩa vụ pháp lý, không cấp phép, không chứng nhận
          và không thay thế ý kiến tư vấn pháp lý chuyên nghiệp.
        </p>
      </div>
    </footer>
  </div>
</template>
