<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
  modulos: { type: Array, default: () => [] },
  notificaciones: { type: Array, default: () => [] },
  misReservas: { type: Array, default: () => [] },
  operaciones: { type: Array, default: () => [] },
  misCotizaciones: { type: Array, default: () => [] },
  sistemas: { type: Object, default: () => ({}) },
})

const page = usePage()
const user = computed(() => page.props.auth?.user ?? {})
const fullName = computed(() => [user.value.usuario_nombre, user.value.usuario_apellido].filter(Boolean).join(' ') || 'Usuario')
const tiene = (m) => props.modulos.includes(m)

const PERIODOS = [
  { value: 'semana', label: 'Últimas 2 semanas' },
  { value: 'mes', label: 'Último mes' },
  { value: 'seis', label: 'Últimos 6 meses' },
  { value: 'anio', label: 'Último año' },
]
const AREAS = [1, 2, 3, 4, 7]
const COLORES = { 1: '#66CC00', 2: '#FF33FF', 3: '#00B5B5', 4: '#6633CC', 7: '#66CC00' }
const fmt = new Intl.NumberFormat('es-AR', { maximumFractionDigits: 0 })

// Un estado por gráfico: filtros + datos.
function graficoState() {
  return reactive({ sistema: '', periodo: 'anio', cargando: false, datos: null })
}
const reservas = graficoState()
const cobranzas = graficoState()

async function cargar(estado, url) {
  estado.cargando = true
  try {
    const q = new URLSearchParams({ sistema: estado.sistema, periodo: estado.periodo })
    const r = await fetch(`${url}?${q}`, { headers: { Accept: 'application/json' } })
    estado.datos = r.ok ? await r.json() : null
  } finally {
    estado.cargando = false
  }
}
const cargarReservas = () => cargar(reservas, '/app/dashboard/reservas')
const cargarCobranzas = () => cargar(cobranzas, '/app/dashboard/cobranzas')

onMounted(() => {
  if (tiene('inicio_reservas')) cargarReservas()
  if (tiene('inicio_cobranza')) cargarCobranzas()
})

// Barras SVG simples (sin librería): altura proporcional al máximo de la serie.
function barras(series, campo) {
  const max = Math.max(1, ...series.map((s) => Number(s[campo]) || 0))
  return series.map((s) => ({ clave: s.clave, valor: Number(s[campo]) || 0, alto: Math.round(((Number(s[campo]) || 0) / max) * 100) }))
}
const nombreArea = (id) => (id ? props.sistemas[id] || `Área ${id}` : 'Todas')
const badge = (status) => ({ CO: 'badge-success', CL: 'badge-info', RQ: 'badge-warning', CA: 'badge-danger' }[status] || 'badge-gray')
</script>

