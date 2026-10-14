<script setup>
import { onMounted, onUnmounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute()
const bar = ref(null)
const bottom = ref(null)

function shown(node) {
  if (!node) return false
  const style = getComputedStyle(node)
  if (style.display === 'none' || style.visibility === 'hidden') return false
  const box = node.getBoundingClientRect()
  return box.width > 0 && box.height > 0
}

function hits(left, right) {
  return !(left.right < right.left || left.left > right.right || left.bottom < right.top || left.top > right.bottom)
}

function place() {
  const node = bar.value
  if (!node) return
  const narrow = window.matchMedia('(max-width: 860px)').matches
  let next = narrow ? 72 : 16
  node.style.bottom = `${next}px`

  const fab = node.querySelector('.mr-project-fab')
  if (!fab) {
    bottom.value = next
    return
  }

  const consent = document.querySelector('.consent')
  if (shown(consent)) {
    const needed = Math.round(window.innerHeight - consent.getBoundingClientRect().top + 12)
    if (needed > next) next = needed
  }

  const header = document.querySelector('.header')
  const headerBottom = shown(header) ? header.getBoundingClientRect().bottom : 0
  const fabHeight = fab.getBoundingClientRect().height || 34
  const maxBottom = Math.round(window.innerHeight - headerBottom - fabHeight - 12)
  if (maxBottom >= 16 && next > maxBottom) next = maxBottom

  node.style.bottom = `${next}px`
  const burger = document.querySelector('.burger')
  if (shown(burger) && hits(fab.getBoundingClientRect(), burger.getBoundingClientRect())) {
    const under = Math.round(window.innerHeight - burger.getBoundingClientRect().bottom - 12)
    if (under >= 16) next = Math.min(next, under)
  }

  bottom.value = next
}

let frame = 0

function schedule() {
  if (frame) return
  frame = requestAnimationFrame(() => {
    frame = 0
    place()
  })
}

watch(() => route.fullPath, () => schedule())

onMounted(() => {
  schedule()
  window.addEventListener('resize', schedule)
  document.addEventListener('click', schedule)
})

onUnmounted(() => {
  window.removeEventListener('resize', schedule)
  document.removeEventListener('click', schedule)
  if (frame) cancelAnimationFrame(frame)
})
</script>

<template>
  <div
    v-if="route.path !== '/app-description'"
    ref="bar"
    class="mr-project-fab-bar"
    :style="bottom === null ? undefined : { bottom: `${bottom}px` }"
  >
    <div class="mr-project-fab-wrap">
      <router-link class="mr-project-fab" to="/app-description">О проекте</router-link>
    </div>
  </div>
</template>
