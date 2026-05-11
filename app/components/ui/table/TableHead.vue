<script setup lang="ts">
import { computed } from 'vue'
import { cn } from '../../../utils/cn'

const props = withDefaults(defineProps<{ class?: string }>(), { class: '' })
const classes = computed(() => cn('h-11 px-3 text-left align-middle font-medium text-muted-foreground', props.class))
</script>

<template>
  <th :class="classes">
    <slot />
  </th>
</template>
