<script setup>
import { reactive, ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { useEnvio } from '@/lib/envio'

defineOptions({ layout: AppLayout })

const props = defineProps({
  // [{ id, nombre, existe, habilitado, hijos: [{ id, nombre, existe, habilitado, tipo, reciproco, es_titular }] }]
  arbol: { type: Array, default: () => [] },
  resumen: { type: Object, required: true },
  usuarios: { type: Array, default: () => [] },
  puedeEscribir: { type: Boolean, default: false },
  aplica: { type: Boolean, default: false },
  baseUrl: { type: String, required: true },
})

const form = reactive({ titular: '', secundario: '', inversa: false })
const filtro = ref('')
const { enviando, enviar } = useEnvio()

function agregar() {
  if (!form.titular || !form.secundario) return
  enviar((o) => router.post(`${props.baseUrl}/agregar`, { ...form, inversa: form.inversa ? 1 : 0 }, o), { preserveScroll: true })
}
function quitar(titular, secundario) {
  if (!window.confirm(`¿Quitar a #${secundario} del escritorio #${titular}?`)) return
  enviar((o) => router.post(`${props.baseUrl}/quitar`, { titular, secundario }, o), { preserveScroll: true })
}
function visible(esc) {
  const f = filtro.value.trim().toLowerCase()
  if (!f) return true
  return esc.nombre.toLowerCase().includes(f) || String(esc.id) === f || esc.hijos.some((h) => h.nombre.toLowerCase().includes(f) || String(h.id) === f)
}
const fieldBase =
  'w-full rounded-lg border border-gray-300 bg-gray-50 py-2 px-3 text-sm text-gray-800 transition focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20'
</script>

<template>
  <div>
    <nav class="flex items-center gap-2 text-sm text-gray-500 mb-3">
      <Link href="/app" class="hover:text-gray-700">Inicio</Link>
      <span>/</span>
      <span class="font-medium" style="color: #ff9900">Configuración</span>
      <span>/</span>
      <span>Usuarios</span>
      <span>/</span>
      <span class="text-gray-900 font-semibold">Escritorios</span>
    </nav>

    <div class="mb-6">
      <h1 class="text-2xl font-bold text-gray-900">Escritorios</h1>
      <p class="text-gray-500">
        {{ resumen.escritorios }} escritorio{{ resumen.escritorios === 1 ? '' : 's' }} · {{ resumen.relaciones }} relaciones · {{ resumen.personas }} personas
        <span v-if="resumen.huerfanas" class="text-red-600">· {{ resumen.huerfanas }} referencias a usuarios inexistentes</span>
      </p>
    </div>

    <div class="rounded-md border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 mb-4">
      Un escritorio es un titular con sus secundarios: define qué files ve el titular (permiso "sólo files del usuario") y el combo "escritorio" al crear reservas.
      La relación <b>no es transitiva</b>: si A es titular de B y de C, B ve a A pero no a C; por eso hay pares recíprocos (↔).
      <span v-if="!aplica" class="block mt-1">En esta licencia la tabla se ve pero no gobierna el scoping de files (sólo mundotour_sdg y witwan_rays).</span>
      <span v-if="!puedeEscribir" class="block mt-1 font-medium">La escritura está deshabilitada (sysconfig.escritorios_abm ≠ 1).</span>
    </div>

    <form v-if="puedeEscribir" class="card mb-4" @submit.prevent="agregar">
      <div class="card-header"><h3 class="card-title">Agregar relación</h3></div>
      <div class="card-body grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
        <div>
          <label class="form-label">Titular</label>
          <select v-model="form.titular" :class="fieldBase">
            <option value="">--</option>
            <option v-for="u in usuarios" :key="u.id" :value="u.id">{{ u.nombre }} (#{{ u.id }}){{ u.habilitado ? '' : ' · deshabilitado' }}</option>
          </select>
        </div>
        <div>
          <label class="form-label">Secundario</label>
          <select v-model="form.secundario" :class="fieldBase">
            <option value="">--</option>
            <option v-for="u in usuarios" :key="u.id" :value="u.id">{{ u.nombre }} (#{{ u.id }}){{ u.habilitado ? '' : ' · deshabilitado' }}</option>
          </select>
        </div>
        <label class="flex items-center gap-2 text-sm h-10"><input type="checkbox" v-model="form.inversa" /> Crear también la inversa (↔)</label>
        <button type="submit" class="btn btn-primary" :disabled="enviando || !form.titular || !form.secundario">Agregar</button>
      </div>
    </form>

    <div class="card">
      <div class="card-header">
        <h3 class="card-title">Árbol de escritorios</h3>
        <input v-model="filtro" type="text" placeholder="Filtrar por nombre o id…" class="rounded-md border border-gray-300 px-3 py-1 text-sm" />
      </div>
      <div class="divide-y divide-gray-200">
        <div v-for="esc in arbol" v-show="visible(esc)" :key="esc.id" :id="`esc-${esc.id}`" class="px-4 py-3">
          <div class="font-semibold text-gray-900">
            <span class="text-gray-400 mr-1">#{{ esc.id }}</span>
            <span :class="{ 'text-red-600': !esc.existe, 'line-through text-gray-400': esc.existe && !esc.habilitado }">{{ esc.nombre || 'usuario inexistente' }}</span>
            <span class="text-xs text-gray-500 font-normal ml-2">{{ esc.hijos.length }} secundario{{ esc.hijos.length === 1 ? '' : 's' }}</span>
          </div>
          <ul class="mt-1 ml-6 space-y-0.5 text-sm">
            <li v-for="h in esc.hijos" :key="h.id" class="flex items-center gap-2">
              <span class="text-gray-400">#{{ h.id }}</span>
              <span :class="{ 'text-red-600': !h.existe, 'line-through text-gray-400': h.existe && !h.habilitado }">{{ h.nombre || 'usuario inexistente' }}</span>
              <span v-if="h.reciproco" class="badge badge-info" title="Relación recíproca">↔</span>
              <span v-if="h.es_titular" class="badge badge-gray" title="También es titular de un escritorio">titular</span>
              <span v-if="h.tipo !== 1" class="badge badge-warning">tipo {{ h.tipo }}</span>
              <button v-if="puedeEscribir" type="button" class="text-xs text-red-600 hover:underline ml-2" :disabled="enviando" @click="quitar(esc.id, h.id)">quitar</button>
            </li>
          </ul>
        </div>
        <div v-if="arbol.length === 0" class="px-4 py-12 text-center text-gray-500">No hay escritorios cargados.</div>
      </div>
    </div>
  </div>
</template>
