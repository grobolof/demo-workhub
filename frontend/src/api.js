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

export function loadVacancies({ q = '', city = '', page = 1, sort = '' } = {}) {
  const params = new URLSearchParams()
  if (q) params.set('q', q)
  if (city) params.set('city', city)
  if (sort) params.set('sort', sort)
  params.set('page', String(page))

  return request(`/api/vacancies/?${params.toString()}`)
}

export function loadVacancySuggestions({ q = '', city = '' } = {}) {
  const params = new URLSearchParams()
  if (q) params.set('q', q)
  if (city) params.set('city', city)

  return request(`/api/suggest/?${params.toString()}`)
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

export function postProfile(body, sessid) {
  return request('/api/profile/', { method: 'POST', body, sessid })
}

export async function postAvatar(file, sessid) {
  const body = new FormData()
  if (file) body.append('avatar', file)
  else body.append('remove', '1')

  const response = await fetch('/api/profile/', {
    method: 'POST',
    headers: { 'X-Bitrix-Csrf-Token': sessid },
    body,
  })
  const payload = await response.json().catch(() => ({}))
  if (!response.ok) {
    throw new Error(payload.error || 'Не удалось сохранить аватар')
  }

  return payload
}

export async function postCompanyLogo(file, sessid) {
  const body = new FormData()
  if (file) body.append('logo', file)
  else {
    body.append('remove', '1')
    body.append('target', 'logo')
  }

  const response = await fetch('/api/profile/', {
    method: 'POST',
    headers: { 'X-Bitrix-Csrf-Token': sessid },
    body,
  })
  const payload = await response.json().catch(() => ({}))
  if (!response.ok) {
    throw new Error(payload.error || 'Не удалось сохранить картинку компании')
  }

  return payload
}

export function loadMyVacancies() {
  return request('/api/my-vacancies/')
}

export function loadMyVacancy(id) {
  return request(`/api/my-vacancies/?id=${encodeURIComponent(id)}`)
}

export function loadApplicant(vacancyId, userId) {
  const params = new URLSearchParams()
  params.set('vacancy', String(vacancyId))
  params.set('applicant', String(userId))
  return request(`/api/my-vacancies/?${params.toString()}`)
}

export function saveVacancy(body, sessid) {
  return request('/api/my-vacancies/', { method: 'POST', body, sessid })
}

export function loadFavourites(page = 1) {
  return request(`/api/favourites/?page=${encodeURIComponent(page)}`)
}

export function postFavourite(body, sessid) {
  return request('/api/favourites/', { method: 'POST', body, sessid })
}

export function loadResumeFavourites() {
  return request('/api/resume-favourites/')
}

export function postResumeFavourite(body, sessid) {
  return request('/api/resume-favourites/', { method: 'POST', body, sessid })
}

export function loadResponses(page = 1) {
  return request(`/api/responses/?page=${encodeURIComponent(page)}`)
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

export async function postRequest({ email, subject, message, files }, sessid) {
  const body = new FormData()
  body.append('email', email)
  body.append('subject', subject)
  body.append('message', message)
  body.append('sessid', sessid)
  for (const file of files) body.append('files[]', file)

  const response = await fetch('/api/requests/', {
    method: 'POST',
    headers: { 'X-Bitrix-Csrf-Token': sessid },
    body,
  })
  const payload = await response.json().catch(() => ({}))
  if (!response.ok) {
    throw new Error(payload.error || 'Не удалось отправить заявку')
  }

  return payload
}
