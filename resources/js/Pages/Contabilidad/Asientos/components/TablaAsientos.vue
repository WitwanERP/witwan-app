<script setup>
import { computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { formatearImporte, formatearFecha } from '@/lib/formato'
import { useEnvio } from '@/lib/envio'

const props = defineProps({
  registros: { type: Object, required: true },
  config: { type: Object, required: true },
})

const columnas = computed(() => {
  const cols = [
    { key: 'numero', label: 'Número' },
    { key: 'fecha', label: 'Fecha', tipo: 'fecha' },
    { key: 'usuario', label: 'Usuario' },
    { key: 'moneda', label: 'Mon.' },
    { key: 'monto', label: 'Monto', tipo: 'importe', destacada: true },
    { key: 'movimientos', label: 'Mov.', tipo: 'numero' },
    { key: 'estado', label: 'Estado' },
    { key: 'observaciones', label: 'Obs.' },
  ]

  if (props.config.usaProyecto) cols.splice(3, 0, { key: 'proyecto', label: 'Proyecto' })

  return cols
})

/**
 * Anular va por POST y con confirmación. En el CI era un `<a href>` a
 * `/anular/{id}`: un prefetch del navegador o un click accidental anulaban el
 * asiento sin preguntar nada.
 */
const { enviando, enviar } = useEnvio()

function anular(fila) {
  const ok = window.confirm(
    `¿Anular el asiento N° ${fila.numero} del ${formatearFecha(fila.fecha)} por ${fila.moneda} ${formatearImporte(fila.monto)}?`
  )

  if (!ok) return

  enviar((opciones) => router.post(`${props.config.baseUrl}/${fila.id}/anular`, {}, opciones), {
    preserveScroll: true,
  })
}
</script>

<template>
  <div class="card">
    <!-- La tabla scrollea dentro de su contenedor: la página nunca scrollea
         horizontalmente. -->
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-600">
          <tr>
            <th v-for="c in columnas" :key="c.key" class="whitespace-nowrap px-3 py-2 text-left font-semibold">
              {{ c.label }}
            </th>
            <th class="px-3 py-2 text-right font-semibold">Acciones</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr
            v-for="fila in registros.data"
            :key="fila.id"
            class="hover:bg-blue-50/40"
            :class="fila.anulado ? 'text-gray-400' : ''"
          >
            <td
              v-for="c in columnas"
              :key="c.key"
              class="px-3 py-2"
              :class="[
                c.tipo === 'importe' || c.tipo === 'numero' ? 'text-right tabular-nums' : '',
                c.key === 'observaciones' ? 'max-w-xs truncate' : 'whitespace-nowrap',
                c.destacada && !fila.anulado ? 'font-semibold text-gray-900' : '',
                fila.anulado ? 'line-through decoration-gray-400' : '',
              ]"
              :title="c.key === 'observaciones' ? fila.observaciones : undefined"
            >
              <template v-if="c.tipo === 'importe'">{{ formatearImporte(fila[c.key]) }}</template>
              <template v-else-if="c.tipo === 'fecha'">{{ formatearFecha(fila[c.key]) }}</template>
              <template v-else-if="c.key === 'estado'">
                <span
                  class="badge"
                  :class="{
                    'badge-danger': fila.status === 'AN',
                    'badge-info': fila.status === 'PR',
                    'badge-success': fila.status === 'OK',
                  }"
                  >{{ fila.estado }}</span
                >
              </template>
              <template v-else>{{ fila[c.key] || '—' }}</template>
            </td>

            <td class="whitespace-nowrap px-3 py-2 text-right">
              <Link :href="`${config.baseUrl}/${fila.id}`" class="btn btn-sm btn-secondary">Ver</Link>
              <Link
                v-if="config.permisos.edicion && !fila.anulado"
                :href="`${config.baseUrl}/${fila.id}/edit`"
                class="btn btn-sm btn-secondary ml-1"
                >Editar</Link
              >
              <button
                v-if="config.permisos.borrado && !fila.anulado"
                type="button"
                class="btn btn-sm btn-secondary ml-1 text-red-600 disabled:opacity-50"
                :disabled="enviando"
                @click="anular(fila)"
              >
                Anular
              </button>
            </td>
          </tr>

          <tr v-if="!registros.data.length">
            <td :colspan="columnas.length + 1" class="px-3 py-8 text-center text-gray-500">
              No hay asientos para los filtros aplicados.
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="registros.links && registros.links.length > 3" class="card-footer flex flex-wrap items-center gap-2">
      <span class="text-sm text-gray-500">
        Mostrando {{ registros.from }}–{{ registros.to }} de {{ registros.total }}
      </span>
      <div class="ml-auto flex flex-wrap gap-1">
        <component
          :is="link.url ? 'Link' : 'span'"
          v-for="(link, i) in registros.links"
          :key="i"
          :href="link.url || undefined"
          preserve-scroll
          class="rounded px-3 py-1 text-sm"
          :class="link.active ? 'bg-blue-600 text-white' : link.url ? 'text-gray-700 hover:bg-gray-100' : 'text-gray-300'"
          v-html="link.label"
        />
      </div>
    </div>
  </div>
</template>
