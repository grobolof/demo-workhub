<script setup>
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import FavouriteButton from './FavouriteButton.vue'
import { sendResponse, session } from '../session'

const props = defineProps({
  vacancyId: { type: Number, required: true },
  layout: { type: String, default: 'card' },
  hideHeart: { type: Boolean, default: false },
})

const route = useRoute()
const router = useRouter()
const note = ref('')
const busy = ref(false)

const responded = computed(() => session.responseIds.includes(props.vacancyId))

async function onRespond() {
  note.value = ''
  if (responded.value) return
  if (!session.user) {
    router.push({ path: '/login', query: { redirect: route.fullPath } })
    return
  }
  if (session.user.role !== 'seeker') {
    note.value = 'Откликнуться могут только соискатели'
    return
  }
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
    <FavouriteButton v-if="!hideHeart" :vacancy-id="vacancyId" @note="note = $event" />
    <button class="apply" :class="{ done: responded }" type="button" :disabled="!session.ready || busy || responded" @click="onRespond">
      {{ responded ? 'Вы откликнулись' : 'Откликнуться' }}
    </button>
    <p v-if="note" class="action-note">{{ note }}</p>
  </div>
</template>
