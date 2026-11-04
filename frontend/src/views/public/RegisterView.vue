<script setup lang="ts">
import { ref } from 'vue'
import { useRouter, RouterLink } from 'vue-router'
import PublicShell from './PublicShell.vue'
import UiInput from '@/components/ui/UiInput.vue'
import UiButton from '@/components/ui/UiButton.vue'
import CheckOption from '@/components/ui/CheckOption.vue'
import { useAuthStore } from '@/stores/auth'
import { useToastsStore } from '@/stores/toasts'
import type { ApiError } from '@/lib/api'

const router = useRouter()
const auth = useAuthStore()
const toasts = useToastsStore()

const name = ref('')
const email = ref('')
const password = ref('')
const passwordConfirmation = ref('')
const terms = ref(false)

const loading = ref(false)
const errors = ref<Record<string, string>>({})

const FIELD_CLASSES =
  'h-9 w-full rounded-md border border-line-strong bg-surface px-3 text-sm text-ink placeholder:text-ink-faint focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/20'

function validate(): boolean {
  errors.value = {}
  if (name.value.trim().length < 2) errors.value.name = 'Vui lòng nhập họ và tên.'
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim())) errors.value.email = 'Email không hợp lệ.'
  if (password.value.length < 10) errors.value.password = 'Mật khẩu cần ít nhất 10 ký tự.'
  if (passwordConfirmation.value !== password.value) {
    errors.value.password_confirmation = 'Xác nhận mật khẩu không khớp.'
  }
  if (!terms.value) errors.value.terms = 'Vui lòng đồng ý với điều khoản sử dụng.'
  return Object.keys(errors.value).length === 0
}

async function onSubmit(): Promise<void> {
  if (!validate()) return
  loading.value = true
  try {
    await auth.register({
      name: name.value.trim(),
      email: email.value.trim(),
      password: password.value,
      password_confirmation: passwordConfirmation.value,
      consent: terms.value,
    })
    toasts.push('Đã tạo tài khoản', 'Kiểm tra email để xác thực tài khoản.', 'ok')
    await router.replace({ name: 'verify-email', query: { email: email.value.trim() } })
  } catch (err) {
    const apiError = err as ApiError
    const details = apiError.details ?? {}
    for (const key of ['name', 'email', 'password', 'password_confirmation', 'terms']) {
      const messages = details[key]
      if (Array.isArray(messages) && typeof messages[0] === 'string') errors.value[key] = messages[0]
    }
    if (!Object.keys(errors.value).length) {
      toasts.push('Đăng ký không thành công', apiError.message, 'danger')
    }
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <PublicShell>
    <div class="mx-auto flex max-w-[28rem] flex-col px-4 py-16 sm:px-6">
      <h1 class="text-balance font-serif text-[clamp(] font-semibold leading-[1.2] tracking-[-0.02em] text-ink">
        Đăng ký tài khoản
      </h1>
      <p class="mt-2 text-[13.5px] text-ink-muted">
        Tài khoản cá nhân để khai báo dự án và tra cứu nghĩa vụ pháp lý.
      </p>

      <form class="mt-10 flex flex-col gap-5" @submit.prevent="onSubmit">
        <UiInput v-model="name" label="Họ và tên" :error="errors.name" required autocomplete="name" />
        <UiInput
          v-model="email"
          label="Email"
          :error="errors.email"
          placeholder="ban@congty.vn"
          required
          autocomplete="email"
        />
        <div class="flex flex-col gap-1.5">
          <label for="password" class="text-[13px] font-medium text-ink required">Mật khẩu</label>
          <input
            id="password"
            v-model="password"
            type="password"
            :class="FIELD_CLASSES"
            :aria-invalid="errors.password ? 'true' : undefined"
            placeholder="Ít nhất 10 ký tự"
            required
            autocomplete="new-password"
          />
          <p v-if="errors.password" class="text-xs text-seal" role="alert">{{ errors.password }}</p>
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
            :aria-invalid="errors.password_confirmation ? 'true' : undefined"
            required
            autocomplete="new-password"
          />
          <p v-if="errors.password_confirmation" class="text-xs text-seal" role="alert">
            {{ errors.password_confirmation }}
          </p>
        </div>
        <div>
          <CheckOption v-model="terms" label="Tôi đồng ý với điều khoản sử dụng và tuyên bố giới hạn trách nhiệm." required />
          <p v-if="errors.terms" class="mt-1.5 text-xs text-seal" role="alert">{{ errors.terms }}</p>
        </div>
        <UiButton type="submit" :loading="loading">Tạo tài khoản</UiButton>
      </form>

      <p class="mt-8 text-[13px] text-ink-muted">
        Đã có tài khoản?
        <RouterLink to="/dang-nhap" class="font-medium text-accent hover:underline underline-offset-2">Đăng nhập</RouterLink>
      </p>
    </div>
  </PublicShell>
</template>
