<script setup>
import { reactive, ref, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'

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
  fecha_desde: props.filtros.fecha_desde ?? '',
  fecha_hasta: props.filtros.fecha_hasta ?? '',
  fechacontable_desde: props.filtros.fechacontable_desde ?? '',
  fechacontable_hasta: props.filtros.fechacontable_hasta ?? '',
  fechacarga_desde: props.filtros.fechacarga_desde ?? '',
  fechacarga_hasta: props.filtros.fechacarga_hasta ?? '',
})

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
  router.get(props.destino, params, { preserveState: true, preserveScroll: true, replace: true })
}

function limpiar() {
  for (const k of Object.keys(form)) form[k] = ''
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

          <div>
            <label class="form-label">Fecha de factura</label>
            <div class="flex gap-2">
              <input v-model="form.fecha_desde" type="date" :class="fieldBase" />
              <input v-model="form.fecha_hasta" type="date" :class="fieldBase" />
            </div>
          </div>

          <div>
            <label class="form-label">Fecha contable</label>
            <div class="flex gap-2">
              <input v-model="form.fechacontable_desde" type="date" :class="fieldBase" />
              <input v-model="form.fechacontable_hasta" type="date" :class="fieldBase" />
            </div>
          </div>

          <div>
            <label class="form-label">Fecha de carga</label>
            <div class="flex gap-2">
              <input v-model="form.fechacarga_desde" type="date" :class="fieldBase" />
              <input v-model="form.fechacarga_hasta" type="date" :class="fieldBase" />
            </div>
          </div>
        </div>

        <div class="mt-4 flex gap-2">
          <button type="submit" class="btn btn-primary">Filtrar</button>
          <button type="button" class="btn btn-secondary" @click="limpiar">Limpiar</button>
        </div>
      </form>
    </div>
  </div>
</template>
