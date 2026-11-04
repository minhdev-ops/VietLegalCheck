<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ArrowLeft } from '@lucide/vue'
import PageHeader from '@/components/common/PageHeader.vue'
import UiCard from '@/components/ui/UiCard.vue'
import UiInput from '@/components/ui/UiInput.vue'
import UiTextarea from '@/components/ui/UiTextarea.vue'
import UiSelect from '@/components/ui/UiSelect.vue'
import UiButton from '@/components/ui/UiButton.vue'
import Callout from '@/components/ui/Callout.vue'
import { api, type ApiError } from '@/lib/api'
import { useToastsStore } from '@/stores/toasts'
import type { Project } from '@/types/models'

const router = useRouter()

function goProjects(): void {
  void router.push({ name: 'projects' })
}

const toasts = useToastsStore()

const SECTORS = [
  { value: 'ECOMMERCE', label: 'Thương mại điện tử' },
  { value: 'FINTECH', label: 'Fintech — tài chính số' },
  { value: 'HEALTH', label: 'Y tế — sức khoẻ số' },
  { value: 'SOCIAL', label: 'Mạng xã hội' },
  { value: 'MEDIA', label: 'Truyền thông — nội dung số' },
  { value: 'EDU', label: 'Giáo dục — đào tạo số' },
  { value: 'OTHER', label: 'Khác' },
] as const

function todayIso(): string {
  return new Date().toISOString().slice(0, 10)
}

const name = ref('')
const website = ref('')
const sector = ref('')
const description = ref('')
const asOfDate = ref(todayIso())

const submitting = ref(false)
const fieldErrors = ref<Record<string, string>>({})
const formError = ref<string | null>(null)

const FIELD_CLASSES =
  'h-9 w-full rounded-md border border-line-strong bg-surface px-3 text-sm text-ink placeholder:text-ink-faint focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/20'

const trimmedName = computed(() => name.value.trim())

function validate(): boolean {
  fieldErrors.value = {}
  if (trimmedName.value.length < 3) {
    fieldErrors.value.name = 'Tên dự án cần ít nhất 3 ký tự.'
  }
  if (website.value.trim() !== '') {
    try {
      const url = new URL(website.value.trim())
      if (!['http:', 'https:'].includes(url.protocol)) fieldErrors.value.url = 'Địa chỉ website phải bắt đầu bằng http:// hoặc https://.'
    } catch {
      fieldErrors.value.url = 'Địa chỉ website không hợp lệ.'
    }
  }
  if (description.value.length > 2000) {
    fieldErrors.value.description = 'Mô tả không vượt quá 2000 ký tự.'
  }
  if (asOfDate.value !== '' && Number.isNaN(Date.parse(asOfDate.value))) {
    fieldErrors.value.as_of_date = 'Ngày đánh giá không hợp lệ.'
  }
  return Object.keys(fieldErrors.value).length === 0
}

function fieldError(key: string): string | undefined {
  return fieldErrors.value[key]
}

async function onSubmit(): Promise<void> {
  formError.value = null
  if (!validate()) return
  submitting.value = true
  try {
    const payload = await api.post<{ project: Project }>('/projects', {
      name: trimmedName.value,
      description: description.value.trim() === '' ? null : description.value.trim(),
      url: website.value.trim() === '' ? null : website.value.trim(),
      product_type: sector.value || null,
      as_of_date: asOfDate.value || null,
    })
    toasts.push('Đã tạo dự án', 'Tiếp tục khai báo hồ sơ tác động để bắt đầu đánh giá.', 'ok')
    await router.push({ name: 'project-wizard', params: { id: payload.project.id } })
  } catch (err) {
    const apiError = err as ApiError
    const details = apiError.details
    const pick = (key: string): string | undefined => {
      const messages = details?.[key]
      return Array.isArray(messages) && typeof messages[0] === 'string' ? messages[0] : undefined
    }
    const mapped: Record<string, string> = {}
    for (const key of ['name', 'url', 'description', 'product_type', 'as_of_date']) {
      const message = pick(key)
      if (message) {
        mapped[key === 'url' ? 'url' : key] = message
        if (key === 'product_type') mapped.sector = message
      }
    }
    if (Object.keys(mapped).length > 0) {
      fieldErrors.value = { ...fieldErrors.value, ...mapped }
    } else {
      formError.value = apiError.message ?? 'Tạo dự án không thành công. Vui lòng thử lại.'
    }
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="mx-auto max-w-3xl">
    <PageHeader
      title="Tạo dự án mới"
      subtitle="Khai báo thông tin cơ bản. Hồ sơ tác động (wizard) sẽ mở ngay sau khi tạo."
    />

    <Callout
      v-if="formError"
      tone="danger"
      icon="warning"
      :title="formError"
      action-label="Thử lại"
      class="mb-4"
      @action="onSubmit()"
    />

    <form class="flex flex-col gap-5" @submit.prevent="onSubmit">
      <UiCard class="flex flex-col gap-5">
        <UiInput
          v-model="name"
          label="Tên dự án"
          placeholder="VD: Sàn thương mại điện tử ABC"
          required
          :error="fieldError('name')"
          hint="Tên hiển thị trong suốt không gian làm việc."
        />

        <div class="grid gap-5 sm:grid-cols-2">
          <UiInput
            v-model="website"
            label="Website"
            placeholder="https://example.vn"
            :error="fieldError('url')"
            hint="Địa chỉ sản phẩm, dùng cho quét trang."
          />
          <UiSelect
            v-model="sector"
            label="Lĩnh vực"
            placeholder="Chọn lĩnh vực"
            :options="[...SECTORS]"
            hint="Phân loại để chọn bộ quy tắc phù hợp."
          />
        </div>

        <UiTextarea
          label="Mô tả"
          :rows="4"
          placeholder="Mô tả ngắn gọn về sản phẩm, mô hình hoạt động và thị trường…"
          :error="fieldError('description')"
        />

        <div class="flex flex-col gap-1.5">
          <label for="as-of" class="text-[13px] font-medium text-ink">Ngày đánh giá khởi tạo</label>
          <input
            id="as-of"
            v-model="asOfDate"
            type="date"
            class="tnum"
            :class="FIELD_CLASSES"
            :aria-invalid="fieldError('as_of_date') ? 'true' : undefined"
          />
          <p v-if="fieldError('as_of_date')" class="text-xs text-seal" role="alert">{{ fieldError('as_of_date') }}</p>
          <p v-else class="text-xs text-ink-muted">Mặc định là hôm nay; dùng cho đợt đánh giá đầu tiên.</p>
        </div>
      </UiCard>

      <div class="flex flex-wrap items-center justify-end gap-3">
        <UiButton variant="ghost" type="button" @click="goProjects">
          <ArrowLeft class="size-4" aria-hidden="true" /> Quay lại
        </UiButton>
        <UiButton variant="primary" type="submit" :loading="submitting"> Tạo dự án </UiButton>
      </div>
    </form>
  </div>
</template>
