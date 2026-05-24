<script setup lang="ts">
import { ArrowLeft } from 'lucide-vue-next'
import GameSetupForm from '~/components/GameSetupForm.vue'

definePageMeta({
  middleware: 'auth'
})
</script>

<template>
  <main class="mx-auto flex min-h-screen w-full max-w-3xl flex-col gap-10 px-4 py-8 lg:px-8 lg:py-12">
    <NuxtLink
      to="/"
      class="inline-flex items-center gap-1.5 self-start text-sm text-muted-foreground transition-colors hover:text-foreground"
    >
      <ArrowLeft class="size-3.5" />
      Все игры
    </NuxtLink>

    <header class="border-b border-[var(--rule-strong)] pb-6">
      <p class="text-[11px] uppercase tracking-[0.18em] text-muted-foreground">
        Новая игра
      </p>
      <h1 class="mt-3 font-display text-4xl font-medium leading-tight lg:text-5xl">
        Подготовка к подсчёту
      </h1>
      <p class="mt-3 max-w-xl text-sm text-muted-foreground">
        Заведите команды и раунды. Дальше откроется таблица и можно начинать вносить баллы.
      </p>
    </header>

    <GameSetupForm />
  </main>
</template>
