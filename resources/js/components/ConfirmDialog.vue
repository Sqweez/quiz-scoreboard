<script setup lang="ts">
import {
  AlertDialog,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogTitle
} from '~/components/ui/alert-dialog'
import { Button } from '~/components/ui/button'
import { Spinner } from '~/components/ui/spinner'

const open = defineModel<boolean>('open', { required: true })

defineProps<{
  title: string
  description: string
  confirmLabel: string
  pending?: boolean
  disabled?: boolean
}>()

defineEmits<{
  confirm: []
}>()
</script>

<template>
  <AlertDialog v-model:open="open">
    <AlertDialogContent>
      <AlertDialogTitle class="font-display text-2xl font-medium">
        {{ title }}
      </AlertDialogTitle>
      <AlertDialogDescription class="text-sm text-muted-foreground">
        {{ description }}
      </AlertDialogDescription>
      <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
        <AlertDialogCancel as-child>
          <Button variant="outline" size="lg" :disabled="pending">
            Отмена
          </Button>
        </AlertDialogCancel>
        <Button
          size="lg"
          :disabled="pending || disabled"
          @click="$emit('confirm')"
        >
          <Spinner v-if="pending" />
          {{ confirmLabel }}
        </Button>
      </div>
    </AlertDialogContent>
  </AlertDialog>
</template>
