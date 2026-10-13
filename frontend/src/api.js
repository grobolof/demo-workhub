async function request(url, { method = 'GET', body, sessid = '' } = {}) {
  const headers = {}
  if (body !== undefined) {
    headers['Content-Type'] = 'application/json'
    headers['X-Bitrix-Csrf-Token'] = sessid
  }

  const response = await fetch(url, {
    method,
    headers,
    body: body !== undefined ? JSON.stringify(body) : undefined,
  })
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

export function loadVacancies({ q = '', city = '', page = 1 } = {}) {
  const params = new URLSearchParams()
  if (q) params.set('q', q)
  if (city) params.set('city', city)
  params.set('page', String(page))

  return request(`/api/vacancies/?${params.toString()}`)
}

export function loadCompanies() {
  return request('/api/companies/')
}

export function searchResumes(q = '') {
  const params = new URLSearchParams()
  if (q) params.set('q', q)

  return request(`/api/resume-search/?${params.toString()}`)
}

export function loadVacancy(id) {
  return request(`/api/vacancy/?id=${encodeURIComponent(id)}`)
}

export function loadCompany(id) {
  return request(`/api/company/?id=${encodeURIComponent(id)}`)
}

export function loadSession() {
  return request('/api/session/')
}

export function postRegister(body, sessid) {
  return request('/api/register/', { method: 'POST', body, sessid })
}

export function postLogin(body, sessid) {
  return request('/api/login/', { method: 'POST', body, sessid })
}

export function postLogout(sessid) {
  return request('/api/logout/', { method: 'POST', body: {}, sessid })
}

export function loadFavourites() {
  return request('/api/favourites/')
}

export function postFavourite(body, sessid) {
  return request('/api/favourites/', { method: 'POST', body, sessid })
}

export function loadResponses() {
  return request('/api/responses/')
}

export function postResponse(body, sessid) {
  return request('/api/responses/', { method: 'POST', body, sessid })
}

export function loadResumes() {
  return request('/api/resumes/')
}

export function loadResume(id) {
  return request(`/api/resumes/?id=${encodeURIComponent(id)}`)
}

export function saveResume(body, sessid) {
  return request('/api/resumes/', { method: 'POST', body, sessid })
}
