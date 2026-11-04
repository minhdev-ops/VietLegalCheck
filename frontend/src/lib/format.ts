export function formatDate(value?: string | Date | null): string {
  if (!value) return '—'
  const date = typeof value === 'string' ? new Date(value) : value
  if (Number.isNaN(date.getTime())) return '—'
  return new Intl.DateTimeFormat('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(date)
}

export function formatDateTime(value?: string | Date | null): string {
  if (!value) return '—'
  const date = typeof value === 'string' ? new Date(value) : value
  if (Number.isNaN(date.getTime())) return '—'
  return new Intl.DateTimeFormat('vi-VN', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }).format(date)
}

export function formatBytes(bytes?: number | null): string {
  if (bytes === undefined || bytes === null) return '—'
  const units = ['B', 'KB', 'MB', 'GB']
  let value = bytes
  let unit = 0
  while (value >= 1024 && unit < units.length - 1) {
    value /= 1024
    unit += 1
  }
  return `${new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 1 }).format(value)} ${units[unit]}`
}

export function formatNumber(value?: number | null): string {
  if (value === undefined || value === null) return '—'
  return new Intl.NumberFormat('vi-VN').format(value)
}

export function daysSince(value?: string | null): number | null {
  if (!value) return null
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return null
  return Math.floor((Date.now() - date.getTime()) / 86_400_000)
}

/** Thiết kế Công báo: số hiệu văn bản, không rút gọn, cách nhau bằng dấu chấm. */
export function documentLabel(doc: { doc_number?: string | null; title?: string | null }): string {
  return doc?.doc_number ?? '—'
}
