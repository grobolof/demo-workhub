<template>
  <footer class="site-footer">
    <div class="footer-nav">
      <section>
        <h2>WorkHub</h2>
        <router-link to="/help">Помощь</router-link>
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
        <router-link to="/">Вакансии</router-link>
      </section>
    </div>
    <div class="footer-bottom">
      <div class="footer-bottom-inner">
        <p>WorkHub — сервис поиска работы. Выбор об использовании данных хранится только в вашем браузере.</p>
      </div>
    </div>
  </footer>
</template>
