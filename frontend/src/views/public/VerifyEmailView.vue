<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import PublicShell from './PublicShell.vue'
import UiInput from '@/components/ui/UiInput.vue'
import UiButton from '@/components/ui/UiButton.vue'
import Callout from '@/components/ui/Callout.vue'
import { api, type ApiError } from '@/lib/api'
import { useToastsStore } from '@/stores/toasts'

const route = useRoute()
const router = useRouter()
const toasts = useToastsStore()

const email = ref('')
const code = ref('')
const loading = ref(false)
const verified = ref(false)

async function onSubmit(): Promise<void> {
  loading.value = true
  try {
    await api.post('/auth/verify-email', { email: email.value.trim(), token: code.value.trim() })
    verified.value = true
    toasts.push('Đã xác thực email', 'Tài khoản sẵn sàng sử dụng.', 'ok')
    await router.replace({ name: 'login' })
  } catch (err) {
    toasts.push('Chưa xác thực được email', (err as ApiError).message ?? 'Kiểm tra mã và thử lại.', 'danger')
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  email.value = typeof route.query.email === 'string' ? route.query.email : ''
  code.value = typeof route.query.token === 'string' ? route.query.token : ''
})
</script>

<template>
  <PublicShell>
    <div class="mx-auto flex max-w-[28rem] flex-col px-4 py-16 sm:px-6">
      <h1 class="text-balance font-serif text-[clamp(] font-semibold leading-[1.2] tracking-[-0.02em] text-ink">
        Xác thực email
      </h1>
      <p class="mt-2 text-[13.5px] text-ink-muted">
        Nhập mã xác thực trong email chúng tôi gửi sau khi đăng ký.
      </p>

      <Callout v-if="verified" tone="ok" title="Email đã được xác thực" class="mt-10">
        Đăng nhập để bắt đầu khai báo dự án đầu tiên.
      </Callout>

      <form v-else class="mt-10 flex flex-col gap-5" @submit.prevent="onSubmit">
        <UiInput v-model="email" label="Email" placeholder="ban@congty.vn" required autocomplete="email" />
        <UiInput v-model="code" label="Mã xác thực" placeholder="Mã trong email" required />
        <UiButton type="submit" :loading="loading">Xác thực email</UiButton>
      </form>

      <p class="mt-8 text-[13px] text-ink-muted">
        Đã xác thực rồi?
        <RouterLink to="/dang-nhap" class="font-medium text-accent hover:underline underline-offset-2">
          Đăng nhập
        </RouterLink>
      </p>
    </div>
  </PublicShell>
</template>
