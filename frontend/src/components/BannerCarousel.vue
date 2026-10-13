<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { labeledCount } from '../format'

const props = defineProps({
  banners: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  stats: { type: Object, default: null },
})

const index = ref(0)
let timer = 0
let reduceMotion = false

const current = computed(() => props.banners[index.value] || null)
const several = computed(() => props.banners.length > 1)
const statItems = computed(() => {
  if (!props.stats) return []
  return [
    labeledCount(props.stats.resumes, ['резюме', 'резюме', 'резюме']),
    labeledCount(props.stats.vacancies, ['вакансия', 'вакансии', 'вакансий']),
    labeledCount(props.stats.companies, ['компания', 'компании', 'компаний']),
  ]
})

function show(next) {
  if (!props.banners.length) return
  index.value = (next + props.banners.length) % props.banners.length
}

function step(direction) {
  show(index.value + direction)
  start()
}

function start() {
  stop()
  if (reduceMotion || props.banners.length < 2) return
  timer = window.setInterval(() => show(index.value + 1), 5000)
}

function onFocusOut(event) {
  if (!event.currentTarget.contains(event.relatedTarget)) start()
}

function stop() {
  if (timer) window.clearInterval(timer)
  timer = 0
}

watch(() => props.banners, () => {
  index.value = 0
  start()
})

onMounted(() => {
  reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches
  start()
})

onUnmounted(stop)
</script>

<template>
  <section
    v-if="loading || current"
    class="banner"
    aria-roledescription="карусель"
    aria-label="Баннер"
    @mouseenter="stop"
    @mouseleave="start"
    @focusin="stop"
    @focusout="onFocusOut"
  >
    <Transition name="banner-fade">
      <img v-if="current" :key="current.id" :src="current.image" :alt="current.name" />
    </Transition>

    <div v-if="statItems.length" class="banner-stats">
      <p v-for="item in statItems" :key="item.word">
        <strong>{{ item.value }}</strong>
        <span>{{ item.word }}</span>
      </p>
    </div>

    <template v-if="several">
      <button class="banner-nav prev" type="button" aria-label="Предыдущий баннер" @click="step(-1)">
        <svg viewBox="0 0 20 20" aria-hidden="true">
          <path fill="currentColor" d="M12.7 4.3 7 10l5.7 5.7 1.2-1.2L9.4 10l4.5-4.5-1.2-1.2Z" />
        </svg>
      </button>
      <button class="banner-nav next" type="button" aria-label="Следующий баннер" @click="step(1)">
        <svg viewBox="0 0 20 20" aria-hidden="true">
          <path fill="currentColor" d="M7.3 4.3 13 10l-5.7 5.7-1.2-1.2L10.6 10 6.1 5.5l1.2-1.2Z" />
        </svg>
      </button>
      <div class="banner-dots">
        <button
          v-for="(banner, dot) in banners"
          :key="banner.id"
          type="button"
          :class="{ active: dot === index }"
          :aria-label="`Баннер ${dot + 1}: ${banner.name}`"
          :aria-current="dot === index ? 'true' : undefined"
          @click="show(dot); start()"
        />
      </div>
    </template>
  </section>
</template>
