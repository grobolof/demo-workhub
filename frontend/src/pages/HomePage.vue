<script setup>
import { computed, inject, onMounted, onUnmounted, ref, watch } from 'vue'
import BannerCarousel from '../components/BannerCarousel.vue'
import VacancyCard from '../components/VacancyCard.vue'
import { loadBanners, loadVacancies } from '../api'
import { cityPhrase, vacancyCount } from '../format'

const SUGGESTIONS = ['Курьер', 'Водитель', 'Продавец', 'Программист', 'Повар', 'Бухгалтер', 'Кладовщик', 'Менеджер']

const cityCode = inject('cityCode', ref(''))
const cities = ref([])
const vacancies = ref([])
const total = ref(0)
const page = ref(1)
const banners = ref([])
const stats = ref(null)
const bannersLoading = ref(true)
const query = ref('')
const draft = ref('')
const initialLoading = ref(true)
const refreshing = ref(false)
const loadingMore = ref(false)
const error = ref('')
const moreError = ref('')

const selectedCity = computed(() => cities.value.find((item) => item.code === cityCode.value) || null)
const place = computed(() => cityPhrase(selectedCity.value))
const heading = computed(() => (query.value ? `Вакансии по запросу «${query.value}»` : `Вакансии ${place.value}`))

let requestId = 0

function nearBottom() {
  return document.documentElement.scrollHeight - window.scrollY - window.innerHeight < 700
}

async function refresh() {
  const current = ++requestId
  error.value = ''
  moreError.value = ''
  page.value = 1
  refreshing.value = true
  try {
    const payload = await loadVacancies({ q: query.value, city: cityCode.value, page: 1 })
    if (current !== requestId) return
    cities.value = payload.cities || []
    vacancies.value = payload.vacancies || []
    total.value = Number(payload.total ?? vacancies.value.length)
  } catch (exception) {
    if (current !== requestId) return
    error.value = exception.message || 'Не удалось загрузить вакансии'
  } finally {
    if (current === requestId) {
      initialLoading.value = false
      refreshing.value = false
      queueMicrotask(maybeLoadMore)
    }
  }
}

async function loadMore() {
  if (loadingMore.value || initialLoading.value || refreshing.value) return
  if (vacancies.value.length >= total.value) return
  const current = requestId
  const nextPage = page.value + 1
  loadingMore.value = true
  moreError.value = ''
  try {
    const payload = await loadVacancies({ q: query.value, city: cityCode.value, page: nextPage })
    if (current !== requestId) return
    const seen = new Set(vacancies.value.map((item) => item.id))
    let added = 0
    for (const item of payload.vacancies || []) {
      if (seen.has(item.id)) continue
      vacancies.value.push(item)
      added += 1
    }
    total.value = Number(payload.total ?? total.value)
    page.value = nextPage
    if (added === 0) total.value = vacancies.value.length
  } catch (exception) {
    if (current !== requestId) return
    moreError.value = exception.message || 'Не удалось загрузить вакансии'
  } finally {
    if (current === requestId) {
      loadingMore.value = false
      if (!moreError.value && vacancies.value.length < total.value && nearBottom()) {
        queueMicrotask(loadMore)
      }
    }
  }
}

function maybeLoadMore() {
  if (nearBottom()) loadMore()
}

let timer = 0

function flushSearch() {
  window.clearTimeout(timer)
  const next = draft.value.trim()
  if (next === query.value && !initialLoading.value) return
  query.value = next
  refresh()
}

watch(draft, () => {
  window.clearTimeout(timer)
  timer = window.setTimeout(flushSearch, 300)
})

function applySuggestion(value) {
  draft.value = value
  flushSearch()
}

watch(cityCode, () => {
  document.title = `Работа ${place.value} — WorkHub`
  refresh()
})

onMounted(async () => {
  document.title = `Работа ${place.value} — WorkHub`
  window.addEventListener('scroll', maybeLoadMore, { passive: true })
  refresh()
  try {
    const payload = await loadBanners()
    banners.value = payload.banners || []
    stats.value = payload.stats || null
  } catch {
    banners.value = []
    stats.value = null
  } finally {
    bannersLoading.value = false
  }
})

onUnmounted(() => {
  window.removeEventListener('scroll', maybeLoadMore)
})
</script>

<template>
  <main>
    <BannerCarousel :banners="banners" :loading="bannersLoading" :stats="stats" />

    <section class="search-wrap">
      <div class="search-card">
        <h1>Поиск работы</h1>
        <p class="search-place">{{ place }}</p>
        <form class="search" @submit.prevent="flushSearch">
          <label class="sr-only" for="query">Название вакансии</label>
          <input
            id="query"
            v-model="draft"
            type="search"
            placeholder="Название вакансии"
            autocomplete="off"
          />
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
        <p v-if="!initialLoading && !error">{{ vacancyCount(total) }}</p>
      </div>

      <p v-if="error" class="state error">{{ error }}</p>

      <div v-else-if="initialLoading" class="preloader preloader-block" role="status">
        <span class="spinner" aria-hidden="true" />
        <span>Загружаем вакансии</span>
      </div>

      <p v-else-if="vacancies.length === 0" class="state">
        Ничего не нашлось. Попробуйте другое название или город.
      </p>

      <div v-else class="list" :class="{ dim: refreshing }">
        <VacancyCard v-for="vacancy in vacancies" :key="vacancy.id" :vacancy="vacancy" />
      </div>

      <div v-if="loadingMore" class="preloader" role="status">
        <span class="spinner" aria-hidden="true" />
        <span>Загружаем вакансии</span>
      </div>
      <p v-if="moreError" class="state error">{{ moreError }}</p>
    </section>
  </main>
</template>
