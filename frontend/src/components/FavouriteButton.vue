<script setup>
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { session, toggleFavourite, toggleResumeFavourite } from '../session'

const props = defineProps({
  vacancyId: { type: Number, default: 0 },
  resumeId: { type: Number, default: 0 },
})

const emit = defineEmits(['note'])

const route = useRoute()
const router = useRouter()
const busy = ref(false)
const resume = computed(() => props.resumeId > 0)
const favourite = computed(() => (
  resume.value
    ? session.resumeFavouriteIds.includes(props.resumeId)
    : session.favouriteIds.includes(props.vacancyId)
))

async function onHeart() {
  emit('note', '')
  if (!session.user) {
    router.push({ path: '/login', query: { redirect: route.fullPath } })
    return
  }
  if (resume.value && session.user.role !== 'employer') {
    emit('note', 'Добавлять резюме в избранное могут только работодатели')
    return
  }
  if (!resume.value && session.user.role !== 'seeker') {
    emit('note', 'Добавлять в избранное могут только соискатели')
    return
  }

  busy.value = true
  try {
    if (resume.value) await toggleResumeFavourite(props.resumeId)
    else await toggleFavourite(props.vacancyId)
  } catch (exception) {
    emit('note', exception.message || 'Не удалось обновить избранное')
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <button
    class="heart"
    :class="{ on: favourite }"
    type="button"
    :aria-pressed="favourite"
    :aria-label="favourite ? 'Убрать из избранного' : 'В избранное'"
    :disabled="!session.ready || busy"
    @click="onHeart"
  >
    <svg viewBox="0 0 24 24" aria-hidden="true">
      <path
        d="M12 21.35 10.55 20.03C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35Z"
      />
    </svg>
  </button>
</template>
