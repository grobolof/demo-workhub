async function request(url) {
  const response = await fetch(url)
  const payload = await response.json().catch(() => ({}))
  if (!response.ok) {
    throw new Error(payload.error || 'Не удалось загрузить данные')
  }

  return payload
}

export function loadCities() {
  return request('/api/cities/')
}

export function loadBanners() {
  return request('/api/banners/')
}

export function loadVacancies({ q = '', city = '' } = {}) {
  const params = new URLSearchParams()
  if (q) params.set('q', q)
  if (city) params.set('city', city)

  return request(`/api/vacancies/?${params.toString()}`)
}

export function loadVacancy(id) {
  return request(`/api/vacancy/?id=${encodeURIComponent(id)}`)
}

export function loadCompany(id) {
  return request(`/api/company/?id=${encodeURIComponent(id)}`)
}
