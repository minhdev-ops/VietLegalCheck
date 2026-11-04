<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import PublicShell from './PublicShell.vue'
import UiInput from '@/components/ui/UiInput.vue'
import UiButton from '@/components/ui/UiButton.vue'
import Callout from '@/components/ui/Callout.vue'
import { useAuthStore } from '@/stores/auth'
import { useToastsStore } from '@/stores/toasts'
import type { ApiError } from '@/lib/api'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const toasts = useToastsStore()

const email = ref('')
const password = ref('')
const twoFactorCode = ref('')
const needTwoFactor = ref(false)
const loading = ref(false)
const errorMessage = ref<string | null>(null)

const FIELD_CLASSES =
  'h-9 w-full rounded-md border border-line-strong bg-surface px-3 text-sm text-ink placeholder:text-ink-muted focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/20'

function nextPath(): string {
  const next = route.query.next
  return typeof next === 'string' && next.startsWith('/') ? next : '/app'
}

function fieldError(details: Record<string, unknown> | undefined, key: string): string | null {
  const messages = details?.[key]
  return Array.isArray(messages) && typeof messages[0] === 'string' ? messages[0] : null
}

async function onSubmit(): Promise<void> {
  errorMessage.value = null
  loading.value = true
  try {
    await auth.login(email.value.trim(), password.value, twoFactorCode.value || undefined)
    toasts.push('Đã đăng nhập', undefined, 'ok')
    await router.replace(nextPath())
  } catch (err) {
    const apiError = err as ApiError
    const twoFactorMessage = fieldError(apiError.details, 'two_factor_code')
    if (apiError.code === 'VALIDATION_ERROR' && twoFactorMessage) {
      needTwoFactor.value = true
      errorMessage.value = twoFactorMessage
    } else {
      errorMessage.value =
        fieldError(apiError.details, 'email') ??
        apiError.message ??
        'Đăng nhập không thành công. Thử lại sau.'
    }
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  if (auth.isAuthenticated) void router.replace(nextPath())
})
</script>

<template>
  <PublicShell>
    <div class="mx-auto flex max-w-[28rem] flex-col px-4 py-16 sm:px-6">
      <h1 class="text-balance font-serif text-[clamp(] font-semibold leading-[1.2] tracking-[-0.02em] text-ink">
        Đăng nhập
      </h1>
      <p class="mt-2 text-[13.5px] text-ink-muted">
        Đăng nhập để quản lý dự án và nghĩa vụ pháp lý của bạn.
      </p>

      <form class="mt-10 flex flex-col gap-5" @submit.prevent="onSubmit">
        <UiInput v-model="email" label="Email" placeholder="ban@congty.vn" required autocomplete="username" />
        <div class="flex flex-col gap-1.5">
          <label for="password" class="text-[13px] font-medium text-ink required">Mật khẩu</label>
          <input
            id="password"
            v-model="password"
            type="password"
            :class="FIELD_CLASSES"
            placeholder="••••••••••"
            required
            autocomplete="current-password"
          />
        </div>
        <div v-if="needTwoFactor" class="flex flex-col gap-1.5">
          <label for="two-factor" class="text-[13px] font-medium text-ink required">
            Mã xác thực hai bước
          </label>
          <input
            id="two-factor"
            v-model="twoFactorCode"
            inputmode="numeric"
            maxlength="6"
            :class="FIELD_CLASSES"
            placeholder="6 chữ số"
            required
            autocomplete="one-time-code"
          />
          <p class="text-xs text-ink-muted">Mã 6 chữ số từ ứng dụng Authenticator.</p>
        </div>
        <Callout v-if="errorMessage" tone="caution" icon="warning" title="Chưa đăng nhập được">
          {{ errorMessage }}
        </Callout>
        <UiButton type="submit" :loading="loading">Đăng nhập</UiButton>
      </form>

      <p class="mt-8 text-[13px] text-ink-muted">
        Chưa có tài khoản?
        <RouterLink to="/dang-ky" class="font-medium text-accent hover:underline underline-offset-2">
          Đăng ký
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
