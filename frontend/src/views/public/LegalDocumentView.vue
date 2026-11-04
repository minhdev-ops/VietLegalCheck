<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import { ArrowLeft, ExternalLink } from '@lucide/vue'
import PublicShell from './PublicShell.vue'
import Callout from '@/components/ui/Callout.vue'
import StatusChip from '@/components/ui/StatusChip.vue'
import LegalCitation from '@/components/ui/LegalCitation.vue'
import UiSkeleton from '@/components/ui/UiSkeleton.vue'
import type { LegalDocument } from '@/types/models'
import { api, type ApiError } from '@/lib/api'
import { formatDate } from '@/lib/format'

interface DocumentResponse {
  document: LegalDocument
}

const route = useRoute()
const router = useRouter()

const document = ref<LegalDocument | null>(null)
const asOf = ref((route.query.as_of_date as string) || new Date().toISOString().slice(0, 10))
const loading = ref(false)
const error = ref<string | null>(null)

type EffectTone = 'ok' | 'info' | 'neutral'

function isoDay(value: string | null | undefined): string | null {
  return value ? value.slice(0, 10) : null
}

function effectiveness(): { label: string; tone: EffectTone } | null {
  const doc = document.value
  if (!doc) return null
  const from = isoDay(doc.effective_from)
  const to = isoDay(doc.effective_to)
  if (from && asOf.value < from) return { label: 'Chưa có hiệu lực', tone: 'info' }
  if (to && asOf.value > to) return { label: 'Hết hiệu lực', tone: 'neutral' }
  return { label: 'Đang có hiệu lực', tone: 'ok' }
}

async function fetchDocument(): Promise<void> {
  loading.value = true
  error.value = null
  try {
    const payload = await api.get<DocumentResponse>(`/legal/documents/${String(route.params.id)}`, {
      as_of_date: asOf.value,
    })
    document.value = payload.document
  } catch (err) {
    const apiError = err as ApiError
    error.value = apiError.code === 'NOT_FOUND'
      ? 'Không tìm thấy văn bản này. Danh sách văn bản có thể đã thay đổi.'
      : (apiError.message ?? 'Không tải được văn bản.')
  } finally {
    loading.value = false
  }
}

function metaRows(): Array<{ label: string; value: string | null }> {
  const doc = document.value
  if (!doc) return []
  return [
    { label: 'Cơ quan ban hành', value: doc.issuer },
    { label: 'Ngày ban hành', value: formatDate(doc.issued_at) },
    { label: 'Có hiệu lực từ', value: formatDate(doc.effective_from) },
    { label: 'Có hiệu lực đến', value: formatDate(doc.effective_to) },
    { label: 'Kiểu văn bản', value: doc.doc_type },
    { label: 'Lần xác minh gần nhất', value: formatDate(doc.last_verified_at) },
  ]
}

onMounted(() => {
  void fetchDocument()
})
</script>

<template>
  <PublicShell>
    <div class="mx-auto max-w-[1240px] px-4 py-10 sm:px-6">
      <RouterLink
        to="/van-ban"
        class="inline-flex items-center gap-1.5 text-[13px] font-medium text-ink-muted hover:text-ink"
      >
        <ArrowLeft class="size-3.5" aria-hidden="true" /> Quay lại tra cứu văn bản
      </RouterLink>

      <UiSkeleton v-if="loading" :lines="6" class="mt-8" />
      <Callout
        v-else-if="error"
        tone="caution"
        icon="warning"
        title="Chưa tải được văn bản"
        action-label="Thử lại"
        @action="fetchDocument()"
      >
        {{ error }}
      </Callout>
      <template v-else-if="document">
        <header class="mt-8 border-b border-line-strong pb-6">
          <div class="flex flex-wrap items-center gap-3">
            <span class="tnum text-[13px] font-semibold text-ink">{{ document.doc_number }}</span>
            <StatusChip
              v-if="effectiveness()"
              :tone="effectiveness()!.tone"
              :label="effectiveness()!.label"
            />
            <span v-if="document.verified_stale" class="text-[12px] font-medium text-caution">
              Quá hạn xác minh
            </span>
          </div>
          <h1 class="mt-3 max-w-prose text-balance font-serif text-[clamp(] font-semibold leading-[1.2] tracking-[-0.02em] text-ink">
            {{ document.title }}
          </h1>
          <p class="tnum mt-3 text-[12.5px] text-ink-muted">
            Đang xem hiệu lực tính tại ngày {{ formatDate(asOf) }}
          </p>
        </header>

        <Callout v-if="document.verified_stale" tone="caution" icon="warning" title="Quá hạn xác minh" class="mt-6">
          Văn bản này quá hạn xác minh (lần cuối {{ formatDate(document.last_verified_at) }}).
          Kiểm tra lại trên cổng thông tin chính thức trước khi dùng làm căn cứ.
        </Callout>

        <section class="mt-8 grid gap-10 lg:grid-cols-[248px_1fr]">
          <aside>
            <h2 class="text-[11px] font-semibold uppercase tracking-[0.06em] text-ink-muted">
              Thông tin văn bản
            </h2>
            <dl class="mt-3 divide-y divide-line border-y border-line">
              <div v-for="row in metaRows()" :key="row.label" class="flex justify-between gap-4 py-2.5">
                <dt class="text-[13px] text-ink-muted">{{ row.label }}</dt>
                <dd class="tnum text-right text-[13px] text-ink">{{ row.value }}</dd>
              </div>
            </dl>
            <a
              v-if="document.official_url"
              :href="document.official_url"
              target="_blank"
              rel="noopener noreferrer"
              class="mt-4 inline-flex items-center gap-1.5 text-[13px] font-medium text-accent hover:underline underline-offset-2"
            >
              Xem văn bản chính thức <ExternalLink class="size-3.5" aria-hidden="true" />
            </a>
          </aside>

          <div class="min-w-0">
            <h2 class="text-[11px] font-semibold uppercase tracking-[0.06em] text-ink-muted">
              Điều khoản
            </h2>
            <div v-if="document.provisions?.length" class="mt-3 flex flex-col gap-8">
              <LegalCitation
                v-for="provision in document.provisions"
                :key="provision.id"
                :citation="provision.citation"
                :document-number="document.doc_number"
                :document-title="document.title"
                :effective-from="provision.effective_from ?? document.effective_from"
                :official-url="document.official_url"
                :quote="provision.content"
                :verified-at="document.last_verified_at"
                :stale="document.verified_stale"
              />
            </div>
            <p v-else class="mt-3 text-[13.5px] text-ink-muted">
              Không có điều khoản nào có hiệu lực tại ngày {{ formatDate(asOf) }}.
            </p>
          </div>
        </section>
      </template>

      <div v-if="!loading && error" class="mt-8">
        <UiButton variant="secondary" @click="router.push({ name: 'legal-search' })">
          Về trang tra cứu
        </UiButton>
      </div>
    </div>
  </PublicShell>
</template>
