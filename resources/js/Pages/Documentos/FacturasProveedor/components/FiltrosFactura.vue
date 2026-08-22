<script setup>
import { reactive, ref, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import RangoFechas from './RangoFechas.vue'

const props = defineProps({
  filtros: { type: Object, default: () => ({}) },
  opciones: { type: Object, required: true },
  baseUrl: { type: String, required: true },
  destino: { type: String, required: true },
})

const abierto = ref(true)

const form = reactive({
  proveedor: props.filtros.proveedor ?? '',
  numero: props.filtros.numero ?? '',
  codigo: props.filtros.codigo ?? '',
  proyecto: props.filtros.proyecto ?? '',
  tipodocumento: props.filtros.tipodocumento ?? '',
  moneda: props.filtros.moneda ?? '',
})

/**
 * Los tres rangos se manejan como un objeto {desde, hasta} cada uno, para que el
 * componente pueda mantener los dos extremos coherentes. Al enviar se aplanan a
 * los nombres que espera el backend (fecha_desde, fecha_hasta, …).
 */
const RANGOS = [
  { clave: 'fecha', label: 'Fecha de factura' },
  { clave: 'fechacontable', label: 'Fecha contable' },
  { clave: 'fechacarga', label: 'Fecha de carga' },
]

const rangos = reactive(
  Object.fromEntries(
    RANGOS.map((r) => [
      r.clave,
      { desde: props.filtros[`${r.clave}_desde`] ?? '', hasta: props.filtros[`${r.clave}_hasta`] ?? '' },
    ])
  )
)

// Autocomplete de proveedor: el listado completo son miles de filas.
const buscador = ref('')
const sugerencias = ref([])
const buscando = ref(false)
let debounceProveedor = null

function buscarProveedor() {
  clearTimeout(debounceProveedor)
  debounceProveedor = setTimeout(async () => {
    if (buscador.value.length < 2) {
      sugerencias.value = []
      return
    }
    buscando.value = true
    try {
      const r = await fetch(`${props.baseUrl}/proveedores?q=${encodeURIComponent(buscador.value)}`)
      sugerencias.value = r.ok ? await r.json() : []
    } finally {
      buscando.value = false
    }
  }, 300)
}

function elegirProveedor(p) {
  form.proveedor = p.id
  buscador.value = p.label
  sugerencias.value = []
}

function limpiarProveedor() {
  form.proveedor = ''
  buscador.value = ''
  sugerencias.value = []
}

const fieldBase =
  'w-full rounded-lg border border-gray-300 bg-gray-50 py-2 px-3 text-sm text-gray-800 placeholder-gray-400 transition focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20'

function aplicar() {
  const params = {}
  for (const [k, v] of Object.entries(form)) if (v !== '' && v !== null) params[k] = v

  for (const [clave, rango] of Object.entries(rangos)) {
    if (rango.desde) params[`${clave}_desde`] = rango.desde
    if (rango.hasta) params[`${clave}_hasta`] = rango.hasta
  }

  router.get(props.destino, params, { preserveState: true, preserveScroll: true, replace: true })
}

function limpiar() {
  for (const k of Object.keys(form)) form[k] = ''
  for (const clave of Object.keys(rangos)) {
    rangos[clave].desde = ''
    rangos[clave].hasta = ''
  }
  limpiarProveedor()
  router.get(props.destino, {}, { preserveState: true, preserveScroll: true, replace: true })
}

onMounted(() => {
  // Al volver con un filtro de proveedor aplicado, mostrar su nombre.
  if (form.proveedor) buscador.value = `#${form.proveedor}`
})
</script>

<template>
  <div class="card mb-4">
    <div class="card-header flex items-center justify-between">
      <h3 class="card-title">Filtros</h3>
      <button type="button" class="text-sm text-blue-600 hover:underline" @click="abierto = !abierto">
        {{ abierto ? 'Ocultar' : 'Mostrar' }}
      </button>
    </div>

    <div v-show="abierto" class="card-body">
      <form @submit.prevent="aplicar">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3 lg:grid-cols-4">
          <div class="relative">
            <label class="form-label">Proveedor</label>
            <input
              v-model="buscador"
              type="text"
              :class="fieldBase"
              placeholder="Nombre o CUIT…"
              autocomplete="off"
              @input="buscarProveedor"
            />
            <button
              v-if="form.proveedor"
              type="button"
              class="absolute right-2 top-8 text-gray-400 hover:text-gray-700"
              aria-label="Quitar proveedor"
              @click="limpiarProveedor"
            >
              &times;
            </button>
            <ul
              v-if="sugerencias.length"
              class="absolute z-20 mt-1 max-h-56 w-full overflow-auto rounded-lg border border-gray-200 bg-white shadow-lg"
            >
              <li
                v-for="p in sugerencias"
                :key="p.id"
                class="cursor-pointer px-3 py-2 text-sm hover:bg-blue-50"
                @click="elegirProveedor(p)"
              >
                {{ p.label }}
              </li>
            </ul>
            <p v-if="buscando" class="form-hint">Buscando…</p>
          </div>

          <div>
            <label class="form-label">Número</label>
            <input v-model="form.numero" type="text" :class="fieldBase" placeholder="Contiene…" />
          </div>

          <div>
            <label class="form-label">Código de reserva</label>
            <input v-model="form.codigo" type="text" :class="fieldBase" placeholder="Contiene…" />
          </div>

          <div>
            <label class="form-label">Tipo de documento</label>
            <select v-model="form.tipodocumento" :class="fieldBase">
              <option value="">Todos</option>
              <option v-for="(label, valor) in opciones.tiposDocumento" :key="valor" :value="valor">
                {{ label }}
              </option>
            </select>
          </div>

          <div>
            <label class="form-label">Proyecto</label>
            <select v-model="form.proyecto" :class="fieldBase">
              <option value="">Todos</option>
              <option v-for="(nombre, id) in opciones.proyectos" :key="id" :value="id">{{ nombre }}</option>
            </select>
          </div>

          <div>
            <label class="form-label">Moneda</label>
            <select v-model="form.moneda" :class="fieldBase">
              <option value="">Todas</option>
              <option v-for="m in opciones.monedas" :key="m.id" :value="m.id">{{ m.id }}</option>
            </select>
          </div>

          <RangoFechas
            v-for="r in RANGOS"
            :key="r.clave"
            v-model="rangos[r.clave]"
            :label="r.label"
          />
        </div>

        <p class="form-hint mt-3">
          Al cargar un extremo de un rango, el otro se completa solo para que la consulta quede acotada.
        </p>

        <div class="mt-4 flex gap-2">
          <button type="submit" class="btn btn-primary">Filtrar</button>
          <button type="button" class="btn btn-secondary" @click="limpiar">Limpiar</button>
        </div>
      </form>
    </div>
  </div>
</template>
