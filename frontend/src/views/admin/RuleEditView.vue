<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ArrowLeft, Check, CheckCheck, Play, Send, X } from '@lucide/vue'
import PageHeader from '@/components/common/PageHeader.vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiCard from '@/components/ui/UiCard.vue'
import UiInput from '@/components/ui/UiInput.vue'
import UiTextarea from '@/components/ui/UiTextarea.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import StatusChip from '@/components/ui/StatusChip.vue'
import UiSkeleton from '@/components/ui/UiSkeleton.vue'
import Callout from '@/components/ui/Callout.vue'
import { useAuthStore } from '@/stores/auth'
import { useToastsStore } from '@/stores/toasts'
import { api, type ApiError } from '@/lib/api'
import { formatDateTime } from '@/lib/format'
import {
  RULE_STATUS,
  statusMeta,
  type RuleDetail,
  type RuleVersion,
  type RunTestsResult,
  type TestCase,
} from './shared'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const toasts = useToastsStore()
const id = String(route.params.id)

const rule = ref<RuleDetail | null>(null)
const loading = ref(false)
const error = ref<ApiError | null>(null)

const selectedVersionId = ref<string | null>(null)
const cases = ref<TestCase[]>([])
const casesLoading = ref(false)
const casesError = ref<ApiError | null>(null)
const running = ref(false)
const acting = ref<string | null>(null)
const saving = ref(false)

const selected = computed<RuleVersion | null>(
  () => rule.value?.versions.find((version) => version.id === selectedVersionId.value) ?? null,
)

async function load(): Promise<void> {
  loading.value = true
  error.value = null
  try {
    const payload = await api.get<RuleDetail>(`/admin/rules/${id}`)
    rule.value = payload
    if (payload.versions.length > 0) {
      selectedVersionId.value = payload.versions[payload.versions.length - 1].id
      await loadCases()
    }
  } catch (e) {
    error.value = e as ApiError
  } finally {
    loading.value = false
  }
}

async function loadCases(): Promise<void> {
  if (!selectedVersionId.value) return
  casesLoading.value = true
  casesError.value = null
  try {
    cases.value = await api.get<TestCase[]>(`/admin/rule-versions/${selectedVersionId.value}/test-cases`)
  } catch (e) {
    casesError.value = e as ApiError
    cases.value = []
  } finally {
    casesLoading.value = false
  }
}

async function selectVersion(versionId: string): Promise<void> {
  if (versionId === selectedVersionId.value) return
  selectedVersionId.value = versionId
  await loadCases()
}

async function runTests(): Promise<void> {
  if (!selected.value) return
  running.value = true
  try {
    const result = await api.post<RunTestsResult>(`/admin/rule-versions/${selected.value.id}/run-tests`)
    cases.value = result.cases
    if (rule.value && selected.value) {
      selected.value.test_summary = { total: result.total, failing: result.failing }
      rule.value.failing_tests = result.failing
      rule.value.total_tests = result.total
    }
    toasts.push(
      result.failing === 0 ? 'Tất cả ca kiểm thử đều đạt' : `${result.failing} ca kiểm thử đang lỗi`,
      undefined,
      result.failing === 0 ? 'ok' : 'caution',
    )
  } catch (e) {
    toasts.push('Chưa chạy được ca kiểm thử', (e as ApiError).message, 'danger')
  } finally {
    running.value = false
  }
}

async function act(action: 'submit' | 'approve' | 'reject'): Promise<void> {
  if (!selected.value) return
  acting.value = action
  try {
    const version = await api.post<RuleVersion>(`/admin/rule-versions/${selected.value.id}/${action}`)
    if (rule.value) {
      const index = rule.value.versions.findIndex((item) => item.id === version.id)
      if (index >= 0) rule.value.versions[index] = version
      if (action !== 'submit') rule.value.status = version.status
    }
    toasts.push(
      action === 'submit' ? 'Đã trình duyệt phiên bản' : action === 'approve' ? 'Đã duyệt phiên bản' : 'Đã từ chối phiên bản',
      undefined,
      action === 'reject' ? 'danger' : 'ok',
    )
  } catch (e) {
    toasts.push('Thao tác chưa thành công', (e as ApiError).message, 'danger')
  } finally {
    acting.value = null
  }
}

/* Tạo phiên bản mới */
const newVersion = ref({ condition: '', result: '', notes: '' })
const newVersionErrors = ref<{ condition?: string; result?: string }>({})

function parseJson(input: string, field: 'condition' | 'result'): Record<string, unknown> | null {
  if (field === 'result' && input.trim() === '') return {}
  try {
    const parsed = JSON.parse(input)
    if (typeof parsed !== 'object' || parsed === null || Array.isArray(parsed)) {
      newVersionErrors.value[field] = 'Giá trị phải là một object JSON.'
      return null
    }
    return parsed as Record<string, unknown>
  } catch {
    newVersionErrors.value[field] = 'JSON không hợp lệ.'
    return null
  }
}

