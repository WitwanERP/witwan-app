<script setup>
import { reactive, watch, computed } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
  // Paginador de Laravel: { data, links, from, to, total, ... }
  clientes: { type: Object, required: true },
  filtros: { type: Object, default: () => ({}) },
  opciones: {
    type: Object,
    default: () => ({ paises: [], vendedores: [], cadenas: [], monedas: [] }),
  },
})

const page = usePage()
const pais = computed(() => page.props.tenant?.pais ?? 'AR')

function formatCurrency(value) {
  const p = pais.value
  const locale = p === 'CL' ? 'es-CL' : p === 'DO' ? 'es-DO' : 'es-AR'
  const currency = p === 'CL' ? 'CLP' : p === 'DO' ? 'DOP' : 'ARS'
  return new Intl.NumberFormat(locale, {
    style: 'currency',
    currency,
    minimumFractionDigits: p === 'CL' ? 0 : 2,
  }).format(value ?? 0)
}

// Filtros fieles a CI (configuracion/ruc).
const form = reactive({
  cliente_nombre: props.filtros.cliente_nombre ?? '',
  cliente_razonsocial: props.filtros.cliente_razonsocial ?? '',
  cuit: props.filtros.cuit ?? '',
  cliente_ciudad: props.filtros.cliente_ciudad ?? '',
  cliente_id: props.filtros.cliente_id ?? '',
  fk_pais_id: props.filtros.fk_pais_id ?? '',
  fk_usuario_vendedor: props.filtros.fk_usuario_vendedor ?? '',
  fk_cadenacliente_id: props.filtros.fk_cadenacliente_id ?? '',
  fk_moneda_id: props.filtros.fk_moneda_id ?? '',
  clienteminorista: props.filtros.clienteminorista ?? '',
})

const hayFiltros = computed(() => Object.values(form).some((v) => v !== '' && v !== null))

let debounce = null
watch(form, () => {
  clearTimeout(debounce)
  debounce = setTimeout(() => {
    const params = {}
    for (const [k, v] of Object.entries(form)) {
      if (v !== '' && v !== null) params[k] = v
    }
    router.get('/app/clientes', params, {
      preserveState: true,
      preserveScroll: true,
      replace: true,
    })
  }, 350)
})

function limpiar() {
  for (const k of Object.keys(form)) form[k] = ''
}
</script>

<template>
  <div>
    <!-- Encabezado -->
    <div class="mb-6 flex items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Clientes</h1>
        <p class="text-gray-500">{{ clientes.total }} cliente{{ clientes.total === 1 ? '' : 's' }} en total</p>
      </div>
      <Link href="/app/clientes/create" class="btn btn-primary">
        <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        Nuevo Cliente
      </Link>
    </div>

    <!-- Filtros (fieles a CI) -->
    <div class="card mb-4">
      <div class="card-body">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
          <div>
            <label class="form-label">Nombre</label>
            <input v-model="form.cliente_nombre" type="text" class="form-input" placeholder="Nombre o apellido…" />
          </div>
          <div>
            <label class="form-label">Razón Social</label>
            <input v-model="form.cliente_razonsocial" type="text" class="form-input" placeholder="Razón social…" />
          </div>
          <div>
            <label class="form-label">CUIT / Registro fiscal</label>
            <input v-model="form.cuit" type="text" class="form-input" placeholder="CUIT…" />
          </div>
          <div>
            <label class="form-label">Ciudad</label>
            <input v-model="form.cliente_ciudad" type="text" class="form-input" placeholder="Ciudad…" />
          </div>

          <div>
            <label class="form-label">País</label>
            <select v-model="form.fk_pais_id" class="form-select">
              <option value="">Todos</option>
              <option v-for="p in opciones.paises" :key="p.pais_id" :value="p.pais_id">{{ p.pais_nombre }}</option>
            </select>
          </div>
          <div>
            <label class="form-label">Vendedor</label>
            <select v-model="form.fk_usuario_vendedor" class="form-select">
              <option value="">Todos</option>
              <option v-for="v in opciones.vendedores" :key="v.usuario_id" :value="v.usuario_id">{{ v.nombre }}</option>
            </select>
          </div>
          <div>
            <label class="form-label">Cadena</label>
            <select v-model="form.fk_cadenacliente_id" class="form-select">
              <option value="">Todas</option>
              <option v-for="c in opciones.cadenas" :key="c.cadenacliente_id" :value="c.cadenacliente_id">{{ c.cadenacliente_nombre }}</option>
            </select>
          </div>
          <div>
            <label class="form-label">Moneda</label>
            <select v-model="form.fk_moneda_id" class="form-select">
              <option value="">Todas</option>
              <option v-for="m in opciones.monedas" :key="m.moneda_id" :value="m.moneda_id">{{ m.moneda_nombre }}</option>
            </select>
          </div>

          <div>
            <label class="form-label">ID</label>
            <input v-model="form.cliente_id" type="text" inputmode="numeric" class="form-input" placeholder="ID…" />
          </div>
          <div>
            <label class="form-label">Cliente Minorista</label>
            <select v-model="form.clienteminorista" class="form-select">
              <option value="">Todos</option>
              <option value="1">Sí</option>
              <option value="0">No</option>
            </select>
          </div>
          <div class="flex items-end">
            <button v-if="hayFiltros" type="button" class="btn btn-secondary" @click="limpiar">Limpiar filtros</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Tabla -->
    <div class="card">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">ID</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Nombre</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Razón Social</th>
              <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Límite de Crédito</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">CUIT</th>
              <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Acciones</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 bg-white">
            <tr v-for="c in clientes.data" :key="c.id" class="hover:bg-gray-50">
              <td class="px-4 py-3 text-sm text-gray-500">{{ c.id }}</td>
              <td class="px-4 py-3 font-medium text-gray-900">{{ c.nombre || '—' }}</td>
              <td class="px-4 py-3 text-sm text-gray-700">{{ c.razonSocial || '—' }}</td>
              <td class="px-4 py-3 text-sm text-gray-900 text-right tabular-nums">{{ formatCurrency(c.limiteCredito) }}</td>
              <td class="px-4 py-3 text-sm text-gray-700">{{ c.cuit || '—' }}</td>
              <td class="px-4 py-3 text-right">
                <Link :href="`/app/clientes/${c.id}`" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                  Ver
                </Link>
              </td>
            </tr>

            <!-- Estado vacío -->
            <tr v-if="clientes.data.length === 0">
              <td colspan="6" class="px-4 py-12 text-center text-gray-500">
                <svg class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                No se encontraron clientes.
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Paginación -->
      <div v-if="clientes.total > 0" class="card-footer flex items-center justify-between flex-wrap gap-3">
        <p class="text-sm text-gray-600">
          Mostrando <span class="font-medium">{{ clientes.from }}</span>–<span class="font-medium">{{ clientes.to }}</span>
          de <span class="font-medium">{{ clientes.total }}</span>
        </p>
        <div class="flex flex-wrap gap-1">
          <component
            :is="link.url ? 'Link' : 'span'"
            v-for="(link, i) in clientes.links"
            :key="i"
            :href="link.url || undefined"
            preserve-scroll
            class="px-3 py-1.5 text-sm rounded-md border"
            :class="[
              link.active ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-600 border-gray-300',
              link.url ? 'hover:bg-gray-50' : 'opacity-50 cursor-default',
            ]"
            v-html="link.label"
          />
        </div>
      </div>
    </div>
  </div>
</template>
