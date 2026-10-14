<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import FavouritesPage from './FavouritesPage.vue'
import ResumesPage from './ResumesPage.vue'
import ResponsesPage from './ResponsesPage.vue'
import VacanciesPage from './VacanciesPage.vue'
import { logout, session, updateAvatar, updateCompany, updateCompanyLogo, updateProfile } from '../session'

const route = useRoute()
const router = useRouter()
const seeker = computed(() => session.user?.role === 'seeker')
const onAccount = computed(() => route.path === '/account')
const bare = computed(() => /^\/account\/vacancies\/\d+$/.test(route.path) || /^\/account\/applicants\/\d+$/.test(route.path))
const tab = computed(() => {
  const value = typeof route.query.tab === 'string' ? route.query.tab : ''
  if (seeker.value) return ['favourites', 'resumes', 'responses'].includes(value) ? value : 'favourites'
  return value === 'resumes' ? 'resumes' : 'vacancies'
})
const seekerTabs = [
  { name: 'favourites', label: 'Избранное' },
  { name: 'resumes', label: 'Резюме' },
  { name: 'responses', label: 'Отклики' },
]
const employerTabs = [
  { name: 'vacancies', label: 'Вакансии' },
  { name: 'resumes', label: 'Резюме' },
]
const tabView = computed(() => {
  if (tab.value === 'resumes') return ResumesPage
  if (tab.value === 'responses') return ResponsesPage
  if (tab.value === 'favourites') return FavouritesPage
  return VacanciesPage
})
const roleLabel = computed(() => (seeker.value ? 'соискатель' : 'работодатель'))
const lastName = ref('')
const firstName = ref('')
const middleName = ref('')
const nameError = ref('')
const nameSaving = ref(false)
const savedNames = ref({ lastName: '', firstName: '', middleName: '' })
const avatarError = ref('')
const avatarPending = ref(false)
const dragging = ref(false)
const avatar = computed(() => session.user?.avatar || '')
const companyName = ref('')
const companyInn = ref('')
const companyDescription = ref('')
const companyError = ref('')
const companySaving = ref(false)
const savedCompany = ref({ name: '', inn: '', description: '' })
const logoError = ref('')
const logoPending = ref(false)
const logoDragging = ref(false)
const logo = computed(() => session.user?.company?.logo || '')
const extensions = ['png', 'jpg', 'jpeg']
const mimeTypes = ['image/png', 'image/jpeg']

function namesOf(user) {
  return {
    lastName: user?.lastName || '',
    firstName: user?.firstName || '',
    middleName: user?.middleName || '',
  }
}

function sameNames(left, right) {
  return left.lastName === right.lastName && left.firstName === right.firstName && left.middleName === right.middleName
}

const draftNames = computed(() => ({
  lastName: lastName.value.trim(),
  firstName: firstName.value.trim(),
  middleName: middleName.value.trim(),
}))
const namesDirty = computed(() => !sameNames(draftNames.value, savedNames.value))

function companyOf(user) {
  return {
    name: user?.company?.name || '',
    inn: user?.company?.inn || '',
    description: user?.company?.description || '',
  }
}

function sameCompany(left, right) {
  return left.name === right.name && left.inn === right.inn && left.description === right.description
}

const draftCompany = computed(() => ({
  name: companyName.value.trim().replace(/\s+/g, ' '),
  inn: companyInn.value.trim(),
  description: companyDescription.value.trim(),
}))
const companyDirty = computed(() => !sameCompany(draftCompany.value, savedCompany.value))
const profileDirty = computed(() => namesDirty.value || companyDirty.value)
const profileSaving = computed(() => nameSaving.value || companySaving.value)

async function onLogout() {
  try {
    await logout()
  } catch {
    session.user = null
  }
  router.push('/')
}

function fillNames(user) {
  const next = namesOf(user)
  const untouched = sameNames(
    { lastName: lastName.value, firstName: firstName.value, middleName: middleName.value },
    savedNames.value,
  )
  savedNames.value = next
  if (nameSaving.value || untouched) {
    lastName.value = next.lastName
    firstName.value = next.firstName
    middleName.value = next.middleName
  }
}

function fillCompany(user) {
  const next = companyOf(user)
  const untouched = sameCompany(
    { name: companyName.value, inn: companyInn.value, description: companyDescription.value },
    savedCompany.value,
  )
  savedCompany.value = next
  if (companySaving.value || untouched) {
    companyName.value = next.name
    companyInn.value = next.inn
    companyDescription.value = next.description
  }
}

function tabLink(name) {
  return { path: '/account', query: { tab: name } }
}

