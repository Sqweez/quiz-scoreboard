<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { LoaderCircle } from 'lucide-vue-next'
import { cn } from '~/utils/cn'

const props = withDefaults(
  defineProps<{
    class?: HTMLAttributes['class']
    label?: string
  }>(),
  {
    label: 'Загрузка'
  }
)
</script>

<template>
  <LoaderCircle
    role="status"
    :aria-label="props.label"
    :class="cn('size-4 animate-spin', props.class)"
  />
</template>
