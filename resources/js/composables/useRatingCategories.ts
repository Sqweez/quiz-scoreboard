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
