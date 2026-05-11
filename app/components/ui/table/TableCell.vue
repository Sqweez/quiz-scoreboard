<script setup lang="ts">
import { computed } from 'vue'
import { cn } from '../../../utils/cn'

const props = withDefaults(defineProps<{ class?: string }>(), { class: '' })
const classes = computed(() => cn('p-3 align-middle', props.class))
</script>

<template>
  <td :class="classes">
    <slot />
  </td>
</template>
