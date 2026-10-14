<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { loadMyVacancy, saveVacancy } from '../api'
import VacancyFacts from '../components/VacancyFacts.vue'
import { formatDateTime, formatMoney, responseCount } from '../format'
import { session } from '../session'

const route = useRoute()
const router = useRouter()
const vacancy = ref(null)
const loading = ref(true)
const error = ref('')
const pending = ref(false)

const vacancyId = computed(() => {
  const value = Number(route.params.id)
  return Number.isInteger(value) && value > 0 ? value : 0
})
const applicants = computed(() => {
  const people = [...(vacancy.value?.applicants || [])]
  people.sort((left, right) => respondedAt(right) - respondedAt(left))
  return people
})

function respondedAt(person) {
  const time = Date.parse(person.respondedAt || '')
  return Number.isNaN(time) ? 0 : time
}

async function load(id) {
  vacancy.value = null
  error.value = ''
  if (!id) {
    error.value = 'Вакансия не найдена'
    document.title = 'Вакансия — WorkHub'
    loading.value = false
    return
  }

  loading.value = true
  try {
    const payload = await loadMyVacancy(id)
    vacancy.value = payload.vacancy
    document.title = `${payload.vacancy.title} — WorkHub`
  } catch (exception) {
    error.value = exception.message || 'Не удалось загрузить вакансию'
    document.title = 'Вакансия — WorkHub'
  } finally {
    loading.value = false
  }
}

async function remove() {
  if (!vacancy.value || !window.confirm(`Удалить вакансию «${vacancy.value.title}»?`)) return
  pending.value = true
  error.value = ''
  try {
    await saveVacancy({ action: 'delete', id: vacancy.value.id }, session.sessid)
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
  <p v-else-if="error && !vacancy" class="state error">{{ error }}</p>

  <template v-else-if="vacancy">
    <router-link class="back" :to="{ path: '/account', query: { tab: 'vacancies' } }">К вакансиям</router-link>
    <article class="resume">
      <div class="resume-top">
        <h2>{{ vacancy.title }}</h2>
        <p class="salary">{{ formatMoney(vacancy.salary) }}</p>
      </div>
      <VacancyFacts
        :schedule="vacancy.scheduleLabel"
        :workplace="vacancy.workplaceLabel"
        :cities="vacancy.cities || []"
      />
      <p v-if="vacancy.description" class="preview">{{ vacancy.description }}</p>
      <p class="responses-count">{{ responseCount(vacancy.responses || applicants.length) }}</p>
      <div class="resume-actions">
        <button class="ghost" type="button" :disabled="pending" @click="router.push(`/account/vacancies/${vacancy.id}/edit`)">
          Изменить
        </button>
        <button class="danger" type="button" :disabled="pending" @click="remove">Удалить</button>
      </div>
      <p v-if="error" class="form-error">{{ error }}</p>
    </article>

    <h2 class="section-title">Отклики</h2>
    <p v-if="applicants.length === 0" class="state">Откликов пока нет.</p>
    <div v-else class="list">
      <article v-for="person in applicants" :key="person.id" class="resume">
        <router-link
          class="resume-hit applicant-link"
          :to="{ path: `/account/applicants/${person.id}`, query: { vacancy: String(vacancy.id) } }"
        >
          <div class="applicant-main">
            <img v-if="person.avatar" class="avatar-photo applicant-photo" :src="person.avatar" alt="" />
            <div v-else class="avatar-empty applicant-photo">Нет фото</div>
            <div>
              <h2>{{ person.name }}</h2>
              <p v-if="person.resumeTitle" class="company">{{ person.resumeTitle }}</p>
              <p v-if="person.respondedAt" class="responded">Отклик {{ formatDateTime(person.respondedAt) }}</p>
            </div>
          </div>
        </router-link>
      </article>
    </div>
  </template>
</template>
