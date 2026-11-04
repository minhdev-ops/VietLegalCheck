<script setup lang="ts">
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ArrowLeft, BadgeCheck } from '@lucide/vue'
import PageHeader from '@/components/common/PageHeader.vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiCard from '@/components/ui/UiCard.vue'
import UiInput from '@/components/ui/UiInput.vue'
import UiSelect from '@/components/ui/UiSelect.vue'
import UiTextarea from '@/components/ui/UiTextarea.vue'
import LegalCitation from '@/components/ui/LegalCitation.vue'
import StatusChip from '@/components/ui/StatusChip.vue'
import { api, type ApiError } from '@/lib/api'
import { formatDate } from '@/lib/format'
import type { LegalDocument } from '@/types/models'
import { useToastsStore } from '@/stores/toasts'
import {
  DOC_TYPE_OPTIONS,
  DOCUMENT_STATUS,
  emptyToNull,
  toSelectOptions,
} from './shared'

const route = useRoute()
const router = useRouter()
const toasts = useToastsStore()
const id = String(route.params.id)

const doc = ref<LegalDocument | null>(null)
const loading = ref(false)
const error = ref<ApiError | null>(null)
const saving = ref(false)
const verifying = ref(false)

type DocumentForm = Record<'doc_number' | 'title' | 'doc_type' | 'issuer' | 'issued_at' | 'effective_from' | 'effective_to' | 'status' | 'official_url' | 'notes', string>

const form = ref<DocumentForm>({
  doc_number: '',
  title: '',
  doc_type: String(DOC_TYPE_OPTIONS[1]),
  issuer: '',
  issued_at: '',
  effective_from: '',
  effective_to: '',
  status: 'IN_EFFECT',
  official_url: '',
  notes: '',
})

async function load(): Promise<void> {
  loading.value = true
  error.value = null
  try {
    const payload = await api.get<LegalDocument>(`/admin/legal-documents/${id}`)
    doc.value = payload
    form.value = {
      doc_number: payload.doc_number,
      title: payload.title,
      doc_type: payload.doc_type,
      issuer: payload.issuer ?? '',
      issued_at: (payload.issued_at ?? '').slice(0, 10),
      effective_from: (payload.effective_from ?? '').slice(0, 10),
      effective_to: (payload.effective_to ?? '').slice(0, 10),
      status: String(payload.status),
      official_url: payload.official_url ?? '',
      notes: payload.notes ?? '',
    }
  } catch (e) {
    error.value = e as ApiError
  } finally {
    loading.value = false
  }
}

async function save(): Promise<void> {
  saving.value = true
  try {
    await api.put(`/admin/legal-documents/${id}`, {
      doc_number: form.value.doc_number,
      title: form.value.title,
      doc_type: form.value.doc_type,
      issuer: emptyToNull(form.value.issuer),
      issued_at: emptyToNull(form.value.issued_at),
      effective_from: emptyToNull(form.value.effective_from),
      effective_to: emptyToNull(form.value.effective_to),
      status: form.value.status,
      official_url: emptyToNull(form.value.official_url),
      notes: emptyToNull(form.value.notes),
    })
    toasts.push('Đã lưu văn bản', undefined, 'ok')
    await load()
  } catch (e) {
    toasts.push('Chưa lưu được văn bản', (e as ApiError).message, 'danger')
  } finally {
    saving.value = false
  }
}

async function verify(): Promise<void> {
  verifying.value = true
  try {
    doc.value = await api.post<LegalDocument>(`/admin/legal-documents/${id}/verify`)
    toasts.push('Đã xác minh văn bản trên cổng chính thức', undefined, 'ok')
  } catch (e) {
    toasts.push('Chưa xác minh được', (e as ApiError).message, 'danger')
  } finally {
    verifying.value = false
  }
}

load()
</script>

