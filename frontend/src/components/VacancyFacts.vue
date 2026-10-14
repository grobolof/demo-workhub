<script setup>
defineProps({
  schedule: { type: String, default: '' },
  workplace: { type: String, default: '' },
  cities: { type: Array, default: () => [] },
})
</script>

<template>
  <ul v-if="schedule || workplace || cities.length" class="facts">
    <li v-if="schedule"><span>График работы</span>{{ schedule }}</li>
    <li v-if="workplace"><span>Место работы</span>{{ workplace }}</li>
    <li v-for="place in cities" :key="place.code || place.name"><span>Город</span>{{ place.name }}</li>
  </ul>
</template>
