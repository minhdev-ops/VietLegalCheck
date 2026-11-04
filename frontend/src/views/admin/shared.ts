/**
 * Dùng chung cho khu vực quản trị: kiểu nghiệp vụ, ánh xạ trạng thái (SRS 9.3)
 * và composable danh sách phân trang. Tender domain types đồng bộ với backend API.
 */
import { onMounted, ref, watch, type Ref } from 'vue'
import { api, type ApiError, type Paginated } from '@/lib/api'

export type ChipTone = 'neutral' | 'ok' | 'caution' | 'info' | 'expert' | 'danger'

export interface StatusMeta {
  tone: ChipTone
  label: string
}

export interface Rule {
  id: string
  code: string
  title: string
  description: string | null
  domain: string | null
  status: string
  current_version: number
  failing_tests: number
  total_tests: number
  updated_at: string
}

export interface RuleVersion {
  id: string
  rule_id: string
  version: number
  status: string
  condition_json: Record<string, unknown>
  result_json: Record<string, unknown> | null
  notes: string | null
  created_at: string
  created_by: string | null
  test_summary: { total: number; failing: number } | null
}

export type RuleDetail = Rule & { versions: RuleVersion[] }

export interface TestCase {
  id: string
  rule_version_id: string
  name: string
  input_json: Record<string, unknown>
  expected_json: Record<string, unknown>
  passed: boolean | null
  last_run_at: string | null
  error: string | null
}

export interface RunTestsResult {
  total: number
  passed: number
  failing: number
  cases: TestCase[]
}

export interface ExpertReview {
  id: string
  item_type: string
  item_id: string
  title: string
  reason: string | null
  status: string
  assignee: string | null
  created_at: string
  resolved_at: string | null
}

export interface AuditEntry {
  id: string
  actor: string | null
  action: string
  resource_type: string | null
  resource_id: string | null
  summary: string | null
  ip: string | null
  created_at: string
}

export interface SettingPair {
  key: string
  value: string
  description: string | null
}

export interface AdminUser {
  id: string
  name: string
  email: string
  system_role: string
  email_verified_at: string | null
  created_at: string | null
  organization: string | null
}

export interface DashboardStats {
  pending_rules: number
  unverified_documents: number
  open_expert_reviews: number
}

/** Trạng thái là cụm từ không phải từ đơn (DESIGN.md — giọng văn). */
export const DOCUMENT_STATUS: Record<string, StatusMeta> = {
  IN_EFFECT: { tone: 'ok', label: 'Đang hiệu lực' },
  NOT_YET_EFFECTIVE: { tone: 'info', label: 'Chưa có hiệu lực' },
  EXPIRED: { tone: 'neutral', label: 'Hết hiệu lực' },
  SUPERSEDED: { tone: 'neutral', label: 'Đã thay thế' },
  REPEALED: { tone: 'danger', label: 'Đã bãi bỏ' },
  PENDING_REVIEW: { tone: 'caution', label: 'Chờ duyệt' },
}

export const RULE_STATUS: Record<string, StatusMeta> = {
  DRAFT: { tone: 'neutral', label: 'Bản nháp' },
  IN_REVIEW: { tone: 'caution', label: 'Đang duyệt' },
  APPROVED: { tone: 'ok', label: 'Đã duyệt' },
  PUBLISHED: { tone: 'ok', label: 'Đã áp dụng' },
  RETIRED: { tone: 'neutral', label: 'Đã nghỉ hưu' },
}

export const REVIEW_STATUS: Record<string, StatusMeta> = {
  PENDING: { tone: 'expert', label: 'Chờ chuyên gia' },
  IN_PROGRESS: { tone: 'info', label: 'Đang xử lý' },
  NEED_INFO: { tone: 'caution', label: 'Cần bổ sung thông tin' },
  RESOLVED: { tone: 'ok', label: 'Đã xử lý' },
}

export const ROLE_STATUS: Record<string, StatusMeta> = {
  USER: { tone: 'neutral', label: 'Người dùng' },
  LEGAL_EXPERT: { tone: 'expert', label: 'Chuyên gia pháp lý' },
  LEGAL_ADMIN: { tone: 'info', label: 'Quản trị pháp lý' },
  SYS_ADMIN: { tone: 'danger', label: 'Quản trị hệ thống' },
}

export const AUDIT_ACTION: Record<string, StatusMeta> = {
  CREATE: { tone: 'ok', label: 'Tạo mới' },
  UPDATE: { tone: 'info', label: 'Cập nhật' },
  DELETE: { tone: 'danger', label: 'Xoá' },
  VERIFY: { tone: 'ok', label: 'Xác minh' },
  SUBMIT: { tone: 'caution', label: 'Trình duyệt' },
  APPROVE: { tone: 'ok', label: 'Duyệt' },
  REJECT: { tone: 'danger', label: 'Từ chối' },
  LOGIN: { tone: 'neutral', label: 'Đăng nhập' },
  EXPORT: { tone: 'info', label: 'Xuất dữ liệu' },
}

export const DOC_TYPE_OPTIONS = [
  'Hiến pháp',
  'Luật',
  'Pháp lệnh',
  'Nghị định',
  'Thông tư',
  'Quyết định',
  'Chỉ thị',
  'Văn bản khác',
] as const

export function statusMeta(map: Record<string, StatusMeta>, status: string): StatusMeta {
  return map[status] ?? { tone: 'neutral', label: status }
}

export function toSelectOptions(map: Record<string, StatusMeta>) {
  return Object.entries(map).map(([value, meta]) => ({ value, label: meta.label }))
}

/**
 * Danh sách phân trang: page & per_page do composable nắm, filter/q do view giữ.
 * Watch params debounce 300ms — gõ tìm kiếm không vỡ trang.
 */
export function usePaginated<
  T,
  P extends Record<string, string | number | undefined> = Record<string, string>,
>(path: string, params: Ref<P>, perPage = 10) {
  const items = ref<T[]>([])
  const total = ref(0)
  const page = ref(1)
  const lastPage = ref(1)
  const loading = ref(false)
  const error = ref<ApiError | null>(null)

  async function load(): Promise<void> {
    loading.value = true
    error.value = null
    try {
      const payload = await api.get<Paginated<T>>(path, {
        page: page.value,
        per_page: perPage,
        ...params.value,
      })
      items.value = payload.data
      total.value = payload.total
      lastPage.value = payload.last_page
    } catch (e) {
      error.value = e as ApiError
      items.value = []
    } finally {
      loading.value = false
    }
  }

  let timer: ReturnType<typeof setTimeout> | undefined
  watch(
    params,
    () => {
      clearTimeout(timer)
      timer = setTimeout(() => {
        page.value = 1
        load()
      }, 300)
    },
    { deep: true },
  )
  watch(page, () => load())
  onMounted(() => load())

  function reload(): void {
    if (page.value === 1) load()
    else page.value = 1
  }

  return { items, total, page, lastPage, loading, error, load, reload }
}

/** Chuỗi rỗng → null cho trường tuỳ chọn dạng ISO. */
export function emptyToNull(value: string): string | null {
  return value === '' ? null : value
}
