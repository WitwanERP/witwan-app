<script setup>
import { computed, reactive, ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import RangoFechas from '@/Components/RangoFechas.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
  titulo: { type: String, required: true },
  baseUrl: { type: String, required: true },
  filtros: { type: Object, required: true },
  empresas: { type: Array, default: () => [] },
  monedaBasica: { type: String, required: true },
  // null hasta elegir empresa; { anterior, grupos: [{file_id, nombre, fecha, total, movimientos:[{fecha, concepto:[{texto,href}], d, h, saldo}]}], totales: {d,h,saldo} }
  cuenta: { type: Object, default: null },
})

const form = reactive({
  empresa: props.filtros.empresa || '',
  rango: { desde: props.filtros.from || '', hasta: props.filtros.to || '' },
  tiporeporte: props.filtros.tiporeporte || 'C',
  monedareporte: props.filtros.monedareporte || props.monedaBasica,
})
const fieldBase =
  'w-full rounded-lg border border-gray-300 bg-gray-50 py-2 px-3 text-sm text-gray-800 transition focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20'

function params() {
  const p = { empresa: form.empresa, tiporeporte: form.tiporeporte, monedareporte: form.monedareporte }
  if (form.rango.desde) p.from = form.rango.desde
  if (form.rango.hasta) p.to = form.rango.hasta
  return p
}
function buscar() {
  if (!form.empresa) return
  router.get(props.baseUrl, params(), { preserveState: true, preserveScroll: true })
}
const urlExport = computed(() => `${props.baseUrl}?${new URLSearchParams({ ...params(), export: 1 }).toString()}`)

