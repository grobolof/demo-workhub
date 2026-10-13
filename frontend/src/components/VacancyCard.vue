<script setup>
import { computed } from 'vue'
import { formatSalary } from '../format'

const props = defineProps({
  vacancy: { type: Object, required: true },
})

const salary = computed(() => formatSalary(props.vacancy.salaryFrom, props.vacancy.salaryTo))
const logoText = computed(() => (props.vacancy.company?.name || 'W').slice(0, 1).toUpperCase())
</script>

<template>
  <article class="vacancy">
    <div class="vacancy-logo">
      <img v-if="vacancy.company?.logo" :src="vacancy.company.logo" :alt="vacancy.company.name" />
      <span v-else>{{ logoText }}</span>
    </div>
    <div class="vacancy-body">
      <div class="vacancy-top">
        <h2>{{ vacancy.name }}</h2>
        <p class="salary">{{ salary }}</p>
      </div>
      <p v-if="vacancy.company" class="company">{{ vacancy.company.name }}</p>
      <p v-if="vacancy.preview" class="preview">{{ vacancy.preview }}</p>
      <ul v-if="vacancy.cities?.length" class="places">
        <li v-for="place in vacancy.cities" :key="place.code">{{ place.name }}</li>
      </ul>
    </div>
  </article>
</template>
