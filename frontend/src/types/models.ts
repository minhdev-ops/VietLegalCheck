/** Kiểu dùng chung cho các thực thể nghiệp vụ (đồng bộ với backend API). */

export interface LegalDocument {
  id: string
  doc_number: string
  title: string
  doc_type: string
  issuer: string | null
  issued_at: string | null
  effective_from: string | null
  effective_to: string | null
  status: string
  official_url: string | null
  source_type: string
  last_verified_at: string | null
  verified_stale: boolean
  notes: string | null
  provisions?: LegalProvision[]
}

export interface LegalProvision {
  id: string
  legal_document_id: string
  chapter: string | null
  article: string | null
  clause: string | null
  point: string | null
  heading: string | null
  content: string
  effective_from: string | null
  effective_to: string | null
  status: string
  citation: string
  document?: LegalDocument
}

export interface Project {
  id: string
  organization_id: string
  name: string
  description: string | null
  product_type: string | null
  url: string | null
  platform: string | null
  markets: string[] | null
  status: string
  created_at: string
  tag_codes: string[]
  latest_assessment?: Assessment | null
}

export interface ProjectProfile {
  id: string
  project_id: string
  question_set_version: number | null
  answers: Record<string, unknown> | null
  derived_tags: Array<{ tag_code: string; reason: string }> | null
  status: string
  completed_step: number
  confirmed_at: string | null
}

export interface Question {
  id: string
  code: string
  section: string
  prompt: string
  help: string | null
  type: 'BOOL' | 'SINGLE' | 'MULTI' | 'TEXT' | 'NUMBER'
  options: Array<{ value: string; label: string }> | null
  show_if: Record<string, unknown> | null
  activity_tags: string[] | null
  importance: 'REQUIRED' | 'OPTIONAL'
  position: number
}

export interface QuestionSet {
  id: string
  version: number
  status: string
  questions: Question[]
}

export interface Assessment {
  id: string
  project_id: string
  as_of_date: string
  question_set_version: number
  status: string
  overall_status: string | null
  started_at: string | null
  completed_at: string | null
  rule_set_snapshot: Array<{ rule_code: string; version: number }>
  findings?: AssessmentFinding[]
}

export interface AssessmentFinding {
  id: string
  assessment_id: string
  rule_code: string
  title: string
  legal_group: string
  applicability: 'APPLICABLE' | 'NOT_APPLICABLE' | 'NEED_MORE_INFO' | 'NEED_EXPERT_REVIEW'
  fulfillment: 'NOT_STARTED' | 'IN_PROGRESS' | 'SELF_DECLARED_DONE' | 'EVIDENCE_ATTACHED' | 'EXPERT_VERIFIED'
  severity: 'CRITICAL' | 'HIGH' | 'MEDIUM' | 'LOW' | 'INFO'
  trace_json: TraceNode[] | null
  explanation: string | null
  recommendation: string | null
  obligation_json: {
    name?: string
    description?: string
    deadline?: string | null
    authority?: string | null
    guide?: string[] | null
  } | null
  legal_basis: Array<{
    provision_id: string
    citation: string
    document_number: string
    document_title: string
    effective_from: string | null
    official_url: string | null
    content: string | null
  }> | null
  required_info: Array<{ field: string; question: string }> | null
}

export interface TraceNode {
  node: string
  kind: 'group' | 'condition'
  operator?: string
  field?: string
  expected?: unknown
  actual?: unknown
  result: 'TRUE' | 'FALSE' | 'UNKNOWN'
  label?: string | null
  missing_field?: string | null
  children?: TraceNode[]
}

export interface Scan {
  id: string
  project_id: string
  target_url: string
  resolved_ip: string | null
  mode: 'LIGHT' | 'DEEP'
  status: string
  blocked_reason: string | null
  error: string | null
  site: Record<string, unknown> | null
  findings: Array<{
    code: string
    severity: string
    title: string
    detail: string
    evidence_url: string
  }> | null
  page_count: number
  started_at: string | null
  finished_at: string | null
  pages?: ScanPage[]
}

export interface ScanPage {
  id: string
  scan_id: string
  url: string
  http_status: number | null
  title: string | null
  signals: Record<string, unknown> | null
  fetch_error: string | null
}

export interface DomainVerification {
  id: string
  project_id: string
  domain: string
  method: 'META_TAG' | 'DNS_TXT'
  token: string
  status: string
  instruction: string
  verified_at: string | null
  last_checked_at: string | null
}

export interface AdministrativeProcedure {
  id: string
  code: string
  name: string
  authority: string
  subject: string | null
  conditions: string | null
  steps: Array<{ title: string; detail?: string }> | null
  submission_methods: string[] | null
  processing_time: string | null
  fee: string | null
  result: string | null
  portal_url: string | null
  legal_basis: Array<{ provision_id: string; citation: string; document_number: string }> | null
  activity_tags: string[] | null
  last_verified_at: string | null
  verified_stale: boolean
  requirements?: Array<{ id: string; code: string; name: string; description: string | null; required: boolean }>
}

export interface ProjectProcedure {
  id: string
  project_id: string
  procedure: AdministrativeProcedure
  status: string
  notes: string | null
  submitted_at: string | null
  completed_at: string | null
  documents: Array<{
    id: string
    procedure_requirement_id: string | null
    file_id: string | null
    status: string
    note: string | null
  }>
}

export interface DocumentFile {
  id: string
  original_name: string
  mime: string
  size: number
  category: string
  checksum_sha256: string
  scan_status: string
  created_at: string
}

export interface Report {
  id: string
  assessment_id: string
  project_id: string
  status: string
  as_of_date: string
  sha256: string | null
  pdf_url: string | null
  rule_set_snapshot: Array<{ rule_code: string; version: number }>
  created_at: string
}

export interface AiMessage {
  id: string
  role: 'user' | 'assistant'
  content: string
  citations: Array<{
    provision_id: string
    citation: string
    document_number: string
    document_title: string
    content: string | null
    official_url: string | null
  }> | null
  categories: Record<string, string[]> | null
  refused: boolean
  model: string | null
  created_at: string
}

export interface BackgroundJob {
  id: string
  job_type: string
  status: string
  idempotency_key: string | null
  last_error: string | null
  result: Record<string, unknown> | null
  created_at: string
  started_at: string | null
  finished_at: string | null
}

export interface AppNotification {
  id: string
  type: string
  title: string
  body: string
  read_at: string | null
  created_at: string
}