async function saveAll() {
  if (!session.user || profileSaving.value) return
  const names = draftNames.value
  const company = draftCompany.value
  const hasCompany = !seeker.value && !!session.user.company
  lastName.value = names.lastName
  firstName.value = names.firstName
  middleName.value = names.middleName
  nameError.value = ''
  companyError.value = ''

  if (hasCompany) {
    companyName.value = company.name
    companyInn.value = company.inn
    companyDescription.value = company.description
    if (!company.name || !company.inn || !company.description) companyError.value = 'Название, ИНН и описание обязательны'
    else if (!/^\d{10}(\d{2})?$/.test(company.inn)) companyError.value = 'Укажите ИНН из 10 или 12 цифр'
  }
  if (!names.lastName || !names.firstName) {
    nameError.value = 'Фамилия и имя обязательны, отчество можно оставить пустым'
  }
  if (companyError.value || nameError.value) return

  const companyChanged = hasCompany && !sameCompany(company, savedCompany.value)
  const namesChanged = !sameNames(names, savedNames.value)
  if (!companyChanged && !namesChanged) return

  if (companyChanged) {
    companySaving.value = true
    try {
      await updateCompany(company)
      savedCompany.value = company
    } catch (exception) {
      companyError.value = exception.message || 'Не удалось сохранить компанию'
      companySaving.value = false
      return
    }
    companySaving.value = false
  }

  if (!namesChanged) return
  nameSaving.value = true
  try {
    await updateProfile(names)
    savedNames.value = names
  } catch (exception) {
    nameError.value = exception.message || 'Не удалось сохранить имя'
  } finally {
    nameSaving.value = false
  }
}

function extension(name) {
  const index = name.lastIndexOf('.')
  return index === -1 ? '' : name.slice(index + 1).toLowerCase()
}

function imageProblem(file) {
  const ext = extension(file.name)
  const typeOk = !file.type || mimeTypes.includes(file.type)
  if (!extensions.includes(ext) || !typeOk) return 'Можно загрузить только PNG, JPG или JPEG'
  if (file.size > 5 * 1024 * 1024) return 'Изображение не должно быть больше 5 МБ'
  return ''
}

async function uploadImage(file, target) {
  const error = target === 'logo' ? logoError : avatarError
  const pending = target === 'logo' ? logoPending : avatarPending
  error.value = ''
  if (!file) return
  const problem = imageProblem(file)
  if (problem) {
    error.value = problem
    return
  }

  pending.value = true
  try {
    if (target === 'logo') await updateCompanyLogo(file)
    else await updateAvatar(file)
  } catch (exception) {
    error.value = exception.message || 'Не удалось сохранить изображение'
  } finally {
    pending.value = false
  }
}

async function uploadAvatar(file) {
  await uploadImage(file, 'avatar')
}

function onPickAvatar(event) {
  const file = event.target.files?.[0]
  event.target.value = ''
  uploadAvatar(file)
}

function onDropAvatar(event) {
  dragging.value = false
  uploadAvatar(event.dataTransfer?.files?.[0])
}

function onPickLogo(event) {
  const file = event.target.files?.[0]
  event.target.value = ''
  uploadImage(file, 'logo')
}

function onDropLogo(event) {
  logoDragging.value = false
  uploadImage(event.dataTransfer?.files?.[0], 'logo')
}

function onDragLeave(event, flag = dragging) {
  if (event.currentTarget.contains(event.relatedTarget)) return
  flag.value = false
}

async function removeAvatar() {
  avatarError.value = ''
  avatarPending.value = true
  try {
    await updateAvatar(null)
  } catch (exception) {
    avatarError.value = exception.message || 'Не удалось удалить аватар'
  } finally {
    avatarPending.value = false
  }
}

async function removeLogo() {
  logoError.value = ''
  logoPending.value = true
  try {
    await updateCompanyLogo(null)
  } catch (exception) {
    logoError.value = exception.message || 'Не удалось удалить картинку'
  } finally {
    logoPending.value = false
  }
}

watch(() => [session.ready, session.user, route.fullPath], () => {
  if (!session.ready) return
  if (!session.user) {
    router.replace({ path: '/login', query: { redirect: route.fullPath } })
    return
  }
  fillNames(session.user)
  fillCompany(session.user)
  if (onAccount.value) {
    const titles = {
      vacancies: 'Вакансии',
      resumes: 'Резюме',
      favourites: 'Избранное',
      responses: 'Отклики',
    }
    document.title = `${titles[tab.value] || 'Личный кабинет'} — WorkHub`
  }
}, { immediate: true })
</script>

