<script setup lang="ts">
import { onMounted } from 'vue'
import { useRatingCategories } from '~/composables/useRatingCategories'

defineProps<{
  disabled?: boolean
}>()

const categoryId = defineModel<number | null>('categoryId', { required: true })
const includeScores = defineModel<boolean>('includeScores', { required: true })

const { categories, error, isLoading, load } = useRatingCategories()

onMounted(load)

function selectCategory(event: Event): void {
  const value = (event.target as HTMLSelectElement).value
  categoryId.value = value ? Number(value) : null
}
</script>

<template>
  <section class="space-y-4">
    <div class="border-b border-rule-strong pb-2">
      <h2 class="eyebrow text-muted-foreground">Рейтинг</h2>
    </div>

    <div class="space-y-2">
      <label for="rating-category" class="eyebrow-tight block text-muted-foreground">
        Категория рейтинга
      </label>
      <select
        id="rating-category"
        :value="categoryId ?? ''"
        :disabled="disabled || isLoading"
        class="h-12 w-full border-0 border-b border-rule-strong bg-transparent text-base outline-none transition-colors focus:border-foreground disabled:opacity-50"
        @change="selectCategory"
      >
        <option value="">{{ isLoading ? 'Загружаем категории…' : 'Не выбрана' }}</option>
        <option v-for="category in categories" :key="category.id" :value="category.id">
          {{ category.title }}
        </option>
        <!-- Keeps a saved category visible even if the list failed to load. -->
        <option v-if="categoryId !== null && !categories.some((category) => category.id === categoryId)" :value="categoryId">
          Категория #{{ categoryId }}
        </option>
      </select>
      <p v-if="error" class="text-sm text-primary">
        {{ error }}
        <button type="button" class="underline underline-offset-4" @click="load">Повторить</button>
      </p>
    </div>

    <label class="flex min-h-12 items-center gap-3 text-base">
      <input v-model="includeScores" type="checkbox" class="size-5 accent-primary" :disabled="disabled">
      Учитывать баллы в рейтинг
    </label>
  </section>
</template>
