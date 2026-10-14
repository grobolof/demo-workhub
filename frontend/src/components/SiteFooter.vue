<script setup>
const year = new Date().getFullYear()
</script>

<template>
  <footer class="site-footer">
    <div class="footer-nav">
      <section>
        <h2>WorkHub</h2>
        <router-link to="/help">Помощь</router-link>
        <router-link to="/docs">Документация API</router-link>
        <router-link to="/terms">Условия использования</router-link>
      </section>
      <section>
        <h2>Соискателям</h2>
        <router-link to="/">Вакансии</router-link>
        <router-link to="/companies">Компании</router-link>
      </section>
      <section>
        <h2>Работодателям</h2>
        <router-link to="/resume-search">Поиск резюме</router-link>
      </section>
    </div>
    <div class="footer-bottom">
      <div class="footer-bottom-inner">
        <p>WorkHub — сервис поиска работы. Выбор об использовании данных хранится только в вашем браузере.</p>
        <p class="footer-credit">
          © {{ year }} WorkHub —
          <a href="https://github.com/grobolof" target="_blank" rel="noopener noreferrer">@grobolof</a>
          (проект является демонстрацией с открытым
          <a href="https://github.com/grobolof/demo-workhub" target="_blank" rel="noopener noreferrer">исходным кодом
            <svg viewBox="0 0 16 16" aria-hidden="true"><path d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82A7.65 7.65 0 0 1 8 4.77c.68.003 1.36.092 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.013 8.013 0 0 0 16 8c0-4.42-3.58-8-8-8z" fill="currentColor"/></svg></a>)
        </p>
      </div>
    </div>
  </footer>
</template>
