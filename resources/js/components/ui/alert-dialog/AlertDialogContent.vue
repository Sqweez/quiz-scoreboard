<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import {
  AlertDialogContent,
  AlertDialogOverlay,
  AlertDialogPortal
} from 'reka-ui'
import { cn } from '~/utils/cn'

const props = defineProps<{
  class?: HTMLAttributes['class']
}>()
</script>

<template>
  <AlertDialogPortal>
    <AlertDialogOverlay class="fixed inset-0 z-50 bg-foreground/40" />
    <AlertDialogContent
      :class="cn(
        'fixed left-1/2 top-1/2 z-50 grid w-[calc(100%-2rem)] max-w-md -translate-x-1/2 -translate-y-1/2 gap-4 rounded-md border bg-background p-6 shadow-lg',
        props.class
      )"
    >
      <slot />
    </AlertDialogContent>
  </AlertDialogPortal>
</template>
