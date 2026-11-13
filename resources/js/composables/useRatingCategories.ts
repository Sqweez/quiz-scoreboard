import { ref } from 'vue'
import { getRequestMessage, http } from '~/utils/http'

export type RatingCategory = {
  id: number
  title: string
  code: string
}

export function useRatingCategories() {
  const categories = ref<RatingCategory[]>([])
  const error = ref('')
  const isLoading = ref(false)

  async function load(): Promise<void> {
    error.value = ''
    isLoading.value = true

    try {
      categories.value = await http<RatingCategory[]>('/api/quizplease/rating-categories')
    } catch (requestError) {
      error.value = getRequestMessage(requestError)
    } finally {
      isLoading.value = false
    }
  }

  return { categories, error, isLoading, load }
}

// Package names already used in the user's city in the rating; only suggestions, the package stays free text.
export function useRatingPackages() {
  const packages = ref<string[]>([])
  let requested = false

  async function load(): Promise<void> {
    if (requested) {
      return
    }

    requested = true

    try {
      packages.value = await http<string[]>('/api/quizplease/rating-packages')
    } catch {
      // Suggestions need the admin login and both Quiz Please services; without them the field is typed by hand.
      requested = false
    }
  }

  return { packages, load }
}
