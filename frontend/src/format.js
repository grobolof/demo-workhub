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
  if (from && to) return `${money(from)} – ${money(to)} ₽`
  if (from) return `от ${money(from)} ₽`
  if (to) return `до ${money(to)} ₽`
  return 'з/п не указана'
}

export function vacancyCount(count) {
  const mod10 = count % 10
  const mod100 = count % 100
  if (mod10 === 1 && mod100 !== 11) return `${count} вакансия`
  if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) return `${count} вакансии`
  return `${count} вакансий`
}
