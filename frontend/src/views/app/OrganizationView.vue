<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { Building2, Mail, Trash2, UserPlus, Users } from '@lucide/vue'
import PageHeader from '@/components/common/PageHeader.vue'
import UiCard from '@/components/ui/UiCard.vue'
import UiInput from '@/components/ui/UiInput.vue'
import UiSelect from '@/components/ui/UiSelect.vue'
import UiButton from '@/components/ui/UiButton.vue'
import StatusChip from '@/components/ui/StatusChip.vue'
import UiSkeleton from '@/components/ui/UiSkeleton.vue'
import Callout from '@/components/ui/Callout.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import UiTable from '@/components/ui/UiTable.vue'
import UiDialog from '@/components/ui/UiDialog.vue'
import { api, type ApiError } from '@/lib/api'
import { formatDate } from '@/lib/format'
import { useAuthStore } from '@/stores/auth'
import { useToastsStore } from '@/stores/toasts'

type ChipMeta = { tone: 'neutral' | 'ok' | 'caution' | 'info' | 'expert' | 'danger'; label: string }

const ROLE_META: Record<string, ChipMeta> = {
  OWNER: { tone: 'expert', label: 'Chủ sở hữu' },
  ADMIN: { tone: 'info', label: 'Quản trị' },
  MEMBER: { tone: 'ok', label: 'Thành viên' },
}

function roleMeta(role: string): ChipMeta {
  return ROLE_META[role] ?? { tone: 'neutral', label: role }
}

interface OrgSummary {
  id: string
  name: string
  is_personal: boolean
  projects_count?: number
  pivot?: { role: string }
}

interface OrgMember {
  id: string
  user_id: string
  role: string
  user: { id: string; name: string; email: string }
}

interface OrgInvitation {
  email: string
  role: string
  expires_at: string
}

interface OrgDetail {
  id: string
  name: string
  is_personal: boolean
  members: OrgMember[]
  invitations: OrgInvitation[]
}

const auth = useAuthStore()
const toasts = useToastsStore()

const orgs = ref<OrgSummary[]>([])
const activeOrgId = ref<string | null>(null)
const detail = ref<OrgDetail | null>(null)

const loadingOrgs = ref(true)
const orgsError = ref<string | null>(null)
const loadingDetail = ref(false)
const detailError = ref<string | null>(null)

const renameValue = ref('')
const renaming = ref(false)
const renameError = ref<string | null>(null)

const inviteOpen = ref(false)
const inviteEmail = ref('')
const inviteRole = ref('MEMBER')
const inviting = ref(false)
const inviteErrors = ref<Record<string, string>>({})

const acceptToken = ref('')
const accepting = ref(false)
const acceptError = ref<string | null>(null)

const removeTarget = ref<OrgMember | null>(null)
const removing = ref(false)

const membersColumns = [
  { key: 'name', label: 'Thành viên' },
  { key: 'role', label: 'Vai trò' },
  { key: 'actions', label: '', align: 'right' as const },
]

const INVITE_ROLES = [
  { value: 'VIEWER', label: 'Chỉ xem' },
  { value: 'MEMBER', label: 'Thành viên' },
  { value: 'ADMIN', label: 'Quản trị' },
]

const currentUserMember = computed<OrgMember | null>(
  () => detail.value?.members.find((m) => m.user_id === auth.user?.id) ?? null,
)

const canManage = computed(() => currentUserMember.value?.role === 'OWNER')
const canInvite = computed(() => ['OWNER', 'ADMIN'].includes(currentUserMember.value?.role ?? ''))

const activeOrg = computed<OrgSummary | null>(
  () => orgs.value.find((o) => o.id === activeOrgId.value) ?? null,
)

function pickError(err: unknown, fallback: string): string {
  const apiError = err as ApiError
  const messages = apiError?.details?.email
  return (
    (Array.isArray(messages) && typeof messages[0] === 'string' && messages[0]) ||
    apiError?.message ||
    fallback
  )
}

async function loadOrgs(): Promise<void> {
  loadingOrgs.value = true
  orgsError.value = null
  try {
    const payload = await api.get<{ data: OrgSummary[] }>('/organizations')
    orgs.value = payload.data
    const next = orgs.value.find((o) => o.is_personal)?.id ?? orgs.value[0]?.id ?? null
    if (next && next !== activeOrgId.value) {
      activeOrgId.value = next
      await loadDetail()
    }
  } catch (err) {
    orgsError.value = (err as ApiError)?.message ?? 'Chưa tải được danh sách tổ chức.'
  } finally {
    loadingOrgs.value = false
  }
}

