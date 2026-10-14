<script setup>
import { watch } from 'vue'
import VacancyCard from '../components/VacancyCard.vue'
import { loadFavourites } from '../api'
import { useInfiniteList } from '../infiniteList'
import { session } from '../session'

const { items, total, initialLoading, loadingMore, error, moreError, drop } = useInfiniteList(
  loadFavourites,
  {
    failure: 'Не удалось загрузить избранное',
    keep: (item) => session.favouriteIds.includes(item.id),
  },
)

watch(() => session.favouriteIds.slice(), (ids, previous) => {
  if (!previous) return
  const next = new Set(ids)
  drop(previous.filter((id) => !next.has(id)))
})

document.title = 'Избранное — WorkHub'
</script>

<template>
  <div v-if="!initialLoading && !error" class="toolbar">
    <p>{{ items.length }} из {{ total }}</p>
  </div>

  <p v-if="error" class="state error">{{ error }}</p>

  <div v-else-if="initialLoading" class="preloader preloader-block" role="status">
    <span class="spinner" aria-hidden="true" />
    <span>Загружаем избранное</span>
  </div>

  <p v-else-if="items.length === 0" class="state">В избранном пока пусто. Нажмите на сердечко у вакансии.</p>

  <div v-else class="list">
    <VacancyCard v-for="vacancy in items" :key="vacancy.id" :vacancy="vacancy" />
  </div>

  <div v-if="loadingMore" class="preloader" role="status">
    <span class="spinner" aria-hidden="true" />
    <span>Загружаем избранное</span>
  </div>
  <p v-if="moreError" class="state error">{{ moreError }}</p>
</template>
