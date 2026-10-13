<script setup>
import { computed, onMounted, ref } from 'vue'
import VacancyCard from '../components/VacancyCard.vue'
import { loadFavourites } from '../api'
import { session } from '../session'

const vacancies = ref([])
const loading = ref(true)
const error = ref('')
const visible = computed(() => vacancies.value.filter((item) => session.favouriteIds.includes(item.id)))

onMounted(async () => {
  document.title = 'Избранное — WorkHub'
  try {
    const payload = await loadFavourites()
    vacancies.value = payload.vacancies || []
  } catch (exception) {
    error.value = exception.message || 'Не удалось загрузить избранное'
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <p v-if="loading" class="state">Загружаем избранное…</p>
  <p v-else-if="error" class="state error">{{ error }}</p>
  <p v-else-if="visible.length === 0" class="state">В избранном пока пусто. Нажмите на сердечко у вакансии.</p>
  <div v-else class="list">
    <VacancyCard v-for="vacancy in visible" :key="vacancy.id" :vacancy="vacancy" />
  </div>
</template>
