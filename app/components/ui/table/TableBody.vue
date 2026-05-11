<script setup lang="ts">
import { computed } from 'vue'
import { cn } from '../../../utils/cn'

const props = withDefaults(defineProps<{ class?: string }>(), { class: '' })
const classes = computed(() => cn('[&_tr:last-child]:border-0', props.class))
</script>

<template>
  <tbody :class="classes">
    <slot />
  </tbody>
</template>
