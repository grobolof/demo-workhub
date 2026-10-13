<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import FavouriteButton from '../components/FavouriteButton.vue'
import { loadResumeFavourites, loadResumes, saveResume } from '../api'
import { formatMoney } from '../format'
import { session } from '../session'

const router = useRouter()
const resumes = ref([])
const notes = ref({})
const limit = ref(10)
const loading = ref(true)
const error = ref('')
const employer = computed(() => session.user?.role === 'employer')
const visibleFavourites = computed(() => resumes.value.filter((item) => session.resumeFavouriteIds.includes(item.id)))

async function load() {
  loading.value = true
  error.value = ''
  try {
    const payload = await loadResumes()
    resumes.value = payload.resumes || []
    limit.value = payload.limit || 10
  } catch (exception) {
    error.value = exception.message || 'Не удалось загрузить резюме'
  } finally {
    loading.value = false
  }
}

async function remove(resume) {
  if (!window.confirm(`Удалить резюме «${resume.title}»?`)) return
  error.value = ''
  try {
    await saveResume({ action: 'delete', id: resume.id }, session.sessid)
    resumes.value = resumes.value.filter((item) => item.id !== resume.id)
  } catch (exception) {
    error.value = exception.message || 'Не удалось удалить резюме'
  }
}

async function loadFavourites() {
  loading.value = true
  error.value = ''
  try {
    const payload = await loadResumeFavourites()
    resumes.value = payload.resumes || []
    session.resumeFavouriteIds = resumes.value.map((item) => item.id)
  } catch (exception) {
    error.value = exception.message || 'Не удалось загрузить резюме'
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  document.title = 'Резюме — WorkHub'
  if (employer.value) loadFavourites()
  else load()
})
</script>

<template>
  <template v-if="employer">
    <p v-if="loading" class="state">Загружаем резюме…</p>
    <p v-else-if="error" class="state error">{{ error }}</p>
    <template v-else>
      <div class="toolbar">
        <p>{{ visibleFavourites.length }} в избранном</p>
        <router-link class="primary slim" to="/resume-search">Найти резюме</router-link>
      </div>
      <p v-if="visibleFavourites.length === 0" class="state">В избранном пока нет резюме. Отметьте резюме в поиске.</p>
      <div v-else class="list">
        <article v-for="resume in visibleFavourites" :key="resume.id" class="resume">
          <div class="resume-top">
            <h2>{{ resume.title }}</h2>
            <div class="salary-row">
              <p class="salary">{{ formatMoney(resume.salary) }}</p>
              <FavouriteButton :resume-id="resume.id" @note="notes[resume.id] = $event" />
            </div>
          </div>
          <p v-if="resume.author" class="company">{{ resume.author }}</p>
          <p v-if="resume.description" class="preview">{{ resume.description }}</p>
          <p v-if="notes[resume.id]" class="action-note">{{ notes[resume.id] }}</p>
        </article>
      </div>
    </template>
  </template>

  <p v-else-if="loading" class="state">Загружаем резюме…</p>
  <p v-else-if="error" class="state error">{{ error }}</p>
  <template v-else>
  <div class="toolbar">
    <p>{{ resumes.length }} из {{ limit }}</p>
    <router-link v-if="resumes.length < limit" class="primary slim" to="/account/resumes/new">Добавить резюме</router-link>
    <p v-else class="hint">Можно хранить не больше {{ limit }} резюме.</p>
  </div>
  <p v-if="resumes.length === 0" class="state">Резюме пока нет.</p>

  <div v-else class="list">
    <article v-for="resume in resumes" :key="resume.id" class="resume">
      <div class="resume-top">
        <h2>{{ resume.title }}</h2>
        <p class="salary">{{ formatMoney(resume.salary) }}</p>
      </div>
      <p class="preview">{{ resume.description }}</p>
      <div class="resume-actions">
        <button class="ghost" type="button" @click="router.push(`/account/resumes/${resume.id}`)">Изменить</button>
        <button class="danger" type="button" @click="remove(resume)">Удалить</button>
      </div>
    </article>
  </div>
  </template>
</template>
