import { onMounted, onUnmounted, ref } from 'vue'

export function useInfiniteList(loadPage, { failure = 'Не удалось загрузить данные', keep = null } = {}) {
  const items = ref([])
  const total = ref(0)
  const page = ref(1)
  const initialLoading = ref(true)
  const loadingMore = ref(false)
  const error = ref('')
  const moreError = ref('')
  let requestId = 0

  function nearBottom() {
    return document.documentElement.scrollHeight - window.scrollY - window.innerHeight < 700
  }

  async function refresh() {
    const current = ++requestId
    error.value = ''
    moreError.value = ''
    page.value = 1
    try {
      const payload = await loadPage(1)
      if (current !== requestId) return
      const incoming = payload.vacancies || []
      const accepted = keep ? incoming.filter(keep) : incoming
      items.value = accepted
      total.value = Math.max(accepted.length, Number(payload.total ?? accepted.length) - (incoming.length - accepted.length))
    } catch (exception) {
      if (current !== requestId) return
      error.value = exception.message || failure
    } finally {
      if (current === requestId) {
        initialLoading.value = false
        queueMicrotask(maybeLoadMore)
      }
    }
  }

  async function loadMore() {
    if (loadingMore.value || initialLoading.value) return
    if (items.value.length >= total.value) return
    const current = requestId
    const nextPage = page.value + 1
    loadingMore.value = true
    moreError.value = ''
    try {
      const payload = await loadPage(nextPage)
      if (current !== requestId) return
      const seen = new Set(items.value.map((item) => item.id))
      let added = 0
      let skipped = 0
      for (const item of payload.vacancies || []) {
        if (keep && !keep(item)) {
          skipped += 1
          continue
        }
        if (seen.has(item.id)) continue
        items.value.push(item)
        added += 1
      }
      const reported = Number(payload.total ?? total.value)
      total.value = Math.max(items.value.length, reported - skipped)
      page.value = nextPage
      if (added === 0 && skipped === 0) total.value = items.value.length
    } catch (exception) {
      if (current !== requestId) return
      moreError.value = exception.message || failure
    } finally {
      if (current === requestId) {
        loadingMore.value = false
        if (!moreError.value && items.value.length < total.value && nearBottom()) {
          queueMicrotask(loadMore)
        }
      }
    }
  }

  function maybeLoadMore() {
    if (nearBottom()) loadMore()
  }

  function drop(ids) {
    const removed = new Set(ids)
    const next = items.value.filter((item) => !removed.has(item.id))
    const count = items.value.length - next.length
    if (count === 0) return
    items.value = next
    total.value = Math.max(next.length, total.value - count)
    queueMicrotask(maybeLoadMore)
  }

  onMounted(() => {
    window.addEventListener('scroll', maybeLoadMore, { passive: true })
    refresh()
  })

  onUnmounted(() => {
    window.removeEventListener('scroll', maybeLoadMore)
  })

  return { items, total, initialLoading, loadingMore, error, moreError, drop }
}
