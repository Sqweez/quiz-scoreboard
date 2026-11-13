<script setup lang="ts">
import { ref, watch } from 'vue'
import {
  AlertDialog,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogTitle
} from '~/components/ui/alert-dialog'
import { Button } from '~/components/ui/button'
import { Input } from '~/components/ui/input'
import { Label } from '~/components/ui/label'
import { Spinner } from '~/components/ui/spinner'
import { getRequestMessage } from '~/utils/http'

const props = defineProps<{
  open: boolean
  submit: (username: string, password: string) => Promise<void>
}>()

const emit = defineEmits<{
  cancel: []
}>()

const username = ref('')
const password = ref('')
const error = ref('')
const isSubmitting = ref(false)

// The password never outlives the dialog: it is cleared whenever the dialog closes.
watch(() => props.open, (open) => {
  if (!open) {
    password.value = ''
    error.value = ''
  }
})

async function handleSubmit(): Promise<void> {
  if (isSubmitting.value || !username.value.trim() || !password.value) {
    return
  }

  error.value = ''
  isSubmitting.value = true

  try {
    await props.submit(username.value.trim(), password.value)
    password.value = ''
  } catch (submitError) {
    error.value = getRequestMessage(submitError)
  } finally {
    isSubmitting.value = false
  }
}

function handleOpenChange(open: boolean): void {
  if (!open && !isSubmitting.value) {
    emit('cancel')
  }
}
</script>

<template>
  <AlertDialog :open="open" @update:open="handleOpenChange">
    <AlertDialogContent>
      <AlertDialogTitle class="font-display text-2xl font-medium">
        Вход в «Квиз, плиз!»
      </AlertDialogTitle>
      <AlertDialogDescription class="text-sm text-muted-foreground">
        Данные аккаунта admin.quizplease.com. Пароль не сохраняется.
      </AlertDialogDescription>

      <form class="grid gap-4" novalidate @submit.prevent="handleSubmit">
        <div class="grid gap-2">
          <Label for="quizplease-username">Логин</Label>
          <Input id="quizplease-username" v-model="username" type="email" autocomplete="username" :disabled="isSubmitting" />
        </div>
        <div class="grid gap-2">
          <Label for="quizplease-password">Пароль</Label>
          <Input id="quizplease-password" v-model="password" type="password" autocomplete="current-password" :disabled="isSubmitting" />
        </div>

        <p v-if="error" class="text-sm text-primary" role="alert">
          {{ error }}
        </p>

        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
          <Button type="button" variant="outline" size="lg" :disabled="isSubmitting" @click="emit('cancel')">
            Отмена
          </Button>
          <Button type="submit" size="lg" :disabled="isSubmitting || !username.trim() || !password">
            <Spinner v-if="isSubmitting" />
            Войти
          </Button>
        </div>
      </form>
    </AlertDialogContent>
  </AlertDialog>
</template>
