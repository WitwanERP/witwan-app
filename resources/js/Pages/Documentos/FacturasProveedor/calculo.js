/**
 * Cálculo de importes de una factura de tercero, en el navegador.
 *
 * IMPORTANTE: este archivo no conoce ninguna alícuota. Las tasas llegan del
 * servidor en `opciones.calculo` (FacturaproveedorCalculo::tasas()), que es la
 * única fuente de verdad. En el CI legacy el JS tenía 0.21 / 0.19 / 0.18
 * escritos a mano (scriptfactura3ro.js:61-71) mientras el PHP leía
 * sysconfig.tasageneral: si no coincidían, el usuario veía un total y se
 * guardaba otro.
 *
 * Sirve sólo para dar respuesta inmediata mientras se tipea. Al guardar, el
 * servidor recalcula siempre e ignora lo que mande el front.
 */

const CAMPOS_RETPER = [
  'retencioniva',
  'retencioniibb',
  'percepcioniva',
  'percepcioniibb',
  'retencionganancias',
  'percepcionganancias',
  'otrosimpuestos',
]

const num = (v) => {
  const n = Number(v)
  return Number.isFinite(n) ? n : 0
}

const redondear = (v, decimales = 2) => {
  const f = 10 ** decimales
  return Math.round((v + Number.EPSILON) * f) / f
}

/**
 * El exento efectivo: si la licencia tiene conceptos adicionales, su suma
 * REEMPLAZA al campo exento (no se suma encima). Mismo criterio que el servidor.
 */
export function exentoEfectivo(form, conceptos = []) {
  if (!conceptos.length) return num(form.exento)
  return conceptos.reduce((acc, c) => acc + num(form.adicionales?.[c.clave]), 0)
}

/**
 * @param {object} form   Campos del formulario.
 * @param {object} tasas  opciones.calculo del servidor.
 * @param {Array}  conceptos  Conceptos adicionales de la licencia.
 */
export function calcularTotales(form, tasas, conceptos = []) {
  const exento = exentoEfectivo(form, conceptos)
  const nocomputable = num(form.nocomputable)
  const especial = num(form.especial)
  const general = num(form.general)
  const monto27 = num(form.monto27)
  const monto25 = num(form.monto25)
  const ivatur = num(form.ivatur)

  const montosiniva = exento + nocomputable + especial + general + monto27 + monto25

  // Cada componente se redondea por separado, igual que en el servidor.
  const ivaGeneral = redondear(general * num(tasas.general))
  const ivaEspecial = redondear(especial * num(tasas.especial))
  const iva27 = redondear(monto27 * num(tasas.monto27))
  const iva25 = redondear(monto25 * num(tasas.monto25))

  let ivaCalculado = ivaGeneral + ivaEspecial + iva27 + iva25
  let soloiva = ivaCalculado - redondear(ivatur)

  // Donde el IVA es editable (Chile) manda lo que cargó el usuario y no se
  // descuenta el IVA turismo.
  if (tasas.ivatotalEditable) {
    ivaCalculado = num(form.ivatotal)
    soloiva = redondear(ivaCalculado, tasas.decimales ?? 0)
  }

  const retper = CAMPOS_RETPER.reduce((acc, c) => acc + num(form[c]), 0)

  return {
    exento,
    montosiniva,
    ivaGeneral,
    ivaEspecial,
    iva27,
    iva25,
    ivaCalculado,
    soloiva,
    retper,
    montototal: montosiniva + soloiva + retper,
    montoperc: montosiniva + retper,
  }
}

/** Formato de importe para mostrar (es-AR / es-CL usan el mismo separador). */
export function formatearImporte(v, decimales = 2) {
  return num(v).toLocaleString('es-AR', {
    minimumFractionDigits: decimales,
    maximumFractionDigits: decimales,
  })
}
