<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { useRatingCategories, useRatingPackages } from '~/composables/useRatingCategories'

defineProps<{
  disabled?: boolean
  // Package of the linked Quiz Please game; used whenever no manual package is set.
  autoPackage?: string | null
}>()

const categoryId = defineModel<number | null>('categoryId', { required: true })
const includeScores = defineModel<boolean>('includeScores', { required: true })
const ratingPackage = defineModel<string>('package', { required: true })

const { categories, error, isLoading, load } = useRatingCategories()

const { packages, load: loadPackages } = useRatingPackages()

onMounted(load)

// An empty package means "take it from Quiz Please", so turning manual entry off clears it.
const isManualPackage = ref(ratingPackage.value !== '')

watch(ratingPackage, (value) => {
  if (value !== '') {
    isManualPackage.value = true
  }
})

function toggleManualPackage(): void {
  isManualPackage.value = !isManualPackage.value

  if (!isManualPackage.value) {
    ratingPackage.value = ''
  }
}

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

    <div class="space-y-2">
      <p class="eyebrow-tight text-muted-foreground">
        Пакет
      </p>
      <p v-if="!isManualPackage" class="flex min-h-12 items-center border-b border-rule text-base">
        <template v-if="autoPackage">
          {{ autoPackage }}<span class="ml-2 text-sm text-muted-foreground">из Квиз, плиз!</span>
        </template>
        <span v-else class="text-sm text-muted-foreground">Подтянется из Квиз, плиз! после привязки игры.</span>
      </p>
      <template v-else>
        <label for="rating-package" class="sr-only">Пакет вручную</label>
        <input
          id="rating-package"
          v-model="ratingPackage"
          type="text"
          list="rating-packages"
          autocomplete="off"
          :placeholder="autoPackage ?? 'Например, классика'"
          :disabled="disabled"
          class="h-12 w-full border-0 border-b border-rule-strong bg-transparent text-base outline-none transition-colors placeholder:text-muted-foreground/40 focus:border-foreground disabled:opacity-50"
          @focus="loadPackages"
        >
        <datalist id="rating-packages">
          <option v-for="name in packages" :key="name" :value="name" />
        </datalist>
        <p class="text-xs text-muted-foreground">
          Заменяет пакет из Квиз, плиз! Подсказки — пакеты игр вашего города в рейтинге.
        </p>
      </template>
      <label class="flex min-h-11 items-center gap-3 text-sm">
        <input :checked="isManualPackage" type="checkbox" class="size-5 accent-primary" :disabled="disabled" @change="toggleManualPackage">
        Указать пакет вручную
      </label>
    </div>

    <label class="flex min-h-12 items-center gap-3 text-base">
      <input v-model="includeScores" type="checkbox" class="size-5 accent-primary" :disabled="disabled">
      Учитывать баллы в рейтинг
    </label>
  </section>
</template>
