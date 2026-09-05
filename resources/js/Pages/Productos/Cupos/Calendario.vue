<script setup>
import { computed, reactive, ref } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { useEnvio } from '@/lib/envio'
import { formatearFecha } from '@/lib/formato'

defineOptions({ layout: AppLayout })

/**
 * Calendario mensual de cupos y bloqueos de una categoría. Reemplaza a
 * producto/vercupo del CI. El mes se navega por fetch (/mes) sin recargar;
 * un click en un día alterna el soldout (/bloqueo).
 */
const props = defineProps({
  producto: { type: Object, required: true },
  categoria: { type: Number, required: true },
  habitaciones: { type: Array, default: () => [] },
  calendario: { type: Object, required: true },
  cupos: { type: Array, default: () => [] },
  soldouts: { type: Array, default: () => [] },
  tarifarios: { type: Array, default: () => [] },
  baseUrl: { type: String, required: true },
  permisos: { type: Object, default: () => ({}) },
})

const MESES = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre']
const cal = ref(props.calendario)
const cargando = ref(false)
const fieldBase = 'w-full rounded-lg border border-gray-300 bg-gray-50 py-2 px-3 text-sm focus:border-blue-500 focus:bg-white focus:outline-none'

const habActual = computed(() => props.habitaciones.find((h) => Number(h.fk_tarifacategoria_id) === props.categoria))

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? ''

async function irA(mes, anio) {
  cargando.value = true
  try {
    const r = await fetch(`${props.baseUrl}/mes?mes=${mes}&anio=${anio}`)
    if (r.ok) cal.value = await r.json()
  } finally {
    cargando.value = false
  }
}

/** Semanas del mes (lunes a domingo) con celdas vacías al inicio/fin. */
const semanas = computed(() => {
  const dias = Object.keys(cal.value.dias)
  if (!dias.length) return []
  const primero = new Date(dias[0] + 'T00:00:00')
  let offset = (primero.getDay() + 6) % 7
  const celdas = Array(offset).fill(null).concat(dias)
  while (celdas.length % 7) celdas.push(null)
  const out = []
  for (let i = 0; i < celdas.length; i += 7) out.push(celdas.slice(i, i + 7))
  return out
})

async function toggle(fecha) {
  if (!props.permisos.alta) return
  const r = await fetch(`${props.baseUrl}/bloqueo`, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() }, body: JSON.stringify({ fecha }) })
  if (!r.ok) return
  const d = await r.json()
  cal.value.dias[fecha].soldout = d.soldout
}

function estilo(d) {
  if (d.soldout) return 'bg-red-100 border-red-300 text-red-800'
  if (d.freesale) return 'bg-green-50 border-green-300 text-green-800'
  if (d.total > 0 && d.asignado >= d.total) return 'bg-amber-50 border-amber-300 text-amber-800'
  if (d.total > 0) return 'bg-white border-gray-200'
  return 'bg-gray-50 border-gray-100 text-gray-400'
}

// --- Altas ------------------------------------------------------------------
const { enviando, enviar } = useEnvio()
const hoy = new Date().toISOString().slice(0, 10)

const cupo = useForm({ fk_tarifacategoria_id: props.categoria, vigencia_ini: hoy, vigencia_fin: hoy, cantidad: 1, release: 0, bases: [], fk_cliente_id: 0, fk_tarifario_id: 0 })
function guardarCupo() {
  enviar((op) => cupo.post(`${props.baseUrl}/cupo`, op), { preserveScroll: true, onSuccess: () => irA(cal.value.mes, cal.value.anio) })
}

const soldout = useForm({ fk_tarifacategoria_id: props.categoria, todos: 0, vigencia_ini: hoy, vigencia_fin: hoy })
function guardarSoldout() {
  enviar((op) => soldout.post(`${props.baseUrl}/soldout`, op), { preserveScroll: true, onSuccess: () => irA(cal.value.mes, cal.value.anio) })
}

const seleccion = reactive({ cupos: [], soldouts: [] })
function eliminar(tipo) {
  const ids = seleccion[tipo]
  if (!ids.length || !window.confirm(`¿Eliminar ${ids.length} registro(s)?`)) return
  enviar((op) => router.post(`${props.baseUrl}/${tipo === 'cupos' ? 'cupo' : 'soldout'}/eliminar`, { ids }, op), { preserveScroll: true, onSuccess: () => { seleccion[tipo] = []; irA(cal.value.mes, cal.value.anio) } })
}
</script>

