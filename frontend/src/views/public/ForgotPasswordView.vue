<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink } from 'vue-router'
import PublicShell from './PublicShell.vue'
import UiInput from '@/components/ui/UiInput.vue'
import UiButton from '@/components/ui/UiButton.vue'
import Callout from '@/components/ui/Callout.vue'
import { api, type ApiError } from '@/lib/api'
import { useToastsStore } from '@/stores/toasts'

const toasts = useToastsStore()

const email = ref('')
const loading = ref(false)
const sent = ref(false)

async function onSubmit(): Promise<void> {
  loading.value = true
  try {
    await api.post('/auth/forgot-password', { email: email.value.trim() })
    sent.value = true
  } catch (err) {
    toasts.push('Chưa gửi được yêu cầu', (err as ApiError).message ?? 'Thử lại sau ít phút.', 'danger')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <PublicShell>
    <div class="mx-auto flex max-w-[28rem] flex-col px-4 py-16 sm:px-6">
      <h1 class="text-balance font-serif text-[clamp(] font-semibold leading-[1.2] tracking-[-0.02em] text-ink">
        Quên mật khẩu
      </h1>
      <p class="mt-2 text-[13.5px] text-ink-muted">
        Nhập email đăng ký. Chúng tôi gửi liên kết đặt lại mật khẩu đến hộp thư của bạn.
      </p>

      <Callout v-if="sent" tone="ok" title="Đã gửi yêu cầu đặt lại mật khẩu" class="mt-10">
        Nếu email đã đăng ký trong hệ thống, liên kết đặt lại mật khẩu đang được gửi. Hộp thư có thể
        mất vài phút — kiểm tra cả thư mục spam.
      </Callout>

      <form v-else class="mt-10 flex flex-col gap-5" @submit.prevent="onSubmit">
        <UiInput v-model="email" label="Email" placeholder="ban@congty.vn" required autocomplete="email" />
        <UiButton type="submit" :loading="loading">Gửi liên kết đặt lại mật khẩu</UiButton>
      </form>

      <p class="mt-8 text-[13px] text-ink-muted">
        Nhớ lại mật khẩu?
        <RouterLink to="/dang-nhap" class="font-medium text-accent hover:underline underline-offset-2">
          Đăng nhập
        </RouterLink>
      </p>
    </div>
  </PublicShell>
</template>
