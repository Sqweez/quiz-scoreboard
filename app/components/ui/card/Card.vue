<script setup lang="ts">
import { computed } from 'vue'
import { cn } from '../../../utils/cn'

const props = withDefaults(defineProps<{ class?: string }>(), { class: '' })
const classes = computed(() =>
  cn('rounded-lg border bg-card/95 text-card-foreground shadow-sm shadow-slate-200/60', props.class)
)
</script>

<template>
  <div :class="classes">
    <slot />
  </div>
</template>
