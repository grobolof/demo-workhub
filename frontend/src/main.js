import { createApp } from 'vue'
import { createRouter, createWebHistory } from 'vue-router'
import App from './App.vue'
import HomePage from './pages/HomePage.vue'
import VacancyPage from './pages/VacancyPage.vue'
import CompanyPage from './pages/CompanyPage.vue'
import AuthPage from './pages/AuthPage.vue'
import AccountPage from './pages/AccountPage.vue'
import FavouritesPage from './pages/FavouritesPage.vue'
import ResumesPage from './pages/ResumesPage.vue'
import ResumeFormPage from './pages/ResumeFormPage.vue'
import ResponsesPage from './pages/ResponsesPage.vue'
import CompaniesPage from './pages/CompaniesPage.vue'
import ResumeSearchPage from './pages/ResumeSearchPage.vue'
import HelpPage from './pages/HelpPage.vue'
import TermsPage from './pages/TermsPage.vue'
import { refreshSession } from './session'
import './styles.css'

const router = createRouter({
  history: createWebHistory(),
  scrollBehavior() {
    return { top: 0 }
  },
  routes: [
    { path: '/', component: HomePage },
    { path: '/companies', component: CompaniesPage },
    { path: '/resume-search', component: ResumeSearchPage },
    { path: '/help', component: HelpPage },
    { path: '/terms', component: TermsPage },
    { path: '/vacancy/:id', component: VacancyPage },
    { path: '/company/:id', component: CompanyPage },
    { path: '/login', component: AuthPage, props: { mode: 'login' } },
    { path: '/register', component: AuthPage, props: { mode: 'register' } },
    {
      path: '/account',
      component: AccountPage,
      children: [
        { path: '', redirect: '/account/favourites' },
        { path: 'favourites', component: FavouritesPage },
        { path: 'resumes', component: ResumesPage },
        { path: 'resumes/new', component: ResumeFormPage },
        { path: 'resumes/:id', component: ResumeFormPage },
        { path: 'responses', component: ResponsesPage },
      ],
    },
  ],
})

await refreshSession()
createApp(App).use(router).mount('#app')