<template>
  <div>
    <nav class="mb-4 flex items-center gap-2 text-sm text-gray-500">
      <Link href="/app" class="hover:text-gray-700">Inicio</Link><span>/</span>
      <span>{{ producto.producto_nombre }}</span><span>/</span>
      <span class="font-semibold text-gray-900">Cupos</span>
    </nav>
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Cupos y bloqueos</h1>
        <p class="text-gray-500">{{ producto.producto_nombre }} · {{ habActual?.nombre || `categoría ${categoria}` }}</p>
      </div>
      <div class="flex flex-wrap gap-1">
        <Link v-for="h in habitaciones" :key="h.alojamientohabitacion_id" :href="`/app/productos/${producto.producto_id}/cupos/${h.fk_tarifacategoria_id}`" class="rounded-md border px-3 py-1.5 text-sm" :class="Number(h.fk_tarifacategoria_id) === categoria ? 'border-blue-600 bg-blue-600 text-white' : 'border-gray-300 bg-white text-gray-600 hover:bg-gray-50'">{{ h.nombre }}</Link>
      </div>
    </div>

    <div class="card mb-4">
      <div class="card-header">
        <div class="flex items-center gap-3">
          <button type="button" class="btn btn-secondary btn-sm" :disabled="cargando" @click="irA(cal.anterior.mes, cal.anterior.anio)">‹</button>
          <h3 class="card-title w-40 justify-center">{{ MESES[cal.mes - 1] }} {{ cal.anio }}</h3>
          <button type="button" class="btn btn-secondary btn-sm" :disabled="cargando" @click="irA(cal.siguiente.mes, cal.siguiente.anio)">›</button>
        </div>
        <div class="flex gap-3 text-xs text-gray-500">
          <span><i class="mr-1 inline-block h-3 w-3 rounded-sm border border-green-300 bg-green-50 align-middle"></i>freesale</span>
          <span><i class="mr-1 inline-block h-3 w-3 rounded-sm border border-amber-300 bg-amber-50 align-middle"></i>completo</span>
          <span><i class="mr-1 inline-block h-3 w-3 rounded-sm border border-red-300 bg-red-100 align-middle"></i>soldout</span>
        </div>
      </div>
      <div class="card-body" :class="cargando ? 'opacity-50' : ''">
        <div class="grid grid-cols-7 gap-1 text-center text-xs font-semibold uppercase text-gray-500">
          <div v-for="d in ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom']" :key="d">{{ d }}</div>
        </div>
        <div v-for="(sem, i) in semanas" :key="i" class="mt-1 grid grid-cols-7 gap-1">
          <template v-for="(f, j) in sem" :key="j">
            <button v-if="f" type="button" class="min-h-16 rounded-md border p-1.5 text-left text-xs transition hover:ring-2 hover:ring-blue-300" :class="estilo(cal.dias[f])" :title="`${formatearFecha(f)}: cupo ${cal.dias[f].total}, asignado ${cal.dias[f].asignado}. Click para ${cal.dias[f].soldout ? 'desbloquear' : 'bloquear'}.`" @click="toggle(f)">
              <div class="font-semibold tabular-nums">{{ Number(f.slice(8)) }}</div>
              <div class="tabular-nums" v-if="cal.dias[f].total || cal.dias[f].asignado">{{ cal.dias[f].asignado }} / {{ cal.dias[f].freesale ? '∞' : cal.dias[f].total }}</div>
              <div v-if="cal.dias[f].soldout" class="font-semibold">SOLD OUT</div>
              <div v-for="fl in cal.dias[f].files.slice(0, 2)" :key="fl.codigo" class="truncate text-[10px] text-gray-500">{{ fl.codigo }}</div>
            </button>
            <div v-else class="min-h-16"></div>
          </template>
        </div>
      </div>
    </div>

    <div v-if="permisos.alta" class="mb-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
      <form class="card" @submit.prevent="guardarCupo">
        <div class="card-header"><h3 class="card-title">Cargar cupo</h3></div>
        <div class="card-body grid grid-cols-2 gap-3 md:grid-cols-3">
          <div><label class="form-label">Desde</label><input v-model="cupo.vigencia_ini" type="date" :class="fieldBase" /><p v-if="cupo.errors.vigencia_ini" class="form-error">{{ cupo.errors.vigencia_ini }}</p></div>
          <div><label class="form-label">Hasta</label><input v-model="cupo.vigencia_fin" type="date" :class="fieldBase" /><p v-if="cupo.errors.vigencia_fin" class="form-error">{{ cupo.errors.vigencia_fin }}</p></div>
          <div><label class="form-label">Cantidad</label><input v-model.number="cupo.cantidad" type="number" min="0" :class="fieldBase" /><p class="form-hint">0 = freesale</p></div>
          <div><label class="form-label">Release (días)</label><input v-model.number="cupo.release" type="number" min="0" :class="fieldBase" /></div>
          <div><label class="form-label">Tarifario</label><select v-model.number="cupo.fk_tarifario_id" :class="fieldBase"><option :value="0">Todos</option><option v-for="t in tarifarios" :key="t.value" :value="t.value">{{ t.label }}</option></select></div>
          <div><label class="form-label">Cliente (id)</label><input v-model.number="cupo.fk_cliente_id" type="number" min="0" :class="fieldBase" /></div>
        </div>
        <div class="card-footer text-right"><button type="submit" class="btn btn-primary btn-sm" :disabled="enviando">Cargar cupo</button></div>
      </form>

      <form class="card" @submit.prevent="guardarSoldout">
        <div class="card-header"><h3 class="card-title">Bloquear (sold out)</h3></div>
        <div class="card-body grid grid-cols-2 gap-3 md:grid-cols-3">
          <div><label class="form-label">Desde</label><input v-model="soldout.vigencia_ini" type="date" :class="fieldBase" /></div>
          <div><label class="form-label">Hasta</label><input v-model="soldout.vigencia_fin" type="date" :class="fieldBase" /><p v-if="soldout.errors.vigencia_fin" class="form-error">{{ soldout.errors.vigencia_fin }}</p></div>
          <label class="flex items-center gap-2 pt-6 text-sm"><input v-model="soldout.todos" type="checkbox" :true-value="1" :false-value="0" />Todas las categorías</label>
        </div>
        <div class="card-footer text-right"><button type="submit" class="btn btn-danger btn-sm" :disabled="enviando">Bloquear</button></div>
      </form>
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
      <div class="card">
        <div class="card-header"><h3 class="card-title">Cupos vigentes</h3><button v-if="permisos.borrado && seleccion.cupos.length" type="button" class="text-sm text-red-600 hover:underline" :disabled="enviando" @click="eliminar('cupos')">Eliminar {{ seleccion.cupos.length }}</button></div>
        <div class="overflow-x-auto"><table class="min-w-full text-sm"><thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr><th class="px-3 py-2"></th><th class="px-3 py-2 text-left">Rango</th><th class="px-3 py-2 text-right">Cant.</th><th class="px-3 py-2 text-right">Release</th><th class="px-3 py-2 text-left">Bases</th></tr></thead>
          <tbody class="divide-y divide-gray-100"><tr v-for="c in cupos" :key="c.cupo_id"><td class="px-3 py-1.5"><input v-model="seleccion.cupos" type="checkbox" :value="c.cupo_id" /></td><td class="px-3 py-1.5 tabular-nums">{{ formatearFecha(c.vigencia_ini) }} – {{ formatearFecha(c.vigencia_fin) }}</td><td class="px-3 py-1.5 text-right tabular-nums">{{ Number(c.freesale) ? 'FS' : c.cantidad }}</td><td class="px-3 py-1.5 text-right tabular-nums">{{ c.release }}</td><td class="px-3 py-1.5">{{ c.bases || '—' }}</td></tr>
          <tr v-if="cupos.length === 0"><td colspan="5" class="px-3 py-6 text-center text-gray-500">Sin cupos vigentes.</td></tr></tbody></table></div>
      </div>
      <div class="card">
        <div class="card-header"><h3 class="card-title">Bloqueos recientes</h3><button v-if="permisos.borrado && seleccion.soldouts.length" type="button" class="text-sm text-red-600 hover:underline" :disabled="enviando" @click="eliminar('soldouts')">Eliminar {{ seleccion.soldouts.length }}</button></div>
        <div class="max-h-80 overflow-auto"><table class="min-w-full text-sm"><thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr><th class="px-3 py-2"></th><th class="px-3 py-2 text-left">Día</th><th class="px-3 py-2 text-left">Cargado</th></tr></thead>
          <tbody class="divide-y divide-gray-100"><tr v-for="s in soldouts" :key="s.soldout_id"><td class="px-3 py-1.5"><input v-model="seleccion.soldouts" type="checkbox" :value="s.soldout_id" /></td><td class="px-3 py-1.5 tabular-nums">{{ formatearFecha(s.vigencia_ini) }}</td><td class="px-3 py-1.5 text-gray-500">{{ formatearFecha(s.fecha_alta) }}</td></tr>
          <tr v-if="soldouts.length === 0"><td colspan="3" class="px-3 py-6 text-center text-gray-500">Sin bloqueos.</td></tr></tbody></table></div>
      </div>
    </div>
  </div>
</template>
