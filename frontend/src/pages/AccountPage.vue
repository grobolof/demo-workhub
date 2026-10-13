<script setup>
import { computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { session } from '../session'

const route = useRoute()
const router = useRouter()
const seeker = computed(() => session.user?.role === 'seeker')
const resumesActive = computed(() => route.path.startsWith('/account/resumes'))

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
      <p class="who-line">{{ session.user.email }} · {{ seeker ? 'соискатель' : 'работодатель' }}</p>

      <template v-if="seeker">
        <nav class="tabs" aria-label="Разделы кабинета">
          <router-link to="/account/favourites">Избранное</router-link>
          <router-link to="/account/resumes" :class="{ active: resumesActive }">Резюме</router-link>
          <router-link to="/account/responses">Отклики</router-link>
        </nav>
        <router-view />
      </template>

      <article v-else class="sheet">
        <p class="lead">Вы вошли как работодатель. Избранное, резюме и отклики доступны соискателям.</p>
      </article>
    </template>
  </main>
</template>
