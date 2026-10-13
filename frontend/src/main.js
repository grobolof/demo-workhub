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
import VacanciesPage from './pages/VacanciesPage.vue'
import VacancyFormPage from './pages/VacancyFormPage.vue'
import CompaniesPage from './pages/CompaniesPage.vue'
import ResumeSearchPage from './pages/ResumeSearchPage.vue'
import HelpPage from './pages/HelpPage.vue'
import TermsPage from './pages/TermsPage.vue'
import NotFoundPage from './pages/NotFoundPage.vue'
import { refreshSession, session } from './session'
import './styles.css'

function asSeeker() {
  if (!session.user || session.user.role !== 'employer') return true
  return '/account/vacancies'
}

function asEmployer() {
  if (!session.user || session.user.role === 'employer') return true
  return '/account/favourites'
}

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
        { path: '', redirect: () => (session.user?.role === 'employer' ? '/account/vacancies' : '/account/favourites') },
        { path: 'favourites', component: FavouritesPage, beforeEnter: asSeeker },
        { path: 'resumes', component: ResumesPage },
        { path: 'resumes/new', component: ResumeFormPage, beforeEnter: asSeeker },
        { path: 'resumes/:id', component: ResumeFormPage, beforeEnter: asSeeker },
        { path: 'responses', component: ResponsesPage, beforeEnter: asSeeker },
        { path: 'vacancies', component: VacanciesPage, beforeEnter: asEmployer },
        { path: 'vacancies/new', component: VacancyFormPage, beforeEnter: asEmployer },
        { path: 'vacancies/:id', component: VacancyFormPage, beforeEnter: asEmployer },
      ],
    },
    { path: '/:pathMatch(.*)*', component: NotFoundPage },
  ],
})

await refreshSession()
createApp(App).use(router).mount('#app')
