import { computed, inject, provide, reactive, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { getJson, postJson } from '@/lib/http'
import { useEnvio } from '@/lib/envio'

/**
 * Estado y acciones del generador de reservas v2 (asistente de venta).
 *
 * Un solo objeto reactivo compartido por provide/inject entre los pasos:
 * contexto (cliente, tipo de pasajero, tarifario) → búsqueda por tipo de
 * producto → carrito con nómina única → confirmación. El carrito manda al
 * servidor el mismo `servicios[]` que acepta GeneradorReservaService::crear();
 * `payload()` es la única traducción entre el estado de pantalla y ese contrato.
 *
 * Se persiste en sessionStorage por área (sin resultados de búsqueda) para
 * sobrevivir un refresh a mitad de la venta.
 */
const CLAVE = Symbol('generador')

export const PASOS = [
  { key: 'contexto', label: 'Cliente', descripcion: 'Quién compra y con qué tarifario' },
  { key: 'buscar', label: 'Buscar', descripcion: 'Productos por tipo, precios y ofertas' },
  { key: 'carrito', label: 'Carrito', descripcion: 'Servicios y pasajeros' },
  { key: 'confirmar', label: 'Confirmar', descripcion: 'Validar y crear la reserva' },
]

export const inputCls = 'w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-gray-50 disabled:text-gray-500'
export const inputSm = 'w-full rounded-md border border-gray-300 px-2 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-gray-50 disabled:text-gray-500'

let uid = 0
export const nuevoUid = () => `u${Date.now().toString(36)}${(uid++).toString(36)}`

export function crearGenerador(props) {
  const clave = `generador:v2:${props.area}`

  const contextoInicial = () => ({
    fk_cliente_id: 0,
    cliente_label: '',
    residente: 'N',
    agente: 0,
    fk_moneda_id: props.monedaDefault,
    titular_apellido: '',
    titular_nombre: '',
    titular_email: '',
    titular_celular: '',
    observaciones: '',
    fecha_vencimiento: '',
    forzar_credito: 0,
  })

  const busquedaInicial = () => ({
    tipo: props.pestanas[0]?.tipo || '',
    form: { from: props.fechaMinima, to: '', ciudad: 0, ciudad_label: '', origen: 0, origen_label: '', destino: 0, destino_label: '', nombre: '', producto_id: 0, producto_label: '', stars: [], habitaciones: [{ ad: 2, mn: [] }], ad: 2, mn: [], mayores70: 0 },
    resultados: [],
    truncado: false,
    candidatos: 0,
    ms: 0,
    corriendo: false,
    error: '',
    hash: '',
  })

  const estado = reactive({
    paso: 'contexto',
    contexto: contextoInicial(),
    clienteInfo: null,
    cargandoCliente: false,
    errorCliente: '',
    busqueda: busquedaInicial(),
    seleccion: null,
    carrito: [],
    pasajeros: [],
    previa: { errores: {}, advertencias: [], corriendo: false, hecha: false },
    erroresServidor: {},
    restaurado: false,
  })

  // ---- Conversión de monedas (informativa: el servidor recalcula al crear) ----
  const cot = (m) => (m === props.monedaBasica ? 1 : Number(props.cotizaciones[m]) || 0)
  const convertir = (monto, de, a) => {
    if (de === a) return Number(monto) || 0
    const cd = cot(de)
    const ca = cot(a)
    return cd && ca ? (Number(monto) || 0) * (cd / ca) : Number(monto) || 0
  }

  // ---- Líneas del carrito ----
  const nuevaLinea = (base = {}) => ({
    _uid: nuevoUid(),
    _origen: 'APP',
    _abierto: true,
    fk_tipoproducto_id: '',
    servicio_nombre: '',
    fk_proveedor_id: 0,
    proveedor_label: '',
    fk_prestador_id: 0,
    fk_producto_id: 0,
    fk_tarifacategoria_id: 0,
    fk_regimen_id: 0,
    fk_base_id: '',
    fk_ciudad_id: 0,
    ciudad_label: '',
    vigencia_ini: estado.busqueda.form.from || props.fechaMinima,
    vigencia_fin: '',
    adultos: 2,
    menores: 0,
    infante: 0,
    juniors: 0,
    fk_moneda_id: props.monedaDefault,
    moneda_costo: props.monedaDefault,
    total: 0,
    costo: 0,
    iva: 0,
    iva_costo: 0,
    impuestos: 0,
    status: 'CO',
    nro_confirmacion: '',
    comentarios: '',
    vencimiento_proveedor: '',
    servicio_extra: { pickup: '', dropoff: '', hora_pickup: '' },
    pasajeros_ids: [],
    _detalle: null, // opción del tarifador aplicada (categoría/régimen, cupo, promo…) para mostrar
    ...base,
  })

  function agregarLinea(base = {}) {
    const l = nuevaLinea(base)
    if (!l.pasajeros_ids.length) l.pasajeros_ids = estado.pasajeros.map((p) => p._uid)
    estado.carrito.push(l)
    return l
  }
  function quitarLinea(uidLinea) {
    estado.carrito = estado.carrito.filter((l) => l._uid !== uidLinea)
  }
  function duplicarLinea(uidLinea) {
    const l = estado.carrito.find((x) => x._uid === uidLinea)
    if (!l) return
    const c = JSON.parse(JSON.stringify(l))
    c._uid = nuevoUid()
    c.nro_confirmacion = ''
    estado.carrito.splice(estado.carrito.indexOf(l) + 1, 0, c)
  }

  // ---- Nómina única ----
  const nuevoPasajero = (base = {}) => ({ _uid: nuevoUid(), apellido: '', nombre: '', tipopax: 'ADT', documento: '', nacionalidad: '', nacimiento: '', ...base })
  function agregarPasajero(base = {}) {
    const p = nuevoPasajero(base)
    estado.pasajeros.push(p)
    for (const l of estado.carrito) if (!l.pasajeros_ids.includes(p._uid)) l.pasajeros_ids.push(p._uid)
    return p
  }
  function agregarTitular() {
    const { titular_apellido: ap, titular_nombre: no } = estado.contexto
    if (!ap.trim() && !no.trim()) return null
    const ya = estado.pasajeros.find((p) => p.apellido.trim().toUpperCase() === ap.trim().toUpperCase() && p.nombre.trim().toUpperCase() === no.trim().toUpperCase())
    return ya || agregarPasajero({ apellido: ap.trim(), nombre: no.trim() })
  }
  function quitarPasajero(uidPax) {
    estado.pasajeros = estado.pasajeros.filter((p) => p._uid !== uidPax)
    for (const l of estado.carrito) l.pasajeros_ids = l.pasajeros_ids.filter((id) => id !== uidPax)
  }
  function asignado(linea, uidPax) {
    return linea.pasajeros_ids.includes(uidPax)
  }
  function alternarAsignacion(linea, uidPax) {
    if (asignado(linea, uidPax)) linea.pasajeros_ids = linea.pasajeros_ids.filter((id) => id !== uidPax)
    else linea.pasajeros_ids.push(uidPax)
  }

  // ---- Totales ----
  const totales = computed(() => {
    const porMoneda = {}
    const file = { moneda: estado.contexto.fk_moneda_id, venta: 0, costo: 0, margen: 0 }
    for (const l of estado.carrito) {
      const mv = l.fk_moneda_id || '?'
      const mc = l.moneda_costo || mv
      porMoneda[mv] ||= { venta: 0, costo: 0 }
      porMoneda[mc] ||= { venta: 0, costo: 0 }
      const venta = Number(l.total) || 0
      const costo = (Number(l.costo) || 0) + (Number(l.iva_costo) || 0) + (Number(l.impuestos) || 0)
      porMoneda[mv].venta += venta
      porMoneda[mc].costo += costo
      file.venta += convertir(venta, mv, file.moneda)
      file.costo += convertir(costo, mc, file.moneda)
    }
    file.margen = file.venta - file.costo
    return { porMoneda, file }
  })
  const margenLinea = (l) => {
    const venta = Number(l.total) || 0
    const costo = convertir((Number(l.costo) || 0) + (Number(l.iva_costo) || 0) + (Number(l.impuestos) || 0), l.moneda_costo || l.fk_moneda_id, l.fk_moneda_id)
    return { valor: venta - costo, pct: venta ? ((venta - costo) / venta) * 100 : 0 }
  }
  const paxLinea = (l) => (Number(l.adultos) || 0) + (Number(l.menores) || 0) + (Number(l.infante) || 0) + (Number(l.juniors) || 0)

  // ---- Cliente ----
  async function elegirCliente(id, label) {
    estado.contexto.fk_cliente_id = Number(id) || 0
    estado.contexto.cliente_label = label || ''
    estado.clienteInfo = null
    estado.errorCliente = ''
    if (!estado.contexto.fk_cliente_id) return
    estado.cargandoCliente = true
    try {
      estado.clienteInfo = await getJson(`${props.baseUrl}/nueva/cliente/${estado.contexto.fk_cliente_id}`)
      const v = estado.clienteInfo?.cliente?.vendedor
      if (!estado.contexto.agente && v && !props.opciones.escritorio.length && props.opciones.vendedores.some((u) => Number(u.value) === Number(v))) estado.contexto.agente = Number(v)
      if (!estado.contexto.titular_email && estado.clienteInfo?.cliente?.pasajero_directo) estado.contexto.titular_email = estado.clienteInfo.cliente.email || ''
    } catch (e) {
      estado.errorCliente = e?.data?.message || 'No se pudo cargar el cliente.'
      estado.contexto.fk_cliente_id = 0
    } finally {
      estado.cargandoCliente = false
    }
  }

  // ---- Pasos ----
  const contextoListo = computed(() => estado.contexto.fk_cliente_id > 0 && !!estado.clienteInfo)
  const faltantesContexto = computed(() => {
    const f = []
    if (!estado.contexto.fk_cliente_id) f.push('Elegí un cliente.')
    if (!estado.contexto.fk_moneda_id) f.push('Elegí la moneda del file.')
    return f
  })
  function puedeIr(paso) {
    if (paso === 'contexto') return true
    if (!contextoListo.value) return false
    if (paso === 'confirmar') return estado.carrito.length > 0
    return true
  }
  function irA(paso) {
    if (!puedeIr(paso)) return
    estado.paso = paso
    if (paso === 'confirmar') validarPrevia()
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }
  const siguiente = () => {
    const i = PASOS.findIndex((p) => p.key === estado.paso)
    if (i < PASOS.length - 1) irA(PASOS[i + 1].key)
  }
  const anterior = () => {
    const i = PASOS.findIndex((p) => p.key === estado.paso)
    if (i > 0) irA(PASOS[i - 1].key)
  }

  // ---- Contrato con el servidor ----
  const limpiar = (o, quitar) => Object.fromEntries(Object.entries(o).filter(([k]) => !quitar.includes(k) && !k.startsWith('_')))
  function payload() {
    const porUid = Object.fromEntries(estado.pasajeros.map((p) => [p._uid, p]))
    return {
      ...limpiar(estado.contexto, ['cliente_label']),
      servicios: estado.carrito.map((l) => ({
        ...limpiar(l, ['proveedor_label', 'ciudad_label', 'pasajeros_ids']),
        pasajeros: l.pasajeros_ids.map((id) => porUid[id]).filter(Boolean).map((p) => limpiar(p, [])),
      })),
    }
  }

  async function validarPrevia() {
    estado.previa.corriendo = true
    try {
      const data = await postJson(`${props.baseUrl}/nueva/validar`, payload())
      estado.previa.errores = data.errores || {}
      estado.previa.advertencias = data.advertencias || []
      estado.previa.hecha = true
    } catch (e) {
      estado.previa.errores = e?.data?.errors ? Object.fromEntries(Object.entries(e.data.errors).map(([k, v]) => [k, [].concat(v).join(' ')])) : { general: 'No se pudo validar.' }
      estado.previa.advertencias = []
      estado.previa.hecha = true
    } finally {
      estado.previa.corriendo = false
    }
  }

  const { enviando, enviar } = useEnvio()
  function crear() {
    estado.erroresServidor = {}
    enviar((op) => router.post(`${props.baseUrl}/nueva`, payload(), op), {
      preserveScroll: true,
      onError: (errores) => {
        estado.erroresServidor = errores || {}
      },
      onSuccess: () => {
        try {
          sessionStorage.removeItem(clave)
        } catch {
          /* sin storage */
        }
      },
    })
  }
  const erroresTodos = computed(() => ({ ...estado.previa.errores, ...estado.erroresServidor }))

  function reiniciar() {
    // Se mutan los objetos (no se reemplazan): los pasos guardan `estado.contexto` y `estado.busqueda.form` en su setup.
    estado.paso = 'contexto'
    Object.assign(estado.contexto, contextoInicial())
    estado.clienteInfo = null
    const b = busquedaInicial()
    Object.assign(estado.busqueda.form, b.form)
    Object.assign(estado.busqueda, { ...b, form: estado.busqueda.form })
    estado.seleccion = null
    estado.carrito = []
    estado.pasajeros = []
    estado.previa = { errores: {}, advertencias: [], corriendo: false, hecha: false }
    estado.erroresServidor = {}
    try {
      sessionStorage.removeItem(clave)
    } catch {
      /* sin storage */
    }
  }

  // ---- Persistencia (sin resultados de búsqueda) ----
  function restaurar() {
    try {
      const raw = sessionStorage.getItem(clave)
      if (!raw) return
      const g = JSON.parse(raw)
      if (g.fechaMinima !== props.fechaMinima) return
      Object.assign(estado.contexto, g.contexto || {})
      if (g.busqueda) {
        estado.busqueda.tipo = g.busqueda.tipo || estado.busqueda.tipo
        Object.assign(estado.busqueda.form, g.busqueda.form || {})
      }
      estado.carrito = (g.carrito || []).map((l) => nuevaLinea(l))
      estado.pasajeros = (g.pasajeros || []).map((p) => nuevoPasajero(p))
      estado.paso = g.paso && g.paso !== 'confirmar' ? g.paso : 'contexto'
      estado.restaurado = estado.carrito.length > 0 || estado.contexto.fk_cliente_id > 0
      if (estado.contexto.fk_cliente_id) elegirCliente(estado.contexto.fk_cliente_id, estado.contexto.cliente_label)
    } catch {
      /* storage vacío o corrupto */
    }
  }
  let t = null
  watch(
    () => [estado.paso, estado.contexto, estado.busqueda.tipo, estado.busqueda.form, estado.carrito, estado.pasajeros],
    () => {
      clearTimeout(t)
      t = setTimeout(() => {
        try {
          sessionStorage.setItem(
            clave,
            JSON.stringify({
              fechaMinima: props.fechaMinima,
              paso: estado.paso,
              contexto: estado.contexto,
              busqueda: { tipo: estado.busqueda.tipo, form: estado.busqueda.form },
              carrito: estado.carrito.map((l) => ({ ...l, _detalle: l._detalle })),
              pasajeros: estado.pasajeros,
            }),
          )
        } catch {
          /* sin storage */
        }
      }, 300)
    },
    { deep: true },
  )
  restaurar()

  const g = {
    props,
    estado,
    PASOS,
    convertir,
    totales,
    margenLinea,
    paxLinea,
    nuevaLinea,
    agregarLinea,
    quitarLinea,
    duplicarLinea,
    agregarPasajero,
    agregarTitular,
    quitarPasajero,
    asignado,
    alternarAsignacion,
    elegirCliente,
    contextoListo,
    faltantesContexto,
    puedeIr,
    irA,
    siguiente,
    anterior,
    payload,
    validarPrevia,
    crear,
    enviando,
    erroresTodos,
    reiniciar,
  }
  provide(CLAVE, g)
  return g
}

export function useGenerador() {
  const g = inject(CLAVE)
  if (!g) throw new Error('useGenerador() fuera de Reservas/Nueva.vue')
  return g
}
