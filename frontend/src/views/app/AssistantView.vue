<script setup lang="ts">
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue'
import { MessageSquarePlus, Send, Sparkles, Square } from '@lucide/vue'
import PageHeader from '@/components/common/PageHeader.vue'
import UiCard from '@/components/ui/UiCard.vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiTextarea from '@/components/ui/UiTextarea.vue'
import UiSkeleton from '@/components/ui/UiSkeleton.vue'
import Callout from '@/components/ui/Callout.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import StatusChip from '@/components/ui/StatusChip.vue'
import LegalCitation from '@/components/ui/LegalCitation.vue'
import { api, streamSse, type ApiError } from '@/lib/api'
import { formatDateTime } from '@/lib/format'
import type { AiMessage } from '@/types/models'

interface AiConversation {
  id: string
  title: string
  project_id: string | null
  updated_at: string
  messages?: AiMessage[]
}

/** Backend lưu citations là mã điều khoản; dịch vụ nội bộ có thể trả object đầy đủ. */
type AiCitation =
  | string
  | {
      provision_id?: string
      citation?: string
      document_number?: string | null
      document_title?: string | null
      content?: string | null
      official_url?: string | null
      effective_from?: string | null
    }

interface CitationDetail {
  citation: string
  documentNumber: string | null
  documentTitle: string | null
  quote: string | null
  officialUrl: string | null
  effectiveFrom: string | null
}

function asCitation(entry: AiCitation): CitationDetail {
  if (typeof entry === 'string') {
    return {
      citation: 'Trích dẫn pháp lý',
      documentNumber: null,
      documentTitle: null,
      quote: null,
      officialUrl: null,
      effectiveFrom: null,
    }
  }
  return {
    citation: entry.citation ?? 'Trích dẫn pháp lý',
    documentNumber: entry.document_number ?? null,
    documentTitle: entry.document_title ?? null,
    quote: entry.content ?? null,
    officialUrl: entry.official_url ?? null,
    effectiveFrom: entry.effective_from ?? null,
  }
}

function citationsOf(message: AiMessage): CitationDetail[] {
  return (message.citations ?? []).map(asCitation)
}

const conversations = ref<AiConversation[]>([])
const activeId = ref<string | null>(null)
const messages = ref<AiMessage[]>([])
const question = ref('')
const sending = ref(false)
const streamingContent = ref('')
const streamError = ref<string | null>(null)

const listLoading = ref(true)
const listError = ref<string | null>(null)
const chatLoading = ref(false)

const thread = ref<HTMLElement | null>(null)

let cancelStream: (() => void) | null = null

const activeConversation = computed<AiConversation | null>(
  () => conversations.value.find((c) => c.id === activeId.value) ?? null,
)

const visibleMessages = computed<Array<AiMessage & { streaming?: boolean }>>(() => {
  if (streamingContent.value !== '' && sending.value) {
    const streaming: AiMessage & { streaming?: boolean } = {
      id: 'streaming',
      role: 'assistant',
      content: streamingContent.value,
      citations: null,
      categories: null,
      refused: false,
      model: null,
      created_at: new Date().toISOString(),
      streaming: true,
    }
    return [...messages.value, streaming]
  }
  return messages.value
})

function scrollBottom(): void {
  void nextTick(() => {
    if (thread.value) thread.value.scrollTop = thread.value.scrollHeight
  })
}

async function loadConversations(): Promise<void> {
  listLoading.value = true
  listError.value = null
  try {
    const payload = await api.get<{ data: AiConversation[] }>('/ai/conversations')
    conversations.value = payload.data
    if (!activeId.value && conversations.value.length > 0) {
      await openConversation(conversations.value[0].id)
    }
  } catch (err) {
    listError.value = (err as ApiError)?.message ?? 'Chưa tải được danh sách hội thoại.'
  } finally {
    listLoading.value = false
  }
}

async function openConversation(id: string): Promise<void> {
  activeId.value = id
  streamError.value = null
  chatLoading.value = true
  try {
    const payload = await api.get<{ data: AiMessage[] }>(`/ai/conversations/${id}/messages`)
    messages.value = payload.data
  } catch {
    const cached = conversations.value.find((c) => c.id === id)?.messages ?? []
    messages.value = cached
  } finally {
    chatLoading.value = false
    scrollBottom()
  }
}

