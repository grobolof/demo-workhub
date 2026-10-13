const CITY_PHRASE = {
  moskva: 'в Москве',
  'sankt-peterburg': 'в Санкт-Петербурге',
  kazan: 'в Казани',
  novosibirsk: 'в Новосибирске',
  ekaterinburg: 'в Екатеринбурге',
  krasnodar: 'в Краснодаре',
}

export function cityPhrase(city) {
  if (!city) return 'во всех городах'
  return CITY_PHRASE[city.code] || `в городе ${city.name}`
}

export function formatMoney(value) {
  return `${new Intl.NumberFormat('ru-RU').format(value)} ₽`
}

export function formatSalary(from, to) {
  const money = (value) => new Intl.NumberFormat('ru-RU').format(value)
  if (from && to && from === to) return `${money(from)} ₽`
  if (from && to) return `${money(from)} – ${money(to)} ₽`
  if (from) return `от ${money(from)} ₽`
  if (to) return `до ${money(to)} ₽`
  return 'з/п не указана'
}

export function labeledCount(count, forms) {
  const value = Math.abs(Number(count) || 0)
  const mod10 = value % 10
  const mod100 = value % 100
  let word = forms[2]
  if (mod100 < 11 || mod100 > 14) {
    if (mod10 === 1) word = forms[0]
    else if (mod10 >= 2 && mod10 <= 4) word = forms[1]
  }

  return {
    value: new Intl.NumberFormat('ru-RU').format(value),
    word,
  }
}

export function vacancyCount(count) {
  const label = labeledCount(count, ['вакансия', 'вакансии', 'вакансий'])
  return `${label.value} ${label.word}`
}
