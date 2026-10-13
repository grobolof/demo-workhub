<script setup>
import { ref } from 'vue'

const KEY = 'workhub_consent'

function readConsent() {
  const match = document.cookie.match(/(?:^|; )workhub_consent=(yes|no)(?:;|$)/)
  return match ? match[1] : ''
}

const choice = ref(readConsent())

function choose(value) {
  document.cookie = `${KEY}=${value}; Path=/; Max-Age=31536000; SameSite=Lax`
  choice.value = value
}
</script>

<template>
  <aside v-if="!choice" class="consent" role="dialog" aria-labelledby="consent-title">
    <h2 id="consent-title">Данные пользователей</h2>
    <p>Сайт собирает данные пользователей. Выбор сохраняется в браузере и на сервер не записывается.</p>
    <div class="consent-actions">
      <button class="primary slim" type="button" @click="choose('yes')">Согласен</button>
      <button class="ghost" type="button" @click="choose('no')">Не согласен</button>
    </div>
  </aside>
</template>