async function reloadMessages(): Promise<void> {
  if (!activeId.value) return
  const payload = await api.get<{ data: AiMessage[] }>(`/ai/conversations/${activeId.value}/messages`)
  messages.value = payload.data
}

async function onNewConversation(): Promise<void> {
  try {
    const payload = await api.post<{ conversation: AiConversation }>('/ai/conversations', {})
    conversations.value = [payload.conversation, ...conversations.value]
    await openConversation(payload.conversation.id)
  } catch (err) {
    streamError.value = (err as ApiError)?.message ?? 'Tạo hội thoại không thành công.'
  }
}

function canSend(): boolean {
  return question.value.trim().length >= 5 && !sending.value && activeId.value !== null
}

async function onSend(): Promise<void> {
  const text = question.value.trim()
  if (!canSend()) return
  question.value = ''
  streamError.value = null
  sending.value = true
  streamingContent.value = ''
  scrollBottom()

  cancelStream = streamSse(
    `/ai/conversations/${activeId.value}/ask`,
    { question: text, stream: true },
    {
      onDelta: (delta) => {
        streamingContent.value += delta
        scrollBottom()
      },
      onFinal: () => {
        void finish()
      },
      onError: (err) => {
        streamError.value = err.message
        void finish()
      },
    },
  )
}

/** Luồng xong hoặc lỗi: nạp lại tin nhắn đã được backend lưu, tránh trùng lặp. */
async function finish(): Promise<void> {
  cancelStream = null
  streamingContent.value = ''
  sending.value = false
  try {
    await reloadMessages()
    await loadConversations()
  } catch (err) {
    streamError.value = (err as ApiError)?.message ?? 'Chưa nạp được tin nhắn.'
  }
  scrollBottom()
}

function onStop(): void {
  cancelStream?.()
  cancelStream = null
  void finish()
}

onMounted(() => {
  void loadConversations()
})

onUnmounted(() => {
  cancelStream?.()
})
</script>

