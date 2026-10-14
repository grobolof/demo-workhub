<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { session } from '../session'

const props = defineProps({
  cities: { type: Array, default: () => [] },
  city: { type: String, default: '' },
})

const emit = defineEmits(['update:city'])

const route = useRoute()
const root = ref(null)
const headerRef = ref(null)
const innerRef = ref(null)
const toolsRef = ref(null)
const open = ref(false)
const menuOpen = ref(false)
const compact = ref(typeof window !== 'undefined' && window.matchMedia('(max-width: 430px)').matches)

const current = computed(() => props.cities.find((item) => item.code === props.city) || null)
const label = computed(() => current.value?.name || 'Все города')

let frame = 0
let observer

function toggle() {
  open.value = !open.value
}

function choose(code) {
  open.value = false
  if (code !== props.city) emit('update:city', code)
}

function toggleMenu() {
  menuOpen.value = !menuOpen.value
  if (!menuOpen.value) open.value = false
}

function onDocumentClick(event) {
  if (!root.value?.contains(event.target)) open.value = false
  if (!headerRef.value?.contains(event.target)) {
    menuOpen.value = false
    open.value = false
  }
}

function onKeydown(event) {
  if (event.key === 'Escape') {
    open.value = false
    menuOpen.value = false
  }
}

function toolsWidth() {
  const tools = toolsRef.value
  if (!tools) return 0
  const clone = tools.cloneNode(true)
  clone.removeAttribute('id')
  clone.style.cssText = 'position:fixed;left:0;top:0;transform:translateX(-100%);display:flex;flex-flow:row nowrap;align-items:center;width:max-content;min-width:max-content;max-width:none;gap:10px;visibility:hidden;pointer-events:none;'
  document.body.appendChild(clone)
  const width = clone.getBoundingClientRect().width
  clone.remove()
  return width
}

function updateLayout() {
  const inner = innerRef.value
  if (!inner) return
  const brand = inner.querySelector('.brand')
  const nav = [...inner.children].find((element) => element.tagName === 'NAV')
  const gap = parseFloat(getComputedStyle(inner).columnGap) || 0
  const parts = [
    brand?.getBoundingClientRect().width || 0,
    nav?.getBoundingClientRect().width || 0,
    toolsWidth(),
  ].filter((value) => value > 0)
  const needed = parts.reduce((sum, value) => sum + value, 0) + gap * Math.max(0, parts.length - 1)
  if (inner.clientWidth < 48) return
  const overflow = needed - inner.clientWidth
  const next = compact.value ? overflow > -8 : overflow > 1
  if (next === compact.value) return
  compact.value = next
  if (!next) {
    menuOpen.value = false
    open.value = false
  }
}

function scheduleLayout() {
  if (frame) return
  frame = requestAnimationFrame(() => {
    frame = 0
    updateLayout()
  })
}

watch([label, () => session.ready, () => session.user], () => nextTick(scheduleLayout))

watch(() => route.fullPath, () => {
  menuOpen.value = false
  open.value = false
})

onMounted(() => {
  document.addEventListener('click', onDocumentClick)
  document.addEventListener('keydown', onKeydown)
  observer = new ResizeObserver(scheduleLayout)
  if (innerRef.value) observer.observe(innerRef.value)
  document.fonts?.ready?.then(scheduleLayout)
  scheduleLayout()
})

onUnmounted(() => {
  document.removeEventListener('click', onDocumentClick)
  document.removeEventListener('keydown', onKeydown)
  observer?.disconnect()
  if (frame) cancelAnimationFrame(frame)
})
</script>

<template>
  <header ref="headerRef" class="header" :class="{ 'is-compact': compact, 'is-open': menuOpen }">
    <div ref="innerRef" class="header-inner">
      <router-link class="brand" to="/" aria-label="WorkHub, на главную">
        <img src="/logo.svg" alt="" width="36" height="36" />
        <span>WorkHub</span>
      </router-link>

      <button
        v-show="compact"
        class="burger"
        type="button"
        :aria-expanded="menuOpen"
        aria-controls="header-tools"
        @click="toggleMenu"
      >
        <span class="sr-only">{{ menuOpen ? 'Закрыть меню' : 'Меню' }}</span>
        <svg v-if="!menuOpen" viewBox="0 0 24 24" aria-hidden="true">
          <path fill="currentColor" d="M4 7h16v2H4V7Zm0 4h16v2H4v-2Zm0 4h16v2H4v-2Z" />
        </svg>
        <svg v-else viewBox="0 0 24 24" aria-hidden="true">
          <path fill="currentColor" d="M6.4 5 12 10.6 17.6 5 19 6.4 13.4 12 19 17.6 17.6 19 12 13.4 6.4 19 5 17.6 10.6 12 5 6.4 6.4 5Z" />
        </svg>
      </button>

      <div id="header-tools" ref="toolsRef" class="header-tools">
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

        <span
          v-if="session.ready && session.user"
          class="role-chip"
          :class="session.user.role"
        >
          {{ session.user.role === 'employer' ? 'Работодатель' : 'Соискатель' }}
        </span>
        <router-link v-if="session.ready && session.user" class="menu-button solid" to="/account">Кабинет</router-link>
        <router-link v-else-if="session.ready" class="menu-login" to="/login">Войти</router-link>
      </div>
    </div>
  </header>
</template>
