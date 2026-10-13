<script setup>
import { onMounted, provide, ref, watch } from 'vue'
import SiteHeader from './components/SiteHeader.vue'
import { loadCities } from './api'

const CITY_KEY = 'workhub-city'

const cities = ref([])
const cityCode = ref(localStorage.getItem(CITY_KEY) || '')

function onCity(code) {
  cityCode.value = code
}

watch(cityCode, (code) => {
  localStorage.setItem(CITY_KEY, code)
})

provide('cityCode', cityCode)

onMounted(async () => {
  try {
    const payload = await loadCities()
    cities.value = payload.cities || []
    if (cityCode.value && !cities.value.some((item) => item.code === cityCode.value)) {
      cityCode.value = ''
    }
  } catch {
    cities.value = []
  }
})
</script>

<template>
  <SiteHeader :cities="cities" :city="cityCode" @update:city="onCity" />
  <router-view />
</template>
