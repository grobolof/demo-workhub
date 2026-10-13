<script setup>
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { loadMyVacancies, saveVacancy } from '../api'
import { formatMoney } from '../format'
import { session } from '../session'

const router = useRouter()
const vacancies = ref([])
const loading = ref(true)
const error = ref('')

async function load() {
  loading.value = true
  error.value = ''
  try {
    const payload = await loadMyVacancies()
    vacancies.value = payload.vacancies || []
  } catch (exception) {
    error.value = exception.message || 'Не удалось загрузить вакансии'
  } finally {
    loading.value = false
  }
}

async function remove(vacancy) {
  if (!window.confirm(`Удалить вакансию «${vacancy.title}»?`)) return
  error.value = ''
  try {
    await saveVacancy({ action: 'delete', id: vacancy.id }, session.sessid)
    vacancies.value = vacancies.value.filter((item) => item.id !== vacancy.id)
  } catch (exception) {
    error.value = exception.message || 'Не удалось удалить вакансию'
  }
}

onMounted(() => {
  document.title = 'Вакансии — WorkHub'
  load()
})
</script>

<template>
  <p v-if="loading" class="state">Загружаем вакансии…</p>
  <p v-else-if="error" class="state error">{{ error }}</p>
  <template v-else>
    <div class="toolbar">
      <p>{{ vacancies.length }}</p>
      <router-link class="primary slim" to="/account/vacancies/new">Добавить вакансию</router-link>
    </div>
    <p v-if="vacancies.length === 0" class="state">Вакансий пока нет.</p>

    <div v-else class="list">
      <article v-for="vacancy in vacancies" :key="vacancy.id" class="resume">
        <div class="resume-top">
          <h2>{{ vacancy.title }}</h2>
          <p class="salary">{{ formatMoney(vacancy.salary) }}</p>
        </div>
        <ul v-if="vacancy.workplaceLabel || vacancy.scheduleLabel" class="places">
          <li v-if="vacancy.workplaceLabel">{{ vacancy.workplaceLabel }}</li>
          <li v-if="vacancy.scheduleLabel">{{ vacancy.scheduleLabel }}</li>
        </ul>
        <p class="preview">{{ vacancy.description }}</p>
        <div class="resume-actions">
          <button class="ghost" type="button" @click="router.push(`/account/vacancies/${vacancy.id}`)">Изменить</button>
          <button class="danger" type="button" @click="remove(vacancy)">Удалить</button>
        </div>
      </article>
    </div>
  </template>
</template>
