<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import VacancyCard from '../components/VacancyCard.vue'
import { loadCompany } from '../api'
import { vacancyCount } from '../format'

const route = useRoute()
const company = ref(null)
const loading = ref(true)
const error = ref('')

const logoText = computed(() => (company.value?.name || 'W').slice(0, 1).toUpperCase())
const total = computed(() => {
  const seen = new Set()
  for (const city of company.value?.cities || []) {
    for (const vacancy of city.vacancies || []) seen.add(vacancy.id)
  }
  return seen.size
})

async function load(id) {
  loading.value = true
  error.value = ''
  company.value = null
  const numericId = Number(id)
  if (!Number.isInteger(numericId) || numericId <= 0) {
    error.value = 'Компания не найдена'
    document.title = 'Компания — WorkHub'
    loading.value = false
    return
  }

  try {
    const payload = await loadCompany(numericId)
    company.value = payload.company
    document.title = `${payload.company.name} — WorkHub`
  } catch (exception) {
    error.value = exception.message || 'Не удалось загрузить компанию'
    document.title = 'Компания — WorkHub'
  } finally {
    loading.value = false
  }
}

watch(() => route.params.id, (id) => load(id), { immediate: true })
</script>

<template>
  <main class="page">
    <router-link class="back" to="/">Все вакансии</router-link>

    <p v-if="loading" class="state">Загружаем компанию…</p>
    <p v-else-if="error" class="state error">{{ error }}</p>

    <template v-else-if="company">
      <article class="sheet company-hero">
        <div class="company-logo">
          <img v-if="company.logo" :src="company.logo" :alt="company.name" />
          <span v-else>{{ logoText }}</span>
        </div>
        <div>
          <h1>{{ company.name }}</h1>
          <p v-if="company.preview" class="lead">{{ company.preview }}</p>
        </div>
      </article>

      <article v-if="company.detail" class="sheet">
        <h2>О компании</h2>
        <div class="prose">{{ company.detail }}</div>
      </article>

      <section class="company-jobs">
        <div class="results-head">
          <h2>Вакансии</h2>
          <p>{{ vacancyCount(total) }}</p>
        </div>

        <p v-if="!company.cities?.length" class="state">У компании пока нет открытых вакансий.</p>

        <section v-for="city in company.cities" :key="city.id" class="city-group">
          <h3>{{ city.name }}</h3>
          <div class="list">
            <VacancyCard
              v-for="vacancy in city.vacancies"
              :key="`${city.id}-${vacancy.id}`"
              :vacancy="vacancy"
              :show-company="false"
            />
          </div>
        </section>
      </section>
    </template>
  </main>
</template>
