<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { ArrowLeft } from 'lucide-vue-next'
import GameSetupForm from '~/components/GameSetupForm.vue'

</script>

<template>
  <main class="mx-auto flex min-h-screen w-full max-w-3xl flex-col gap-10 px-4 py-8 lg:px-8 lg:py-12">
    <Link
      href="/"
      class="-my-3 inline-flex items-center gap-1.5 self-start py-3 text-sm text-muted-foreground transition-colors hover:text-foreground"
    >
      <ArrowLeft class="size-3.5" />
      Все игры
    </Link>

    <header class="border-b border-rule-strong pb-6">
      <p class="eyebrow text-muted-foreground">
        Новая игра
      </p>
      <h1 class="mt-3 font-display text-4xl font-medium leading-tight lg:text-5xl">
        Кто играет сегодня
      </h1>
      <p class="mt-3 max-w-xl text-sm text-muted-foreground">
        Название, команды и количество раундов. Всё можно поменять и после старта.
      </p>
    </header>

    <GameSetupForm />
  </main>
</template>
