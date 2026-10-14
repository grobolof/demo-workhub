<script setup>
import { onMounted } from 'vue'

onMounted(() => {
  document.title = 'Документация API — WorkHub'
  const style = document.createElement('link')
  style.rel = 'stylesheet'
  style.href = '/vendor/swagger-ui/swagger-ui.css'
  document.head.appendChild(style)

  const script = document.createElement('script')
  script.src = '/vendor/swagger-ui/swagger-ui-bundle.js'
  script.onload = () => {
    if (typeof window.SwaggerUIBundle !== 'function') {
      showError()
      return
    }
    window.SwaggerUIBundle({
      url: '/api/openapi.json',
      dom_id: '#swagger-ui',
      deepLinking: true,
      docExpansion: 'list',
      defaultModelsExpandDepth: 1,
    })
  }
  script.onerror = showError
  document.body.appendChild(script)
})

function showError() {
  const root = document.querySelector('#swagger-ui')
  if (root) root.textContent = 'Не удалось открыть документацию API'
}
</script>

<template>
  <main class="page docs-page">
    <div id="swagger-ui" />
  </main>
</template>