<template>
  <div>
    <button
      class="mb-4 inline-flex items-center gap-1.5 text-[13px] font-medium text-ink-muted hover:text-ink"
      @click="router.push({ name: 'admin-documents' })"
    >
      <ArrowLeft class="size-4" aria-hidden="true" />
      Danh sách văn bản
    </button>

    <Callout
      v-if="error"
      tone="danger"
      icon="warning"
      :title="error.message"
      action-label="Thử lại"
      @action="load()"
    />
    <template v-else>
      <PageHeader
        :title="loading ? 'Đang tải văn bản…' : doc?.doc_number ?? ''"
        :subtitle="doc?.title"
      >
        <template #actions>
          <UiButton
            v-if="doc"
            variant="secondary"
            :loading="verifying"
            @click="verify()"
          >
            <BadgeCheck class="size-4" aria-hidden="true" />
            Xác minh trên cổng chính thức
          </UiButton>
          <UiButton v-if="doc" variant="primary" :loading="saving" @click="save()">Lưu thay đổi</UiButton>
        </template>
      </PageHeader>

      <UiSkeleton v-if="loading" :lines="6" class="p-5" />
      <div v-else-if="doc" class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <UiCard title="Thông tin văn bản" description="Số hiệu, cơ quan ban hành và hiệu lực.">
          <form class="grid grid-cols-1 gap-4 sm:grid-cols-2" @submit.prevent="save()">
            <UiInput v-model="form.doc_number" label="Số hiệu" required />
            <UiSelect v-model="form.doc_type" label="Loại văn bản" :options="DOC_TYPE_OPTIONS.map((o) => ({ value: o, label: o }))" />
            <UiInput v-model="form.title" label="Trích yếu" required wrapper-class="sm:col-span-2" />
            <UiInput v-model="form.issuer" label="Cơ quan ban hành" />
            <UiSelect v-model="form.status" label="Trạng thái" :options="toSelectOptions(DOCUMENT_STATUS)" />
            <UiInput v-model="form.issued_at" label="Ngày ban hành" hint="Định dạng YYYY-MM-DD" />
            <UiInput v-model="form.effective_from" label="Hiệu lực từ" hint="Định dạng YYYY-MM-DD" />
            <UiInput v-model="form.effective_to" label="Hiệu lực đến" hint="Định dạng YYYY-MM-DD" />
            <UiInput v-model="form.official_url" label="URL nguồn chính thức" placeholder="https://" />
            <UiTextarea v-model="form.notes" label="Ghi chú" wrapper-class="sm:col-span-2" :rows="3" />
          </form>
          <template #footer>
            <div class="flex flex-wrap items-center gap-4 text-[12.5px] text-ink-muted">
              <span v-if="doc?.last_verified_at" class="tnum">Đã xác minh {{ formatDate(doc.last_verified_at) }}</span>
              <StatusChip v-else tone="caution" label="Chưa xác minh" />
              <span v-if="doc?.verified_stale" class="font-medium text-caution">Quá hạn kiểm tra lại trên cổng chính thức</span>
            </div>
          </template>
        </UiCard>

        <UiCard
          title="Danh sách điều khoản"
          :description="`Toàn bộ điều khoản thuộc văn bản (${doc?.provisions?.length ?? 0}).`"
          padding="none"
        >
          <div class="p-5">
            <Callout v-if="!doc?.provisions || doc.provisions.length === 0" tone="neutral" icon="none" title="Chưa có điều khoản" />
          </div>
          <ul v-if="doc?.provisions && doc.provisions.length > 0" class="flex flex-col divide-y divide-line">
            <li v-for="provision in doc?.provisions ?? []" :key="provision.id" class="p-5">
              <LegalCitation
                :citation="provision.citation"
                :document-number="doc?.doc_number ?? ''"
                :document-title="doc?.title ?? ''"
                :effective-from="provision.effective_from"
                :official-url="doc?.official_url"
                :quote="provision.content"
                :stale="doc?.verified_stale"
                :verified-at="doc?.last_verified_at"
              />
            </li>
          </ul>
        </UiCard>
      </div>
    </template>
  </div>
</template>
