<script setup lang="ts">
import { computed } from 'vue'
import { cn } from '../../../utils/cn'

const props = withDefaults(defineProps<{ class?: string }>(), { class: '' })
const classes = computed(() => cn('flex flex-col space-y-1.5 p-6', props.class))
</script>

<template>
  <div :class="classes">
    <slot />
  </div>
</template>
