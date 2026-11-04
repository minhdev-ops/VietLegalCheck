<script setup lang="ts">
import { ExternalLink } from '@lucide/vue'
import { formatDate } from '@/lib/format'
import { cn } from '@/lib/cn'

/**
 * Chữ ký "chip điều khoản" của DESIGN.md: vị trí条款 ở trái, số hiệu văn bản phải,
 * khối trích dẫn Source Serif 4 bên dưới với hairline 1px phía trên.
 */
const props = defineProps<{
  citation: string
  documentNumber?: string | null
  documentTitle?: string | null
  effectiveFrom?: string | null
  officialUrl?: string | null
  quote?: string | null
  verifiedAt?: string | null
  stale?: boolean
  class?: string
}>()
</script>

<template>
  <figure :class="cn('min-w-0', props.class)">
    <figcaption class="flex flex-wrap items-baseline gap-x-3 gap-y-1 border-b border-line-strong pb-1.5">
      <span class="tnum rounded-sm bg-surface-2 px-1.5 py-0.5 text-[11px] font-bold uppercase tracking-[0.06em] text-ink">
        {{ citation }}
      </span>
      <span class="tnum text-[13px] font-semibold text-ink">{{ documentNumber ?? '—' }}</span>
      <span v-if="documentTitle" class="truncate text-xs text-ink-muted">{{ documentTitle }}</span>
      <span class="ml-auto flex items-center gap-1.5 text-[11px] text-ink-muted">
        <span v-if="stale" class="inline-block size-1.5 rounded-full bg-caution" :title="'Quá hạn xác minh'" />
        <span v-else class="inline-block size-1.5 rounded-full bg-seal" :title="'Đã xác minh'" />
        <span class="tnum">hiệu lực {{ formatDate(effectiveFrom) }}</span>
      </span>
    </figcaption>
    <blockquote v-if="quote" class="citation-quote text-[13.5px] text-ink">
      “{{ quote }}”
    </blockquote>
    <div class="mt-2 flex items-center gap-4 text-[11.5px] text-ink-muted">
      <a
        v-if="officialUrl"
        :href="officialUrl"
        target="_blank"
        rel="noopener noreferrer"
        class="inline-flex items-center gap-1 font-medium text-accent hover:underline underline-offset-2"
      >
        Nguồn chính thức <ExternalLink class="size-3" aria-hidden="true" />
      </a>
      <span v-if="stale" class="font-medium text-caution">
        Cần kiểm tra lại trên cổng chính thức (quá hạn xác minh {{ formatDate(verifiedAt) }})
      </span>
      <span v-else-if="verifiedAt" class="tnum">đã xác minh {{ formatDate(verifiedAt) }}</span>
    </div>
  </figure>
</template>
