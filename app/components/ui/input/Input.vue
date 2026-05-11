<script setup lang="ts">
import { computed } from 'vue'
import { cn } from '../../../utils/cn'

const model = defineModel<string | number | null>()

const props = withDefaults(
  defineProps<{
    type?: string
    class?: string
  }>(),
  {
    type: 'text',
    class: ''
  }
)

const classes = computed(() =>
  cn(
    'flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm transition-colors placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50',
    props.class
  )
)

function preventNativeNumberStep(event: KeyboardEvent): void {
  if (props.type === 'number' && (event.key === 'ArrowUp' || event.key === 'ArrowDown')) {
    event.preventDefault()
  }
}

function preventWheelStep(event: WheelEvent): void {
  if (props.type === 'number') {
    event.currentTarget instanceof HTMLInputElement && event.currentTarget.blur()
  }
}
</script>

<template>
  <input
    v-model="model"
    :type="type"
    :class="classes"
    @keydown="preventNativeNumberStep"
    @wheel="preventWheelStep"
  >
</template>
