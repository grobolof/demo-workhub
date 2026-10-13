<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { loadVacancy } from '../api'
import { formatSalary } from '../format'

const route = useRoute()
const vacancy = ref(null)
const loading = ref(true)
const error = ref('')

const salary = computed(() => (
  vacancy.value ? formatSalary(vacancy.value.salaryFrom, vacancy.value.salaryTo) : ''
))
const logoText = computed(() => (vacancy.value?.company?.name || 'W').slice(0, 1).toUpperCase())

async function load(id) {
  loading.value = true
  error.value = ''
  vacancy.value = null
  const numericId = Number(id)
  if (!Number.isInteger(numericId) || numericId <= 0) {
    error.value = 'Вакансия не найдена'
    document.title = 'Вакансия — WorkHub'
    loading.value = false
    return
  }

  try {
    const payload = await loadVacancy(numericId)
    vacancy.value = payload.vacancy
    document.title = `${payload.vacancy.name} — WorkHub`
  } catch (exception) {
    error.value = exception.message || 'Не удалось загрузить вакансию'
    document.title = 'Вакансия — WorkHub'
  } finally {
    loading.value = false
  }
}

watch(() => route.params.id, (id) => load(id), { immediate: true })
</script>

<template>
  <main class="page">
    <router-link class="back" to="/">Все вакансии</router-link>

    <p v-if="loading" class="state">Загружаем вакансию…</p>
    <p v-else-if="error" class="state error">{{ error }}</p>

    <article v-else-if="vacancy" class="sheet">
      <div class="sheet-top">
        <h1>{{ vacancy.name }}</h1>
        <p class="salary">{{ salary }}</p>
      </div>

      <router-link
        v-if="vacancy.company"
        class="employer"
        :to="`/company/${vacancy.company.id}`"
      >
        <span class="vacancy-logo">
          <img v-if="vacancy.company.logo" :src="vacancy.company.logo" :alt="vacancy.company.name" />
          <span v-else>{{ logoText }}</span>
        </span>
        <span>
          <strong>{{ vacancy.company.name }}</strong>
          <small>Страница компании</small>
        </span>
      </router-link>

      <ul v-if="vacancy.cities?.length" class="places">
        <li v-for="place in vacancy.cities" :key="place.code">{{ place.name }}</li>
      </ul>

      <div v-if="vacancy.detail" class="prose">{{ vacancy.detail }}</div>
      <p v-else-if="vacancy.preview" class="prose">{{ vacancy.preview }}</p>
    </article>
  </main>
</template>
