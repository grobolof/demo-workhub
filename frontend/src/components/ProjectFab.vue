<script setup>
import { onMounted, onUnmounted, ref } from 'vue'

const raised = ref(false)

function consentOpen() {
  return !/(?:^|; )workhub_consent=(yes|no)(?:;|$)/.test(document.cookie)
}

function sync() {
  raised.value = consentOpen()
}

onMounted(() => {
  sync()
  document.addEventListener('click', sync)
})

onUnmounted(() => {
  document.removeEventListener('click', sync)
})
</script>

<template>
  <div class="project-fab-bar" :class="{ 'is-raised': raised }">
    <div class="project-fab-wrap">
      <router-link class="project-fab" to="/app-description">О проекте</router-link>
    </div>
  </div>
</template>
