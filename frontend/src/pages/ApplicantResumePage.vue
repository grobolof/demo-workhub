<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { loadApplicant } from '../api'

const route = useRoute()
const applicant = ref(null)
const loading = ref(true)
const error = ref('')

const userId = computed(() => {
  const value = Number(route.params.id)
  return Number.isInteger(value) && value > 0 ? value : 0
})
const vacancyId = computed(() => {
  const value = Number(route.query.vacancy)
  return Number.isInteger(value) && value > 0 ? value : 0
})
const fullName = computed(() => {
  const person = applicant.value
  if (!person) return ''
  return [person.firstName, person.lastName, person.middleName].filter(Boolean).join(' ')
})
const backTo = computed(() => (
  vacancyId.value
    ? `/account/vacancies/${vacancyId.value}`
    : { path: '/account', query: { tab: 'vacancies' } }
))

async function load(vacancy, user) {
  applicant.value = null
  error.value = ''
  if (!vacancy || !user) {
    error.value = 'Резюме не найдено'
    document.title = 'Резюме — WorkHub'
    loading.value = false
    return
  }

  loading.value = true
  try {
    const payload = await loadApplicant(vacancy, user)
    applicant.value = payload.applicant
    document.title = `${fullName.value || 'Резюме'} — WorkHub`
  } catch (exception) {
    error.value = exception.message || 'Не удалось загрузить резюме'
    document.title = 'Резюме — WorkHub'
  } finally {
    loading.value = false
  }
}

watch([vacancyId, userId], ([vacancy, user]) => load(vacancy, user), { immediate: true })
</script>

<template>
  <p v-if="loading" class="state">Загружаем резюме…</p>
  <p v-else-if="error" class="state error">{{ error }}</p>

  <article v-else-if="applicant" class="sheet">
    <router-link class="back" :to="backTo">К вакансии</router-link>
    <div class="person-head">
      <img v-if="applicant.avatar" class="avatar-photo" :src="applicant.avatar" alt="" />
      <div v-else class="avatar-empty">Нет фото</div>
      <div>
        <h1>{{ fullName || 'Соискатель' }}</h1>
        <dl class="contacts">
          <div v-if="applicant.email">
            <dt>Почта:</dt>
            <dd><a :href="`mailto:${applicant.email}`">{{ applicant.email }}</a></dd>
          </div>
          <div v-if="applicant.phone">
            <dt>Телефон:</dt>
            <dd><a :href="`tel:${applicant.phone}`">{{ applicant.phone }}</a></dd>
          </div>
        </dl>
      </div>
    </div>

    <template v-if="applicant.resume">
      <h2 class="section-title">{{ applicant.resume.title }}</h2>
      <div class="prose">{{ applicant.resume.description }}</div>
    </template>
    <p v-else class="state">Резюме не заполнено.</p>
  </article>
</template>
