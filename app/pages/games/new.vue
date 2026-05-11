<script setup lang="ts">
import { ArrowLeft } from 'lucide-vue-next'
import { Button } from '~/components/ui/button'
import GameSetupForm from '~/components/GameSetupForm.vue'

definePageMeta({
  middleware: 'auth'
})
</script>

<template>
  <main class="mx-auto flex min-h-screen w-full max-w-4xl flex-col gap-6 px-4 py-6 lg:py-8">
    <header class="flex items-center justify-between gap-3">
      <div class="space-y-2">
        <p class="text-sm font-medium text-primary">Таблица квиза</p>
        <h1 class="text-3xl font-bold tracking-normal">Создание игры</h1>
        <p class="max-w-2xl text-sm text-muted-foreground">
          Настройте команды и раунды, а затем откройте игру по ее адресу.
        </p>
      </div>
      <NuxtLink to="/">
        <Button variant="outline">
          <ArrowLeft class="size-4" />
          К списку
        </Button>
      </NuxtLink>
    </header>

    <GameSetupForm />
  </main>
</template>
