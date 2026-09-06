<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import FilasRepetibles from '@/Components/FilasRepetibles.vue'
import { useEnvio } from '@/lib/envio'

defineOptions({ layout: AppLayout })

const props = defineProps({
  area: { type: String, required: true },
  baseUrl: { type: String, required: true },
  idsistema: { type: Number, required: true },
  fechaMinima: { type: String, required: true },
  monedaDefault: { type: String, default: 'USD' },
  statusFile: { type: String, default: 'CO' },
  puedeForzarCredito: { type: Boolean, default: false },
  // { clientes, proveedores, tipos, monedas, ciudades, paises, vendedores, escritorio, tiposPax }
  opciones: { type: Object, required: true },
})

const inputCls = 'w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500'
const nuevoServicio = () => ({
  fk_tipoproducto_id: '',
  servicio_nombre: '',
  fk_proveedor_id: 0,
  fk_ciudad_id: 0,
  vigencia_ini: props.fechaMinima,
  vigencia_fin: props.fechaMinima,
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
  pasajeros: [],
  _abierto: true,
})

const form = useForm({
  fk_cliente_id: 0,
  titular_nombre: '',
  titular_apellido: '',
  titular_email: '',
  titular_celular: '',
  fk_moneda_id: props.monedaDefault,
  agente: 0,
  observaciones: '',
  fecha_vencimiento: '',
  forzar_credito: 0,
  servicios: [nuevoServicio()],
})

const cliente = computed(() => props.opciones.clientes.find((c) => c.value === Number(form.fk_cliente_id)) || null)
watch(
  () => form.fk_cliente_id,
  () => {
    if (cliente.value && !form.agente && cliente.value.vendedor) form.agente = cliente.value.vendedor
  },
)

const totales = computed(() => {
  const t = {}
  for (const s of form.servicios) {
    const m = s.fk_moneda_id || '?'
    t[m] = t[m] || { total: 0, costo: 0 }
    t[m].total += Number(s.total) || 0
    t[m].costo += (Number(s.costo) || 0) + (Number(s.iva_costo) || 0)
  }
  return t
})
const fmt = (n) => Number(n || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })

function agregarServicio() {
  form.servicios.push(nuevoServicio())
}
function duplicarServicio(i) {
  const c = JSON.parse(JSON.stringify(form.servicios[i]))
  c.nro_confirmacion = ''
  form.servicios.splice(i + 1, 0, c)
}
function quitarServicio(i) {
  if (form.servicios.length === 1) return
  form.servicios.splice(i, 1)
}
function copiarTitular(i) {
  const s = form.servicios[i]
  if (!s.pasajeros.some((p) => p.apellido === form.titular_apellido && p.nombre === form.titular_nombre)) {
    s.pasajeros.push({ nombre: form.titular_nombre, apellido: form.titular_apellido, documento: '', nacionalidad: '', tipopax: 'ADT', nacimiento: '' })
  }
}
const colPax = [
  { campo: 'apellido', label: 'Apellido' },
  { campo: 'nombre', label: 'Nombre' },
  { campo: 'tipopax', label: 'Tipo', tipo: 'select', opciones: props.opciones.tiposPax, ancho: '110px' },
  { campo: 'documento', label: 'Documento', ancho: '140px' },
  { campo: 'nacionalidad', label: 'Nacionalidad', ancho: '130px' },
  { campo: 'nacimiento', label: 'Nacimiento', tipo: 'date', ancho: '150px' },
]

// Validación previa sin crear (misma lógica que el POST) para mostrar errores y avisos antes de confirmar.
const previa = reactive({ errores: {}, advertencias: [], corriendo: false, hecha: false })
async function validarPrevia() {
  previa.corriendo = true
  try {
    const res = await fetch(`${props.baseUrl}/nueva/validar`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-XSRF-TOKEN': decodeURIComponent((document.cookie.match(/XSRF-TOKEN=([^;]+)/) || [])[1] || '') },
      body: JSON.stringify(form.data()),
    })
    const data = res.ok ? await res.json() : { errores: { general: 'No se pudo validar.' }, advertencias: [] }
    previa.errores = data.errores || {}
    previa.advertencias = data.advertencias || []
    previa.hecha = true
  } finally {
    previa.corriendo = false
  }
}
const erroresTodos = computed(() => ({ ...previa.errores, ...form.errors }))
const listaErrores = computed(() => Object.values(erroresTodos.value))

