<script setup>
import VacancyCard from '../components/VacancyCard.vue'
import { loadResponses } from '../api'
import { useInfiniteList } from '../infiniteList'

const { items, total, initialLoading, loadingMore, error, moreError } = useInfiniteList(loadResponses, {
  failure: 'Не удалось загрузить отклики',
})

document.title = 'Отклики — WorkHub'
</script>

<template>
  <div v-if="!initialLoading && !error" class="toolbar">
    <p>{{ items.length }} из {{ total }}</p>
  </div>

  <p v-if="error" class="state error">{{ error }}</p>

  <div v-else-if="initialLoading" class="preloader preloader-block" role="status">
    <span class="spinner" aria-hidden="true" />
    <span>Загружаем отклики</span>
  </div>

  <p v-else-if="items.length === 0" class="state">Вы ещё не откликались на вакансии.</p>

  <div v-else class="list">
    <VacancyCard v-for="vacancy in items" :key="vacancy.id" :vacancy="vacancy" />
  </div>

  <div v-if="loadingMore" class="preloader" role="status">
    <span class="spinner" aria-hidden="true" />
    <span>Загружаем отклики</span>
  </div>
  <p v-if="moreError" class="state error">{{ moreError }}</p>
</template>
