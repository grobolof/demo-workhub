<script setup>
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { sendResponse, session, toggleFavourite } from '../session'

const props = defineProps({
  vacancyId: { type: Number, required: true },
  layout: { type: String, default: 'card' },
})

const route = useRoute()
const router = useRouter()
const note = ref('')
const busy = ref(false)

const favourite = computed(() => session.favouriteIds.includes(props.vacancyId))
const responded = computed(() => session.responseIds.includes(props.vacancyId))

function ensureSeeker(message) {
  note.value = ''
  if (!session.user) {
    router.push({ path: '/login', query: { redirect: route.fullPath } })
    return false
  }
  if (session.user.role !== 'seeker') {
    note.value = message
    return false
  }

  return true
}

async function onHeart() {
  if (!ensureSeeker('Добавлять в избранное могут только соискатели')) return
  busy.value = true
  try {
    await toggleFavourite(props.vacancyId)
  } catch (exception) {
    note.value = exception.message || 'Не удалось обновить избранное'
  } finally {
    busy.value = false
  }
}

async function onRespond() {
  if (responded.value) return
  if (!ensureSeeker('Откликнуться могут только соискатели')) return
  busy.value = true
  try {
    await sendResponse(props.vacancyId)
  } catch (exception) {
    note.value = exception.message || 'Не удалось отправить отклик'
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <div class="vacancy-actions" :class="layout">
    <button
      class="heart"
      :class="{ on: favourite, pin: layout === 'card' }"
      type="button"
      :aria-pressed="favourite"
      :aria-label="favourite ? 'Убрать из избранного' : 'В избранное'"
      :disabled="!session.ready || busy"
      @click="onHeart"
    >
      <svg viewBox="0 0 24 24" aria-hidden="true">
        <path
          d="M12 20.5s-6.6-4.2-9.2-8C1 9.8 1.9 6.2 5 5.2c2-.7 3.8.1 5 1.8 1.2-1.7 3-2.5 5-1.8 3.1 1 4 4.6 2.2 7.3-2.6 3.8-5.2 8-5.2 8Z"
        />
      </svg>
    </button>
    <button class="apply" :class="{ done: responded }" type="button" :disabled="!session.ready || busy || responded" @click="onRespond">
      {{ responded ? 'Вы откликнулись' : 'Откликнуться' }}
    </button>
    <p v-if="note" class="action-note">{{ note }}</p>
  </div>
</template>
