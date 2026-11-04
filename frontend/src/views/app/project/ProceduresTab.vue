<script setup lang="ts">
/**
 * Thủ tục hành chính: danh sách thủ tục đang theo dõi, thêm thủ tục theo id
 * và đánh dấu hoàn thành / cập nhật trạng thái.
 */
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { CheckCircle2, ExternalLink, Plus } from '@lucide/vue'
import PageHeader from '@/components/common/PageHeader.vue'
import Callout from '@/components/ui/Callout.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import StatusChip from '@/components/ui/StatusChip.vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiCard from '@/components/ui/UiCard.vue'
import UiDialog from '@/components/ui/UiDialog.vue'
import UiInput from '@/components/ui/UiInput.vue'
import UiSelect from '@/components/ui/UiSelect.vue'
import UiSkeleton from '@/components/ui/UiSkeleton.vue'
import UiTable from '@/components/ui/UiTable.vue'
import UiTextarea from '@/components/ui/UiTextarea.vue'
import { useToastsStore } from '@/stores/toasts'
import { api, type ApiError } from '@/lib/api'
import type { AdministrativeProcedure, ProjectProcedure } from '@/types/models'
import { PROCEDURE_STATUS, fetchList, statusMeta, toSelectOptions } from './shared'

const route = useRoute()
const toasts = useToastsStore()
const id = computed(() => String(route.params.id))

const loading = ref(true)
const error = ref<ApiError | null>(null)
const tracked = ref<ProjectProcedure[]>([])
const catalog = ref<AdministrativeProcedure[]>([])

const columns = [
  { key: 'name', label: 'Thủ tục' },
  { key: 'authority', label: 'Cơ quan', width: '11rem' },
  { key: 'status', label: 'Trạng thái', width: '10rem' },
  { key: 'submitted_at', label: 'Nộp hồ sơ', width: '9rem' },
  { key: 'completed_at', label: 'Hoàn thành', width: '9rem' },
  { key: 'actions', label: '', align: 'right' as const, width: '11rem' },
]

const rows = computed(() =>
  tracked.value.map((item) => ({
    id: item.id,
    name: item.procedure.name,
    code: item.procedure.code,
    authority: item.procedure.authority,
    status: item.status,
    submitted_at: item.submitted_at,
    completed_at: item.completed_at,
    portal_url: item.procedure.portal_url,
    item,
  })),
)

async function load(): Promise<void> {
  loading.value = true
  error.value = null
  try {
    const [items, catalogItems] = await Promise.all([
      fetchList<ProjectProcedure>(`/projects/${id.value}/procedures`),
      fetchList<AdministrativeProcedure>('/procedures'),
    ])
    tracked.value = items
    catalog.value = catalogItems
  } catch (e) {
    error.value = e as ApiError
  } finally {
    loading.value = false
  }
}

/* ---- Thêm thủ tục theo id ---- */
const addOpen = ref(false)
const addId = ref('')
const addNotes = ref('')
const addError = ref<string | null>(null)
const adding = ref(false)

const catalogOptions = computed(() =>
  catalog.value
    .filter((procedure) => !tracked.value.some((item) => item.procedure.id === procedure.id))
    .map((procedure) => ({ value: procedure.id, label: `${procedure.code} — ${procedure.name}` })),
)

function openAdd(): void {
  addId.value = catalogOptions.value[0]?.value ?? ''
  addNotes.value = ''
  addError.value = null
  addOpen.value = true
}

async function submitAdd(): Promise<void> {
  if (!addId.value.trim()) {
    addError.value = 'Chọn hoặc nhập id thủ tục cần thêm.'
    return
  }
  adding.value = true
  addError.value = null
  try {
    await api.post(`/projects/${id.value}/procedures`, {
      procedure_id: addId.value.trim(),
      notes: addNotes.value || undefined,
    })
    toasts.push('Đã thêm thủ tục', undefined, 'ok')
    addOpen.value = false
    await load()
  } catch (e) {
    addError.value = (e as ApiError).message
  } finally {
    adding.value = false
  }
}

/* ---- Cập nhật trạng thái ---- */
const editing = ref<ProjectProcedure | null>(null)
const editOpen = ref(false)
const editStatus = ref('')
const editNotes = ref('')
const editDate = ref('')
const editError = ref<string | null>(null)
const saving = ref(false)

const STATUS_OPTIONS = toSelectOptions(PROCEDURE_STATUS)

function openEdit(item: ProjectProcedure): void {
  editing.value = item
  editStatus.value = item.status
  editNotes.value = item.notes ?? ''
  editDate.value = item.status === 'COMPLETED' ? (item.completed_at ?? '') : (item.submitted_at ?? '')
  editError.value = null
  editOpen.value = true
}

async function saveEdit(): Promise<void> {
  if (!editing.value) return
  saving.value = true
  editError.value = null
  try {
    const payload: Record<string, unknown> = { status: editStatus.value, notes: editNotes.value || undefined }
    if (editStatus.value === 'COMPLETED') payload.completed_at = editDate.value || undefined
    else if (editStatus.value === 'SUBMITTED') payload.submitted_at = editDate.value || undefined
    await api.patch(`/projects/${id.value}/procedures/${editing.value.id}`, payload)
    toasts.push('Đã cập nhật thủ tục', undefined, 'ok')
    editOpen.value = false
    await load()
  } catch (e) {
    editError.value = (e as ApiError).message
  } finally {
    saving.value = false
  }
}

const completing = ref<string | null>(null)

