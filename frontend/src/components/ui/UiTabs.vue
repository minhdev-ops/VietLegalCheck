<script setup lang="ts">
import { TabsContent, TabsList, TabsRoot, TabsTrigger } from 'reka-ui'
import { cn } from '@/lib/cn'

interface TabItem {
  value: string
  label: string
  icon?: never
}

const props = defineProps<{
  modelValue: string
  tabs: TabItem[]
  lazy?: boolean
}>()

const emit = defineEmits<{ 'update:modelValue': [value: string] }>()
</script>

<template>
  <TabsRoot
    :model-value="props.modelValue"
    @update:model-value="emit('update:modelValue', $event as string)"
    class="flex flex-col"
  >
    <TabsList class="flex gap-1 border-b border-line" aria-label="Tab">
      <TabsTrigger
        v-for="tab in props.tabs"
        :key="tab.value"
        :value="tab.value"
        :class="cn(
          'relative h-10 px-3.5 text-[13.5px] font-medium text-ink-muted',
          'hover:text-ink data-[state=active]:text-ink data-[state=active]:font-semibold',
          'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/40',
          'after:absolute after:inset-x-2 after:bottom-[-1px] after:h-[2px] after:rounded-full',
          'after:bg-transparent data-[state=active]:after:bg-accent after:transition-colors after:duration-200',
        )"
      >
        {{ tab.label }}
      </TabsTrigger>
    </TabsList>
    <TabsContent v-for="tab in props.tabs" :key="tab.value" :value="tab.value" tabindex="-1">
      <slot v-if="!props.lazy || props.modelValue === tab.value" :name="tab.value" />
    </TabsContent>
  </TabsRoot>
</template>
