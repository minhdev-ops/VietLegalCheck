<script setup lang="ts">
import { RouterLink } from 'vue-router'
import { ArrowRight, FileSearch, ListChecks, FolderCog } from '@lucide/vue'
import PublicShell from './PublicShell.vue'
import Disclaimer from '@/components/common/Disclaimer.vue'

const STEPS = [
  {
    icon: FolderCog,
    title: 'Khai báo sản phẩm',
    body: 'Mô tả website hoặc ứng dụng của bạn: loại hình, cách thanh toán, dữ liệu cá nhân thu thập. Hệ thống gắn thẻ hoạt động tương ứng.',
  },
  {
    icon: ListChecks,
    title: 'Xem nghĩa vụ có khả năng áp dụng',
    body: 'Mỗi nghĩa vụ kèm căn cứ điều khoản pháp luật, hiệu lực tại thời điểm kiểm tra và vết giải thích True/False/Unknown.',
  },
  {
    icon: FileSearch,
    title: 'Chuẩn bị hồ sơ và theo dõi',
    body: 'Đọc hướng dẫn thủ tục hành chính, thu thập bằng chứng công khai và theo dõi thay đổi văn bản pháp luật.',
  },
] as const
</script>

<template>
  <PublicShell>
    <section class="border-b border-line bg-surface">
      <div class="mx-auto max-w-[1240px] px-4 py-16 sm:px-6 sm:py-24">
        <h1 class="mt-4 max-w-[46rem] text-balance font-serif text-[clamp(2rem,4vw,3rem)] font-semibold leading-[1.15] tracking-[-0.02em] text-ink">
          Kiểm tra nghĩa vụ pháp lý có khả năng áp dụng cho website của bạn
        </h1>
        <p class="measure mt-6 text-[15px] leading-relaxed text-ink-muted">
          VietLegal Check soi nghĩa vụ theo bộ quy tắc pháp lý Việt Nam, trích dẫn điều khoản
          văn bản gốc và hiệu lực tại thời điểm bạn kiểm tra — không kết luận thay chuyên gia.
        </p>
        <div class="mt-10 flex flex-wrap items-center gap-3">
          <RouterLink
            to="/dang-ky"
            class="inline-flex h-9 items-center gap-2 rounded-md bg-accent px-5 text-sm font-medium text-white transition-colors hover:bg-accent-strong"
          >
            Tạo tài khoản <ArrowRight class="size-4" aria-hidden="true" />
          </RouterLink>
          <RouterLink
            to="/dang-nhap"
            class="inline-flex h-9 items-center rounded-md border border-line-strong bg-surface px-5 text-sm font-medium text-ink transition-colors hover:bg-surface-2"
          >
            Đăng nhập
          </RouterLink>
          <RouterLink
            to="/van-ban"
            class="inline-flex h-9 items-center rounded-md px-3 text-sm font-medium text-accent hover:underline underline-offset-2"
          >
            Tra cứu văn bản pháp luật
          </RouterLink>
        </div>
      </div>
    </section>

    <section class="mx-auto max-w-[1240px] px-4 py-16 sm:px-6">
      <h2 class="text-[1.125rem] text-balance font-semibold leading-snug tracking-[-0.01em] text-ink">
        Ba bước để có danh sách nghĩa vụ
      </h2>
      <div class="mt-8 grid gap-6 sm:grid-cols-3">
        <div
          v-for="(step, index) in STEPS"
          :key="step.title"
          class="flex flex-col rounded-lg border border-line bg-surface p-6"
        >
          <div class="flex items-center gap-3">
            <span
              class="tnum flex size-8 items-center justify-center rounded-md bg-surface-2 text-[13px] font-bold text-ink"
              >{{ index + 1 }}</span
            >
            <component :is="step.icon" class="size-5 text-ink-muted" aria-hidden="true" />
          </div>
          <h3 class="mt-5 text-[15px] font-semibold leading-snug text-ink">{{ step.title }}</h3>
          <p class="mt-2 text-[13.5px] leading-relaxed text-ink-muted">{{ step.body }}</p>
        </div>
      </div>

      <div class="mt-12">
        <Disclaimer />
      </div>
    </section>
  </PublicShell>
</template>
