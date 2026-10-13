<script setup>
import { computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { logout, session } from '../session'

const route = useRoute()
const router = useRouter()
const seeker = computed(() => session.user?.role === 'seeker')
const resumesActive = computed(() => route.path.startsWith('/account/resumes'))
const vacanciesActive = computed(() => route.path.startsWith('/account/vacancies'))
const roleLabel = computed(() => (seeker.value ? 'соискатель' : 'работодатель'))
const fullName = computed(() => {
  const user = session.user
  if (!user) return ''
  return [user.lastName, user.firstName, user.middleName].filter(Boolean).join(' ')
})

async function onLogout() {
  try {
    await logout()
  } catch {
    session.user = null
  }
  router.push('/')
}

watch(() => [session.ready, session.user, route.fullPath], () => {
  if (!session.ready) return
  document.title = 'Личный кабинет — WorkHub'
  if (!session.user) {
    router.replace({ path: '/login', query: { redirect: route.fullPath } })
  }
}, { immediate: true })
</script>

<template>
  <main class="page">
    <p v-if="!session.ready" class="state">Загружаем кабинет…</p>

    <template v-else-if="session.user">
      <h1 class="page-title">Личный кабинет</h1>
      <div class="identity">
        <span v-if="fullName" class="identity-cell">{{ fullName }}</span>
        <span class="identity-cell">{{ session.user.email }}</span>
        <span class="identity-cell">{{ roleLabel }}</span>
        <span v-if="session.user.company" class="identity-cell">{{ session.user.company.name }}</span>
        <span v-if="session.user.company?.inn" class="identity-cell">ИНН {{ session.user.company.inn }}</span>
        <button class="identity-cell exit" type="button" @click="onLogout">Выйти</button>
      </div>

      <nav v-if="seeker" class="tabs" aria-label="Разделы кабинета">
        <router-link to="/account/favourites">Избранное</router-link>
        <router-link to="/account/resumes" :class="{ active: resumesActive }">Резюме</router-link>
        <router-link to="/account/responses">Отклики</router-link>
      </nav>
      <nav v-else class="tabs" aria-label="Разделы кабинета">
        <router-link to="/account/vacancies" :class="{ active: vacanciesActive }">Вакансии</router-link>
        <router-link to="/account/resumes" :class="{ active: resumesActive }">Резюме</router-link>
      </nav>
      <router-view />
    </template>
  </main>
</template>
