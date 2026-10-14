<script setup>
import { onMounted, provide, ref, watch } from 'vue'
import SiteHeader from './components/SiteHeader.vue'
import SiteFooter from './components/SiteFooter.vue'
import CookieNotice from './components/CookieNotice.vue'
import ProjectFab from './components/ProjectFab.vue'
import { loadCities } from './api'
import { refreshSession } from './session'

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
  refreshSession()
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
  <SiteFooter />
  <CookieNotice />
  <ProjectFab />
</template>