const abiertos = ref(new Set())
function toggle(id) {
  const s = new Set(abiertos.value)
  s.has(id) ? s.delete(id) : s.add(id)
  abiertos.value = s
}
const porFile = computed(() => props.filtros.tiporeporte === 'D')
const fmt = new Intl.NumberFormat('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const num = (v) => (v ? fmt.format(v) : '')
</script>

<template>
  <div>
    <nav class="flex items-center gap-2 text-sm text-gray-500 mb-3">
      <Link href="/app" class="hover:text-gray-700">Inicio</Link>
      <span>/</span>
      <span class="font-medium" style="color: #c9a300">Administración</span>
      <span>/</span>
      <span>Cuentas</span>
      <span>/</span>
      <span class="text-gray-900 font-semibold">{{ titulo }}</span>
    </nav>

    <div class="mb-6 flex items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">{{ titulo }}</h1>
        <p class="text-gray-500" v-if="cuenta">Moneda de reporte: <b>{{ filtros.monedareporte }}</b> · {{ porFile ? 'sólo diferencias por file' : 'completo' }}</p>
        <p class="text-gray-500" v-else>Elija una empresa y presione Buscar.</p>
      </div>
      <a v-if="cuenta" :href="urlExport" class="btn btn-secondary">Exportar CSV</a>
    </div>

    <form class="card mb-4" @submit.prevent="buscar">
      <div class="card-header"><h3 class="card-title">Filtros</h3></div>
      <div class="card-body grid grid-cols-1 lg:grid-cols-4 gap-3">
        <div class="lg:col-span-2">
          <label class="form-label">Empresa</label>
          <select v-model="form.empresa" :class="fieldBase" :disabled="empresas.length === 1">
            <option value="">Seleccione una opción</option>
            <option v-for="e in empresas" :key="e.value" :value="e.value">{{ e.label }}</option>
          </select>
        </div>
        <div class="lg:col-span-2"><RangoFechas v-model="form.rango" label="Período" :meses-atras="120" /></div>
        <div>
          <label class="form-label">Tipo de reporte</label>
          <div class="flex gap-4 text-sm h-9 items-center">
            <label class="flex items-center gap-1"><input type="radio" value="D" v-model="form.tiporeporte" /> Sólo diferencias</label>
            <label class="flex items-center gap-1"><input type="radio" value="C" v-model="form.tiporeporte" /> Completo</label>
          </div>
        </div>
        <div>
          <label class="form-label">Moneda de reporte</label>
          <div class="flex gap-4 text-sm h-9 items-center">
            <label class="flex items-center gap-1"><input type="radio" :value="monedaBasica" v-model="form.monedareporte" /> {{ monedaBasica }}</label>
            <label v-if="monedaBasica !== 'USD'" class="flex items-center gap-1"><input type="radio" value="USD" v-model="form.monedareporte" /> USD</label>
          </div>
        </div>
      </div>
      <div class="card-footer flex gap-2">
        <button type="submit" class="btn btn-primary" :disabled="!form.empresa">Buscar</button>
        <Link :href="baseUrl" class="btn btn-secondary">Limpiar</Link>
      </div>
    </form>

    <div v-if="cuenta" class="card">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase w-28">Fecha</th>
              <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Cuenta</th>
              <th class="px-3 py-2 text-right text-xs font-semibold text-gray-500 uppercase w-32">Debe</th>
              <th class="px-3 py-2 text-right text-xs font-semibold text-gray-500 uppercase w-32">Haber</th>
              <th class="px-3 py-2 text-right text-xs font-semibold text-gray-500 uppercase w-32">Saldo</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 bg-white">
            <tr class="bg-amber-50 font-semibold">
              <td colspan="4" class="px-3 py-2">SALDO ANTERIOR</td>
              <td class="px-3 py-2 text-right tabular-nums">{{ fmt.format(cuenta.anterior) }}</td>
            </tr>
            <template v-for="g in cuenta.grupos" :key="g.file_id">
              <!-- Modo diferencias: una fila por file, desplegable -->
              <template v-if="porFile && g.file_id !== 0">
                <tr class="cursor-pointer hover:bg-gray-50 font-medium" @click="toggle(g.file_id)">
                  <td class="px-3 py-2">{{ g.fecha }}</td>
                  <td class="px-3 py-2"><span class="text-gray-400 mr-1">{{ abiertos.has(g.file_id) ? '▾' : '▸' }}</span>{{ g.nombre || `File #${g.file_id}` }}</td>
                  <td class="px-3 py-2 text-right tabular-nums">{{ g.total > 0 ? fmt.format(g.total) : '' }}</td>
                  <td class="px-3 py-2 text-right tabular-nums">{{ g.total < 0 ? fmt.format(-g.total) : '' }}</td>
                  <td class="px-3 py-2 text-right tabular-nums">{{ fmt.format(g.movimientos[g.movimientos.length - 1]?.saldo ?? 0) }}</td>
                </tr>
                <tr v-for="(m, i) in g.movimientos" v-show="abiertos.has(g.file_id)" :key="i" class="bg-gray-50 text-xs">
                  <td class="px-3 py-1 pl-8">{{ m.fecha }}</td>
                  <td class="px-3 py-1">
                    <template v-for="(c, j) in m.concepto" :key="j"><span v-if="j > 0" class="text-gray-400"> // </span><a v-if="c.href" :href="c.href" target="_blank" class="text-blue-700 hover:underline">{{ c.texto }}</a><span v-else>{{ c.texto }}</span></template>
                  </td>
                  <td class="px-3 py-1 text-right tabular-nums">{{ num(m.d) }}</td>
                  <td class="px-3 py-1 text-right tabular-nums">{{ num(m.h) }}</td>
                  <td class="px-3 py-1 text-right tabular-nums">{{ fmt.format(m.saldo) }}</td>
                </tr>
              </template>
              <!-- Completo (o grupo sin file): movimientos planos -->
              <tr v-else v-for="(m, i) in g.movimientos" :key="`${g.file_id}-${i}`" class="hover:bg-gray-50">
                <td class="px-3 py-2">{{ m.fecha }}</td>
                <td class="px-3 py-2">
                  <template v-for="(c, j) in m.concepto" :key="j"><span v-if="j > 0" class="text-gray-400"> // </span><a v-if="c.href" :href="c.href" target="_blank" class="text-blue-700 hover:underline">{{ c.texto }}</a><span v-else>{{ c.texto }}</span></template>
                </td>
                <td class="px-3 py-2 text-right tabular-nums">{{ num(m.d) }}</td>
                <td class="px-3 py-2 text-right tabular-nums">{{ num(m.h) }}</td>
                <td class="px-3 py-2 text-right tabular-nums" :class="m.saldo < 0 ? 'text-red-700' : ''">{{ fmt.format(m.saldo) }}</td>
              </tr>
            </template>
            <tr v-if="cuenta.grupos.length === 0">
              <td colspan="5" class="px-3 py-8 text-center text-gray-500">Sin movimientos en el período.</td>
            </tr>
          </tbody>
          <tfoot class="bg-gray-50 font-semibold">
            <tr>
              <td colspan="2" class="px-3 py-2">TOTALES</td>
              <td class="px-3 py-2 text-right tabular-nums">{{ fmt.format(cuenta.totales.d) }}</td>
              <td class="px-3 py-2 text-right tabular-nums">{{ fmt.format(cuenta.totales.h) }}</td>
              <td class="px-3 py-2 text-right tabular-nums" :class="cuenta.totales.saldo < 0 ? 'text-red-700' : ''">{{ fmt.format(cuenta.totales.saldo) }}</td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>
</template>
