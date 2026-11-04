<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { ArrowUpRight, ShieldCheck } from '@lucide/vue'
import PageHeader from '@/components/common/PageHeader.vue'
import UiCard from '@/components/ui/UiCard.vue'
import StatusChip from '@/components/ui/StatusChip.vue'
import UiSkeleton from '@/components/ui/UiSkeleton.vue'
import Callout from '@/components/ui/Callout.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import UiButton from '@/components/ui/UiButton.vue'
import { api, type ApiError } from '@/lib/api'
import { formatDate } from '@/lib/format'
import { useToastsStore } from '@/stores/toasts'

type ChipMeta = { tone: 'neutral' | 'ok' | 'caution' | 'info' | 'expert' | 'danger'; label: string }

const SYSTEM_ROLE: Record<string, ChipMeta> = {
  USER: { tone: 'neutral', label: 'Người dùng' },
  LEGAL_EXPERT: { tone: 'expert', label: 'Chuyên gia pháp lý' },
  LEGAL_ADMIN: { tone: 'info', label: 'Quản trị pháp lý' },
  SYS_ADMIN: { tone: 'danger', label: 'Quản trị hệ thống' },
}

const ORG_ROLE: Record<string, ChipMeta> = {
  OWNER: { tone: 'expert', label: 'Chủ sở hữu' },
  ADMIN: { tone: 'info', label: 'Quản trị' },
  MEMBER: { tone: 'ok', label: 'Thành viên' },
  VIEWER: { tone: 'neutral', label: 'Chỉ xem' },
}

function meta(map: Record<string, ChipMeta>, value: string): ChipMeta {
  return map[value] ?? { tone: 'neutral', label: value }
}

interface MeOrg {
  id: string
  name: string
  role: string
  is_personal: boolean
}

interface MeUser {
  id: string
  name: string
  email: string
  system_role: string
  email_verified: boolean
  two_factor_enabled: boolean
  created_at: string | null
  organizations: MeOrg[]
}

const toasts = useToastsStore()

const me = ref<MeUser | null>(null)
const loading = ref(true)
const error = ref<string | null>(null)

const code = ref('')
const codeError = ref<string | null>(null)
const busyTwoFactor = ref(false)
const recoveryCodes = ref<string[] | null>(null)

const FIELD_CLASSES =
  'h-9 w-full rounded-md border border-line-strong bg-surface px-3 text-sm text-ink placeholder:text-ink-faint focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/20'

async function load(): Promise<void> {
  loading.value = true
  error.value = null
  recoveryCodes.value = null
  code.value = ''
  try {
    const payload = await api.get<{ user: MeUser }>('/auth/me')
    me.value = payload.user
  } catch (err) {
    error.value = (err as ApiError)?.message ?? 'Chưa tải được hồ sơ cá nhân.'
  } finally {
    loading.value = false
  }
}

async function onTwoFactorSubmit(): Promise<void> {
  if (code.value.trim().length !== 6 || busyTwoFactor.value) return
  busyTwoFactor.value = true
  codeError.value = null
  const path = me.value?.two_factor_enabled ? '/auth/2fa/disable' : '/auth/2fa/enable'
  try {
    const payload = await api.post<{ recovery_codes?: string[] }>(path, { code: code.value.trim() })
    if (payload.recovery_codes) recoveryCodes.value = payload.recovery_codes
    toasts.push(
      me.value?.two_factor_enabled ? 'Đã tắt xác thực hai bước' : 'Đã bật xác thực hai bước',
      undefined,
      'ok',
    )
    await load()
  } catch (err) {
    const details = (err as ApiError)?.details
    const messages = details?.code
    codeError.value =
      Array.isArray(messages) && typeof messages[0] === 'string'
        ? messages[0]
        : (err as ApiError)?.message ?? 'Xác thực hai bước không thành công.'
  } finally {
    busyTwoFactor.value = false
  }
}

onMounted(() => {
  void load()
})
</script>

