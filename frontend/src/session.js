import { reactive } from 'vue'
import {
  loadSession,
  postFavourite,
  postLogin,
  postAvatar,
  postCompanyLogo,
  postLogout,
  postProfile,
  postRegister,
  postResponse,
  postResumeFavourite,
} from './api'

export const session = reactive({
  ready: false,
  user: null,
  sessid: '',
  favouriteIds: [],
  responseIds: [],
  resumeFavouriteIds: [],
  adminUrl: '',
})

export function applySession(payload) {
  session.ready = true
  session.user = payload.user || null
  session.sessid = payload.sessid || ''
  session.favouriteIds = payload.favouriteIds || []
  session.responseIds = payload.responseIds || []
  session.resumeFavouriteIds = payload.resumeFavouriteIds || []
  session.adminUrl = payload.adminUrl || session.adminUrl
}

export async function refreshSession() {
  try {
    applySession(await loadSession())
  } catch {
    session.ready = true
  }
}

export async function register(body) {
  applySession(await postRegister(body, session.sessid))
}

export async function login(body) {
  applySession(await postLogin(body, session.sessid))
}

export async function logout() {
  applySession(await postLogout(session.sessid))
}

export async function updateProfile(body) {
  applySession(await postProfile(body, session.sessid))
}

export async function updateAvatar(file) {
  applySession(await postAvatar(file, session.sessid))
}

export async function updateCompany(body) {
  applySession(await postProfile({ scope: 'company', ...body }, session.sessid))
}

export async function updateCompanyLogo(file) {
  applySession(await postCompanyLogo(file, session.sessid))
}

export async function toggleFavourite(vacancyId) {
  const payload = await postFavourite({ vacancyId }, session.sessid)
  const id = payload.vacancyId
  if (payload.favourite) {
    if (!session.favouriteIds.includes(id)) session.favouriteIds.push(id)
  } else {
    session.favouriteIds = session.favouriteIds.filter((item) => item !== id)
  }

  return payload
}

export async function toggleResumeFavourite(resumeId) {
  const payload = await postResumeFavourite({ resumeId }, session.sessid)
  const id = payload.resumeId
  if (payload.favourite) {
    if (!session.resumeFavouriteIds.includes(id)) session.resumeFavouriteIds.push(id)
  } else {
    session.resumeFavouriteIds = session.resumeFavouriteIds.filter((item) => item !== id)
  }

  return payload
}

export async function sendResponse(vacancyId) {
  const payload = await postResponse({ vacancyId }, session.sessid)
  const id = payload.vacancyId
  if (payload.responded && !session.responseIds.includes(id)) session.responseIds.push(id)

  return payload
}