async function createVersion(): Promise<void> {
  if (!rule.value) return
  newVersionErrors.value = {}
  const condition = parseJson(newVersion.value.condition, 'condition')
  const result = newVersion.value.result.trim() === '' ? {} : parseJson(newVersion.value.result, 'result')
  if (!condition || !result) return
  saving.value = true
  try {
    const version = await api.post<RuleVersion>(`/admin/rules/${id}/versions`, {
      condition_json: condition,
      result_json: result,
      notes: newVersion.value.notes || null,
    })
    rule.value.versions.push(version)
    selectedVersionId.value = version.id
    newVersion.value = { condition: '', result: '', notes: '' }
    toasts.push(`Đã tạo phiên bản v${version.version}`, undefined, 'ok')
    await loadCases()
  } catch (e) {
    toasts.push('Chưa tạo được phiên bản', (e as ApiError).message, 'danger')
  } finally {
    saving.value = false
  }
}

const caseColumns = [
  { key: 'name', label: 'Ca kiểm thử' },
  { key: 'passed', label: 'Kết quả' },
  { key: 'last_run_at', label: 'Lần chạy cuối' },
  { key: 'error', label: 'Lỗi ghi nhận' },
]

const caseRows = computed(() =>
  cases.value.map((testCase) => ({
    name: testCase.name,
    passed: testCase.passed,
    last_run_at: testCase.last_run_at,
    error: testCase.error ?? '—',
  })),
)

load()
</script>

