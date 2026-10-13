<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import SiteHeader from './components/SiteHeader.vue'
import VacancyCard from './components/VacancyCard.vue'
import { loadVacancies } from './api'
import { cityPhrase, vacancyCount } from './format'

const CITY_KEY = 'workhub-city'
const SUGGESTIONS = ['Курьер', 'Водитель', 'Продавец', 'Программист', 'Повар', 'Бухгалтер', 'Кладовщик', 'Менеджер']

const cities = ref([])
const vacancies = ref([])
const cityCode = ref(localStorage.getItem(CITY_KEY) || '')
const query = ref('')
const draft = ref('')
const initialLoading = ref(true)
const refreshing = ref(false)
const error = ref('')

const selectedCity = computed(() => cities.value.find((item) => item.code === cityCode.value) || null)
const place = computed(() => cityPhrase(selectedCity.value))
const heading = computed(() => (query.value ? `Вакансии по запросу «${query.value}»` : `Вакансии ${place.value}`))

async function refresh() {
  error.value = ''
  refreshing.value = true
  try {
    const payload = await loadVacancies({ q: query.value, city: cityCode.value })
    cities.value = payload.cities || []
    vacancies.value = payload.vacancies || []
    if (cityCode.value && !cities.value.some((item) => item.code === cityCode.value)) {
      cityCode.value = ''
    }
  } catch (exception) {
    error.value = exception.message || 'Не удалось загрузить вакансии'
  } finally {
    initialLoading.value = false
    refreshing.value = false
  }
}

function onCity(code) {
  cityCode.value = code
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

watch(cityCode, (code) => {
  localStorage.setItem(CITY_KEY, code)
  document.title = `Работа ${place.value} — WorkHub`
  refresh()
})

onMounted(refresh)
</script>

<template>
  <SiteHeader :cities="cities" :city="cityCode" @update:city="onCity" />

  <main>
    <section class="banner" aria-label="Баннер">
      <img src="/banner.jpg" alt="Офис: люди работают в открытом пространстве" />
    </section>

    <section class="search-wrap">
      <div class="search-card">
        <h1>Поиск работы</h1>
        <p class="search-place">{{ place }}</p>
        <form class="search" @submit.prevent="submitSearch">
          <label class="sr-only" for="query">Профессия, должность или компания</label>
          <input
            id="query"
            v-model="draft"
            type="search"
            placeholder="Профессия, должность, компания"
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
        Ничего не нашлось. Попробуйте другой запрос или город.
      </p>

      <div v-else class="list" :class="{ dim: refreshing }">
        <VacancyCard v-for="vacancy in vacancies" :key="vacancy.id" :vacancy="vacancy" />
      </div>
    </section>
  </main>
</template>
