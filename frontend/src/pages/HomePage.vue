<script setup>
import { computed, inject, onMounted, ref, watch } from 'vue'
import BannerCarousel from '../components/BannerCarousel.vue'
import VacancyCard from '../components/VacancyCard.vue'
import { loadBanners, loadVacancies } from '../api'
import { cityPhrase, vacancyCount } from '../format'

const SUGGESTIONS = ['Курьер', 'Водитель', 'Продавец', 'Программист', 'Повар', 'Бухгалтер', 'Кладовщик', 'Менеджер']

const cityCode = inject('cityCode', ref(''))
const cities = ref([])
const vacancies = ref([])
const banners = ref([])
const bannersLoading = ref(true)
const query = ref('')
const draft = ref('')
const initialLoading = ref(true)
const refreshing = ref(false)
const error = ref('')

const selectedCity = computed(() => cities.value.find((item) => item.code === cityCode.value) || null)
const place = computed(() => cityPhrase(selectedCity.value))
const heading = computed(() => (query.value ? `Вакансии по запросу «${query.value}»` : `Вакансии ${place.value}`))

let requestId = 0

async function refresh() {
  const current = ++requestId
  error.value = ''
  refreshing.value = true
  try {
    const payload = await loadVacancies({ q: query.value, city: cityCode.value })
    if (current !== requestId) return
    cities.value = payload.cities || []
    vacancies.value = payload.vacancies || []
  } catch (exception) {
    if (current !== requestId) return
    error.value = exception.message || 'Не удалось загрузить вакансии'
  } finally {
    if (current === requestId) {
      initialLoading.value = false
      refreshing.value = false
    }
  }
}

function submitSearch() {
  query.value = draft.value.trim()
  refresh()
}

function applySuggestion(value) {
  draft.value = value
  query.value = value
  refresh()
}

watch(cityCode, () => {
  document.title = `Работа ${place.value} — WorkHub`
  refresh()
})

onMounted(async () => {
  document.title = `Работа ${place.value} — WorkHub`
  refresh()
  try {
    const payload = await loadBanners()
    banners.value = payload.banners || []
  } catch {
    banners.value = []
  } finally {
    bannersLoading.value = false
  }
})
</script>

<template>
  <main>
    <BannerCarousel :banners="banners" :loading="bannersLoading" />

    <section class="search-wrap">
      <div class="search-card">
        <h1>Поиск работы</h1>
        <p class="search-place">{{ place }}</p>
        <form class="search" @submit.prevent="submitSearch">
          <label class="sr-only" for="query">Название вакансии</label>
          <input
            id="query"
            v-model="draft"
            type="search"
            placeholder="Название вакансии"
            autocomplete="off"
          />
          <button type="submit">Найти</button>
        </form>
        <div class="suggestions" aria-label="Популярные запросы">
          <button
            v-for="item in SUGGESTIONS"
            :key="item"
            type="button"
            :class="{ active: query.toLowerCase() === item.toLowerCase() }"
            @click="applySuggestion(item)"
          >
            {{ item }}
          </button>
        </div>
      </div>
    </section>

    <section class="results" :aria-busy="refreshing">
      <div class="results-head">
        <h2>{{ heading }}</h2>
        <p v-if="!initialLoading && !error">{{ vacancyCount(vacancies.length) }}</p>
      </div>

      <p v-if="error" class="state error">{{ error }}</p>

      <div v-else-if="initialLoading" class="list">
        <div v-for="index in 4" :key="index" class="skeleton" />
      </div>

      <p v-else-if="vacancies.length === 0" class="state">
        Ничего не нашлось. Попробуйте другое название или город.
      </p>

      <div v-else class="list" :class="{ dim: refreshing }">
        <VacancyCard v-for="vacancy in vacancies" :key="vacancy.id" :vacancy="vacancy" />
      </div>
    </section>
  </main>
</template>
