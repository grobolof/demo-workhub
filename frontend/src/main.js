import { createApp } from 'vue'
import { createRouter, createWebHistory } from 'vue-router'
import App from './App.vue'
import HomePage from './pages/HomePage.vue'
import VacancyPage from './pages/VacancyPage.vue'
import CompanyPage from './pages/CompanyPage.vue'
import './styles.css'

const router = createRouter({
  history: createWebHistory(),
  scrollBehavior() {
    return { top: 0 }
  },
  routes: [
    { path: '/', component: HomePage },
    { path: '/vacancy/:id', component: VacancyPage },
    { path: '/company/:id', component: CompanyPage },
  ],
})

createApp(App).use(router).mount('#app')
