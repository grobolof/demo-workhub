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
const lastName = ref('')
const firstName = ref('')
const middleName = ref('')
const companyName = ref('')
const inn = ref('')
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

  if (session.user?.role === 'employer') return '/account/vacancies'
  if (session.user?.role === 'seeker') return '/account/favourites'
  return '/'
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
  if (isRegister.value && (!lastName.value.trim() || !firstName.value.trim())) {
    error.value = 'Укажите фамилию и имя'
    return
  }
  if (isRegister.value && role.value === 'employer' && !companyName.value.trim()) {
    error.value = 'Укажите название компании'
    return
  }
  if (isRegister.value && role.value === 'employer' && !/^\d{10}(\d{2})?$/.test(inn.value.trim())) {
    error.value = 'Укажите ИНН из 10 или 12 цифр'
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
      await register({
        ...body,
        passwordConfirm: passwordConfirm.value,
        firstName: firstName.value,
        lastName: lastName.value,
        middleName: middleName.value,
        companyName: role.value === 'employer' ? companyName.value : '',
        inn: role.value === 'employer' ? inn.value.trim() : '',
      })
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

      <template v-if="isRegister">
        <label class="field">
          <span>Фамилия</span>
          <input v-model="lastName" type="text" autocomplete="family-name" maxlength="50" required />
        </label>
        <label class="field">
          <span>Имя</span>
          <input v-model="firstName" type="text" autocomplete="given-name" maxlength="50" required />
        </label>
        <label class="field">
          <span>Отчество</span>
          <input v-model="middleName" type="text" autocomplete="additional-name" maxlength="50" />
          <small class="hint">Необязательно</small>
        </label>
      </template>

      <label class="field">
        <span>Почта</span>
        <input v-model="email" type="email" autocomplete="email" required />
      </label>

      <template v-if="isRegister && role === 'employer'">
        <label class="field">
          <span>Название компании</span>
          <input v-model="companyName" type="text" autocomplete="organization" maxlength="255" required />
        </label>
        <label class="field">
          <span>ИНН</span>
          <input v-model="inn" type="text" inputmode="numeric" autocomplete="off" maxlength="12" required />
        </label>
      </template>

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
