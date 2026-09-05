<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { Link, router, useForm, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { useEnvio } from '@/lib/envio'
import { formatearFecha } from '@/lib/formato'
import GrillaAlojamiento from './GrillaAlojamiento.vue'
import GrillaTramos from './GrillaTramos.vue'

defineOptions({ layout: AppLayout })

/**
 * Alta/edición de una vigencia (período tarifario) con su grilla de tarifas.
 * Reemplaza a productos/vigencia.php + vigencia.js del CI. La grilla la arma
 * el server (GrillaTarifas): acá se editan celdas y se manda el payload de
 * VigenciaRequest. El preview de venta se pide al server (VentaPreview) en
 * vez de recalcular en JS.
 */
const props = defineProps({
  producto: { type: Object, required: true },
  vigencia: { type: Object, required: true },
  alojamientos: { type: Array, default: () => [] },
  grilla: { type: Object, required: true },
  otras: { type: Array, default: () => [] },
  catalogos: { type: Object, required: true },
  monedas: { type: Array, default: () => [] },
  baseUrl: { type: String, required: true },
  urlProducto: { type: String, required: true },
  permisos: { type: Object, default: () => ({}) },
})

const page = usePage()
const esEdicion = computed(() => Number(props.vigencia.vigencia_id) > 0)
const esAlojamiento = computed(() => props.grilla.modo === 'alojamiento')
const esPaquete = computed(() => ['PAQ', 'TRL'].includes(props.producto.fk_tipoproducto_id))
const avisos = computed(() => page.props.flash?.avisos ?? [])

const fieldBase =
  'w-full rounded-lg border border-gray-300 bg-gray-50 py-2 px-3 text-sm text-gray-800 transition focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 disabled:bg-gray-100 disabled:text-gray-500'

const v = props.vigencia
const monedaBasica = props.monedas.find((m) => m.basica)?.id ?? ''

// --- Celdas de la grilla ------------------------------------------------------
// Alojamiento: un objeto por celda indexado por "categoria_base" (el orden de
// inserción es el que se manda y el que usa el server para nombrar errores).
const celdas = reactive({})
if (esAlojamiento.value) {
  for (const fila of props.grilla.filas) {
    for (const c of fila.celdas) {
      celdas[`${fila.categoria}_${c.base}`] = { categoria: fila.categoria, base: c.base, costo: c.costo, venta: { ...c.venta } }
    }
  }
}
// Tramos: array editable.
const tramos = reactive(
  esAlojamiento.value
    ? []
    : props.grilla.filas.map((f) => ({ min: f.min, max: f.max, costos: { ...f.costos }, venta: JSON.parse(JSON.stringify(f.venta)) }))
)

const form = useForm({
  vigencia_ini: v.vigencia_ini ?? '',
  vigencia_fin: v.vigencia_fin ?? '',
  vigencia_ventaini: v.vigencia_ventaini ?? '',
  vigencia_ventafin: v.vigencia_ventafin ?? '',
  vencimiento_promocion: v.vencimiento_promocion ?? '',
  vigencia_descripcion: v.vigencia_descripcion ?? '',
  comentarios: v.comentarios ?? '',
  nota_promocion: v.nota_promocion ?? '',
  residente: v.residente ?? '',
  vigencia_prioridad: Number(v.vigencia_prioridad ?? 0),
  vigencia_promocional: Number(v.vigencia_promocional ?? 0),
  acumulable: Number(v.acumulable ?? 0),
  web: Number(v.web ?? 0),
  cargamanual: Number(v.cargamanual ?? 0),
  promo_noches: Number(v.promo_noches ?? 0),
  promo_pornoches: Number(v.promo_pornoches ?? 0),
  vencimiento_reserva: Number(v.vencimiento_reserva ?? 0),
  vencimiento_checkin: Number(v.vencimiento_checkin ?? 0),
  noches_minimas: Number(v.noches_minimas ?? 0),
  modo_nochesminimas: v.modo_nochesminimas || 'X',
  noches: Number(v.noches ?? 0),
  dias: Number(v.dias ?? 0),
  fk_regimen_id: Number(v.fk_regimen_id ?? 0),
  fk_tarifacategoria_id: Number(v.fk_tarifacategoria_id ?? 0),
  dias_semana: [...(v.dias_semana ?? [1, 2, 3, 4, 5, 6, 7])],
  moneda_costo: v.moneda_costo || monedaBasica,
  impuestos: Number(v.impuestos ?? 0),
  impuestos_menor: Number(v.impuestos_menor ?? 0),
  redondeo: v.redondeo ?? ' ',
  alojamientos: props.alojamientos.map((a) => ({
    vigenciaalojamiento_id: a.vigenciaalojamiento_id,
    fk_producto_id: a.fk_producto_id,
    fk_tarifacategoria_id: a.fk_tarifacategoria_id,
    fk_regimen_id: a.fk_regimen_id,
    noches: a.noches,
    ncategoria: a.ncategoria,
    producto_nombre: a.producto_nombre,
  })),
  tarifas: [],
  tramos: [],
})

function toggleDia(d) {
  const i = form.dias_semana.indexOf(d)
  if (i >= 0) form.dias_semana.splice(i, 1)
  else form.dias_semana.push(d)
  form.dias_semana.sort()
}

// --- Preview de venta (server) ------------------------------------------------
const preview = ref({})
const previewInfo = ref(null)
let debounce = null

function costosParaPreview() {
  const costos = []
  if (esAlojamiento.value) {
    for (const [clave, c] of Object.entries(celdas)) {
      if (c.costo !== null && c.costo !== '' && !isNaN(Number(c.costo))) costos.push({ clave, costo: Number(c.costo) })
    }
  } else {
    tramos.forEach((t, i) => {
      for (const [pax, costo] of Object.entries(t.costos)) {
        if (costo !== null && costo !== '' && !isNaN(Number(costo))) costos.push({ clave: `${i}_${pax}`, costo: Number(costo) })
      }
    })
  }
  return costos
}

async function pedirPreview() {
  const costos = costosParaPreview()
  if (costos.length === 0) {
    preview.value = {}
    return
  }
  try {
    const r = await fetch(`${props.baseUrl}/preview-venta`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '' },
      body: JSON.stringify({ moneda_costo: form.moneda_costo, residente: form.residente, redondeo: form.redondeo, costos }),
    })
    if (!r.ok) return
    const data = await r.json()
    preview.value = data.celdas
    previewInfo.value = data
  } catch {
    // Sin preview se puede guardar igual: el server no lo necesita.
  }
}

