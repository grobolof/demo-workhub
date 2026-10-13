<script setup>
import { onMounted, ref } from 'vue'
import { postRequest } from '../api'
import { session } from '../session'

const email = ref('')
const subject = ref('')
const message = ref('')
const files = ref([])
const dragging = ref(false)
const error = ref('')
const success = ref('')
const pending = ref(false)

const extensions = ['png', 'jpg', 'jpeg']
const mimeTypes = ['image/png', 'image/jpeg']

onMounted(() => {
  document.title = 'Помощь — WorkHub'
})

function extension(name) {
  const index = name.lastIndexOf('.')
  return index === -1 ? '' : name.slice(index + 1).toLowerCase()
}

function addFiles(list) {
  error.value = ''
  success.value = ''
  const next = [...files.value]
  for (const file of list) {
    const ext = extension(file.name)
    const typeOk = !file.type || mimeTypes.includes(file.type)
    if (!extensions.includes(ext) || !typeOk) {
      error.value = 'Можно прикрепить только файлы PNG, JPG и JPEG'
      continue
    }
    if (next.length >= 10) {
      error.value = 'Можно прикрепить не больше 10 файлов'
      break
    }
    next.push(file)
  }
  files.value = next
}

function onPick(event) {
  addFiles(event.target.files || [])
  event.target.value = ''
}

function onDrop(event) {
  dragging.value = false
  addFiles(event.dataTransfer?.files || [])
}

function onDragLeave(event) {
  if (event.currentTarget.contains(event.relatedTarget)) return
  dragging.value = false
}

function removeFile(index) {
  files.value = files.value.filter((_, item) => item !== index)
}

function validEmail(value) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/i.test(value)
}

async function submit() {
  error.value = ''
  success.value = ''
  const emailValue = email.value.trim()
  const subjectValue = subject.value.trim()
  const messageValue = message.value.trim()
  if (!emailValue || !subjectValue || !messageValue) {
    error.value = 'Заполните email, тему и сообщение'
    return
  }
  if (!validEmail(emailValue)) {
    error.value = 'Укажите корректный email'
    return
  }
  if (files.value.length > 10) {
    error.value = 'Можно прикрепить не больше 10 файлов'
    return
  }

  pending.value = true
  try {
    await postRequest({
      email: emailValue,
      subject: subjectValue,
      message: messageValue,
      files: files.value,
    }, session.sessid)
    email.value = ''
    subject.value = ''
    message.value = ''
    files.value = []
    success.value = 'Заявка отправлена'
  } catch (exception) {
    error.value = exception.message || 'Не удалось отправить заявку'
  } finally {
    pending.value = false
  }
}
</script>

<template>
  <main class="page">
    <article class="sheet">
      <h1>Помощь</h1>
      <div class="prose">Выберите город в шапке и введите название вакансии. Список на главной подгружается по мере прокрутки.

Войти в кабинет можно кнопкой «Войти». Соискателю доступны резюме, избранное и отклики. Работодатель может смотреть резюме в разделе «Поиск резюме».</div>

      <h2>Заявка</h2>
      <form @submit.prevent="submit">
        <label class="field">
          <span>Email</span>
          <input v-model="email" type="email" autocomplete="email" required />
        </label>
        <label class="field">
          <span>Тема</span>
          <input v-model="subject" type="text" maxlength="255" required />
        </label>
        <label class="field">
          <span>Сообщение</span>
          <textarea v-model="message" required />
        </label>
        <div class="field">
          <span>Файлы</span>
          <label
            class="dropzone"
            :class="{ over: dragging }"
            @dragenter.prevent="dragging = true"
            @dragover.prevent="dragging = true"
            @dragleave="onDragLeave"
            @drop.prevent="onDrop"
          >
            <input
              class="sr-only"
              type="file"
              accept="image/png,image/jpeg,.png,.jpg,.jpeg"
              multiple
              @change="onPick"
            />
            <strong>Перетащите изображения сюда или нажмите, чтобы выбрать</strong>
            <small>Необязательно. PNG, JPG или JPEG, не больше 10 файлов.</small>
          </label>
          <ul v-if="files.length" class="file-list">
            <li v-for="(file, index) in files" :key="`${file.name}-${index}`">
              <span>{{ file.name }}</span>
              <button type="button" @click="removeFile(index)">Убрать</button>
            </li>
          </ul>
        </div>
        <p v-if="error" class="form-error">{{ error }}</p>
        <p v-if="success" class="form-ok">{{ success }}</p>
        <button class="primary" type="submit" :disabled="pending || !session.ready">Отправить</button>
      </form>
    </article>
  </main>
</template>