const { enviando, enviar } = useEnvio()
function guardar() {
  if (!window.confirm(`¿Crear la reserva para ${form.titular_apellido}, ${form.titular_nombre} con ${form.servicios.length} servicio(s)?`)) return
  enviar((o) => form.post(`${props.baseUrl}/nueva`, o), { preserveScroll: true })
}
const abierto = ref(true)
</script>

<template>
  <div>
    <nav class="flex items-center gap-2 text-sm text-gray-500 mb-4">
      <Link href="/app" class="hover:text-gray-700">Inicio</Link>
      <span>/</span>
      <Link :href="baseUrl" class="hover:text-gray-700">Reservas {{ area }}</Link>
      <span>/</span>
      <span class="text-gray-900 font-semibold">Nueva reserva</span>
    </nav>

    <div class="mb-4 flex items-start justify-between gap-4 flex-wrap">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Nueva reserva ({{ area }})</h1>
        <p class="text-gray-500 text-sm">Alta manual de file y servicios. El código, las cotizaciones y los totales los calcula el servidor al confirmar. Status inicial del file: <b>{{ statusFile }}</b>.</p>
      </div>
      <div class="flex items-center gap-2">
        <button type="button" class="btn btn-secondary" :disabled="previa.corriendo" @click="validarPrevia">{{ previa.corriendo ? 'Validando…' : 'Validar' }}</button>
        <button type="button" class="btn btn-primary" :disabled="enviando" @click="guardar">{{ enviando ? 'Creando…' : 'Crear reserva' }}</button>
        <Link :href="baseUrl" class="btn btn-secondary">Cancelar</Link>
      </div>
    </div>

    <div v-if="listaErrores.length" class="rounded-md border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800 mb-4">
      <b>No se puede crear:</b>
      <ul class="list-disc ml-5 mt-1"><li v-for="(e, k) in listaErrores" :key="k">{{ e }}</li></ul>
      <label v-if="erroresTodos.credito && puedeForzarCredito" class="flex items-center gap-2 mt-2 font-medium"><input type="checkbox" :true-value="1" :false-value="0" v-model="form.forzar_credito" /> Crear igual excediendo el límite de crédito (queda auditado)</label>
    </div>
    <div v-if="previa.advertencias.length" class="rounded-md border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 mb-4">
      <b>Avisos:</b>
      <ul class="list-disc ml-5 mt-1"><li v-for="(a, k) in previa.advertencias" :key="k">{{ a }}</li></ul>
    </div>
    <div v-else-if="previa.hecha && !listaErrores.length" class="rounded-md border border-green-300 bg-green-50 px-4 py-3 text-sm text-green-800 mb-4">Validación correcta: se puede crear la reserva.</div>

    <section class="card mb-4">
      <div class="card-header"><h2 class="card-title">File</h2></div>
      <div class="card-body grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="md:col-span-2">
          <label class="block text-sm mb-1 font-bold">Cliente</label>
          <select v-model="form.fk_cliente_id" :class="inputCls">
            <option :value="0">Seleccione un cliente</option>
            <option v-for="c in opciones.clientes" :key="c.value" :value="c.value">{{ c.label }}</option>
          </select>
          <p v-if="cliente && cliente.limite > 0" class="text-xs text-gray-500 mt-1">Límite de crédito: {{ fmt(cliente.limite) }}{{ cliente.credito_habilitado ? ' (control activo)' : ' (sin control)' }}</p>
        </div>
        <div>
          <label class="block text-sm mb-1 font-bold">Moneda del file</label>
          <select v-model="form.fk_moneda_id" :class="inputCls"><option v-for="m in opciones.monedas" :key="m.value" :value="m.value">{{ m.label }}</option></select>
        </div>
        <div>
          <label class="block text-sm mb-1">{{ opciones.escritorio.length ? 'Escritorio' : 'Vendedor' }}</label>
          <select v-model="form.agente" :class="inputCls">
            <option :value="0">(yo / vendedor del cliente)</option>
            <option v-for="u in opciones.escritorio.length ? opciones.escritorio : opciones.vendedores" :key="u.value" :value="u.value">{{ u.label }}</option>
          </select>
        </div>
        <div><label class="block text-sm mb-1 font-bold">Apellido del titular</label><input v-model="form.titular_apellido" type="text" maxlength="150" :class="inputCls" /></div>
        <div><label class="block text-sm mb-1 font-bold">Nombre del titular</label><input v-model="form.titular_nombre" type="text" maxlength="150" :class="inputCls" /></div>
        <div><label class="block text-sm mb-1">Email del titular</label><input v-model="form.titular_email" type="email" maxlength="50" :class="inputCls" /></div>
        <div><label class="block text-sm mb-1">Celular del titular</label><input v-model="form.titular_celular" type="text" maxlength="50" :class="inputCls" /></div>
        <div><label class="block text-sm mb-1">Vencimiento del file</label><input v-model="form.fecha_vencimiento" type="date" :class="inputCls" /><span class="text-xs text-gray-400">Vacío = hoy (como el legacy).</span></div>
        <div class="md:col-span-3"><label class="block text-sm mb-1">Observaciones</label><textarea v-model="form.observaciones" rows="2" :class="inputCls"></textarea></div>
      </div>
    </section>

    <section v-for="(s, i) in form.servicios" :key="i" class="card mb-4">
      <div class="card-header flex items-center justify-between">
        <h2 class="card-title">Servicio {{ i + 1 }} <span class="text-gray-400 font-normal text-sm">{{ s.servicio_nombre || '(sin nombre)' }}</span></h2>
        <div class="flex gap-3 text-xs">
          <button type="button" class="text-blue-600 hover:underline" @click="s._abierto = !s._abierto">{{ s._abierto ? 'Contraer' : 'Expandir' }}</button>
          <button type="button" class="text-blue-600 hover:underline" @click="duplicarServicio(i)">Duplicar</button>
          <button type="button" class="text-red-600 hover:underline" :disabled="form.servicios.length === 1" @click="quitarServicio(i)">Quitar</button>
        </div>
      </div>
      <div v-show="s._abierto" class="card-body space-y-4">
        <div class="grid grid-cols-2 md:grid-cols-6 gap-3">
          <div>
            <label class="block text-sm mb-1 font-bold">Tipo</label>
            <select v-model="s.fk_tipoproducto_id" :class="inputCls"><option value="">--</option><option v-for="t in opciones.tipos" :key="t.value" :value="t.value">{{ t.label }}</option></select>
          </div>
          <div class="md:col-span-3"><label class="block text-sm mb-1 font-bold">Nombre del servicio</label><input v-model="s.servicio_nombre" type="text" maxlength="200" :class="inputCls" /></div>
          <div class="md:col-span-2">
            <label class="block text-sm mb-1">Proveedor</label>
            <select v-model="s.fk_proveedor_id" :class="inputCls"><option :value="0">--</option><option v-for="p in opciones.proveedores" :key="p.value" :value="p.value">{{ p.label }}</option></select>
          </div>
          <div class="md:col-span-2">
            <label class="block text-sm mb-1">Ciudad</label>
            <select v-model="s.fk_ciudad_id" :class="inputCls"><option :value="0">--</option><option v-for="c in opciones.ciudades" :key="c.value" :value="c.value">{{ c.label }}</option></select>
          </div>
          <div><label class="block text-sm mb-1 font-bold">Inicio</label><input v-model="s.vigencia_ini" type="date" :min="fechaMinima" :class="inputCls" /></div>
          <div><label class="block text-sm mb-1">Fin</label><input v-model="s.vigencia_fin" type="date" :min="s.vigencia_ini" :class="inputCls" /></div>
          <div><label class="block text-sm mb-1">Status</label><select v-model="s.status" :class="inputCls"><option value="CO">CO · Confirmado</option><option value="RQ">RQ · A confirmar</option></select></div>
          <div><label class="block text-sm mb-1">Vence pago prov.</label><input v-model="s.vencimiento_proveedor" type="date" :class="inputCls" /></div>
          <div><label class="block text-sm mb-1">Adultos</label><input v-model.number="s.adultos" type="number" min="0" :class="inputCls" /></div>
          <div><label class="block text-sm mb-1">Menores</label><input v-model.number="s.menores" type="number" min="0" :class="inputCls" /></div>
          <div><label class="block text-sm mb-1">Infantes</label><input v-model.number="s.infante" type="number" min="0" :class="inputCls" /></div>
          <div><label class="block text-sm mb-1">Juniors</label><input v-model.number="s.juniors" type="number" min="0" :class="inputCls" /></div>
          <div class="md:col-span-2"><label class="block text-sm mb-1">Nro. confirmación</label><input v-model="s.nro_confirmacion" type="text" maxlength="200" :class="inputCls" /></div>
          <div><label class="block text-sm mb-1 font-bold">Moneda venta</label><select v-model="s.fk_moneda_id" :class="inputCls"><option v-for="m in opciones.monedas" :key="m.value" :value="m.value">{{ m.label }}</option></select></div>
          <div><label class="block text-sm mb-1 font-bold">Total venta</label><input v-model.number="s.total" type="number" step="0.01" min="0" :class="inputCls" /></div>
          <div><label class="block text-sm mb-1">IVA venta</label><input v-model.number="s.iva" type="number" step="0.01" min="0" :class="inputCls" /></div>
          <div><label class="block text-sm mb-1">Moneda costo</label><select v-model="s.moneda_costo" :class="inputCls"><option v-for="m in opciones.monedas" :key="m.value" :value="m.value">{{ m.label }}</option></select></div>
          <div><label class="block text-sm mb-1">Costo</label><input v-model.number="s.costo" type="number" step="0.01" min="0" :class="inputCls" /></div>
          <div><label class="block text-sm mb-1">IVA costo</label><input v-model.number="s.iva_costo" type="number" step="0.01" min="0" :class="inputCls" /></div>
          <div><label class="block text-sm mb-1">Impuestos</label><input v-model.number="s.impuestos" type="number" step="0.01" min="0" :class="inputCls" /></div>
          <div class="md:col-span-5"><label class="block text-sm mb-1">Comentarios</label><input v-model="s.comentarios" type="text" :class="inputCls" /></div>
        </div>
        <div>
          <div class="flex items-center justify-between mb-1">
            <h3 class="text-sm font-semibold text-gray-800">Nómina de pasajeros</h3>
            <button type="button" class="text-xs text-blue-600 hover:underline" @click="copiarTitular(i)">Agregar al titular</button>
          </div>
          <FilasRepetibles v-model="s.pasajeros" :columnas="colPax" agregar-label="Agregar pasajero" />
        </div>
      </div>
    </section>

    <div class="flex items-center justify-between flex-wrap gap-3 mb-8">
      <button type="button" class="btn btn-secondary" @click="agregarServicio">+ Agregar servicio</button>
      <div class="text-sm text-gray-700 flex gap-4">
        <span v-for="(t, m) in totales" :key="m"><b>{{ m }}</b>: venta {{ fmt(t.total) }} · costo {{ fmt(t.costo) }}</span>
      </div>
      <button type="button" class="btn btn-primary" :disabled="enviando" @click="guardar">{{ enviando ? 'Creando…' : 'Crear reserva' }}</button>
    </div>
  </div>
</template>
