<script setup>
import { onMounted, ref } from 'vue'
import VacancyCard from '../components/VacancyCard.vue'
import { loadResponses } from '../api'

const vacancies = ref([])
const loading = ref(true)
const error = ref('')

onMounted(async () => {
  document.title = 'Отклики — WorkHub'
  try {
    const payload = await loadResponses()
    vacancies.value = payload.vacancies || []
  } catch (exception) {
    error.value = exception.message || 'Не удалось загрузить отклики'
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <p v-if="loading" class="state">Загружаем отклики…</p>
  <p v-else-if="error" class="state error">{{ error }}</p>
  <p v-else-if="vacancies.length === 0" class="state">Вы ещё не откликались на вакансии.</p>
  <div v-else class="list">
    <VacancyCard v-for="vacancy in vacancies" :key="vacancy.id" :vacancy="vacancy" />
  </div>
</template>
