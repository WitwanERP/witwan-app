/**
 * fetch JSON contra los endpoints propios de /app, con la cookie XSRF de Laravel.
 *
 * Los endpoints de apoyo de las pantallas Inertia (autocompletes, validaciones
 * previas, cotizaciones) devuelven JSON plano y no pasan por el router de
 * Inertia. Antes cada pantalla repetía el header XSRF a mano (Reservas/Nueva.vue
 * lo tenía dos veces); acá queda una sola vez.
 *
 * Si la respuesta no es 2xx se lanza un Error con `status` y `data` (el JSON
 * de error de Laravel: `message` y `errors` para un 422).
 */
export class HttpError extends Error {
  constructor(status, data) {
    super(data?.message || `HTTP ${status}`)
    this.status = status
    this.data = data
  }
}

const xsrf = () => decodeURIComponent((document.cookie.match(/XSRF-TOKEN=([^;]+)/) || [])[1] || '')

async function parsear(res) {
  const data = await res.json().catch(() => null)
  if (!res.ok) throw new HttpError(res.status, data)
  return data
}

export function getJson(url, params = {}) {
  const qs = new URLSearchParams()
  for (const [k, v] of Object.entries(params)) if (v !== undefined && v !== null && v !== '') qs.set(k, v)
  const sep = url.includes('?') ? '&' : '?'
  return fetch(qs.toString() ? `${url}${sep}${qs}` : url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }).then(parsear)
}

export function postJson(url, body = {}) {
  return fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-XSRF-TOKEN': xsrf() },
    body: JSON.stringify(body),
  }).then(parsear)
}
