<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterView, useRouter } from 'vue-router'
import { Menu, Bell, LogOut, CircleUser } from '@lucide/vue'
import { useAuthStore } from '@/stores/auth'
import { useNotificationsStore } from '@/stores/notifications'
import { formatDate } from '@/lib/format'
import UiButton from '@/components/ui/UiButton.vue'

interface NavItem {
  label: string
  to: string
  badge?: number
}

const router = useRouter()
const auth = useAuthStore()
const notifications = useNotificationsStore()
const sidebarOpen = ref(false)

const PRIMARY: NavItem[] = [
  { label: 'Tổng quan', to: '/app' },
  { label: 'Dự án của tôi', to: '/app/du-an' },
  { label: 'Trợ lý pháp lý AI', to: '/app/tro-ly-ai' },
  { label: 'Báo cáo', to: '/app/bao-cao' },
]

function onNavigate(): void {
  sidebarOpen.value = false
}

async function onLogout(): Promise<void> {
  await auth.logout()
  await router.push({ name: 'home' })
}

onMounted(() => {
  if (auth.isAuthenticated && !notifications.loaded) void notifications.load()
})
</script>

<template>
  <div class="flex min-h-dvh bg-paper">
    <aside
      :class="[
        'fixed inset-y-0 left-0 z-40 w-[248px] border-r border-line bg-surface-2 transition-transform duration-300 ease-out',
        'lg:static lg:translate-x-0',
        sidebarOpen ? 'translate-x-0' : '-translate-x-full',
      ]"
      aria-label="Thanh điều hướng"
    >
      <div class="flex h-16 items-center gap-2.5 border-b border-line px-5">
        <span class="flex size-8 items-center justify-center rounded-md bg-seal/10 text-seal">
          <span class="text-[15px] font-bold">§</span>
        </span>
        <div class="leading-tight">
          <p class="text-[14px] font-semibold tracking-[-0.01em] text-ink">VietLegal Check</p>
          <p class="text-[10.5px] font-medium uppercase tracking-[0.08em] text-ink-faint">Không gian làm việc</p>
        </div>
      </div>

      <nav class="flex flex-col gap-0.5 p-3" @click="onNavigate">
        <RouterLink
          v-for="item in PRIMARY"
          :key="item.to"
          :to="item.to"
          class="flex items-center justify-between rounded-md px-3 py-2 text-[13.5px] font-medium text-ink-muted transition-colors hover:bg-surface-3 hover:text-ink"
          active-class="!bg-accent/8 !text-accent"
        >
          {{ item.label }}
          <span v-if="item.badge" class="tnum rounded-pill bg-surface-3 px-1.5 text-[11px] text-ink-muted">{{ item.badge }}</span>
        </RouterLink>
      </nav>

      <div class="mt-auto border-t border-line p-3">
        <RouterLink
          v-if="auth.isAdmin || auth.isExpert"
          to="/admin"
          class="block rounded-md px-3 py-2 text-[13.5px] font-medium text-ink-muted hover:bg-surface-3 hover:text-ink"
          @click="onNavigate"
        >
          Khu vực quản trị
        </RouterLink>
        <RouterLink
          to="/app/to-chuc"
          class="block rounded-md px-3 py-2 text-[13.5px] font-medium text-ink-muted hover:bg-surface-3 hover:text-ink"
          @click="onNavigate"
        >
          Tổ chức
        </RouterLink>
        <RouterLink
          to="/app/tai-khoan"
          class="block rounded-md px-3 py-2 text-[13.5px] font-medium text-ink-muted hover:bg-surface-3 hover:text-ink"
          @click="onNavigate"
        >
          Tài khoản
        </RouterLink>
      </div>
    </aside>

    <div
      v-if="sidebarOpen"
      class="fixed inset-0 z-30 bg-ink/20 lg:hidden"
      aria-hidden="true"
      @click="sidebarOpen = false"
    />

    <div class="flex min-w-0 flex-1 flex-col">
      <header class="toolbar-material sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-line px-4 sm:px-6">
        <button
          class="flex size-9 items-center justify-center rounded-md text-ink-muted hover:bg-surface-2 lg:hidden"
          aria-label="Mở menu"
          @click="sidebarOpen = !sidebarOpen"
        >
          <Menu class="size-5" />
        </button>

        <div class="min-w-0 flex-1" />

        <RouterLink
          to="/app/thong-bao"
          class="relative flex size-9 items-center justify-center rounded-md text-ink-muted hover:bg-surface-2"
          aria-label="Thông báo"
        >
          <Bell class="size-[18px]" />
          <span
            v-if="notifications.unread > 0"
            class="tnum absolute right-1 top-1 flex min-w-[16px] items-center justify-center rounded-pill bg-seal px-1 text-[10px] font-semibold text-white"
          >
            {{ notifications.unread }}
          </span>
        </RouterLink>

        <RouterLink
          to="/app/tai-khoan"
          class="flex min-w-0 items-center gap-2 rounded-md px-2 py-1.5 text-left hover:bg-surface-2"
        >
          <span class="flex size-7 items-center justify-center rounded-full bg-surface-3 text-ink-muted">
            <CircleUser class="size-4" />
          </span>
          <span class="hidden min-w-0 sm:block">
            <span class="block truncate text-[12.5px] font-medium text-ink">{{ auth.user?.name }}</span>
            <span class="block truncate text-[11px] text-ink-faint">{{ formatDate(auth.user?.email_verified_at) }}</span>
          </span>
        </RouterLink>

        <UiButton variant="quiet" size="icon" aria-label="Đăng xuất" @click="onLogout">
          <LogOut class="size-[18px]" />
        </UiButton>
      </header>

      <main class="mx-auto w-full max-w-[1240px] flex-1 px-4 py-6 sm:px-6 lg:py-8">
        <RouterView />
      </main>
    </div>
  </div>
</template>