<template>
  <main class="page">
    <p v-if="!session.ready && onAccount" class="state">Загружаем кабинет…</p>

    <template v-else-if="session.user && !bare">
      <h1 class="page-title">Личный кабинет</h1>
      <div class="identity">
        <span class="identity-cell locked">{{ session.user.email }}</span>
        <span class="identity-cell locked">{{ roleLabel }}</span>
        <button class="identity-cell exit" type="button" @click="onLogout">Выйти</button>
      </div>

      <section v-if="seeker" class="avatar-panel account-avatar">
        <img v-if="avatar" class="avatar-photo" :src="avatar" alt="Аватар" />
        <div v-else class="avatar-empty">Нет фото</div>
        <div>
          <label
            class="dropzone"
            :class="{ over: dragging }"
            @dragenter.prevent="dragging = true"
            @dragover.prevent="dragging = true"
            @dragleave="onDragLeave"
            @drop.prevent="onDropAvatar"
          >
            <input
              class="sr-only"
              type="file"
              accept="image/png,image/jpeg,.png,.jpg,.jpeg"
              :disabled="avatarPending"
              @change="onPickAvatar"
            />
            <strong>{{ avatarPending ? 'Сохраняем…' : 'Перетащите фото сюда или нажмите, чтобы выбрать' }}</strong>
            <small>PNG, JPG или JPEG, не больше 5 МБ.</small>
          </label>
          <button v-if="avatar" class="danger avatar-remove" type="button" :disabled="avatarPending" @click="removeAvatar">
            Удалить
          </button>
          <p v-if="avatarError" class="form-error">{{ avatarError }}</p>
        </div>
      </section>

      <section v-else-if="session.user.company" class="avatar-panel account-avatar">
        <h2 class="avatar-heading">Логотип компании</h2>
        <img v-if="logo" class="avatar-photo" :src="logo" alt="Логотип компании" />
        <div v-else class="avatar-empty">Нет картинки</div>
        <div>
          <label
            class="dropzone"
            :class="{ over: logoDragging }"
            @dragenter.prevent="logoDragging = true"
            @dragover.prevent="logoDragging = true"
            @dragleave="onDragLeave($event, logoDragging)"
            @drop.prevent="onDropLogo"
          >
            <input
              class="sr-only"
              type="file"
              accept="image/png,image/jpeg,.png,.jpg,.jpeg"
              :disabled="logoPending"
              @change="onPickLogo"
            />
            <strong>{{ logoPending ? 'Сохраняем…' : 'Перетащите фото сюда или нажмите, чтобы выбрать' }}</strong>
            <small>PNG, JPG или JPEG, не больше 5 МБ.</small>
          </label>
          <button v-if="logo" class="danger avatar-remove" type="button" :disabled="logoPending" @click="removeLogo">
            Удалить
          </button>
          <p v-if="logoError" class="form-error">{{ logoError }}</p>
        </div>
      </section>

      <form class="identity-names" @submit.prevent="saveAll">
        <template v-if="!seeker && session.user.company">
          <label class="identity-cell editable">
            <span>Название<span class="req" aria-hidden="true">*</span></span>
            <input v-model="companyName" type="text" maxlength="255" autocomplete="organization" aria-label="Название компании" required />
          </label>
          <label class="identity-cell editable">
            <span>ИНН<span class="req" aria-hidden="true">*</span></span>
            <input v-model="companyInn" type="text" inputmode="numeric" maxlength="12" autocomplete="off" aria-label="ИНН" required />
          </label>
          <label class="identity-cell editable area">
            <span>Описание<span class="req" aria-hidden="true">*</span></span>
            <textarea v-model="companyDescription" maxlength="10000" aria-label="Описание компании" required />
          </label>
        </template>
        <label class="identity-cell editable">
          <span>Фамилия<span class="req" aria-hidden="true">*</span></span>
          <input v-model="lastName" type="text" autocomplete="family-name" maxlength="50" aria-label="Фамилия" required />
        </label>
        <label class="identity-cell editable">
          <span>Имя<span class="req" aria-hidden="true">*</span></span>
          <input v-model="firstName" type="text" autocomplete="given-name" maxlength="50" aria-label="Имя" required />
        </label>
        <label class="identity-cell editable">
          <span>Отчество</span>
          <input v-model="middleName" type="text" autocomplete="additional-name" maxlength="50" aria-label="Отчество" />
        </label>
        <button class="primary slim" type="submit" :disabled="profileSaving || !profileDirty">
          {{ profileSaving ? 'Сохраняем…' : 'Сохранить' }}
        </button>
        <p v-if="companyError" class="identity-error">{{ companyError }}</p>
        <p v-if="nameError" class="identity-error">{{ nameError }}</p>
      </form>

      <nav v-if="seeker" class="tabs" aria-label="Разделы кабинета">
        <router-link v-for="item in seekerTabs" :key="item.name" v-slot="{ href, navigate }" custom :to="tabLink(item.name)">
          <a :href="href" :class="{ active: tab === item.name }" :aria-current="tab === item.name ? 'page' : undefined" @click="navigate">{{ item.label }}</a>
        </router-link>
      </nav>
      <nav v-else class="tabs" aria-label="Разделы кабинета">
        <router-link v-for="item in employerTabs" :key="item.name" v-slot="{ href, navigate }" custom :to="tabLink(item.name)">
          <a :href="href" :class="{ active: tab === item.name }" :aria-current="tab === item.name ? 'page' : undefined" @click="navigate">{{ item.label }}</a>
        </router-link>
      </nav>
      <component :is="tabView" v-if="onAccount" />
    </template>
    <router-view v-if="!onAccount" />
  </main>
</template>
