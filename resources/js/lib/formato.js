/**
 * Formato de importes y fechas para mostrar.
 *
 * Vive acá y no dentro de un módulo porque lo necesita cualquier pantalla que
 * muestre plata. Nació en Pages/Documentos/FacturasProveedor/calculo.js, que
 * ahora lo reexporta para no tocar sus veinte usos.
 */

const num = (v) => {
  const n = typeof v === 'number' ? v : parseFloat(String(v ?? '').replace(',', '.'))

  return Number.isFinite(n) ? n : 0
}

/** Importe con separador de miles (es-AR y es-CL usan el mismo). */
export function formatearImporte(v, decimales = 2) {
  return num(v).toLocaleString('es-AR', {
    minimumFractionDigits: decimales,
    maximumFractionDigits: decimales,
  })
}

/**
 * Fecha ISO (YYYY-MM-DD) a dd/mm/aaaa, que es como la lee el usuario en todo el
 * sistema. Se corta el string en vez de instanciar un Date a propósito: `new
 * Date('2026-03-01')` se interpreta en UTC y en Argentina (UTC-3) muestra el día
 * anterior.
 */
export function formatearFecha(iso) {
  if (!iso) return '—'

  const [y, m, d] = String(iso).slice(0, 10).split('-')

  return d && m && y ? `${d}/${m}/${y}` : String(iso)
}

/** Hoy en ISO, para los defaults de los formularios. */
export function hoyIso() {
  const d = new Date()

  return [
    d.getFullYear(),
    String(d.getMonth() + 1).padStart(2, '0'),
    String(d.getDate()).padStart(2, '0'),
  ].join('-')
}
