<script setup lang="ts">
import { computed } from 'vue'

/**
 * Radio dạng thẻ và checkbox: input gốc vẫn nằm trong accessibility tree
 * (WCAG), lớp visual chỉ vẽ phía trên.
 */
const props = withDefaults(defineProps<{
  modelValue?: boolean | Array<string | number> | string | number | null
  value?: string | number | boolean
  label?: string
  description?: string
  type?: 'radio' | 'checkbox'
  name?: string
  required?: boolean
}>(), { type: 'checkbox' })

const emit = defineEmits<{ 'update:modelValue': [value: unknown] }>()

const isRadio = computed(() => props.type === 'radio')

const checked = computed(() => {
  if (isRadio.value) return props.modelValue === props.value
  if (Array.isArray(props.modelValue)) return props.modelValue.includes(props.value as never)
  return Boolean(props.modelValue)
})

function onChange(): void {
  if (isRadio.value) {
    emit('update:modelValue', props.value)
    return
  }
  if (Array.isArray(props.modelValue)) {
    const next = props.modelValue.includes(props.value as never)
      ? props.modelValue.filter((item) => item !== props.value)
      : [...props.modelValue, props.value]
    emit('update:modelValue', next)
    return
  }
  emit('update:modelValue', !props.modelValue)
}
</script>

<template>
  <label
    :class="[
      'flex cursor-pointer items-start gap-3 rounded-md border p-3 transition-colors',
      checked ? 'border-accent bg-accent/5' : 'border-line bg-surface hover:bg-surface-2',
    ]"
  >
    <input
      :type="props.type"
      :name="props.name"
      :value="props.value"
      :checked="checked"
      :required="props.required"
      @change="onChange"
      class="mt-0.5 size-4 accent-accent"
    />
    <span class="min-w-0 flex-1">
      <span v-if="props.label" class="block text-[13.5px] font-medium text-ink">{{ props.label }}</span>
      <span v-if="props.description" class="mt-0.5 block text-[12.5px] text-ink-muted">{{ props.description }}</span>
    </span>
  </label>
</template>
