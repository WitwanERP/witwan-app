<script setup>
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { useEnvio } from '@/lib/envio'
import { formatearFecha } from '@/lib/formato'

defineOptions({ layout: AppLayout })

const props = defineProps({
  producto: { type: Object, required: true },
  vigencias: { type: Array, default: () => [] },
  baseUrl: { type: String, required: true },
})

const DIAS = ['L', 'M', 'X', 'J', 'V', 'S', 'D']
const { enviando, enviar } = useEnvio()

function eliminar(v) {
  if (!window.confirm(`¿Eliminar la vigencia "${v.vigencia_descripcion || '#' + v.vigencia_id}" con sus ${v.tarifas} tarifas?`)) return
  enviar((op) => router.delete(`${props.baseUrl}/${v.vigencia_id}`, op), { preserveScroll: true })
}
</script>

<template>
  <div>
    <nav class="mb-4 flex items-center gap-2 text-sm text-gray-500">
      <Link href="/app" class="hover:text-gray-700">Inicio</Link><span>/</span>
      <Link :href="producto.url" class="hover:text-gray-700">{{ producto.producto_nombre }}</Link><span>/</span>
      <span class="font-semibold text-gray-900">Vigencias</span>
    </nav>
    <div class="mb-6 flex items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Vigencias y tarifas</h1>
        <p class="text-gray-500">{{ producto.producto_nombre }} · {{ vigencias.length }} vigencia{{ vigencias.length === 1 ? '' : 's' }}</p>
      </div>
      <Link :href="`${baseUrl}/create`" class="btn btn-primary">Nueva vigencia</Link>
    </div>

    <div class="card">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
          <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
            <tr>
              <th class="px-4 py-3 text-left">Descripción</th>
              <th class="px-4 py-3 text-left">Estadía</th>
              <th class="px-4 py-3 text-left">Venta</th>
              <th class="px-4 py-3 text-left">Días</th>
              <th class="px-4 py-3 text-right">Prior.</th>
              <th class="px-4 py-3 text-left">Res.</th>
              <th class="px-4 py-3 text-left">Moneda</th>
              <th class="px-4 py-3 text-right">Tarifas</th>
              <th class="px-4 py-3 text-right">Acciones</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 bg-white">
            <tr v-for="v in vigencias" :key="v.vigencia_id" class="hover:bg-gray-50">
              <td class="px-4 py-2"><Link :href="`${baseUrl}/${v.vigencia_id}/edit`" class="font-medium text-blue-600 hover:underline">{{ v.vigencia_descripcion || `#${v.vigencia_id}` }}</Link><span v-if="v.cargamanual == 1" class="ml-2 rounded bg-amber-100 px-1.5 py-0.5 text-[10px] uppercase text-amber-800">manual</span></td>
              <td class="whitespace-nowrap px-4 py-2 tabular-nums">{{ formatearFecha(v.vigencia_ini) }} – {{ formatearFecha(v.vigencia_fin) }}</td>
              <td class="whitespace-nowrap px-4 py-2 tabular-nums text-gray-500">{{ v.vigencia_ventaini && v.vigencia_ventaini !== '0000-00-00' ? `${formatearFecha(v.vigencia_ventaini)} – ${formatearFecha(v.vigencia_ventafin)}` : '—' }}</td>
              <td class="px-4 py-2 font-mono text-xs"><span v-for="(d, i) in DIAS" :key="i" :class="v.dias_semana.includes(i + 1) ? 'text-gray-800' : 'text-gray-300'">{{ d }}</span></td>
              <td class="px-4 py-2 text-right tabular-nums">{{ v.vigencia_prioridad }}</td>
              <td class="px-4 py-2">{{ { '': 'Todos', R: 'Res.', O: 'No res.' }[v.residente] ?? v.residente }}</td>
              <td class="px-4 py-2">{{ v.moneda_costo || '—' }}</td>
              <td class="px-4 py-2 text-right tabular-nums">{{ v.tarifas }}</td>
              <td class="whitespace-nowrap px-4 py-2 text-right">
                <Link :href="`${baseUrl}/${v.vigencia_id}/edit`" class="text-sm font-medium text-blue-600 hover:text-blue-800">Editar</Link>
                <button type="button" class="ml-3 text-sm font-medium text-red-600 hover:text-red-800 disabled:opacity-50" :disabled="enviando" @click="eliminar(v)">Eliminar</button>
              </td>
            </tr>
            <tr v-if="vigencias.length === 0"><td colspan="9" class="px-4 py-12 text-center text-gray-500">El producto no tiene vigencias cargadas.</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
