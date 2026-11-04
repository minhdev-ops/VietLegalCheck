/**
 * Dùng chung cho khu vực dự án: ánh xạ trạng thái (SRS 9.3) và composable hồ sơ
 * dự án được chia sẻ giữa ProjectShellView và 8 tab con. Tender domain types đồng
 */
import { inject, provide, type InjectionKey, type Ref } from 'vue'
import { api, type ApiError } from '@/lib/api'
import type { Project } from '@/types/models'

export type ChipTone = 'neutral' | 'ok' | 'caution' | 'info' | 'expert' | 'danger'

export interface StatusMeta {
  tone: ChipTone
  label: string
}

/** Trạng thái là cụm từ không phải từ đơn (DESIGN.md — giọng văn). */
export const PROJECT_STATUS: Record<string, StatusMeta> = {
  DRAFT: { tone: 'neutral', label: 'Bản nháp' },
  ACTIVE: { tone: 'ok', label: 'Đang hoạt động' },
  ARCHIVED: { tone: 'neutral', label: 'Đã lưu trữ' },
}

export const ASSESSMENT_STATUS: Record<string, StatusMeta> = {
  QUEUED: { tone: 'info', label: 'Đã xếp hàng' },
  RUNNING: { tone: 'caution', label: 'Đang chạy' },
  COMPLETED: { tone: 'ok', label: 'Đã hoàn thành' },
  FAILED: { tone: 'danger', label: 'Lỗi khi chạy' },
  CANCELLED: { tone: 'neutral', label: 'Đã huỷ' },
}

export const OVERALL_STATUS: Record<string, StatusMeta> = {
  ACTION_REQUIRED: { tone: 'caution', label: 'Có nghĩa vụ cần xử lý' },
  INCOMPLETE: { tone: 'info', label: 'Cần bổ sung thông tin' },
  NEEDS_EXPERT_REVIEW: { tone: 'expert', label: 'Cần chuyên gia xác nhận' },
  COMPLIANT: { tone: 'ok', label: 'Chưa phát hiện nghĩa vụ' },
}

export const APPLICABILITY: Record<string, StatusMeta> = {
  APPLICABLE: { tone: 'caution', label: 'Có khả năng áp dụng' },
  NOT_APPLICABLE: { tone: 'neutral', label: 'Không thuộc phạm vi áp dụng' },
  NEED_MORE_INFO: { tone: 'info', label: 'Cần bổ sung thông tin' },
  NEED_EXPERT_REVIEW: { tone: 'expert', label: 'Cần chuyên gia xác nhận' },
}

export const FULFILLMENT: Record<string, StatusMeta> = {
  NOT_STARTED: { tone: 'neutral', label: 'Chưa bắt đầu' },
  IN_PROGRESS: { tone: 'info', label: 'Đang thực hiện' },
  SELF_DECLARED_DONE: { tone: 'ok', label: 'Tự xác nhận hoàn thành' },
  EVIDENCE_ATTACHED: { tone: 'ok', label: 'Đã đính kèm bằng chứng' },
  EXPERT_VERIFIED: { tone: 'expert', label: 'Chuyên gia đã xác minh' },
}

export const SEVERITY: Record<string, StatusMeta> = {
  CRITICAL: { tone: 'danger', label: 'Nghiêm trọng' },
  HIGH: { tone: 'danger', label: 'Cao' },
  MEDIUM: { tone: 'caution', label: 'Trung bình' },
  LOW: { tone: 'info', label: 'Thấp' },
  INFO: { tone: 'neutral', label: 'Thông tin' },
}

export const SCAN_STATUS: Record<string, StatusMeta> = {
  QUEUED: { tone: 'info', label: 'Đã xếp hàng' },
  RUNNING: { tone: 'caution', label: 'Đang quét' },
  COMPLETED: { tone: 'ok', label: 'Đã quét xong' },
  FAILED: { tone: 'danger', label: 'Quét lỗi' },
  CANCELLED: { tone: 'neutral', label: 'Đã huỷ' },
  BLOCKED: { tone: 'danger', label: 'Bị chặn' },
}

export const VERIFICATION_STATUS: Record<string, StatusMeta> = {
  PENDING: { tone: 'caution', label: 'Chờ xác minh' },
  VERIFIED: { tone: 'ok', label: 'Đã xác minh' },
}

export const PROCEDURE_STATUS: Record<string, StatusMeta> = {
  NOT_STARTED: { tone: 'neutral', label: 'Chưa bắt đầu' },
  IN_PROGRESS: { tone: 'info', label: 'Đang thực hiện' },
  SUBMITTED: { tone: 'caution', label: 'Đã nộp hồ sơ' },
  COMPLETED: { tone: 'ok', label: 'Đã hoàn thành' },
}
export const REPORT_STATUS: Record<string, StatusMeta> = {
  QUEUED: { tone: 'info', label: 'Đã xếp hàng' },
  RUNNING: { tone: 'caution', label: 'Đang tạo' },
  COMPLETED: { tone: 'ok', label: 'Sẵn sàng tải' },
  FAILED: { tone: 'danger', label: 'Tạo lỗi' },
}

export const DOCUMENT_SCAN_STATUS: Record<string, StatusMeta> = {
  CLEAN: { tone: 'ok', label: 'An toàn' },
  PENDING: { tone: 'info', label: 'Đang chờ quét' },
}

export function statusMeta(map: Record<string, StatusMeta>, status: string): StatusMeta {
  return map[status] ?? { tone: 'neutral', label: status }
}

export function toSelectOptions(map: Record<string, StatusMeta>) {
  return Object.entries(map).map(([value, meta]) => ({ value, label: meta.label }))
}

/* ---- Hồ sơ dự án: shell tải một lần, các tab inject ---- */

interface ProjectStore {
  project: Ref<Project | null>
  loading: Ref<boolean>
  error: Ref<ApiError | null>
  reload: () => Promise<void>
}

const PROJECT_KEY: InjectionKey<ProjectStore> = Symbol('vlc.project')

export function provideProject(store: ProjectStore): void {
  provide(PROJECT_KEY, store)
}

export function useProject(): ProjectStore {
  const store = inject(PROJECT_KEY)
  if (!store) throw new Error('useProject() phải được gọi trong ProjectShellView')
  return store
}

/** Danh sách không phân trang: { data: T[] } từ ApiController::collection. */
export async function fetchList<T>(path: string): Promise<T[]> {
  const payload = await api.get<{ data: T[] }>(path)
  return payload.data ?? []
}

export function emptyToNull(value: string): string | null {
  return value === '' ? null : value
}

/** Chuỗi hash SHA-256 rút gọn để hiển thị (DESIGN.md: tabular numerals). */
export function shortHash(value?: string | null): string {
  if (!value) return '—'
  return value.length <= 16 ? value : `${value.slice(0, 10)}…${value.slice(-6)}`
}