async function loadDetail(): Promise<void> {
  if (!activeOrgId.value) return
  loadingDetail.value = true
  detailError.value = null
  detail.value = null
  try {
    const payload = await api.get<{ organization: OrgDetail }>(`/organizations/${activeOrgId.value}`)
    detail.value = payload.organization
    renameValue.value = detail.value.name
  } catch (err) {
    detailError.value = (err as ApiError)?.message ?? 'Chưa tải được thông tin tổ chức.'
  } finally {
    loadingDetail.value = false
  }
}

async function onOpenOrg(id: string): Promise<void> {
  if (id === activeOrgId.value) return
  activeOrgId.value = id
  await loadDetail()
}

async function onRename(): Promise<void> {
  if (!activeOrgId.value || renaming.value) return
  renaming.value = true
  renameError.value = null
  try {
    await api.put(`/organizations/${activeOrgId.value}`, { name: renameValue.value.trim() })
    toasts.push('Đã đổi tên tổ chức', undefined, 'ok')
    await Promise.all([loadDetail(), loadOrgs()])
  } catch (err) {
    renameError.value = (err as ApiError)?.message ?? 'Đổi tên không thành công.'
  } finally {
    renaming.value = false
  }
}

function openInviteDialog(): void {
  inviteEmail.value = ''
  inviteRole.value = 'MEMBER'
  inviteErrors.value = {}
  inviteOpen.value = true
}

async function onInvite(): Promise<void> {
  if (!activeOrgId.value || inviting.value) return
  inviting.value = true
  inviteErrors.value = {}
  try {
    await api.post(`/organizations/${activeOrgId.value}/invite`, {
      email: inviteEmail.value.trim(),
      role: inviteRole.value,
    })
    toasts.push('Đã gửi lời mời', `Lời mời đã gửi tới ${inviteEmail.value.trim()}.`, 'ok')
    inviteOpen.value = false
    await loadDetail()
  } catch (err) {
    inviteErrors.value = { email: pickError(err, 'Mời thành viên không thành công.') }
  } finally {
    inviting.value = false
  }
}

async function onAccept(): Promise<void> {
  if (acceptToken.value.trim().length !== 40 || accepting.value) return
  accepting.value = true
  acceptError.value = null
  try {
    const payload = await api.post<{ organization: OrgDetail }>('/organizations/invitations/accept', {
      token: acceptToken.value.trim(),
    })
    toasts.push('Đã tham gia tổ chức', payload.organization.name, 'ok')
    acceptToken.value = ''
    await Promise.all([loadOrgs(), loadDetail()])
  } catch (err) {
    acceptError.value = (err as ApiError)?.message ?? 'Mã lời mời không hợp lệ hoặc đã hết hạn.'
  } finally {
    accepting.value = false
  }
}

function openRemoveDialog(member: OrgMember): void {
  removeTarget.value = member
}

async function onConfirmRemove(): Promise<void> {
  if (!activeOrgId.value || !removeTarget.value || removing.value) return
  removing.value = true
  try {
    await api.del(`/organizations/${activeOrgId.value}/members/${removeTarget.value.user_id}`)
    toasts.push('Đã xóa thành viên', removeTarget.value.user.name, 'ok')
    removeTarget.value = null
    await loadDetail()
  } catch (err) {
    toasts.push('Chưa xóa được thành viên', (err as ApiError)?.message, 'danger')
  } finally {
    removing.value = false
  }
}

function invitationStatus(invitation: OrgInvitation): ChipMeta {
  const expired = new Date(invitation.expires_at).getTime() < Date.now()
  return expired
    ? { tone: 'neutral', label: 'Đã hết hạn' }
    : { tone: 'caution', label: 'Đang chờ chấp nhận' }
}

onMounted(() => {
  void loadOrgs()
})
</script>

