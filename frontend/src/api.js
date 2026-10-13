export async function loadVacancies({ q = '', city = '' } = {}) {
  const params = new URLSearchParams()
  if (q) params.set('q', q)
  if (city) params.set('city', city)

  const response = await fetch(`/api/vacancies/?${params.toString()}`)
  const payload = await response.json().catch(() => ({}))
  if (!response.ok) {
    throw new Error(payload.error || 'Не удалось загрузить вакансии')
  }

  return payload
}