<template>
  <div>
    <PageHeader
      title="Trợ lý pháp lý AI"
      subtitle="Hỏi đáp về nghĩa vụ pháp lý, căn cứ theo văn bản hiệu lực tại ngày đánh giá."
    >
      <template #actions>
        <UiButton variant="secondary" :loading="listLoading" @click="onNewConversation">
          <MessageSquarePlus class="size-4" aria-hidden="true" /> Hội thoại mới
        </UiButton>
      </template>
    </PageHeader>

    <div class="grid gap-4 lg:grid-cols-[20rem_minmax(0,1fr)]">
      <!-- Danh sách hội thoại -->
      <UiCard padding="none" class="min-h-[28rem]">
        <UiSkeleton v-if="listLoading" :lines="6" class="p-5" />
        <Callout
          v-else-if="listError"
          icon="warning"
          :title="listError"
          action-label="Thử lại"
          class="m-5"
          @action="loadConversations()"
        />
        <EmptyState
          v-else-if="conversations.length === 0"
          :icon="Sparkles"
          title="Chưa có hội thoại nào"
          body="Bắt đầu cuộc trò chuyện để đặt câu hỏi về nghĩa vụ pháp lý của dự án."
          action-label="Tạo hội thoại"
          class="m-5"
          @action="onNewConversation"
        />
        <ul v-else>
          <li
            v-for="conversation in conversations"
            :key="conversation.id"
          >
            <button
              type="button"
              class="flex w-full items-center gap-3 border-b border-line px-5 py-3.5 text-left transition-colors hover:bg-surface-2"
              :class="conversation.id === activeId ? 'bg-accent/8' : ''"
              @click="openConversation(conversation.id)"
            >
              <span
                class="size-1.5 shrink-0 rounded-full"
                :class="conversation.id === activeId ? 'bg-accent' : 'bg-line-strong'"
                aria-hidden="true"
              />
              <span class="min-w-0 flex-1">
                <span class="block truncate text-[13.5px] font-medium text-ink">{{ conversation.title }}</span>
                <span class="tnum mt-0.5 block text-[11.5px] text-ink-muted">
                  {{ formatDateTime(conversation.updated_at) }}
                </span>
              </span>
            </button>
          </li>
        </ul>
      </UiCard>

      <!-- Khung chat -->
      <UiCard padding="none" class="flex min-h-[28rem] flex-col">
        <template #header>
          <div class="flex min-w-0 items-center justify-between gap-4">
            <h3 class="truncate text-[15px] font-semibold leading-tight tracking-[-0.005em] text-ink">
              {{ activeConversation?.title ?? 'Chưa chọn hội thoại' }}
            </h3>
            <StatusChip v-if="sending" tone="info" label="Đang trả lời" />
          </div>
        </template>

        <div ref="thread" class="flex-1 overflow-y-auto px-5 py-4">
          <UiSkeleton v-if="chatLoading" :lines="5" />
          <EmptyState
            v-else-if="visibleMessages.length === 0 && activeConversation"
            :icon="Sparkles"
            title="Đặt câu hỏi pháp lý đầu tiên"
            body="VD: Dự án thương mại điện tử có nghĩa vụ đăng ký ngành hàng nào?"
            class="border-0 bg-transparent"
          />
          <EmptyState
            v-else-if="!activeConversation"
            :icon="Sparkles"
            title="Chưa có hội thoại"
            body="Tạo hội thoại mới để bắt đầu trò chuyện với trợ lý."
            action-label="Tạo hội thoại"
            class="border-0 bg-transparent"
            @action="onNewConversation"
          />
          <ul v-else class="flex flex-col gap-5">
            <li
              v-for="message in visibleMessages"
              :key="message.id"
              :class="message.role === 'user' ? 'flex justify-end' : 'flex justify-start'"
            >
              <div :class="message.role === 'user' ? 'max-w-[80%] rounded-lg bg-surface-2 p-3.5' : 'max-w-[88%]'">
                <Callout
                  v-if="message.refused"
                  tone="caution"
                  icon="warning"
                  title="Trợ lý từ chối trả lời"
                  class="mb-2"
                >
                  {{ message.content }}
                </Callout>
                <p v-else class="whitespace-pre-wrap text-[13.5px] leading-relaxed text-ink">{{ message.content }}</p>

                <div v-if="citationsOf(message).length > 0" class="mt-3 flex flex-col gap-3">
                  <LegalCitation
                    v-for="(citation, index) in citationsOf(message)"
                    :key="index"
                    :citation="citation.citation"
                    :document-number="citation.documentNumber"
                    :document-title="citation.documentTitle"
                    :quote="citation.quote"
                    :official-url="citation.officialUrl"
                    :effective-from="citation.effectiveFrom"
                  />
                </div>

                <div v-if="message.categories" class="mt-3 flex flex-wrap gap-1.5">
                  <StatusChip
                    v-for="(values, category) in message.categories"
                    :key="category"
                    tone="neutral"
                    :label="`${String(category)}: ${values.join(', ')}`"
                  />
                </div>

                <p
                  v-if="message.role === 'assistant' && message.model"
                  class="tnum mt-2 text-[11px] text-ink-faint"
                >
                  {{ formatDateTime(message.created_at) }} · {{ message.model }}
                </p>
              </div>
            </li>
          </ul>
        </div>

        <div class="border-t border-line p-4">
          <Callout
            v-if="streamError"
            tone="caution"
            icon="warning"
            :title="streamError"
            action-label="Nạp lại"
            class="mb-3"
            @action="reloadMessages()"
          />
          <form class="flex items-end gap-3" @submit.prevent="onSend" @keydown.enter.meta.prevent="onSend" @keydown.enter.ctrl.prevent="onSend">
            <UiTextarea
              v-model="question"
              :rows="2"
              wrapper-class="flex-1"
              placeholder="Đặt câu hỏi pháp lý (tối thiểu 5 ký tự)…"
            />
            <UiButton v-if="sending" variant="secondary" type="button" @click="onStop">
              <Square class="size-4" aria-hidden="true" /> Dừng
            </UiButton>
            <UiButton v-else variant="primary" type="submit" :disabled="!canSend()">
              <Send class="size-4" aria-hidden="true" /> Gửi
            </UiButton>
          </form>
          <p class="mt-2 text-[11.5px] text-ink-faint">Nhấn Ctrl/⌘ + Enter để gửi nhanh.</p>
        </div>
      </UiCard>
    </div>
  </div>
</template>
