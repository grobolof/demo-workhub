<script setup>
import { computed, ref, watch } from 'vue'
import { searchResumes } from '../api'
import FavouriteButton from '../components/FavouriteButton.vue'
import { formatMoney } from '../format'
import { session } from '../session'

const draft = ref('')
const query = ref('')
const resumes = ref([])
const notes = ref({})
const loading = ref(false)
const error = ref('')
const allowed = computed(() => session.ready && session.user?.role === 'employer')

async function load() {
  loading.value = true
  error.value = ''
  try {
    const payload = await searchResumes(query.value)
    resumes.value = payload.resumes || []
  } catch (exception) {
    error.value = exception.message || 'Не удалось найти резюме'
  } finally {
    loading.value = false
  }
}

function submit() {
  query.value = draft.value.trim()
  load()
}

watch(allowed, (ok) => {
  document.title = 'Поиск резюме — WorkHub'
  if (!ok) {
    loading.value = false
    resumes.value = []
    return
  }
  load()
}, { immediate: true })
</script>

<template>
  <main class="page">
    <h1 class="page-title">Поиск резюме</h1>
    <p v-if="!session.ready" class="state">Проверяем доступ…</p>
    <p v-else-if="!allowed" class="state error">Поиск резюме доступен только работодателям.</p>
    <form v-else class="search resume-search" @submit.prevent="submit">
      <label class="sr-only" for="resume-query">Должность или навык</label>
      <input id="resume-query" v-model="draft" type="search" placeholder="Должность или навык" autocomplete="off" />
    </form>

    <p v-if="allowed && loading" class="state">Ищем резюме…</p>
    <p v-else-if="allowed && error" class="state error">{{ error }}</p>
    <p v-else-if="allowed && resumes.length === 0" class="state">Ничего не нашлось.</p>
    <div v-else-if="allowed" class="list">
      <article v-for="resume in resumes" :key="resume.id" class="resume">
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
  </main>
</template>
