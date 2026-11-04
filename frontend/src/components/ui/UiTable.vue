<script setup lang="ts">
import { cn } from '@/lib/cn'

interface Column {
  key: string
  label: string
  align?: 'left' | 'right' | 'center'
  width?: string
  numeric?: boolean
}

const props = defineProps<{
  columns: Column[]
  rows: Array<Record<string, unknown>>
  rowClass?: (row: Record<string, unknown>) => string
  onRowClick?: (row: Record<string, unknown>) => void
}>()
</script>

<template>
  <div class="overflow-x-auto">
    <table class="w-full border-collapse text-sm">
      <thead>
        <tr class="border-b border-line-strong">
          <th
            v-for="column in props.columns"
            :key="column.key"
            :style="column.width ? { width: column.width } : undefined"
            :class="cn(
              'py-2 pr-4 text-left text-[11px] font-semibold uppercase tracking-[0.06em] text-ink-muted',
              column.align === 'right' && 'text-right',
              column.numeric && 'tnum',
            )"
          >
            {{ column.label }}
          </th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="(row, index) in props.rows"
          :key="index as number"
          :class="cn(
            'border-b border-line transition-colors',
            props.onRowClick && 'cursor-pointer hover:bg-surface-2',
            props.rowClass?.(row),
          )"
          @click="props.onRowClick?.(row)"
        >
          <td
            v-for="column in props.columns"
            :key="column.key"
            :class="cn('py-2.5 pr-4 align-middle text-ink', column.align === 'right' && 'text-right', column.numeric && 'tnum')"
          >
            <slot :name="`cell-${column.key}`" :row="row" :value="row[column.key]">
              {{ row[column.key] ?? '—' }}
            </slot>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
