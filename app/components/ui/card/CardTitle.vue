<script setup lang="ts">
import { computed } from 'vue'
import { cn } from '../../../utils/cn'

const props = withDefaults(defineProps<{ class?: string }>(), { class: '' })
const classes = computed(() => cn('text-xl font-semibold leading-none tracking-normal', props.class))
</script>

<template>
  <h2 :class="classes">
    <slot />
  </h2>
</template>