function programarPreview() {
  clearTimeout(debounce)
  debounce = setTimeout(pedirPreview, 500)
}

watch(() => [form.moneda_costo, form.residente, form.redondeo], programarPreview)
pedirPreview()

// --- Alojamientos (PAQ/TRL) ---------------------------------------------------
function agregarAlojamiento() {
  form.alojamientos.push({ vigenciaalojamiento_id: 0, fk_producto_id: 0, fk_tarifacategoria_id: 0, fk_regimen_id: 0, noches: 1, ncategoria: '', producto_nombre: '' })
}

// --- Guardar / clonar / eliminar ----------------------------------------------
const { enviando, enviar } = useEnvio()

function guardar() {
  form.tarifas = esAlojamiento.value ? Object.values(celdas).map((c) => ({ categoria: c.categoria, base: c.base, costo: vacioANull(c.costo), venta: mapaNull(c.venta) })) : []
  form.tramos = esAlojamiento.value
    ? []
    : tramos.map((t) => {
        const venta = {}
        for (const [tid, porPax] of Object.entries(t.venta)) venta[tid] = mapaNull(porPax)
        return { min: t.min, max: t.max, costos: mapaNull(t.costos), venta }
      })

  enviar(
    (op) => (esEdicion.value ? form.put(`${props.baseUrl}/${v.vigencia_id}`, op) : form.post(props.baseUrl, op)),
    { preserveScroll: true },
  )
}

const vacioANull = (x) => (x === '' || x === undefined ? null : x)
const mapaNull = (o) => {
  const out = {}
  for (const [k, val] of Object.entries(o || {})) out[k] = vacioANull(val)
  return out
}