<template>
  <div>
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-gray-900">Inicio</h1>
      <p class="text-gray-500">Hola, {{ fullName }}.</p>
    </div>

    <div v-if="modulos.length === 0" class="card"><div class="card-body text-center text-gray-500 py-12">Su perfil no tiene widgets de inicio habilitados.</div></div>

    <!-- Reservas por período -->
    <section v-if="tiene('inicio_reservas')" class="card mb-4">
      <div class="card-header flex-wrap gap-2">
        <h3 class="card-title">Reservas por período <span class="text-gray-400 font-normal">· {{ nombreArea(reservas.sistema) }}</span></h3>
        <div class="flex gap-2 text-sm">
          <select v-model="reservas.sistema" class="form-select text-sm" @change="cargarReservas">
            <option value="">Todas las áreas</option>
            <option v-for="a in AREAS" :key="a" :value="a">{{ nombreArea(a) }}</option>
          </select>
          <select v-model="reservas.periodo" class="form-select text-sm" @change="cargarReservas">
            <option v-for="p in PERIODOS" :key="p.value" :value="p.value">{{ p.label }}</option>
          </select>
        </div>
      </div>
      <div class="card-body">
        <div v-if="reservas.cargando" class="text-gray-400 text-sm">Cargando…</div>
        <template v-else-if="reservas.datos">
          <div class="flex items-end gap-1 h-40 border-b border-gray-200 mb-2 overflow-x-auto">
            <div v-for="b in barras(reservas.datos.series, 'total')" :key="b.clave" class="flex flex-col items-center justify-end flex-1 min-w-[28px] h-full" :title="`${b.clave}: ${b.valor} reservas`">
              <span class="text-[10px] text-gray-500">{{ b.valor }}</span>
              <div class="w-full rounded-t" :style="{ height: b.alto + '%', backgroundColor: COLORES[reservas.sistema] || '#3b82f6' }"></div>
            </div>
          </div>
          <div class="flex gap-1 overflow-x-auto text-[10px] text-gray-500">
            <div v-for="s in reservas.datos.series" :key="s.clave" class="flex-1 min-w-[28px] text-center truncate">{{ s.clave }}</div>
          </div>
          <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mt-4 text-sm">
            <div><div class="text-xs text-gray-500">Reservas en período</div><div class="text-xl font-semibold">{{ reservas.datos.totales.reservas }}</div></div>
            <div><div class="text-xs text-gray-500">Costo sin impuestos</div><div class="text-xl font-semibold">$ {{ fmt.format(reservas.datos.totales.costo) }}</div></div>
            <div><div class="text-xs text-gray-500">Impuestos</div><div class="text-xl font-semibold">$ {{ fmt.format(reservas.datos.totales.impuestos) }}</div></div>
            <div><div class="text-xs text-gray-500">Rentabilidad</div><div class="text-xl font-semibold">$ {{ fmt.format(reservas.datos.totales.renta) }}</div></div>
          </div>
        </template>
      </div>
    </section>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
      <!-- Cobranzas por período -->
      <section v-if="tiene('inicio_cobranza')" class="card">
        <div class="card-header flex-wrap gap-2">
          <h3 class="card-title">Cobranza por período <span class="text-gray-400 font-normal">· {{ nombreArea(cobranzas.sistema) }}</span></h3>
          <div class="flex gap-2 text-sm">
            <select v-model="cobranzas.sistema" class="form-select text-sm" @change="cargarCobranzas">
              <option value="">Todas</option>
              <option v-for="a in AREAS" :key="a" :value="a">{{ nombreArea(a) }}</option>
            </select>
            <select v-model="cobranzas.periodo" class="form-select text-sm" @change="cargarCobranzas">
              <option v-for="p in PERIODOS" :key="p.value" :value="p.value">{{ p.label }}</option>
            </select>
          </div>
        </div>
        <div class="card-body">
          <div v-if="cobranzas.cargando" class="text-gray-400 text-sm">Cargando…</div>
          <template v-else-if="cobranzas.datos">
            <div class="flex items-end gap-1 h-32 border-b border-gray-200 mb-2 overflow-x-auto">
              <div v-for="b in barras(cobranzas.datos.series, 'total')" :key="b.clave" class="flex flex-col items-center justify-end flex-1 min-w-[24px] h-full" :title="`${b.clave}: $ ${fmt.format(b.valor)}`">
                <div class="w-full rounded-t bg-emerald-500" :style="{ height: b.alto + '%' }"></div>
              </div>
            </div>
            <div class="flex gap-1 overflow-x-auto text-[10px] text-gray-500">
              <div v-for="s in cobranzas.datos.series" :key="s.clave" class="flex-1 min-w-[24px] text-center truncate">{{ s.clave }}</div>
            </div>
            <div class="mt-3 text-sm"><span class="text-xs text-gray-500">Total cobrado en el período:</span> <b>$ {{ fmt.format(cobranzas.datos.totales.total) }}</b> <span class="text-gray-500">= USD {{ fmt.format(cobranzas.datos.totales.total_usd) }}</span></div>
          </template>
        </div>
      </section>

      <!-- Vencimientos y alertas -->
      <section v-if="tiene('inicio_vencimientos')" class="card">
        <div class="card-header"><h3 class="card-title">Vencimientos y alertas</h3></div>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Prioridad</th>
                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Descripción</th>
                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Tipo</th>
                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Área</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white">
              <tr v-for="(n, i) in notificaciones" :key="i">
                <td class="px-3 py-2 whitespace-nowrap">
                  <span class="badge" :class="`badge-${n.prioridad.clase}`">{{ n.prioridad.label }}</span>
                  <a v-if="n.url" :href="n.url" class="ml-1 text-blue-600 hover:underline text-xs">abrir</a>
                </td>
                <td class="px-3 py-2">{{ n.nombre }} <span v-if="n.cantidad > 1" class="text-gray-400">({{ n.cantidad }})</span></td>
                <td class="px-3 py-2">{{ n.tipo }}</td>
                <td class="px-3 py-2">{{ n.area }}</td>
              </tr>
              <tr v-if="notificaciones.length === 0"><td colspan="4" class="px-3 py-6 text-center text-gray-400">Sin alertas.</td></tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>

    <!-- Tablas de reservas / operaciones / cotizaciones -->
    <template v-for="bloque in [
      { clave: 'inicio_ureservas', titulo: 'Mis reservas', filas: misReservas },
      { clave: 'inicio_operacion', titulo: 'Operaciones a mi cargo', filas: operaciones },
      { clave: 'inicio_ucotizaciones', titulo: 'Mis cotizaciones', filas: misCotizaciones },
    ]" :key="bloque.clave">
      <section v-if="tiene(bloque.clave)" class="card mt-4">
        <div class="card-header"><h3 class="card-title">{{ bloque.titulo }}</h3><span class="text-sm text-gray-500">{{ bloque.filas.length }}</span></div>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Área</th>
                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">#</th>
                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Status</th>
                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Cliente / usuario</th>
                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Titular</th>
                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Fecha</th>
                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Vencimiento</th>
                <th class="px-3 py-2 text-right text-xs font-semibold text-gray-500 uppercase">Total</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white">
              <tr v-for="r in bloque.filas" :key="r.id" class="hover:bg-gray-50">
                <td class="px-3 py-2">{{ r.area }}</td>
                <td class="px-3 py-2 font-semibold"><a :href="r.href" target="_blank" class="text-blue-700 hover:underline">{{ r.codigo }}</a></td>
                <td class="px-3 py-2"><span class="badge" :class="badge(r.status)">{{ r.status }}</span></td>
                <td class="px-3 py-2">{{ r.cliente }}<div v-if="r.usuario" class="text-xs text-gray-400">{{ r.usuario }}</div></td>
                <td class="px-3 py-2">{{ r.titular }}</td>
                <td class="px-3 py-2 whitespace-nowrap">{{ r.fecha }}</td>
                <td class="px-3 py-2 whitespace-nowrap">{{ r.vencimiento }}</td>
                <td class="px-3 py-2 text-right tabular-nums whitespace-nowrap">{{ r.moneda }} {{ fmt.format(r.total) }}</td>
              </tr>
              <tr v-if="bloque.filas.length === 0"><td colspan="8" class="px-3 py-6 text-center text-gray-400">Sin registros.</td></tr>
            </tbody>
          </table>
        </div>
      </section>
    </template>
  </div>
</template>