<template>
  <div class="mx-auto max-w-3xl">
    <PageHeader title="Tài khoản" subtitle="Hồ sơ cá nhân, bảo mật và các tổ chức bạn đang tham gia." />

    <Callout
      v-if="error"
      tone="danger"
      icon="warning"
      :title="error"
      action-label="Thử lại"
      class="mb-4"
      @action="load()"
    />
    <div v-else-if="loading" class="flex flex-col gap-4">
      <UiCard><UiSkeleton :lines="3" /></UiCard>
      <UiCard><UiSkeleton :lines="4" /></UiCard>
    </div>
    <template v-else-if="me">
      <!-- Hồ sơ cá nhân -->
      <UiCard title="Hồ sơ cá nhân">
        <dl class="grid gap-x-6 gap-y-4 sm:grid-cols-2">
          <div>
            <dt class="text-[10.5px] font-semibold uppercase tracking-[0.06em] text-ink-muted">Họ tên</dt>
            <dd class="mt-1 text-[13.5px] font-medium text-ink">{{ me.name }}</dd>
          </div>
          <div>
            <dt class="text-[10.5px] font-semibold uppercase tracking-[0.06em] text-ink-muted">Email</dt>
            <dd class="mt-1 text-[13.5px] font-medium text-ink">{{ me.email }}</dd>
          </div>
          <div>
            <dt class="text-[10.5px] font-semibold uppercase tracking-[0.06em] text-ink-muted">Vai trò hệ thống</dt>
            <dd class="mt-1"><StatusChip v-bind="meta(SYSTEM_ROLE, me.system_role)" /></dd>
          </div>
          <div>
            <dt class="text-[10.5px] font-semibold uppercase tracking-[0.06em] text-ink-muted">Xác thực email</dt>
            <dd class="mt-1">
              <StatusChip v-if="me.email_verified" tone="ok" label="Đã xác thực" />
              <StatusChip v-else tone="caution" label="Chưa xác thực" />
            </dd>
          </div>
          <div>
            <dt class="text-[10.5px] font-semibold uppercase tracking-[0.06em] text-ink-muted">Ngày tham gia</dt>
            <dd class="tnum mt-1 text-[13.5px] text-ink-muted">{{ formatDate(me.created_at) }}</dd>
          </div>
        </dl>
      </UiCard>

      <!-- Bảo mật: xác thực hai bước -->
      <UiCard class="mt-4">
        <template #header>
          <div class="flex min-w-0 flex-1 items-center justify-between gap-4">
            <div class="min-w-0">
              <h3 class="text-[15px] font-semibold leading-tight tracking-[-0.005em] text-ink">Bảo mật</h3>
              <p class="mt-1 text-[13px] text-ink-muted">Xác thực hai bước bằng mã TOTP từ ứng dụng.</p>
            </div>
            <StatusChip
              v-if="me.two_factor_enabled"
              tone="ok"
              label="Đã bật"
              :icon="ShieldCheck"
            />
            <StatusChip v-else tone="neutral" label="Chưa bật" />
          </div>
        </template>

        <Callout v-if="recoveryCodes" tone="ok" title="Lưu mã dự phòng" class="mb-4">
          Mã dùng một lần khi mất ứng dụng xác thực. Giữ an toàn và không chia sẻ:
          <ul class="tnum mt-2 grid grid-cols-2 gap-1 text-[12.5px]">
            <li v-for="(codeValue, index) in recoveryCodes" :key="index">{{ codeValue }}</li>
          </ul>
        </Callout>

        <form class="flex flex-wrap items-end gap-3" @submit.prevent="onTwoFactorSubmit">
          <div class="flex flex-col gap-1.5">
            <label for="totp-code" class="text-[13px] font-medium text-ink">
              {{ me.two_factor_enabled ? 'Mã xác thực để tắt' : 'Mã xác thực 6 số' }}
            </label>
            <input
              id="totp-code"
              v-model="code"
              inputmode="numeric"
              maxlength="6"
              placeholder="123456"
              class="tnum"
              :class="FIELD_CLASSES"
              :aria-invalid="codeError ? 'true' : undefined"
            />
            <p v-if="codeError" class="text-xs text-seal" role="alert">{{ codeError }}</p>
            <p v-else class="text-xs text-ink-muted">
              Mở ứng dụng xác thực (Google Authenticator, Authy…) và nhập mã đang hiện.
            </p>
          </div>
          <UiButton
            variant="primary"
            type="submit"
            :loading="busyTwoFactor"
            :disabled="code.trim().length !== 6"
          >
            {{ me.two_factor_enabled ? 'Tắt xác thực' : 'Bật xác thực' }}
          </UiButton>
        </form>
      </UiCard>

      <!-- Tổ chức -->
      <UiCard class="mt-4">
        <template #header>
          <div class="flex min-w-0 flex-1 items-center justify-between gap-4">
            <div class="min-w-0">
              <h3 class="text-[15px] font-semibold leading-tight tracking-[-0.005em] text-ink">Tổ chức</h3>
              <p class="mt-1 text-[13px] text-ink-muted">Tổ chức bạn đang tham gia và vai trò tương ứng.</p>
            </div>
            <RouterLink
              to="/app/to-chuc"
              class="inline-flex shrink-0 items-center gap-1 text-[13px] font-medium text-accent hover:underline underline-offset-2"
            >
              Quản lý <ArrowUpRight class="size-3.5" aria-hidden="true" />
            </RouterLink>
          </div>
        </template>
        <EmptyState
          v-if="me.organizations.length === 0"
          :icon="ShieldCheck"
          title="Chưa thuộc tổ chức nào"
          body="Tạo tổ chức hoặc chấp nhận lời mời để bắt đầu làm việc nhóm."
        />
        <ul v-else class="-mx-5 flex flex-col">
          <li
            v-for="org in me.organizations"
            :key="org.id"
            class="flex items-center justify-between gap-4 border-b border-line px-5 py-3 last:border-0"
          >
            <div class="min-w-0">
              <p class="truncate text-[13.5px] font-medium text-ink">{{ org.name }}</p>
              <p class="mt-0.5 text-[11.5px] text-ink-muted">{{ org.is_personal ? 'Tổ chức cá nhân' : 'Tổ chức' }}</p>
            </div>
            <StatusChip v-bind="meta(ORG_ROLE, org.role)" />
          </li>
        </ul>
      </UiCard>
    </template>
  </div>
</template>
