<script setup lang="ts">
import { ref } from 'vue'
import { Plus, Trash2 } from '@lucide/vue'
import PageHeader from '@/components/common/PageHeader.vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiCard from '@/components/ui/UiCard.vue'
import UiInput from '@/components/ui/UiInput.vue'
import UiSkeleton from '@/components/ui/UiSkeleton.vue'
import Callout from '@/components/ui/Callout.vue'
import { useToastsStore } from '@/stores/toasts'
import { api, type ApiError } from '@/lib/api'
import type { SettingPair } from './shared'

const toasts = useToastsStore()

const settings = ref<SettingPair[]>([])
const loading = ref(false)
const error = ref<ApiError | null>(null)
const saving = ref(false)

async function load(): Promise<void> {
  loading.value = true
  error.value = null
  try {
    const payload = await api.get<{ settings: SettingPair[] }>('/admin/settings')
    settings.value = payload.settings
  } catch (e) {
    error.value = e as ApiError
  } finally {
    loading.value = false
  }
}

function addRow(): void {
  settings.value.push({ key: '', value: '', description: null })
}

function removeRow(index: number): void {
  settings.value.splice(index, 1)
}

async function save(): Promise<void> {
  saving.value = true
  try {
    const cleaned = settings.value.filter((item) => item.key.trim() !== '')
    await api.put('/admin/settings', { settings: cleaned })
    settings.value = cleaned
    toasts.push('Đã lưu cấu hình hệ thống', undefined, 'ok')
  } catch (e) {
    toasts.push('Chưa lưu được cấu hình', (e as ApiError).message, 'danger')
  } finally {
    saving.value = false
  }
}

load()
</script>

<template>
  <div>
    <PageHeader
      title="Cấu hình hệ thống"
      subtitle="Kho cấu hình key–value phục vụ vận hành; mỗi thay đổi cần lưu để áp dụng."
    />

    <Callout
      v-if="error"
      tone="danger"
      icon="warning"
      :title="error.message"
      action-label="Thử lại"
      @action="load()"
    />
    <UiCard v-else padding="none">
      <UiSkeleton v-if="loading" :lines="4" class="p-5" />
      <template v-else>
        <div class="flex flex-col gap-3 p-5">
          <p class="text-[13px] text-ink-muted">
            {{ settings.length }} mục cấu hình. Trùng key sẽ bị backend từ chối khi lưu.
          </p>
          <div class="grid grid-cols-[1fr_1.5fr_auto] items-center gap-3 text-[11px] font-semibold uppercase tracking-[0.06em] text-ink-muted">
            <span>Khoá</span>
            <span>Giá trị</span>
            <span class="sr-only">Xoá</span>
          </div>
          <div
            v-for="(item, index) in settings"
            :key="index"
            class="grid grid-cols-[1fr_1.5fr_auto] items-start gap-3"
          >
            <div class="flex flex-col gap-1">
              <UiInput v-model="item.key" placeholder="Tên khoá" />
              <p v-if="item.description" class="text-xs text-ink-muted">{{ item.description }}</p>
            </div>
            <UiInput v-model="item.value" placeholder="Giá trị" />
            <UiButton variant="ghost" size="icon" aria-label="Xoá cấu hình" @click="removeRow(index)">
              <Trash2 class="size-4 text-ink-muted" aria-hidden="true" />
            </UiButton>
          </div>
          <p v-if="settings.length === 0" class="py-4 text-center text-[13.5px] text-ink-muted">Chưa có mục cấu hình nào.</p>
        </div>
      </template>
      <template #footer>
          <div class="flex items-center justify-between">
            <UiButton variant="secondary" size="sm" @click="addRow()">
              <Plus class="size-3.5" aria-hidden="true" />
              Thêm cấu hình
            </UiButton>
            <UiButton variant="primary" :loading="saving" @click="save()">Lưu cấu hình</UiButton>
          </div>
        </template>
    </UiCard>
  </div>
</template>