<template>
  <div>
    <PageHeader
      title="Tổ chức"
      subtitle="Quản lý thành viên, vai trò và lời mời tham gia tổ chức của bạn."
    />

    <Callout
      v-if="orgsError"
      tone="danger"
      icon="warning"
      :title="orgsError"
      action-label="Thử lại"
      class="mb-4"
      @action="loadOrgs()"
    />
    <div v-else-if="loadingOrgs" class="grid gap-4 lg:grid-cols-[18rem_minmax(0,1fr)]">
      <UiCard><UiSkeleton :lines="4" /></UiCard>
      <UiCard><UiSkeleton :lines="6" /></UiCard>
    </div>
    <div v-else class="grid gap-4 lg:grid-cols-[18rem_minmax(0,1fr)]">
      <!-- Chọn tổ chức -->
      <UiCard padding="none">
        <template #header>
          <h3 class="text-[15px] font-semibold leading-tight tracking-[-0.005em] text-ink">Tổ chức của bạn</h3>
        </template>
        <ul>
          <li v-for="org in orgs" :key="org.id">
            <button
              type="button"
              class="flex w-full items-center gap-3 border-b border-line px-5 py-3.5 text-left transition-colors hover:bg-surface-2"
              :class="org.id === activeOrgId ? 'bg-accent/8' : ''"
              @click="onOpenOrg(org.id)"
            >
              <Building2 class="size-4 shrink-0 text-ink-muted" aria-hidden="true" />
              <span class="min-w-0 flex-1">
                <span class="block truncate text-[13.5px] font-medium text-ink">{{ org.name }}</span>
                <span v-if="org.projects_count !== undefined" class="tnum mt-0.5 block text-[11.5px] text-ink-muted">
                  {{ org.projects_count }} dự án
                </span>
              </span>
              <StatusChip v-if="org.is_personal" tone="neutral" label="Cá nhân" />
            </button>
          </li>
        </ul>
      </UiCard>

      <!-- Chi tiết tổ chức -->
      <div class="flex min-w-0 flex-col gap-4">
        <UiCard>
          <Callout
            v-if="detailError"
            tone="danger"
            icon="warning"
            :title="detailError"
            action-label="Thử lại"
            @action="loadDetail()"
          />
          <UiSkeleton v-else-if="loadingDetail || !detail" :lines="3" />
          <template v-else>
            <div class="flex flex-wrap items-end justify-between gap-x-6 gap-y-3">
              <div class="min-w-0">
                <h3 class="text-[15px] font-semibold leading-tight tracking-[-0.005em] text-ink">{{ detail.name }}</h3>
                <p class="mt-1 text-[13px] text-ink-muted">
                  {{ detail.members.length }} thành viên
                  <span v-if="detail.is_personal"> · tổ chức cá nhân</span>
                </p>
              </div>
              <div class="flex flex-wrap items-center gap-2">
                <UiButton
                  v-if="canInvite"
                  variant="secondary"
                  @click="openInviteDialog"
                >
                  <UserPlus class="size-4" aria-hidden="true" /> Mời thành viên
                </UiButton>
              </div>
            </div>

            <form
              v-if="canManage"
              class="mt-5 flex flex-wrap items-end gap-3 border-t border-line pt-5"
              @submit.prevent="onRename"
            >
              <UiInput
                v-model="renameValue"
                label="Đổi tên tổ chức"
                wrapper-class="min-w-[16rem] flex-1"
                :error="renameError ?? undefined"
              />
              <UiButton variant="primary" type="submit" :loading="renaming"> Lưu tên </UiButton>
            </form>
          </template>
        </UiCard>

        <!-- Thành viên -->
        <UiCard padding="none">
          <template #header>
            <h3 class="text-[15px] font-semibold leading-tight tracking-[-0.005em] text-ink">Thành viên</h3>
          </template>
          <UiSkeleton v-if="loadingDetail" :lines="4" class="p-5" />
          <EmptyState
            v-else-if="detail && detail.members.length === 0"
            :icon="Users"
            title="Chưa có thành viên nào"
            body="Mời đồng nghiệp tham gia để cùng quản lý dự án và nghĩa vụ pháp lý."
            class="m-5"
          />
          <UiTable
            v-else-if="detail"
            :columns="membersColumns"
            :rows="detail.members as unknown as Array<Record<string, unknown>>"
            class="px-5"
          >
            <template #cell-name="{ row }">
              <div class="min-w-0">
                <p class="truncate font-medium text-ink">
                  {{ (row as unknown as OrgMember).user.name }}
                  <span v-if="(row as unknown as OrgMember).user_id === auth.user?.id" class="text-[12px] font-normal text-ink-muted">(bạn)</span>
                </p>
                <p class="truncate text-[12px] text-ink-muted">{{ (row as unknown as OrgMember).user.email }}</p>
              </div>
            </template>
            <template #cell-role="{ row }">
              <StatusChip v-bind="roleMeta((row as unknown as OrgMember).role)" />
            </template>
            <template #cell-actions="{ row }">
              <UiButton
                v-if="canManage && (row as unknown as OrgMember).user_id !== auth.user?.id"
                size="sm"
                variant="ghost"
                aria-label="Xóa thành viên"
                @click="openRemoveDialog(row as unknown as OrgMember)"
              >
                <Trash2 class="size-4" aria-hidden="true" />
              </UiButton>
            </template>
          </UiTable>
        </UiCard>

        <!-- Lời mời đang chờ -->
        <UiCard v-if="detail && detail.invitations.length > 0" padding="none">
          <template #header>
            <h3 class="text-[15px] font-semibold leading-tight tracking-[-0.005em] text-ink">Lời mời đã gửi</h3>
          </template>
          <ul class="px-5 py-2">
            <li
              v-for="(invitation, index) in detail.invitations"
              :key="index"
              class="flex items-center justify-between gap-4 border-b border-line py-3 last:border-0"
            >
              <div class="min-w-0">
                <p class="flex items-center gap-2 truncate text-[13.5px] font-medium text-ink">
                  <Mail class="size-3.5 shrink-0 text-ink-muted" aria-hidden="true" />
                  {{ invitation.email }}
                </p>
                <p class="tnum mt-0.5 text-[11.5px] text-ink-muted">Hết hạn {{ formatDate(invitation.expires_at) }}</p>
              </div>
              <div class="flex items-center gap-2">
                <StatusChip v-bind="roleMeta(invitation.role)" />
                <StatusChip v-bind="invitationStatus(invitation)" />
              </div>
            </li>
          </ul>
        </UiCard>

        <!-- Chấp nhận lời mời -->
        <UiCard>
          <h3 class="text-[15px] font-semibold leading-tight tracking-[-0.005em] text-ink">Chấp nhận lời mời</h3>
          <p class="mt-1 text-[13px] text-ink-muted">
            Dán mã lời mời nhận được qua email để tham gia tổ chức.
          </p>
          <form class="mt-4 flex flex-wrap items-end gap-3" @submit.prevent="onAccept">
            <UiInput
              v-model="acceptToken"
              label="Mã lời mời"
              placeholder="40 ký tự"
              wrapper-class="min-w-[16rem] flex-1"
              :error="acceptError ?? undefined"
            />
            <UiButton variant="primary" type="submit" :loading="accepting" :disabled="acceptToken.trim().length !== 40">
              Tham gia
            </UiButton>
          </form>
        </UiCard>
      </div>
    </div>

    <UiDialog
      :open="inviteOpen"
      title="Mời thành viên"
      description="Thành viên được mời sẽ nhận email kèm liên kết tham gia tổ chức."
      size="sm"
      @update:open="inviteOpen = $event"
    >
      <form class="flex flex-col gap-4" @submit.prevent="onInvite">
        <UiInput
          v-model="inviteEmail"
          label="Email"
          placeholder="dong-nghiep@congty.vn"
          required
          :error="inviteErrors.email"
        />
        <UiSelect v-model="inviteRole" label="Vai trò" :options="INVITE_ROLES" />
      </form>
      <template #footer>
        <UiButton variant="ghost" type="button" @click="inviteOpen = false">Huỷ</UiButton>
        <UiButton variant="primary" :loading="inviting" @click="onInvite">Gửi lời mời</UiButton>
      </template>
    </UiDialog>

    <UiDialog
      :open="removeTarget !== null"
      title="Xóa thành viên"
      :description="`Xóa ${removeTarget?.user.name ?? ''} khỏi tổ chức ${activeOrg?.name ?? ''}. Hành động không thể hoàn tác.`"
      size="sm"
      @update:open="removeTarget = $event ? removeTarget : null"
    >
      <template #footer>
        <UiButton variant="ghost" type="button" @click="removeTarget = null">Huỷ</UiButton>
        <UiButton variant="danger" :loading="removing" @click="onConfirmRemove">Xóa thành viên</UiButton>
      </template>
    </UiDialog>
  </div>
</template>
