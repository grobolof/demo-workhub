<script setup>
import { onMounted, ref } from 'vue'
import { loadCompanies } from '../api'

const companies = ref([])
const loading = ref(true)
const error = ref('')

onMounted(async () => {
  document.title = 'Компании — WorkHub'
  try {
    const payload = await loadCompanies()
    companies.value = payload.companies || []
  } catch (exception) {
    error.value = exception.message || 'Не удалось загрузить компании'
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <main class="page">
    <h1 class="page-title">Компании</h1>
    <p v-if="loading" class="state">Загружаем компании…</p>
    <p v-else-if="error" class="state error">{{ error }}</p>
    <p v-else-if="companies.length === 0" class="state">Компаний пока нет.</p>
    <div v-else class="list company-list">
      <router-link v-for="company in companies" :key="company.id" class="company-row" :to="`/company/${company.id}`">
        <span class="company-logo">
          <img v-if="company.logo" :src="company.logo" alt="" />
          <template v-else>{{ company.name.slice(0, 1) }}</template>
        </span>
        <strong>{{ company.name }}</strong>
      </router-link>
    </div>
  </main>
</template>
