<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { login, refreshSession, register, session } from '../session'

const props = defineProps({
  mode: { type: String, required: true },
})

const route = useRoute()
const router = useRouter()
const role = ref('seeker')
const email = ref('')
const password = ref('')
const passwordConfirm = ref('')
const error = ref('')
const pending = ref(false)

const isRegister = computed(() => props.mode === 'register')

function destination() {
  const value = route.query.redirect
  if (typeof value === 'string' && value.startsWith('/') && !value.startsWith('//') && !value.includes('\\')) {
    return value
  }

  return session.user?.role === 'seeker' ? '/account/favourites' : '/'
}

watch(() => props.mode, () => {
  document.title = `${isRegister.value ? 'Регистрация' : 'Вход'} — WorkHub`
  error.value = ''
}, { immediate: true })

watch(() => session.ready && session.user, (loggedIn) => {
  if (loggedIn) router.replace(destination())
}, { immediate: true })

async function submit() {
  error.value = ''
  if (!email.value.trim() || !password.value || (isRegister.value && !passwordConfirm.value)) {
    error.value = 'Заполните все поля'
    return
  }
  if (isRegister.value && password.value !== passwordConfirm.value) {
    error.value = 'Пароли не совпадают'
    return
  }
  if (isRegister.value && !/^[A-Za-z0-9]{6,20}$/.test(password.value)) {
    error.value = 'Пароль должен состоять из латинских букв и цифр и быть длиной от 6 до 20 символов'
    return
  }

  pending.value = true
  try {
    const body = {
      email: email.value,
      password: password.value,
      role: role.value,
    }
    if (isRegister.value) {
      await register({ ...body, passwordConfirm: passwordConfirm.value })
    } else {
      await login(body)
    }
    router.push(destination())
  } catch (exception) {
    error.value = exception.message || 'Не удалось выполнить запрос'
    await refreshSession()
  } finally {
    pending.value = false
  }
}
</script>

<template>
  <main class="auth-wrap">
    <form class="sheet auth-card" @submit.prevent="submit">
      <h1>{{ isRegister ? 'Регистрация' : 'Вход' }}</h1>
      <p class="hint">Укажите, кто вы: соискатель или работодатель.</p>

      <div class="roles" role="radiogroup" aria-label="Роль">
        <label class="role" :class="{ active: role === 'seeker' }">
          <input v-model="role" type="radio" value="seeker" />
          Соискатель
        </label>
        <label class="role" :class="{ active: role === 'employer' }">
          <input v-model="role" type="radio" value="employer" />
          Работодатель
        </label>
      </div>

      <label class="field">
        <span>Почта</span>
        <input v-model="email" type="email" autocomplete="email" required />
      </label>

      <label class="field">
        <span>Пароль</span>
        <input
          v-model="password"
          type="password"
          :autocomplete="isRegister ? 'new-password' : 'current-password'"
          required
        />
        <small v-if="isRegister" class="hint">От 6 до 20 символов: только латинские буквы и цифры.</small>
      </label>

      <label v-if="isRegister" class="field">
        <span>Подтверждение пароля</span>
        <input v-model="passwordConfirm" type="password" autocomplete="new-password" required />
      </label>

      <p v-if="error" class="form-error">{{ error }}</p>

      <button class="primary" type="submit" :disabled="pending || !session.ready">
        {{ isRegister ? 'Зарегистрироваться' : 'Войти' }}
      </button>

      <p class="switch">
        <router-link v-if="isRegister" :to="{ path: '/login', query: route.query }">Уже есть аккаунт? Войти</router-link>
        <router-link v-else :to="{ path: '/register', query: route.query }">Нет аккаунта? Зарегистрироваться</router-link>
      </p>
    </form>
  </main>
</template>
