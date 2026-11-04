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

const token = ref('')
const email = ref('')
const password = ref('')
const passwordConfirmation = ref('')
const loading = ref(false)
const error = ref<string | null>(null)

const FIELD_CLASSES =
  'h-9 w-full rounded-md border border-line-strong bg-surface px-3 text-sm text-ink placeholder:text-ink-faint focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/20'

function validate(): boolean {
  error.value = null
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim())) error.value = 'Email không hợp lệ.'
  else if (password.value.length < 10) error.value = 'Mật khẩu cần ít nhất 10 ký tự.'
  else if (password.value !== passwordConfirmation.value) error.value = 'Xác nhận mật khẩu không khớp.'
  return error.value === null
}

async function onSubmit(): Promise<void> {
  if (!validate()) return
  loading.value = true
  try {
    await api.post('/auth/reset-password', {
      token: token.value,
      email: email.value.trim(),
      password: password.value,
      password_confirmation: passwordConfirmation.value,
    })
    toasts.push('Đặt lại mật khẩu thành công', 'Dùng mật khẩu mới để đăng nhập.', 'ok')
    await router.replace({ name: 'login' })
  } catch (err) {
    toasts.push('Chưa đặt lại được mật khẩu', (err as ApiError).message ?? 'Liên kết có thể đã hết hạn.', 'danger')
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  token.value = typeof route.query.token === 'string' ? route.query.token : ''
  email.value = typeof route.query.email === 'string' ? route.query.email : ''
})
</script>

<template>
  <PublicShell>
    <div class="mx-auto flex max-w-[28rem] flex-col px-4 py-16 sm:px-6">
      <h1 class="text-balance font-serif text-[clamp(] font-semibold leading-[1.2] tracking-[-0.02em] text-ink">
        Đặt lại mật khẩu
      </h1>
      <p class="mt-2 text-[13.5px] text-ink-muted">
        Dùng liên kết từ email để tạo mật khẩu mới cho tài khoản.
      </p>

      <form class="mt-10 flex flex-col gap-5" @submit.prevent="onSubmit">
        <UiInput v-model="email" label="Email" placeholder="ban@congty.vn" required autocomplete="email" />
        <div class="flex flex-col gap-1.5">
          <label for="password" class="text-[13px] font-medium text-ink required">Mật khẩu mới</label>
          <input
            id="password"
            v-model="password"
            type="password"
            :class="FIELD_CLASSES"
            placeholder="Ít nhất 10 ký tự"
            required
            autocomplete="new-password"
          />
        </div>
        <div class="flex flex-col gap-1.5">
          <label for="password-confirmation" class="text-[13px] font-medium text-ink required">
            Xác nhận mật khẩu
          </label>
          <input
            id="password-confirmation"
            v-model="passwordConfirmation"
            type="password"
            :class="FIELD_CLASSES"
            required
            autocomplete="new-password"
          />
        </div>
        <Callout v-if="error" tone="caution" icon="warning" title="Kiểm tra lại thông tin">
          {{ error }}
        </Callout>
        <UiButton type="submit" :loading="loading">Đặt lại mật khẩu</UiButton>
      </form>

      <p class="mt-8 text-[13px] text-ink-muted">
        Chưa có liên kết?
        <RouterLink to="/quen-mat-khau" class="font-medium text-accent hover:underline underline-offset-2">
          Yêu cầu lại email đặt lại mật khẩu
        </RouterLink>
      </p>
    </div>
  </PublicShell>
</template>

<style scoped>
label.required::after {
  content: ' *';
  color: var(--seal);
}
</style>
