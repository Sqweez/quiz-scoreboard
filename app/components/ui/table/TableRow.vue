<script setup lang="ts">
import { computed } from 'vue'
import { cn } from '../../../utils/cn'

const props = withDefaults(defineProps<{ class?: string }>(), { class: '' })
const classes = computed(() => cn('border-b transition-colors hover:bg-muted/50', props.class))
</script>

<template>
  <tr :class="classes">
    <slot />
  </tr>
</template>
