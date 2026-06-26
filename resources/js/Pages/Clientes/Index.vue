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
const cantidadFiltros = computed(() => Object.values(form).filter((v) => v !== '' && v !== null).length)

// Estilos compartidos de los campos de filtro.
const fieldBase =
  'w-full rounded-lg border border-gray-300 bg-gray-50 py-2 text-sm text-gray-800 placeholder-gray-400 transition focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20'
const inputClass = `${fieldBase} pl-10 pr-3`
const selectClass = `${fieldBase} pl-3 pr-10 appearance-none cursor-pointer`

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
      <div class="card-header">
        <h3 class="card-title">
          <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L14 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 018 21v-7.586L3.293 6.707A1 1 0 013 6V4z" />
          </svg>
          Filtros
          <span v-if="cantidadFiltros" class="badge badge-info">{{ cantidadFiltros }}</span>
        </h3>
        <button
          v-if="hayFiltros"
          type="button"
          class="inline-flex items-center gap-1 text-sm font-medium text-gray-500 hover:text-red-600 transition-colors"
          @click="limpiar"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
          Limpiar
        </button>
      </div>

      <div class="card-body">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-x-4 gap-y-3">
          <!-- Nombre -->
          <div>
            <label class="form-label">Nombre</label>
            <div class="relative">
              <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
              </span>
              <input v-model="form.cliente_nombre" type="text" :class="inputClass" placeholder="Nombre o apellido…" />
            </div>
          </div>

          <!-- Razón Social -->
          <div>
            <label class="form-label">Razón Social</label>
            <div class="relative">
              <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5" />
                </svg>
              </span>
              <input v-model="form.cliente_razonsocial" type="text" :class="inputClass" placeholder="Razón social…" />
            </div>
          </div>

          <!-- CUIT -->
          <div>
            <label class="form-label">CUIT / Registro fiscal</label>
            <div class="relative">
              <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                </svg>
              </span>
              <input v-model="form.cuit" type="text" :class="inputClass" placeholder="CUIT…" />
            </div>
          </div>

          <!-- Ciudad -->
          <div>
            <label class="form-label">Ciudad</label>
            <div class="relative">
              <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
              </span>
              <input v-model="form.cliente_ciudad" type="text" :class="inputClass" placeholder="Ciudad…" />
            </div>
          </div>

          <!-- País -->
          <div>
            <label class="form-label">País</label>
            <div class="relative">
              <select v-model="form.fk_pais_id" :class="selectClass">
                <option value="">Todos</option>
                <option v-for="p in opciones.paises" :key="p.pais_id" :value="p.pais_id">{{ p.pais_nombre }}</option>
              </select>
              <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
              </span>
            </div>
          </div>

          <!-- Vendedor -->
          <div>
            <label class="form-label">Vendedor</label>
            <div class="relative">
              <select v-model="form.fk_usuario_vendedor" :class="selectClass">
                <option value="">Todos</option>
                <option v-for="v in opciones.vendedores" :key="v.usuario_id" :value="v.usuario_id">{{ v.nombre }}</option>
              </select>
              <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
              </span>
            </div>
          </div>

          <!-- Cadena -->
          <div>
            <label class="form-label">Cadena</label>
            <div class="relative">
              <select v-model="form.fk_cadenacliente_id" :class="selectClass">
                <option value="">Todas</option>
                <option v-for="c in opciones.cadenas" :key="c.cadenacliente_id" :value="c.cadenacliente_id">{{ c.cadenacliente_nombre }}</option>
              </select>
              <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
              </span>
            </div>
          </div>

          <!-- Moneda -->
          <div>
            <label class="form-label">Moneda</label>
            <div class="relative">
              <select v-model="form.fk_moneda_id" :class="selectClass">
                <option value="">Todas</option>
                <option v-for="m in opciones.monedas" :key="m.moneda_id" :value="m.moneda_id">{{ m.moneda_nombre }}</option>
              </select>
              <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
              </span>
            </div>
          </div>

          <!-- ID -->
          <div>
            <label class="form-label">ID</label>
            <div class="relative">
              <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14" />
                </svg>
              </span>
              <input v-model="form.cliente_id" type="text" inputmode="numeric" :class="inputClass" placeholder="ID…" />
            </div>
          </div>

          <!-- Cliente Minorista -->
          <div>
            <label class="form-label">Cliente Minorista</label>
            <div class="relative">
              <select v-model="form.clienteminorista" :class="selectClass">
                <option value="">Todos</option>
                <option value="1">Sí</option>
                <option value="0">No</option>
              </select>
              <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
              </span>
            </div>
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
                <Link :href="`/app/clientes/${c.id}/edit`" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                  Editar
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
