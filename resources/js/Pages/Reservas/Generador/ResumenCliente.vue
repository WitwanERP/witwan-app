<script setup>
import { computed } from 'vue'
import { formatearFecha, formatearImporte } from '@/lib/formato'
import { useGenerador } from './useGenerador'

/**
 * Lo que el vendedor tiene que saber del cliente antes de cotizar: tarifario que
 * rige, crédito disponible y qué suele comprar (destinos, tipos, gasto medio).
 */
const { estado } = useGenerador()
const info = computed(() => estado.clienteInfo)
const credito = computed(() => info.value?.credito)
const h = computed(() => info.value?.historial)
const statusCls = (s) => ({ CO: 'badge-success', RQ: 'badge-warning', CL: 'badge-info', CA: 'badge-danger', AN: 'badge-danger' }[s] || 'badge-gray')
</script>

<template>
  <aside class="card">
    <div class="card-header"><h2 class="card-title">Ficha rápida</h2></div>
    <div v-if="estado.cargandoCliente" class="card-body text-sm text-gray-500">Cargando cliente…</div>
    <div v-else-if="!info" class="card-body text-sm text-gray-400">Elegí un cliente para ver su tarifario, crédito e historial.</div>
    <div v-else class="card-body space-y-4 text-sm">
      <div>
        <div class="font-semibold text-gray-900">{{ info.cliente.nombre }} <span class="text-gray-400 font-normal">#{{ info.cliente.id }}</span></div>
        <div v-if="info.cliente.pasajero_directo" class="text-xs text-gray-500">Pasajero directo</div>
      </div>

      <div class="rounded-md border px-3 py-2" :class="info.tarifario ? 'border-gray-200 bg-gray-50' : 'border-amber-300 bg-amber-50'">
        <div class="text-xs uppercase tracking-wide text-gray-500">Tarifario</div>
        <div v-if="info.tarifario" class="font-medium text-gray-900">{{ info.tarifario.nombre }} <span class="text-xs text-gray-500">({{ info.tarifario.moneda }})</span></div>
        <div v-else class="text-amber-800">Sin tarifario para esta área: los productos se cotizan al costo, sin markup del cliente.</div>
      </div>

      <div class="rounded-md border border-gray-200 bg-gray-50 px-3 py-2">
        <div class="text-xs uppercase tracking-wide text-gray-500">Crédito</div>
        <template v-if="credito.limite > 0">
          <div class="flex justify-between"><span>Límite</span><b class="tabular-nums">{{ formatearImporte(credito.limite) }} {{ credito.moneda }}</b></div>
          <template v-if="credito.habilitado">
            <div class="flex justify-between"><span>Utilizado</span><span class="tabular-nums">{{ formatearImporte(credito.utilizado) }}</span></div>
            <div class="flex justify-between" :class="credito.disponible < 0 ? 'text-red-700 font-semibold' : 'text-green-700 font-semibold'"><span>Disponible</span><span class="tabular-nums">{{ formatearImporte(credito.disponible) }}</span></div>
          </template>
          <div v-else class="text-xs text-gray-500">Sin control de crédito activo.</div>
        </template>
        <div v-else class="text-gray-500">Sin límite de crédito cargado.</div>
      </div>

      <div v-if="h">
        <div class="text-xs uppercase tracking-wide text-gray-500 mb-1">Últimos 12 meses</div>
        <div class="grid grid-cols-2 gap-2 mb-2">
          <div class="rounded-md bg-blue-50 px-2 py-1"><div class="text-xs text-blue-700">Reservas</div><div class="font-semibold tabular-nums">{{ h.reservas }}</div></div>
          <div class="rounded-md bg-blue-50 px-2 py-1"><div class="text-xs text-blue-700">Gasto promedio</div><div class="font-semibold tabular-nums">{{ formatearImporte(h.gasto_promedio.valor, 0) }} <span class="text-xs">{{ h.gasto_promedio.moneda }}</span></div></div>
        </div>
        <div v-if="h.destinos_top.length" class="mb-2">
          <div class="text-xs text-gray-500">Destinos que compra</div>
          <div class="flex flex-wrap gap-1 mt-1"><span v-for="d in h.destinos_top" :key="d.ciudad_id" class="badge badge-gray">{{ d.nombre }} <span class="ml-1 text-gray-500">×{{ d.n }}</span></span></div>
        </div>
        <div v-if="h.tipos_top.length" class="mb-2">
          <div class="text-xs text-gray-500">Tipos de producto</div>
          <div class="flex flex-wrap gap-1 mt-1"><span v-for="t in h.tipos_top" :key="t.tipo" class="badge badge-info">{{ t.nombre }} <span class="ml-1">×{{ t.n }}</span></span></div>
        </div>
        <div v-if="h.ultimas.length">
          <div class="text-xs text-gray-500 mb-1">Últimas reservas</div>
          <ul class="divide-y divide-gray-100">
            <li v-for="r in h.ultimas" :key="r.reserva_id" class="py-1 flex items-start justify-between gap-2">
              <div>
                <div class="font-medium text-gray-800">{{ r.codigo }} <span class="badge ml-1" :class="statusCls(r.status)">{{ r.status }}</span></div>
                <div class="text-xs text-gray-500">{{ r.titular }}<span v-if="r.destinos.length"> · {{ r.destinos.join(', ') }}</span></div>
                <div class="text-xs text-gray-400">alta {{ formatearFecha(r.fecha_alta) }}<span v-if="r.inicio && r.inicio !== '0000-00-00'"> · viaja {{ formatearFecha(r.inicio) }}</span></div>
              </div>
              <div class="text-right tabular-nums whitespace-nowrap">{{ formatearImporte(r.total) }} <span class="text-xs text-gray-500">{{ r.moneda }}</span></div>
            </li>
          </ul>
        </div>
        <div v-else class="text-xs text-gray-400">Sin reservas previas.</div>
      </div>
    </div>
  </aside>
</template>
