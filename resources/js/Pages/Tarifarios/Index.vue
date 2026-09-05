<script setup>
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { useEnvio } from '@/lib/envio'
import { formatearImporte } from '@/lib/formato'

defineOptions({ layout: AppLayout })

const props = defineProps({
  config: { type: Object, required: true },
  registros: { type: Array, default: () => [] },
})

const { enviando, enviar } = useEnvio()

function eliminar(t) {
  if (!window.confirm(`¿Eliminar el tarifario "${t.tarifario_nombre}" y sus comisiones?`)) return
  enviar((op) => router.delete(`${props.config.baseUrl}/${t.tarifario_id}`, op), { preserveScroll: true })
}
</script>

<template>
  <div>
    <div class="mb-6 flex items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Tarifarios <span class="text-base font-normal capitalize text-gray-400">· {{ config.sistema }}</span></h1>
        <p class="text-gray-500">Listas de precios con su markup y comisión general.</p>
      </div>
      <Link v-if="config.permisos.alta" :href="`${config.baseUrl}/create`" class="btn btn-primary">Nuevo tarifario</Link>
    </div>
    <div class="card">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
          <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
            <tr><th class="px-4 py-3 text-left">ID</th><th class="px-4 py-3 text-left">Nombre</th><th class="px-4 py-3 text-left">Moneda</th><th class="px-4 py-3 text-right">Cotización</th><th class="px-4 py-3 text-right">Divisor markup</th><th class="px-4 py-3 text-right">Comisión %</th><th class="px-4 py-3 text-center">Interno</th><th class="px-4 py-3 text-right">Acciones</th></tr>
          </thead>
          <tbody class="divide-y divide-gray-200 bg-white">
            <tr v-for="t in registros" :key="t.tarifario_id" class="hover:bg-gray-50">
              <td class="px-4 py-2 tabular-nums text-gray-500">{{ t.tarifario_id }}</td>
              <td class="px-4 py-2 font-medium">{{ t.tarifario_nombre }}</td>
              <td class="px-4 py-2">{{ t.fk_moneda_id }}</td>
              <td class="px-4 py-2 text-right tabular-nums">{{ Number(t.cotizacion) ? formatearImporte(t.cotizacion, 4) : '—' }}</td>
              <td class="px-4 py-2 text-right tabular-nums">{{ t.divisor_markup ?? '—' }}</td>
              <td class="px-4 py-2 text-right tabular-nums">{{ t.porcentaje_comision ?? '—' }}</td>
              <td class="px-4 py-2 text-center">{{ Number(t.interno) ? 'Sí' : '' }}</td>
              <td class="whitespace-nowrap px-4 py-2 text-right">
                <Link :href="`${config.baseUrl}/${t.tarifario_id}/edit`" class="text-sm font-medium text-blue-600 hover:text-blue-800">Editar</Link>
                <button v-if="config.permisos.borrado" type="button" class="ml-3 text-sm font-medium text-red-600 hover:text-red-800 disabled:opacity-50" :disabled="enviando" @click="eliminar(t)">Eliminar</button>
              </td>
            </tr>
            <tr v-if="registros.length === 0"><td colspan="8" class="px-4 py-12 text-center text-gray-500">Sin tarifarios en este sistema.</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
