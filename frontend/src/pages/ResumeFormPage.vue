<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { loadResume, saveResume } from '../api'
import { session } from '../session'

const route = useRoute()
const router = useRouter()
const title = ref('')
const salary = ref('')
const description = ref('')
const error = ref('')
const loading = ref(false)
const pending = ref(false)

const resumeId = computed(() => {
  const value = Number(route.params.id)
  return Number.isInteger(value) && value > 0 ? value : 0
})
const editing = computed(() => resumeId.value > 0)

async function load(id) {
  title.value = ''
  salary.value = ''
  description.value = ''
  error.value = ''
  if (!id) {
    document.title = 'Новое резюме — WorkHub'
    loading.value = false
    return
  }

  loading.value = true
  try {
    const payload = await loadResume(id)
    title.value = payload.resume.title
    salary.value = String(payload.resume.salary)
    description.value = payload.resume.description
    document.title = 'Изменение резюме — WorkHub'
  } catch (exception) {
    error.value = exception.message || 'Не удалось загрузить резюме'
    document.title = 'Резюме — WorkHub'
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

  pending.value = true
  try {
    await saveResume({
      action: editing.value ? 'update' : 'create',
      id: resumeId.value,
      title: title.value,
      salary: String(salary.value).trim(),
      description: description.value,
    }, session.sessid)
    router.push({ path: '/account', query: { tab: 'resumes' } })
  } catch (exception) {
    error.value = exception.message || 'Не удалось сохранить резюме'
  } finally {
    pending.value = false
  }
}

async function remove() {
  if (!editing.value || !window.confirm('Удалить резюме?')) return
  pending.value = true
  error.value = ''
  try {
    await saveResume({ action: 'delete', id: resumeId.value }, session.sessid)
    router.push({ path: '/account', query: { tab: 'resumes' } })
  } catch (exception) {
    error.value = exception.message || 'Не удалось удалить резюме'
  } finally {
    pending.value = false
  }
}

watch(resumeId, (id) => load(id), { immediate: true })
</script>

<template>
  <p v-if="loading" class="state">Загружаем резюме…</p>

  <form v-else class="sheet resume-form" @submit.prevent="submit">
    <h2>{{ editing ? 'Изменить резюме' : 'Новое резюме' }}</h2>

    <label class="field">
      <span>Название резюме</span>
      <input v-model="title" type="text" maxlength="255" required />
    </label>

    <label class="field">
      <span>Заработная плата</span>
      <input v-model="salary" type="text" inputmode="numeric" required />
    </label>

    <label class="field">
      <span>Описание резюме</span>
      <textarea v-model="description" required />
    </label>

    <p v-if="error" class="form-error">{{ error }}</p>

    <div class="resume-actions">
      <button class="primary slim" type="submit" :disabled="pending">Сохранить</button>
      <button v-if="editing" class="danger" type="button" :disabled="pending" @click="remove">Удалить</button>
      <router-link class="ghost link" :to="{ path: '/account', query: { tab: 'resumes' } }">Отмена</router-link>
    </div>
  </form>
</template>
