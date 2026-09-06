<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import RangoFechas from '@/Components/RangoFechas.vue'
import { useEnvio } from '@/lib/envio'

defineOptions({ layout: AppLayout })

const props = defineProps({
  area: { type: String, required: true },
  filtros: { type: Object, required: true },
  consultado: { type: Boolean, default: false },
  opciones: { type: Object, required: true },
  filas: { type: Array, default: () => [] },
})

const base = `/app/operaciones/trafico/${props.area}`
const fieldBase =
  'w-full rounded-lg border border-gray-300 bg-gray-50 py-2 px-3 text-sm text-gray-800 transition focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20'
const inputCls = 'w-full rounded-md border border-gray-300 px-2 py-1 text-xs focus:outline-none focus:ring-2 focus:ring-blue-500'

const form = reactive({
  rango: { desde: props.filtros.from || '', hasta: props.filtros.to || '' },
  tipocodigo: props.filtros.tipocodigo || '',
  tipo: [...(props.filtros.tipo || [])],
  cliente: props.filtros.cliente || '',
  producto: props.filtros.producto || '',
  proveedor: props.filtros.proveedor || '',
  titular: props.filtros.titular || '',
  codigo: props.filtros.codigo || '',
  negocio: props.filtros.negocio || '',
  orden: props.filtros.orden || '',
})
const productos = ref(props.opciones.productos || [])
watch(
  () => form.proveedor,
  async (p) => {
    form.producto = ''
    productos.value = []
    if (!p) return
    const r = await fetch(`${base}/productos/${p}`, { headers: { Accept: 'application/json' } })
    if (r.ok) productos.value = await r.json()
  },
)

function params() {
  const p = {}
  if (form.rango.desde) p.from = form.rango.desde
  if (form.rango.hasta) p.to = form.rango.hasta
  for (const k of ['tipocodigo', 'cliente', 'producto', 'proveedor', 'titular', 'codigo', 'negocio', 'orden']) if (form[k] !== '') p[k] = form[k]
  if (form.tipo.length) p.tipo = form.tipo
  return p
}
function buscar() {
  router.get(base, params(), { preserveState: true, preserveScroll: true })
}
function limpiar() {
  router.get(base)
}

// Edición en línea: copia editable de cada fila.
const edicion = reactive({})
for (const f of props.filas) {
  edicion[f.servicio_id] = {
    idioma: f.idioma, pickup: f.pickup, dropoff: f.dropoff, dropoff_hora: f.dropoff_hora, codigo_vuelo: f.codigo_vuelo,
    trafico_contacto: f.trafico_contacto, comentario: f.comentario, proveedor: f.fk_proveedor_id,
  }
}
const seleccion = ref(new Set())
const todos = computed({
  get: () => props.filas.length > 0 && props.filas.every((f) => seleccion.value.has(f.servicio_id)),
  set: (v) => {
    seleccion.value = new Set(v ? props.filas.map((f) => f.servicio_id) : [])
  },
})
function toggle(id) {
  const s = new Set(seleccion.value)
  s.has(id) ? s.delete(id) : s.add(id)
  seleccion.value = s
}

const aviso = reactive({ pickup_avisado_entre: '', pickup_avisado_hasta: '', trafico_contacto: '' })
const pro = reactive({ pro_moneda: 'USD', pro_costo: '', pro_ivacosto: '', pro_proveedor: '' })

