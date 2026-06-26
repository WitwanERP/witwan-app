<script setup>
import { Link } from '@inertiajs/vue3'

const props = defineProps({
  registros: { type: Object, required: true },
  config: { type: Object, required: true },
})
const emit = defineEmits(['resumen'])

function badgeFacturado(r) {
  if (props.config.flags.facturado_med) {
    return r.facturado_med ? { txt: 'FACTURADO', cls: 'badge-success' } : null
  }
  if (r.srvpendientes === 0 && r.srvfacturados > 0) return { txt: 'FACTURADO', cls: 'badge-success' }
  if (r.srvpendientes > 0 && r.srvfacturados > 0) return { txt: 'FACTURADO PARCIAL', cls: 'badge-danger' }
  return null
}

function num(v) {
  return new Intl.NumberFormat('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(v ?? 0)
}

function plano(html) {
  return (html || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim()
}

const editarUrl = (id) => `/reserva/editar/${id}`
const gatewayUrl = (id) => `/reserva/gateway/${id}`
</script>

<template>
  <div class="card">
    <div class="overflow-x-auto">
      <table class="min-w-full divide-y divide-gray-200 text-sm">
        <thead class="bg-gray-50">
          <tr class="text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
            <th class="px-3 py-3">#</th>
            <th class="px-3 py-3">Status</th>
            <th v-if="config.interno" class="px-3 py-3">Cliente / Vendedor</th>
            <th class="px-3 py-3">Titular</th>
            <th class="px-3 py-3">Productos</th>
            <th class="px-3 py-3">Alta / Vto.</th>
            <th class="px-3 py-3">In</th>
            <th class="px-3 py-3">Out</th>
            <th class="px-3 py-3 text-right">Neto / Saldo</th>
            <th v-if="config.interno" class="px-3 py-3 text-right">Acciones</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 bg-white">
          <tr v-for="r in registros.data" :key="r.id" class="hover:bg-gray-50 align-top">
            <!-- # código + badges -->
            <td class="px-3 py-3 whitespace-nowrap">
              <a :href="editarUrl(r.id)" class="font-semibold text-blue-600 hover:text-blue-800">{{ r.codigo }}</a>
              <div v-if="config.flags.codigo_externo_visible && r.codigo_externo" class="text-xs text-gray-500">{{ r.codigo_externo }}</div>
              <div class="mt-1 flex flex-wrap gap-1">
                <span v-if="badgeFacturado(r)" class="badge" :class="badgeFacturado(r).cls">{{ badgeFacturado(r).txt }}</span>
                <span v-if="r.problemas" class="text-red-600 cursor-help" :title="plano(r.problemas)">⚠</span>
                <span v-if="r.vemitido" class="text-green-600" title="Vouchers emitidos">🖨</span>
              </div>
              <div v-if="r.padre" class="mt-1 text-xs text-gray-500">
                Padre: <a :href="editarUrl(r.padre.id)" class="text-blue-600">{{ r.padre.codigo }}</a>
              </div>
              <div v-if="r.relacionados && r.relacionados.length" class="text-xs text-gray-500">
                Hijos:
                <a v-for="h in r.relacionados" :key="h.id" :href="editarUrl(h.id)" class="text-blue-600 mr-1">{{ h.codigo }}</a>
              </div>
            </td>

            <!-- Status -->
            <td class="px-3 py-3 whitespace-nowrap">
              <span class="inline-flex items-center gap-1.5">
                <span class="inline-block h-2.5 w-2.5 rounded-full" :style="{ backgroundColor: '#' + r.color }"></span>
                {{ r.status }}
              </span>
            </td>

            <!-- Cliente / Vendedor -->
            <td v-if="config.interno" class="px-3 py-3">
              <div class="font-medium text-gray-800">{{ r.cliente_nombre }}</div>
              <div class="text-xs text-gray-500">{{ r.nagente }}</div>
            </td>

            <!-- Titular -->
            <td class="px-3 py-3">
              <div>{{ r.titular }}</div>
              <div class="text-xs text-gray-500">{{ r.maxpax }}</div>
            </td>

            <!-- Productos -->
            <td class="px-3 py-3">
              <div v-for="(linea, i) in r.serviciostxt" :key="i" class="text-xs text-gray-600">{{ linea }}</div>
            </td>

            <!-- Alta / Vto -->
            <td class="px-3 py-3 whitespace-nowrap">
              <div>{{ r.fecha_alta }}</div>
              <div class="text-xs"><span class="badge badge-success">{{ r.fecha_vencimiento }}</span></div>
              <div v-if="r.historial_date" class="text-xs text-red-600">Cancelado: {{ r.historial_date }}</div>
            </td>

            <td class="px-3 py-3 whitespace-nowrap">{{ r.fecha_in }}</td>
            <td class="px-3 py-3 whitespace-nowrap">{{ r.fecha_out }}</td>

            <!-- Neto / Saldo -->
            <td class="px-3 py-3 text-right whitespace-nowrap">
              <div>{{ r.moneda }} {{ num(r.total) }}</div>
              <div class="text-xs">
                <span class="badge" :class="r.saldo > 0 ? 'badge-danger' : 'badge-success'">Saldo {{ num(r.saldo) }}</span>
              </div>
              <a v-if="config.flags.pago && r.saldo > 0" :href="gatewayUrl(r.id)" class="mt-1 inline-block text-xs text-blue-600 hover:underline">Pagar Online</a>
            </td>

            <!-- Acciones -->
            <td v-if="config.interno" class="px-3 py-3 text-right whitespace-nowrap">
              <button type="button" class="text-blue-600 hover:text-blue-800 text-xs font-medium" @click="emit('resumen', r.id)">Resumen</button>
            </td>
          </tr>

          <tr v-if="registros.data.length === 0">
            <td colspan="10" class="px-4 py-12 text-center text-gray-500">No se encontraron reservas.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="registros.total > 0" class="card-footer flex items-center justify-between flex-wrap gap-3">
      <p class="text-sm text-gray-600">
        Mostrando <span class="font-medium">{{ registros.from }}</span>–<span class="font-medium">{{ registros.to }}</span>
        de <span class="font-medium">{{ registros.total }}</span>
      </p>
      <div class="flex flex-wrap gap-1">
        <component
          :is="link.url ? Link : 'span'"
          v-for="(link, i) in registros.links"
          :key="i"
          :href="link.url || undefined"
          preserve-scroll
          preserve-state
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
</template>
