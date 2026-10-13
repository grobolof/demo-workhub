<script setup>
import { onMounted, ref } from 'vue'
import { searchResumes } from '../api'
import { formatMoney } from '../format'

const draft = ref('')
const query = ref('')
const resumes = ref([])
const loading = ref(true)
const error = ref('')

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

onMounted(() => {
  document.title = 'Поиск резюме — WorkHub'
  load()
})
</script>

<template>
  <main class="page">
    <h1 class="page-title">Поиск резюме</h1>
    <form class="search resume-search" @submit.prevent="submit">
      <label class="sr-only" for="resume-query">Должность или навык</label>
      <input id="resume-query" v-model="draft" type="search" placeholder="Должность или навык" autocomplete="off" />
    </form>

    <p v-if="loading" class="state">Ищем резюме…</p>
    <p v-else-if="error" class="state error">{{ error }}</p>
    <p v-else-if="resumes.length === 0" class="state">Ничего не нашлось.</p>
    <div v-else class="list">
      <article v-for="resume in resumes" :key="resume.id" class="resume">
        <div class="resume-top">
          <h2>{{ resume.title }}</h2>
          <p v-if="resume.salary" class="salary">{{ formatMoney(resume.salary) }}</p>
        </div>
        <p v-if="resume.description" class="preview">{{ resume.description }}</p>
      </article>
    </div>
  </main>
</template>