async function markComplete(item: ProjectProcedure): Promise<void> {
  completing.value = item.id
  try {
    await api.patch(`/projects/${id.value}/procedures/${item.id}`, {
      status: 'COMPLETED',
      completed_at: new Date().toISOString().slice(0, 10),
    })
    toasts.push('Đã đánh dấu hoàn thành', item.procedure.name, 'ok')
    await load()
  } catch (e) {
    toasts.push('Cập nhật không thành công', (e as ApiError).message, 'danger')
  } finally {
    completing.value = null
  }
}

onMounted(load)
</script>

<template>
  <div>
    <PageHeader
      title="Thủ tục hành chính"
      subtitle="Theo dõi các thủ tục áp dụng và đánh dấu trạng thái hoàn thành."
    >
      <template #actions>
        <UiButton variant="primary" @click="openAdd()">
          <Plus class="size-4" aria-hidden="true" />
          Thêm thủ tục
        </UiButton>
      </template>
    </PageHeader>

    <UiCard padding="none">
      <UiSkeleton v-if="loading" :lines="4" class="p-5" />
      <Callout
        v-else-if="error"
        tone="danger"
        icon="warning"
        :title="error.message"
        action-label="Thử lại"
        class="m-5"
        @action="load()"
      />
      <EmptyState
        v-else-if="!rows.length"
        title="Chưa theo dõi thủ tục nào"
        body="Thêm thủ tục hành chính áp dụng cho dự án để theo dõi tiến độ."
        action-label="Thêm thủ tục"
        class="m-5"
        @action="openAdd()"
      />
      <UiTable v-else :columns="columns" :rows="rows">
        <template #cell-name="{ row }">
          <div class="flex flex-col gap-0.5 py-1">
            <span class="text-[13.5px] font-medium text-ink">{{ row.name as string }}</span>
            <div class="flex flex-wrap items-center gap-2">
              <code class="rounded-sm bg-surface-2 px-1.5 py-0.5 text-[11px] text-ink-muted">{{ row.code as string }}</code>
              <a
                v-if="row.portal_url"
                :href="String(row.portal_url)"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex items-center gap-1 text-[12.5px] font-medium text-accent hover:underline underline-offset-2"
              >
                Cổng tiếp nhận <ExternalLink class="size-3" aria-hidden="true" />
              </a>
            </div>
            <span v-if="(row.item as unknown as ProjectProcedure).notes" class="text-[12.5px] text-ink-muted">
              {{ (row.item as unknown as ProjectProcedure).notes }}
            </span>
          </div>
        </template>
        <template #cell-status="{ value }">
          <StatusChip :tone="statusMeta(PROCEDURE_STATUS, String(value)).tone" :label="statusMeta(PROCEDURE_STATUS, String(value)).label" />
        </template>
        <template #cell-actions="{ row }">
          <div class="flex justify-end gap-1">
            <UiButton v-if="(row.item as unknown as ProjectProcedure).status !== 'COMPLETED'" variant="quiet" size="sm" :loading="completing === (row.item as unknown as ProjectProcedure).id" @click="markComplete(row.item as unknown as ProjectProcedure)">
              <CheckCircle2 class="size-3.5" aria-hidden="true" />
              Hoàn thành
            </UiButton>
            <UiButton variant="quiet" size="sm" @click="openEdit(row.item as unknown as ProjectProcedure)">
              Cập nhật
            </UiButton>
          </div>
        </template>
      </UiTable>
    </UiCard>

    <UiDialog
      :open="addOpen"
      title="Thêm thủ tục"
      description="Chọn thủ tục từ danh sách hoặc dán id thủ tục để theo dõi."
      size="md"
      @update:open="addOpen = $event"
    >
      <form class="flex flex-col gap-4" @submit.prevent="submitAdd()">
        <UiSelect
          v-model="addId"
          :options="catalogOptions"
          label="Thủ tục hành chính"
          placeholder="Chọn thủ tục"
        />
        <UiInput v-model="addId" label="Hoặc nhập id thủ tục" hint="Mã định danh UUID của thủ tục." />
        <UiTextarea v-model="addNotes" label="Ghi chú" :rows="3" />
        <Callout v-if="addError" tone="danger" icon="warning" :title="addError" />
      </form>
      <template #footer>
        <UiButton variant="secondary" :disabled="adding" @click="addOpen = false">Đóng</UiButton>
        <UiButton variant="primary" :loading="adding" @click="submitAdd()">Thêm vào dự án</UiButton>
      </template>
    </UiDialog>

    <UiDialog
      :open="editOpen"
      title="Cập nhật thủ tục"
      :description="editing?.procedure.name"
      size="md"
      @update:open="editOpen = $event"
    >
      <form class="flex flex-col gap-4" @submit.prevent="saveEdit()">
        <UiSelect v-model="editStatus" :options="STATUS_OPTIONS" label="Trạng thái" />
        <label class="flex flex-col gap-1.5">
          <span class="text-[13px] font-medium text-ink">Ngày nộp hồ sơ / hoàn thành</span>
          <input
            v-model="editDate"
            type="date"
            class="h-9 w-full rounded-md border border-line-strong bg-surface px-3 text-sm text-ink focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/20"
          />
        </label>
        <UiTextarea v-model="editNotes" label="Ghi chú" :rows="3" />
        <Callout v-if="editError" tone="danger" icon="warning" :title="editError" />
      </form>
      <template #footer>
        <UiButton variant="secondary" :disabled="saving" @click="editOpen = false">Đóng</UiButton>
        <UiButton variant="primary" :loading="saving" @click="saveEdit()">Lưu cập nhật</UiButton>
      </template>
    </UiDialog>
  </div>
</template>
