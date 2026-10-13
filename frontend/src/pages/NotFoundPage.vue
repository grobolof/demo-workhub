<script setup>
import { onMounted } from 'vue'

onMounted(() => {
  document.title = 'Страница не найдена — WorkHub'
})
</script>

<template>
  <main class="page">
    <article class="sheet not-found">
      <p class="not-found-code">404</p>
      <h1>Страница не найдена</h1>
      <p class="lead">Такой страницы на WorkHub нет. Проверьте адрес или вернитесь к поиску вакансий.</p>
      <router-link class="primary" to="/">На главную</router-link>
    </article>
  </main>
</template>
