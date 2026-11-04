<script setup lang="ts">
import { ChevronDown } from '@lucide/vue'
import { RouterLink } from 'vue-router'
import PublicShell from './PublicShell.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import Disclaimer from '@/components/common/Disclaimer.vue'

const STEPS = [
  {
    title: 'Tạo tài khoản và dự án',
    body: 'Đăng ký bằng email, tạo dự án cho website hoặc ứng dụng cần kiểm tra. Mỗi dự án lưu riêng kết quả và hồ sơ.',
  },
  {
    title: 'Khai báo sản phẩm',
    body: 'Trả lời câu hỏi về loại hình bán hàng, thanh toán, dữ liệu cá nhân… Hệ thống gắn thẻ hoạt động như ECOM_SELLING, PAYMENT, PERSONAL_DATA kèm lý do.',
  },
  {
    title: 'Xem nghĩa vụ có khả năng áp dụng',
    body: 'Mỗi nghĩa vụ có căn cứ điều khoản (chip Điều/Khoản), hiệu lực tại ngày kiểm tra và vết giải thích True/False/Unknown từ bộ quy tắc.',
  },
  {
    title: 'Quét bằng chứng công khai',
    body: 'Quét các trang public của website. Mỗi bằng chứng ghi URL, thời điểm và trích đoạn — không kết luận chỉ vì có hoặc không có một trang.',
  },
  {
    title: 'Chuẩn bị hồ sơ và theo dõi',
    body: 'Đọc hướng dẫn thủ tục hành chính, tải checklist hồ sơ và theo dõi thay đổi văn bản pháp luật liên quan đến dự án.',
  },
] as const

const READING = [
  { label: 'Có khả năng áp dụng', tone: 'text-caution', note: 'văn bản có hiệu lực và dấu hiệu khớp với hoạt động của bạn' },
  { label: 'Chưa tìm thấy bằng chứng', tone: 'text-ink-muted', note: 'quét chưa thấy dấu hiệu công khai, cần xác minh thêm' },
  { label: 'Cần chuyên gia xác nhận', tone: 'text-expert', note: 'trường hợp ngoặt, nên trao đổi với chuyên gia pháp lý' },
] as const
</script>

<template>
  <PublicShell>
    <div class="mx-auto max-w-[1240px] px-4 py-10 sm:px-6">
      <PageHeader
        title="Hướng dẫn sử dụng"
        subtitle="Năm bước từ khai báo sản phẩm đến hồ sơ thủ tục hành chính."
      />

      <section class="border-t border-line pt-8">
        <h2 class="text-[11px] font-semibold uppercase tracking-[0.06em] text-ink-muted">
          Sơ đồ quy trình
        </h2>
        <div class="mt-5 flex flex-col items-stretch lg:flex-row lg:items-center">
          <template v-for="(step, index) in STEPS" :key="step.title">
            <div class="min-w-0 flex-1 rounded-md border border-line bg-surface-2 p-4">
              <span class="tnum text-[12px] font-bold text-accent">Bước {{ index + 1 }}</span>
              <p class="mt-1.5 text-[13.5px] font-medium leading-snug text-ink">{{ step.title }}</p>
            </div>
            <ChevronDown
              v-if="index < STEPS.length - 1"
              class="my-1 size-4 shrink-0 self-center text-ink-faint lg:my-0 lg:mx-1"
              aria-hidden="true"
            />
          </template>
        </div>
      </section>

      <section class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        <div
          v-for="(step, index) in STEPS"
          :key="step.title"
          class="flex flex-col rounded-lg border border-line bg-surface p-6"
        >
          <span
            class="tnum flex size-8 items-center justify-center rounded-md bg-surface-2 text-[13px] font-bold text-ink"
            >{{ index + 1 }}</span
          >
          <h3 class="mt-5 text-[15px] font-semibold leading-snug text-ink">{{ step.title }}</h3>
          <p class="mt-2 text-[13.5px] leading-relaxed text-ink-muted">{{ step.body }}</p>
        </div>
      </section>

      <section class="mt-12 grid gap-10 lg:grid-cols-[1fr_248px]">
        <div>
          <h2 class="text-[1.125rem] font-semibold leading-snug tracking-[-0.01em] text-ink">
            Cách đọc trạng thái nghĩa vụ
          </h2>
          <ul class="mt-6 flex flex-col gap-5">
            <li v-for="item in READING" :key="item.label" class="flex flex-col gap-1">
              <span class="text-[14px] font-semibold" :class="item.tone">{{ item.label }}</span>
              <span class="measure text-[13px] text-ink-muted">{{ item.note }}</span>
            </li>
          </ul>
        </div>
        <aside class="rounded-lg border border-line bg-surface-2 p-5">
          <h3 class="text-[13px] font-semibold text-ink">Sẵn sàng bắt đầu?</h3>
          <p class="mt-2 text-[12.5px] leading-relaxed text-ink-muted">
            Tài khoản mới có thể khai báo dự án đầu tiên ngay.
          </p>
          <RouterLink
            to="/dang-ky"
            class="mt-4 inline-flex h-9 items-center rounded-md bg-accent px-4 text-sm font-medium text-white transition-colors hover:bg-accent-strong"
          >
            Tạo tài khoản
          </RouterLink>
        </aside>
      </section>

      <div class="mt-12">
        <Disclaimer />
      </div>
    </div>
  </PublicShell>
</template>
