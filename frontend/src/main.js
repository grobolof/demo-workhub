import { createApp } from 'vue'
import { createRouter, createWebHistory } from 'vue-router'
import App from './App.vue'
import HomePage from './pages/HomePage.vue'
import VacancyPage from './pages/VacancyPage.vue'
import CompanyPage from './pages/CompanyPage.vue'
import AuthPage from './pages/AuthPage.vue'
import AccountPage from './pages/AccountPage.vue'
import ResumeFormPage from './pages/ResumeFormPage.vue'
import VacancyFormPage from './pages/VacancyFormPage.vue'
import VacancyInboxPage from './pages/VacancyInboxPage.vue'
import ApplicantResumePage from './pages/ApplicantResumePage.vue'
import CompaniesPage from './pages/CompaniesPage.vue'
import ResumeSearchPage from './pages/ResumeSearchPage.vue'
import HelpPage from './pages/HelpPage.vue'
import DocsPage from './pages/DocsPage.vue'
import TermsPage from './pages/TermsPage.vue'
import NotFoundPage from './pages/NotFoundPage.vue'
import { refreshSession, session } from './session'
import './styles.css'

function asSeeker() {
  if (!session.user || session.user.role !== 'employer') return true
  return '/account'
}

function asEmployer() {
  if (!session.user || session.user.role === 'employer') return true
  return '/account'
}

function accountTab(tab) {
  return { path: '/account', query: { tab } }
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
    { path: '/docs', component: DocsPage },
    { path: '/terms', component: TermsPage },
    { path: '/vacancy/:id', component: VacancyPage },
    { path: '/company/:id', component: CompanyPage },
    { path: '/login', component: AuthPage, props: { mode: 'login' } },
    { path: '/register', component: AuthPage, props: { mode: 'register' } },
    {
      path: '/account',
      component: AccountPage,
      children: [
        { path: 'favourites', redirect: () => accountTab('favourites') },
        { path: 'responses', redirect: () => accountTab('responses') },
        { path: 'resumes/new', component: ResumeFormPage, beforeEnter: asSeeker },
        { path: 'resumes/:id', component: ResumeFormPage, beforeEnter: asSeeker },
        { path: 'resumes', redirect: () => accountTab('resumes') },
        { path: 'vacancies/new', component: VacancyFormPage, beforeEnter: asEmployer },
        { path: 'vacancies/:id/edit', component: VacancyFormPage, beforeEnter: asEmployer },
        { path: 'vacancies/:id', component: VacancyInboxPage, beforeEnter: asEmployer },
        { path: 'vacancies', redirect: () => accountTab('vacancies') },
        { path: 'applicants/:id', component: ApplicantResumePage, beforeEnter: asEmployer },
      ],
    },
    { path: '/:pathMatch(.*)*', component: NotFoundPage },
  ],
})

await refreshSession()
createApp(App).use(router).mount('#app')