<template>
  <div>
    <button
      class="mb-4 inline-flex items-center gap-1.5 text-[13px] font-medium text-ink-muted hover:text-ink"
      @click="router.push({ name: 'admin-rules' })"
    >
      <ArrowLeft class="size-4" aria-hidden="true" />
      Bộ quy tắc
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
        :title="loading ? 'Đang tải quy tắc…' : rule ? `${rule.code} — ${rule.title}` : ''"
        :subtitle="rule?.description ?? undefined"
      >
        <template v-if="rule" #actions>
          <StatusChip v-bind="statusMeta(RULE_STATUS, rule.status)" />
        </template>
      </PageHeader>

      <UiSkeleton v-if="loading" :lines="5" class="p-5" />
      <div v-else-if="rule" class="grid grid-cols-1 gap-6 lg:grid-cols-[1.15fr_1fr]">
        <!-- Phiên bản + ca kiểm thử -->
        <div class="flex flex-col gap-6">
          <UiCard title="Các phiên bản" description="Chọn một phiên bản để xem và thao tác." padding="none">
            <ul class="divide-y divide-line">
              <li
                v-for="version in [...rule.versions].reverse()"
                :key="version.id"
                :class="[
                  'flex cursor-pointer items-center gap-4 px-5 py-3 transition-colors hover:bg-surface-2',
                  version.id === selectedVersionId && 'bg-surface-2',
                ]"
                @click="selectVersion(version.id)"
              >
                <span class="tnum text-[13.5px] font-semibold text-ink">v{{ version.version }}</span>
                <StatusChip v-bind="statusMeta(RULE_STATUS, version.status)" />
                <span class="tnum ml-auto text-[12.5px] text-ink-muted">
                  {{ version.test_summary ? `${version.test_summary.total - version.test_summary.failing}/${version.test_summary.total} ca đạt` : 'chưa chạy test' }}
                </span>
                <span class="tnum text-[12.5px] text-ink-muted">{{ formatDateTime(version.created_at) }}</span>
              </li>
            </ul>
          </UiCard>

          <UiCard padding="none">
            <template #header>
              <div class="flex flex-wrap items-center gap-2">
                <h3 class="text-[15px] font-semibold leading-tight tracking-[-0.005em] text-ink">
                  Ca kiểm thử
                  <span v-if="selected" class="tnum font-normal text-ink-muted">v{{ selected.version }}</span>
                </h3>
                <div class="ml-auto flex flex-wrap items-center gap-2">
                  <UiButton variant="secondary" size="sm" :loading="running" :disabled="!selected" @click="runTests()">
                    <Play class="size-3.5" aria-hidden="true" />
                    Chạy test
                  </UiButton>
                  <UiButton
                    v-if="selected && selected.status === 'DRAFT'"
                    variant="secondary"
                    size="sm"
                    :loading="acting === 'submit'"
                    @click="act('submit')"
                  >
                    <Send class="size-3.5" aria-hidden="true" />
                    Trình duyệt
                  </UiButton>
                  <template v-if="auth.isAdmin && selected && selected.status === 'IN_REVIEW'">
                    <UiButton variant="primary" size="sm" :loading="acting === 'approve'" @click="act('approve')">
                      <Check class="size-3.5" aria-hidden="true" />
                      Duyệt
                    </UiButton>
                    <UiButton variant="danger" size="sm" :loading="acting === 'reject'" @click="act('reject')">
                      <X class="size-3.5" aria-hidden="true" />
                      Từ chối
                    </UiButton>
                  </template>
                </div>
              </div>
            </template>

            <Callout
              v-if="casesError"
              tone="danger"
              icon="warning"
              :title="casesError.message"
              action-label="Thử lại"
              class="m-5"
              @action="loadCases()"
            />
            <UiSkeleton v-else-if="casesLoading" :lines="3" class="p-5" />
            <EmptyState
              v-else-if="caseRows.length === 0"
              :icon="CheckCheck"
              title="Chưa có ca kiểm thử"
              body="Phiên bản này chưa có ca kiểm thử nào. Chạy test để sinh kết quả hoặc tạo phiên bản mới."
              class="m-5"
            />
            <table v-else class="w-full border-collapse text-sm">
              <thead>
                <tr class="border-b border-line-strong">
                  <th
                    v-for="column in caseColumns"
                    :key="column.key"
                    class="py-2 pr-4 text-left text-[11px] font-semibold uppercase tracking-[0.06em] text-ink-muted"
                  >
                    {{ column.label }}
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(row, index) in caseRows" :key="index" class="border-b border-line">
                  <td class="py-2.5 pr-4 text-ink">{{ row.name }}</td>
                  <td class="py-2.5 pr-4">
                    <StatusChip v-if="row.passed === true" tone="ok" label="Đạt" />
                    <StatusChip v-else-if="row.passed === false" tone="danger" label="Lỗi" />
                    <StatusChip v-else tone="neutral" label="Chưa chạy" />
                  </td>
                  <td class="tnum py-2.5 pr-4 text-ink-muted">{{ formatDateTime(row.last_run_at as string | null) }}</td>
                  <td class="py-2.5 pr-4 text-ink-muted">{{ row.error }}</td>
                </tr>
              </tbody>
            </table>
          </UiCard>
        </div>

        <!-- Tạo phiên bản mới -->
        <div class="flex flex-col gap-6">
          <UiCard title="Tạo phiên bản mới" description="Sửa điều kiện và kết quả của quy tắc dưới dạng JSON.">
            <form class="flex flex-col gap-4" @submit.prevent="createVersion()">
              <UiTextarea
                v-model="newVersion.condition"
                label="Điều kiện (condition_json)"
                hint='Object JSON, ví dụ { "field": "value" }'
                :error="newVersionErrors.condition"
                :rows="8"
                required
              />
              <UiTextarea
                v-model="newVersion.result"
                label="Kết quả (result_json)"
                hint="Object JSON; để trống nếu quy tắc không sinh kết quả."
                :error="newVersionErrors.result"
                :rows="6"
              />
              <UiInput v-model="newVersion.notes" label="Ghi chú phiên bản" placeholder="Lý do sửa đổi…" />
              <UiButton variant="primary" block :loading="saving" @click="createVersion()">Tạo phiên bản</UiButton>
            </form>
          </UiCard>

          <UiCard v-if="selected" title="Chi tiết phiên bản đang chọn" padding="none">
            <dl class="grid grid-cols-2 gap-px overflow-hidden rounded-lg bg-line">
              <div class="bg-surface p-4">
                <dt class="text-[11px] font-semibold uppercase tracking-[0.06em] text-ink-muted">Phiên bản</dt>
                <dd class="tnum mt-1 text-[13.5px] font-semibold text-ink">v{{ selected.version }}</dd>
              </div>
              <div class="bg-surface p-4">
                <dt class="text-[11px] font-semibold uppercase tracking-[0.06em] text-ink-muted">Trạng thái</dt>
                <dd class="mt-1"><StatusChip v-bind="statusMeta(RULE_STATUS, selected.status)" /></dd>
              </div>
              <div class="bg-surface p-4">
                <dt class="text-[11px] font-semibold uppercase tracking-[0.06em] text-ink-muted">Người tạo</dt>
                <dd class="mt-1 text-[13.5px] text-ink">{{ selected.created_by ?? '—' }}</dd>
              </div>
              <div class="bg-surface p-4">
                <dt class="text-[11px] font-semibold uppercase tracking-[0.06em] text-ink-muted">Ngày tạo</dt>
                <dd class="tnum mt-1 text-[13.5px] text-ink">{{ formatDateTime(selected.created_at) }}</dd>
              </div>
              <div class="bg-surface p-4 col-span-2">
                <dt class="text-[11px] font-semibold uppercase tracking-[0.06em] text-ink-muted">Ghi chú</dt>
                <dd class="mt-1 text-[13.5px] text-ink-muted">{{ selected.notes ?? '—' }}</dd>
              </div>
            </dl>
          </UiCard>
        </div>
      </div>
    </template>
  </div>
</template>
