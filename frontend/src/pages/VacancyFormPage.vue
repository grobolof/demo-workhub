<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { loadMyVacancy, saveVacancy } from '../api'
import { session } from '../session'

const workplaces = [
  { value: 'office', label: 'Офис' },
  { value: 'hybrid', label: 'Гибрид' },
  { value: 'remote', label: 'Удалённо' },
]
const schedules = [
  { value: '5-2', label: '5/2' },
  { value: 'floating', label: 'Плавающий' },
  { value: 'shift', label: 'Вахта' },
]

const route = useRoute()
const router = useRouter()
const title = ref('')
const salary = ref('')
const description = ref('')
const workplace = ref('')
const schedule = ref('')
const error = ref('')
const loading = ref(false)
const pending = ref(false)

const vacancyId = computed(() => {
  const value = Number(route.params.id)
  return Number.isInteger(value) && value > 0 ? value : 0
})
const editing = computed(() => vacancyId.value > 0)

async function load(id) {
  title.value = ''
  salary.value = ''
  description.value = ''
  workplace.value = ''
  schedule.value = ''
  error.value = ''
  if (!id) {
    document.title = 'Новая вакансия — WorkHub'
    loading.value = false
    return
  }

  loading.value = true
  try {
    const payload = await loadMyVacancy(id)
    title.value = payload.vacancy.title
    salary.value = String(payload.vacancy.salary)
    description.value = payload.vacancy.description
    workplace.value = payload.vacancy.workplace
    schedule.value = payload.vacancy.schedule
    document.title = 'Изменение вакансии — WorkHub'
  } catch (exception) {
    error.value = exception.message || 'Не удалось загрузить вакансию'
    document.title = 'Вакансия — WorkHub'
  } finally {
    loading.value = false
  }
}

async function submit() {
  error.value = ''
  if (!title.value.trim() || !description.value.trim() || String(salary.value).trim() === '') {
    error.value = 'Заполните название, заработную плату и описание'
    return
  }
  if (!/^\d{1,12}$/.test(String(salary.value).trim())) {
    error.value = 'Укажите заработную плату числом'
    return
  }
  if (!workplace.value) {
    error.value = 'Выберите место работы'
    return
  }
  if (!schedule.value) {
    error.value = 'Выберите график работы'
    return
  }

  pending.value = true
  try {
    await saveVacancy({
      action: editing.value ? 'update' : 'create',
      id: vacancyId.value,
      title: title.value,
      salary: String(salary.value).trim(),
      description: description.value,
      workplace: workplace.value,
      schedule: schedule.value,
    }, session.sessid)
    router.push({ path: '/account', query: { tab: 'vacancies' } })
  } catch (exception) {
    error.value = exception.message || 'Не удалось сохранить вакансию'
  } finally {
    pending.value = false
  }
}

async function remove() {
  if (!editing.value || !window.confirm('Удалить вакансию?')) return
  pending.value = true
  error.value = ''
  try {
    await saveVacancy({ action: 'delete', id: vacancyId.value }, session.sessid)
    router.push({ path: '/account', query: { tab: 'vacancies' } })
  } catch (exception) {
    error.value = exception.message || 'Не удалось удалить вакансию'
  } finally {
    pending.value = false
  }
}

watch(vacancyId, (id) => load(id), { immediate: true })
</script>

<template>
  <p v-if="loading" class="state">Загружаем вакансию…</p>

  <form v-else class="sheet resume-form" @submit.prevent="submit">
    <h2>{{ editing ? 'Изменить вакансию' : 'Новая вакансия' }}</h2>

    <label class="field">
      <span>Название<span class="req" aria-hidden="true">*</span></span>
      <input v-model="title" type="text" maxlength="255" required />
    </label>

    <label class="field">
      <span>Описание<span class="req" aria-hidden="true">*</span></span>
      <textarea v-model="description" required />
    </label>

    <label class="field">
      <span>Заработная плата<span class="req" aria-hidden="true">*</span></span>
      <input v-model="salary" type="text" inputmode="numeric" required />
    </label>

    <label class="field">
      <span>Место работы<span class="req" aria-hidden="true">*</span></span>
      <select v-model="workplace" required>
        <option disabled value="">Выберите</option>
        <option v-for="item in workplaces" :key="item.value" :value="item.value">{{ item.label }}</option>
      </select>
    </label>

    <label class="field">
      <span>График работы<span class="req" aria-hidden="true">*</span></span>
      <select v-model="schedule" required>
        <option disabled value="">Выберите</option>
        <option v-for="item in schedules" :key="item.value" :value="item.value">{{ item.label }}</option>
      </select>
    </label>

    <p v-if="error" class="form-error">{{ error }}</p>

    <div class="resume-actions">
      <button class="primary slim" type="submit" :disabled="pending">Сохранить</button>
      <button v-if="editing" class="danger" type="button" :disabled="pending" @click="remove">Удалить</button>
      <router-link class="ghost link" :to="{ path: '/account', query: { tab: 'vacancies' } }">Отмена</router-link>
    </div>
  </form>
</template>