const clonar = reactive({ abierto: false, desplazar_meses: 12, desplazar_dias: 0, ajustar_pct: 0, descripcion: '' })
function confirmarClonar() {
  enviar((op) => router.post(`${props.baseUrl}/${v.vigencia_id}/clonar`, { ...clonar, abierto: undefined }, op))
}

function eliminar() {
  if (!window.confirm('¿Eliminar esta vigencia con todas sus tarifas? No se puede deshacer.')) return
  enviar((op) => router.delete(`${props.baseUrl}/${v.vigencia_id}`, op))
}

const erroresGenerales = computed(() =>
  Object.entries(form.errors).filter(([k]) => k.startsWith('tarifas.') || k.startsWith('tramos.')).map(([, m]) => m)
)
</script>

<template>
  <div>
    <nav class="mb-4 flex flex-wrap items-center gap-2 text-sm text-gray-500">
      <Link href="/app" class="hover:text-gray-700">Inicio</Link>
      <span>/</span>
      <Link :href="urlProducto" class="hover:text-gray-700">{{ producto.producto_nombre }}</Link>
      <span>/</span>
      <Link :href="baseUrl" class="hover:text-gray-700">Vigencias</Link>
      <span>/</span>
      <span class="font-semibold text-gray-900">{{ esEdicion ? `#${vigencia.vigencia_id}` : 'Nueva' }}</span>
    </nav>

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">{{ esEdicion ? 'Editar vigencia' : 'Nueva vigencia' }}</h1>
        <p class="text-gray-500">{{ producto.producto_nombre }} · {{ producto.fk_tipoproducto_id }} · bases {{ producto.bases.join(', ') }}</p>
      </div>
      <div class="flex flex-wrap gap-2">
        <button v-if="esEdicion && permisos.alta" type="button" class="btn btn-secondary" @click="clonar.abierto = !clonar.abierto">Clonar…</button>
        <button v-if="esEdicion && permisos.borrado" type="button" class="btn btn-danger" :disabled="enviando" @click="eliminar">Eliminar</button>
        <button type="submit" form="formvigencia" class="btn btn-primary" :disabled="enviando">{{ enviando ? 'Guardando…' : 'Guardar' }}</button>
      </div>
    </div>

    <div v-if="clonar.abierto" class="card mb-4 border-blue-200">
      <div class="card-header"><h3 class="card-title">Clonar esta vigencia</h3></div>
      <div class="card-body grid grid-cols-2 gap-3 md:grid-cols-5">
        <div><label class="form-label">Desplazar meses</label><input v-model.number="clonar.desplazar_meses" type="number" :class="fieldBase" /></div>
        <div><label class="form-label">Desplazar días</label><input v-model.number="clonar.desplazar_dias" type="number" :class="fieldBase" /></div>
        <div><label class="form-label">Ajustar costos %</label><input v-model.number="clonar.ajustar_pct" type="number" step="0.1" :class="fieldBase" /></div>
        <div class="col-span-2"><label class="form-label">Descripción de la copia</label><input v-model="clonar.descripcion" type="text" :class="fieldBase" :placeholder="vigencia.vigencia_descripcion" /></div>
      </div>
      <div class="card-footer flex justify-end gap-2">
        <button type="button" class="btn btn-secondary" @click="clonar.abierto = false">Cancelar</button>
        <button type="button" class="btn btn-primary" :disabled="enviando" @click="confirmarClonar">Crear copia</button>
      </div>
    </div>

    <div v-if="avisos.length" class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
      <p v-for="(a, i) in avisos" :key="i">{{ a }}</p>
    </div>
    <div v-if="erroresGenerales.length" class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
      <p v-for="(e, i) in erroresGenerales" :key="i">{{ e }}</p>
    </div>

    <form id="formvigencia" @submit.prevent="guardar">
      <div class="card mb-4">
        <div class="card-header"><h3 class="card-title">Período</h3></div>
        <div class="card-body grid grid-cols-1 gap-4 md:grid-cols-4">
          <div class="md:col-span-2">
            <label class="form-label">Descripción</label>
            <input v-model="form.vigencia_descripcion" type="text" :class="fieldBase" placeholder="Temporada alta 2026" />
          </div>
          <div>
            <label class="form-label font-bold">Estadía desde</label>
            <input v-model="form.vigencia_ini" type="date" :class="fieldBase" />
            <p v-if="form.errors.vigencia_ini" class="form-error">{{ form.errors.vigencia_ini }}</p>
          </div>
          <div>
            <label class="form-label font-bold">Estadía hasta</label>
            <input v-model="form.vigencia_fin" type="date" :class="fieldBase" />
            <p v-if="form.errors.vigencia_fin" class="form-error">{{ form.errors.vigencia_fin }}</p>
          </div>
          <div>
            <label class="form-label">Venta desde</label>
            <input v-model="form.vigencia_ventaini" type="date" :class="fieldBase" />
          </div>
          <div>
            <label class="form-label">Venta hasta</label>
            <input v-model="form.vigencia_ventafin" type="date" :class="fieldBase" />
            <p v-if="form.errors.vigencia_ventafin" class="form-error">{{ form.errors.vigencia_ventafin }}</p>
          </div>
          <div>
            <label class="form-label">Prioridad</label>
            <input v-model.number="form.vigencia_prioridad" type="number" min="0" max="99" :class="fieldBase" />
            <p class="form-hint">A mayor prioridad, gana sobre vigencias solapadas.</p>
          </div>
          <div>
            <label class="form-label">Residente</label>
            <select v-model="form.residente" :class="fieldBase">
              <option v-for="(label, val) in catalogos.residente" :key="val" :value="val">{{ label }}</option>
            </select>
          </div>

          <div class="md:col-span-4">
            <label class="form-label font-bold">Días de la semana</label>
            <div class="flex flex-wrap gap-2">
              <label v-for="(nombre, d) in catalogos.dias" :key="d" class="cursor-pointer select-none rounded-md border px-3 py-1.5 text-sm" :class="form.dias_semana.includes(Number(d)) ? 'border-blue-600 bg-blue-600 text-white' : 'border-gray-300 bg-white text-gray-600'">
                <input type="checkbox" class="sr-only" :checked="form.dias_semana.includes(Number(d))" @change="toggleDia(Number(d))" />{{ nombre }}
              </label>
            </div>
            <p v-if="form.errors.dias_semana" class="form-error">{{ form.errors.dias_semana }}</p>
          </div>

          <div v-if="esAlojamiento">
            <label class="form-label">Régimen</label>
            <select v-model.number="form.fk_regimen_id" :class="fieldBase">
              <option :value="0">—</option>
              <option v-for="r in catalogos.regimenes" :key="r.value" :value="r.value">{{ r.label }}</option>
            </select>
          </div>
          <div v-if="esAlojamiento">
            <label class="form-label">Noches mínimas</label>
            <input v-model.number="form.noches_minimas" type="number" min="0" max="99" :class="fieldBase" />
            <p v-if="form.errors.noches_minimas" class="form-error">{{ form.errors.noches_minimas }}</p>
          </div>
          <div v-if="esAlojamiento && form.noches_minimas > 0" class="md:col-span-2">
            <label class="form-label">Se aplican</label>
            <div class="flex gap-4 pt-2 text-sm">
              <label v-for="(label, val) in catalogos.modo_nochesminimas" :key="val" class="flex items-center gap-1.5"><input v-model="form.modo_nochesminimas" type="radio" :value="val" />{{ label }}</label>
            </div>
            <p v-if="form.errors.modo_nochesminimas" class="form-error">{{ form.errors.modo_nochesminimas }}</p>
          </div>
          <div v-if="esPaquete"><label class="form-label">Noches</label><input v-model.number="form.noches" type="number" min="0" :class="fieldBase" /></div>
          <div v-if="esPaquete"><label class="form-label">Días</label><input v-model.number="form.dias" type="number" min="0" :class="fieldBase" /></div>
          <div v-if="!esAlojamiento">
            <label class="form-label">Categoría (opcional)</label>
            <select v-model.number="form.fk_tarifacategoria_id" :class="fieldBase">
              <option :value="0">—</option>
              <option v-for="c in catalogos.tarifacategorias" :key="c.value" :value="c.value">{{ c.label }}</option>
            </select>
          </div>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header"><h3 class="card-title">Promoción y vencimientos</h3></div>
        <div class="card-body grid grid-cols-2 gap-4 md:grid-cols-6">
          <label class="flex items-center gap-2 text-sm md:col-span-2"><input v-model="form.vigencia_promocional" type="checkbox" :true-value="1" :false-value="0" />Es promoción</label>
          <div><label class="form-label">Noches gratis</label><input v-model.number="form.promo_noches" type="number" min="0" :class="fieldBase" /></div>
          <div><label class="form-label">Por cada</label><input v-model.number="form.promo_pornoches" type="number" min="0" :class="fieldBase" /><p v-if="form.errors.promo_pornoches" class="form-error">{{ form.errors.promo_pornoches }}</p></div>
          <label class="flex items-center gap-2 text-sm"><input v-model="form.acumulable" type="checkbox" :true-value="1" :false-value="0" />Acumulable</label>
          <div><label class="form-label">Vence promo</label><input v-model="form.vencimiento_promocion" type="date" :class="fieldBase" /></div>
          <div class="md:col-span-3"><label class="form-label">Nota de promoción</label><input v-model="form.nota_promocion" type="text" :class="fieldBase" /></div>
          <div><label class="form-label">Vto. reserva (días)</label><input v-model.number="form.vencimiento_reserva" type="number" min="0" max="120" :class="fieldBase" /><p v-if="form.errors.vencimiento_reserva" class="form-error">{{ form.errors.vencimiento_reserva }}</p></div>
          <div><label class="form-label">Vto. check-in (días)</label><input v-model.number="form.vencimiento_checkin" type="number" min="0" max="120" :class="fieldBase" /><p v-if="form.errors.vencimiento_checkin" class="form-error">{{ form.errors.vencimiento_checkin }}</p></div>
          <label class="flex items-center gap-2 text-sm"><input v-model="form.web" type="checkbox" :true-value="1" :false-value="0" />Visible en web</label>
        </div>
      </div>

      <div v-if="esPaquete" class="card mb-4">
        <div class="card-header">
          <h3 class="card-title">Hoteles incluidos</h3>
          <button type="button" class="btn btn-secondary btn-sm" @click="agregarAlojamiento">+ Agregar</button>
        </div>
        <div class="card-body space-y-2">
          <div v-for="(a, i) in form.alojamientos" :key="i" class="grid grid-cols-2 gap-2 md:grid-cols-6">
            <div class="md:col-span-2"><label class="form-label">Hotel (id)</label><input v-model.number="a.fk_producto_id" type="number" min="0" :class="fieldBase" :placeholder="a.producto_nombre || 'producto_id'" /></div>
            <div><label class="form-label">Categoría (id)</label><input v-model.number="a.fk_tarifacategoria_id" type="number" min="0" :class="fieldBase" /></div>
            <div><label class="form-label">Régimen</label><select v-model.number="a.fk_regimen_id" :class="fieldBase"><option :value="0">—</option><option v-for="r in catalogos.regimenes" :key="r.value" :value="r.value">{{ r.label }}</option></select></div>
            <div><label class="form-label">Noches</label><input v-model.number="a.noches" type="number" min="0" :class="fieldBase" /></div>
            <div class="flex items-end gap-2"><input v-model="a.ncategoria" type="text" :class="fieldBase" placeholder="Categoría (texto)" /><button type="button" class="text-sm text-red-600" @click="form.alojamientos.splice(i, 1)">Quitar</button></div>
          </div>
          <p v-if="form.alojamientos.length === 0" class="text-sm text-gray-500">Sin hoteles asociados.</p>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header">
          <h3 class="card-title">Tarifas</h3>
          <label class="flex items-center gap-2 text-sm"><input v-model="form.cargamanual" type="checkbox" :true-value="1" :false-value="0" @change="programarPreview" />Carga manual de venta por tarifario</label>
        </div>
        <div class="card-body">
          <div class="mb-4 grid grid-cols-2 gap-4 md:grid-cols-5">
            <div>
              <label class="form-label font-bold">Moneda de costo</label>
              <select v-model="form.moneda_costo" :class="fieldBase">
                <option v-for="m in monedas" :key="m.id" :value="m.id">{{ m.id }}</option>
              </select>
              <p v-if="form.errors.moneda_costo" class="form-error">{{ form.errors.moneda_costo }}</p>
            </div>
            <div><label class="form-label">Impuestos %</label><input v-model.number="form.impuestos" type="number" step="0.01" min="0" :class="fieldBase" /></div>
            <div v-if="esAlojamiento"><label class="form-label">Impuestos menores %</label><input v-model.number="form.impuestos_menor" type="number" step="0.01" min="0" :class="fieldBase" /></div>
            <div>
              <label class="form-label">Redondeo</label>
              <select v-model="form.redondeo" :class="fieldBase">
                <option v-for="(label, val) in catalogos.redondeo" :key="val" :value="val">{{ label }}</option>
              </select>
            </div>
            <div v-if="previewInfo" class="text-xs text-gray-500 md:pt-6">
              IVA costo {{ previewInfo.iva_costo }}% · cotización {{ form.moneda_costo }} {{ previewInfo.cotizacion_costo }}
            </div>
          </div>

          <GrillaAlojamiento
            v-if="esAlojamiento"
            :filas="grilla.filas"
            :tarifarios="grilla.tarifarios"
            :celdas="celdas"
            :cargamanual="form.cargamanual === 1"
            :preview="preview"
            :moneda-costo="form.moneda_costo"
            :errors="form.errors"
            @cambio="programarPreview"
          />
          <GrillaTramos
            v-else
            :tramos="tramos"
            :tipos-pax="grilla.tipos_pax"
            :tarifarios="grilla.tarifarios"
            :cargamanual="form.cargamanual === 1"
            :preview="preview"
            :moneda-costo="form.moneda_costo"
            :errors="form.errors"
            @cambio="programarPreview"
          />
          <p class="form-hint mt-3">
            La venta sugerida sale de <em>costo + IVA de costo × cotización ÷ markup del tarifario</em>, la misma fórmula del tarifador.
            Con carga manual, el valor que se guarda es el que se escribe.
          </p>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header"><h3 class="card-title">Comentarios</h3></div>
        <div class="card-body"><textarea v-model="form.comentarios" rows="3" :class="fieldBase"></textarea></div>
      </div>

      <div v-if="otras.length" class="card mb-6">
        <div class="card-header"><h3 class="card-title">Otras vigencias del producto</h3></div>
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500"><tr><th class="px-4 py-2 text-left">Descripción</th><th class="px-4 py-2 text-left">Estadía</th><th class="px-4 py-2 text-left">Prioridad</th><th class="px-4 py-2 text-left">Residente</th><th class="px-4 py-2 text-right">Tarifas</th></tr></thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="o in otras" :key="o.vigencia_id">
                <td class="px-4 py-2"><Link :href="`${baseUrl}/${o.vigencia_id}/edit`" class="text-blue-600 hover:underline">{{ o.vigencia_descripcion || `#${o.vigencia_id}` }}</Link></td>
                <td class="px-4 py-2 tabular-nums">{{ formatearFecha(o.vigencia_ini) }} – {{ formatearFecha(o.vigencia_fin) }}</td>
                <td class="px-4 py-2">{{ o.vigencia_prioridad }}</td>
                <td class="px-4 py-2">{{ catalogos.residente[o.residente] ?? o.residente }}</td>
                <td class="px-4 py-2 text-right tabular-nums">{{ o.tarifas }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="mb-10 flex gap-2">
        <button type="submit" class="btn btn-primary" :disabled="enviando">{{ enviando ? 'Guardando…' : 'Guardar' }}</button>
        <Link :href="urlProducto" class="btn btn-secondary">Volver al producto</Link>
      </div>
    </form>
  </div>
</template>
