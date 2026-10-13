<script setup>
import { computed, ref } from 'vue'
import FavouriteButton from './FavouriteButton.vue'
import VacancyActions from './VacancyActions.vue'
import { formatSalary } from '../format'

const props = defineProps({
  vacancy: { type: Object, required: true },
  showCompany: { type: Boolean, default: true },
})

const note = ref('')
const salary = computed(() => formatSalary(props.vacancy.salaryFrom, props.vacancy.salaryTo))
const logoText = computed(() => (props.vacancy.company?.name || 'W').slice(0, 1).toUpperCase())
const extra = computed(() => (
  (props.showCompany && props.vacancy.company)
  || props.vacancy.preview
  || props.vacancy.cities?.length
  || props.vacancy.workplace
  || props.vacancy.schedule
))
</script>

<template>
  <article class="vacancy">
    <div class="vacancy-main">
      <router-link class="vacancy-logo" :to="`/vacancy/${vacancy.id}`" :aria-label="vacancy.name">
        <img v-if="vacancy.company?.logo" :src="vacancy.company.logo" :alt="vacancy.company.name" />
        <span v-else>{{ logoText }}</span>
      </router-link>
      <div class="vacancy-body">
        <div class="vacancy-top">
          <router-link class="title-link" :to="`/vacancy/${vacancy.id}`">
            <h2>{{ vacancy.name }}</h2>
          </router-link>
          <div class="salary-row">
            <router-link class="salary-link" :to="`/vacancy/${vacancy.id}`">
              <p class="salary">{{ salary }}</p>
            </router-link>
            <FavouriteButton :vacancy-id="vacancy.id" @note="note = $event" />
          </div>
        </div>
        <router-link v-if="extra" class="vacancy-extra" :to="`/vacancy/${vacancy.id}`">
          <p v-if="showCompany && vacancy.company" class="company">{{ vacancy.company.name }}</p>
          <p v-if="vacancy.preview" class="preview">{{ vacancy.preview }}</p>
          <ul v-if="vacancy.workplace || vacancy.schedule || vacancy.cities?.length" class="places">
            <li v-if="vacancy.workplace">{{ vacancy.workplace }}</li>
            <li v-if="vacancy.schedule">{{ vacancy.schedule }}</li>
            <li v-for="place in vacancy.cities" :key="place.code">{{ place.name }}</li>
          </ul>
        </router-link>
      </div>
    </div>
    <p v-if="note" class="action-note">{{ note }}</p>
    <VacancyActions :vacancy-id="vacancy.id" hide-heart />
  </article>
</template>
