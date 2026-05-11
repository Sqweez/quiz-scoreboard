<script setup lang="ts">
const user = useSupabaseUser()

watch(user, () => {
  if (user.value) {
    navigateTo('/')
  }
}, { immediate: true })
</script>

<template>
  <main class="mx-auto flex min-h-screen w-full max-w-md items-center justify-center px-4">
    <p class="text-sm text-muted-foreground">Подтверждаем вход...</p>
  </main>
</template>
