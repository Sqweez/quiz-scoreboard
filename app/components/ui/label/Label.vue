<script setup lang="ts">
import { computed } from 'vue'
import { cn } from '../../../utils/cn'

const props = withDefaults(
  defineProps<{
    class?: string
  }>(),
  {
    class: ''
  }
)

const classes = computed(() => cn('text-sm font-medium leading-none', props.class))
</script>

<template>
  <label :class="classes">
    <slot />
  </label>
</template>