const { enviando, enviar } = useEnvio()
function enviarAccion(accion, extra = {}) {
  if (seleccion.value.size === 0) {
    window.alert('Seleccione al menos un servicio.')
    return
  }
  const ids = [...seleccion.value]
  const servicios = {}
  for (const id of ids) servicios[id] = edicion[id]
  enviar((o) => router.post(`${base}/guardar`, { accion, ids, servicios, qst: params(), ...extra }, o), { preserveScroll: true })
}
const guardar = () => enviarAccion('guardarcambios')
const confirmar = () => enviarAccion('confirmar', { ...aviso })
function prorratear() {
  if (!pro.pro_proveedor || pro.pro_costo === '') {
    window.alert('Complete proveedor y costo a prorratear.')
    return
  }
  if (!window.confirm(`¿Prorratear ${pro.pro_moneda} ${pro.pro_costo} entre ${seleccion.value.size} servicio(s)? Se pisa el costo de cada uno.`)) return
  enviarAccion('prorratear', { ...pro })
}
const fmt = new Intl.NumberFormat('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
</script>

<template>
  <div>
    <nav class="flex items-center gap-2 text-sm text-gray-500 mb-3">
      <Link href="/app" class="hover:text-gray-700">Inicio</Link>
      <span>/</span>
      <span class="font-medium" style="color: #66cc00">Operaciones</span>
      <span>/</span>
      <span class="text-gray-900 font-semibold">Tráfico <span class="text-gray-500 font-normal">({{ area }})</span></span>
    </nav>

    <div class="mb-6 flex items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Tráfico</h1>
        <p class="text-gray-500">
          <span v-if="consultado">{{ filas.length }} servicio{{ filas.length === 1 ? '' : 's' }}</span>
          <span v-else>Complete al menos un filtro y presione Buscar.</span>
        </p>
      </div>
    </div>

    <form class="card mb-4" @submit.prevent="buscar">
      <div class="card-header">
        <h3 class="card-title">Filtros</h3>
        <button type="button" class="text-sm font-medium text-gray-500 hover:text-red-600" @click="limpiar">Limpiar</button>
      </div>
      <div class="card-body grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
        <div class="sm:col-span-2"><RangoFechas v-model="form.rango" label="Fecha in del servicio" /></div>
        <div>
          <label class="form-label">Tipo de producto</label>
          <select v-model="form.tipo" multiple size="4" :class="fieldBase"><option v-for="o in opciones.tipos" :key="o.value" :value="o.value">{{ o.label }}</option></select>
        </div>
        <div>
          <label class="form-label">Tipo de file</label>
          <input v-model="form.tipocodigo" type="text" :class="fieldBase" placeholder="RE, GR…" />
        </div>
        <div>
          <label class="form-label">Cliente</label>
          <select v-model="form.cliente" :class="fieldBase"><option value="">Todos</option><option v-for="o in opciones.clientes" :key="o.value" :value="o.value">{{ o.label }}</option></select>
        </div>
        <div>
          <label class="form-label">Proveedor</label>
          <select v-model="form.proveedor" :class="fieldBase"><option value="">Todos</option><option v-for="o in opciones.proveedores" :key="o.value" :value="o.value">{{ o.label }}</option></select>
        </div>
        <div>
          <label class="form-label">Producto</label>
          <select v-model="form.producto" :class="fieldBase" :disabled="!form.proveedor"><option value="">Todos</option><option v-for="o in productos" :key="o.value" :value="o.value">{{ o.label }}</option></select>
        </div>
        <div>
          <label class="form-label">Negocio</label>
          <select v-model="form.negocio" :class="fieldBase"><option value="">Todos</option><option v-for="o in opciones.negocios" :key="o.value" :value="o.value">{{ o.label }}</option></select>
        </div>
        <div>
          <label class="form-label">Titular</label>
          <input v-model="form.titular" type="text" :class="fieldBase" />
        </div>
        <div>
          <label class="form-label">Código</label>
          <input v-model="form.codigo" type="text" :class="fieldBase" placeholder="Varios separados por *" />
        </div>
        <div>
          <label class="form-label">Orden</label>
          <select v-model="form.orden" :class="fieldBase">
            <option value="">File y fecha</option>
            <option value="fecha">Fecha</option>
            <option value="cliente">Cliente</option>
            <option value="proveedor">Proveedor</option>
            <option value="servicio">Servicio</option>
          </select>
        </div>
      </div>
      <div class="card-footer"><button type="submit" class="btn btn-primary">Buscar</button></div>
    </form>

    <div v-if="consultado" class="card mb-4">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-2 py-2"><input type="checkbox" v-model="todos" /></th>
              <th class="px-2 py-2 text-left text-xs font-semibold text-gray-500 uppercase">File</th>
              <th class="px-2 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Fecha</th>
              <th class="px-2 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Pax / idioma</th>
              <th class="px-2 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Pickup / dropoff / horario / vuelo</th>
              <th class="px-2 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Servicio / proveedor</th>
              <th class="px-2 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Agencia / vendedor</th>
              <th class="px-2 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Comentarios</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 bg-white">
            <tr v-for="f in filas" :key="f.servicio_id" class="align-top hover:bg-gray-50">
              <td class="px-2 py-2"><input type="checkbox" :checked="seleccion.has(f.servicio_id)" @change="toggle(f.servicio_id)" /></td>
              <td class="px-2 py-2 whitespace-nowrap">
                <b>{{ f.codigo }}</b><br />
                <a :href="`/reserva/itinerario/${f.reserva_id}`" target="_blank" class="text-xs text-blue-700 hover:underline">Ver itinerario</a>
              </td>
              <td class="px-2 py-2 whitespace-nowrap">{{ f.fecha }}</td>
              <td class="px-2 py-2 min-w-[140px]">
                {{ f.titular }} x {{ f.pax }}
                <input v-model="edicion[f.servicio_id].idioma" type="text" :class="inputCls" placeholder="Idioma" />
              </td>
              <td class="px-2 py-2 min-w-[180px] space-y-1">
                <input v-model="edicion[f.servicio_id].pickup" type="text" :class="inputCls" placeholder="Lugar pickup" title="Lugar pickup" />
                <input v-model="edicion[f.servicio_id].dropoff" type="text" :class="inputCls" placeholder="Lugar dropoff" title="Lugar dropoff" />
                <input v-model="edicion[f.servicio_id].dropoff_hora" type="text" :class="inputCls" placeholder="Horario" title="Horario" />
                <input v-model="edicion[f.servicio_id].codigo_vuelo" type="text" :class="inputCls" placeholder="Código vuelo" title="Código de vuelo" />
                <div v-if="f.pickup_avisado" class="text-xs text-gray-500">Avisado: {{ f.pickup_avisado }} <span v-if="f.trafico_contacto">({{ f.trafico_contacto }})</span></div>
              </td>
              <td class="px-2 py-2 min-w-[200px]">
                <span :title="f.servicio">{{ f.servicio.length > 50 ? f.servicio.slice(0, 50) + '…' : f.servicio }}</span> <span class="text-gray-400 text-xs">{{ f.tipo }}</span>
                <select v-model.number="edicion[f.servicio_id].proveedor" :class="inputCls">
                  <option v-for="p in f.proveedores" :key="p.value" :value="p.value">{{ p.label }}</option>
                </select>
                <div class="text-xs text-gray-500 tabular-nums">{{ f.moneda_costo }} {{ fmt.format(f.costo) }} (+IVA {{ fmt.format(f.iva_costo) }})</div>
              </td>
              <td class="px-2 py-2">{{ f.cliente }}<div class="text-xs text-gray-500">{{ f.vendedor }}</div></td>
              <td class="px-2 py-2 min-w-[200px]">
                <textarea v-model="edicion[f.servicio_id].comentario" rows="3" :class="inputCls"></textarea>
              </td>
            </tr>
            <tr v-if="filas.length === 0">
              <td colspan="8" class="px-4 py-12 text-center text-gray-500">No hay servicios para los filtros.</td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-if="filas.length" class="card-footer flex flex-wrap items-end gap-3">
        <button type="button" class="btn btn-primary" :disabled="enviando" @click="guardar">Guardar cambios ({{ seleccion.size }})</button>

        <div class="flex items-end gap-2 border-l pl-3">
          <div><label class="form-label">Pickup avisado entre</label><input v-model="aviso.pickup_avisado_entre" type="text" :class="fieldBase" /></div>
          <div><label class="form-label">hasta</label><input v-model="aviso.pickup_avisado_hasta" type="text" :class="fieldBase" /></div>
          <div><label class="form-label">Contacto</label><input v-model="aviso.trafico_contacto" type="text" :class="fieldBase" /></div>
          <button type="button" class="btn btn-secondary" :disabled="enviando" @click="confirmar">Confirmar</button>
        </div>

        <div class="flex items-end gap-2 border-l pl-3">
          <div>
            <label class="form-label">Moneda</label>
            <select v-model="pro.pro_moneda" :class="fieldBase"><option v-for="m in opciones.monedas" :key="m.value" :value="m.value">{{ m.label }}</option></select>
          </div>
          <div><label class="form-label">Costo</label><input v-model="pro.pro_costo" type="number" step="any" :class="fieldBase" /></div>
          <div><label class="form-label">IVA costo</label><input v-model="pro.pro_ivacosto" type="number" step="any" :class="fieldBase" /></div>
          <div>
            <label class="form-label">Proveedor</label>
            <select v-model="pro.pro_proveedor" :class="fieldBase"><option value="">--</option><option v-for="p in opciones.proveedores" :key="p.value" :value="p.value">{{ p.label }}</option></select>
          </div>
          <button type="button" class="btn btn-warning" :disabled="enviando" @click="prorratear">Prorratear</button>
        </div>
      </div>
    </div>
  </div>
</template>
