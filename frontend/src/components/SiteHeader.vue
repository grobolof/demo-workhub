<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'

const props = defineProps({
  cities: { type: Array, default: () => [] },
  city: { type: String, default: '' },
})

const emit = defineEmits(['update:city'])

const root = ref(null)
const open = ref(false)

const current = computed(() => props.cities.find((item) => item.code === props.city) || null)
const label = computed(() => current.value?.name || 'Все города')

function toggle() {
  open.value = !open.value
}

function choose(code) {
  open.value = false
  if (code !== props.city) emit('update:city', code)
}

function onDocumentClick(event) {
  if (!root.value?.contains(event.target)) open.value = false
}

function onKeydown(event) {
  if (event.key === 'Escape') open.value = false
}

onMounted(() => {
  document.addEventListener('click', onDocumentClick)
  document.addEventListener('keydown', onKeydown)
})

onUnmounted(() => {
  document.removeEventListener('click', onDocumentClick)
  document.removeEventListener('keydown', onKeydown)
})
</script>

<template>
  <header class="header">
    <div class="header-inner">
      <a class="brand" href="/" aria-label="WorkHub, на главную">
        <img src="/logo.svg" alt="" width="36" height="36" />
        <span>WorkHub</span>
      </a>

      <div ref="root" class="city">
        <button
          class="city-button"
          type="button"
          aria-haspopup="listbox"
          :aria-expanded="open"
          @click="toggle"
        >
          <svg viewBox="0 0 24 24" aria-hidden="true">
            <path
              fill="currentColor"
              d="M12 2.5a7 7 0 0 0-7 7c0 5.25 7 12 7 12s7-6.75 7-12a7 7 0 0 0-7-7Zm0 9.5a2.5 2.5 0 1 1 0-5 2.5 2.5 0 0 1 0 5Z"
            />
          </svg>
          <span>{{ label }}</span>
          <svg class="chevron" :class="{ open }" viewBox="0 0 20 20" aria-hidden="true">
            <path fill="currentColor" d="M5.5 7.5 10 12l4.5-4.5 1.2 1.2L10 14.4 4.3 8.7l1.2-1.2Z" />
          </svg>
        </button>

        <div v-if="open" class="city-menu" role="listbox" aria-label="Город">
          <button
            type="button"
            role="option"
            :aria-selected="!city"
            :class="{ active: !city }"
            @click="choose('')"
          >
            Все города
          </button>
          <button
            v-for="item in cities"
            :key="item.code"
            type="button"
            role="option"
            :aria-selected="item.code === city"
            :class="{ active: item.code === city }"
            @click="choose(item.code)"
          >
            {{ item.name }}
          </button>
        </div>
      </div>
    </div>
  </header>
</template>
