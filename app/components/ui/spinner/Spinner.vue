<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { LoaderCircle } from 'lucide-vue-next'
import { cn } from '~/utils/cn'

const props = defineProps<{
  class?: HTMLAttributes['class']
}>()
</script>

<template>
  <LoaderCircle
    role="status"
    aria-label="Loading"
    :class="cn('size-4 animate-spin', props.class)"
  />
</template>
