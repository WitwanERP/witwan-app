<script setup>
import { formatearImporte } from '@/lib/formato'

/**
 * Pie de totales del listado.
 *
 * El legacy no totaliza nada en estos listados. Se agrega porque `ordenadmin`
 * mezcla monedas en la misma grilla, y por eso se totaliza POR MONEDA y no en
 * una sola cifra: `ordenadmin.monto` está expresado en la moneda del asiento y
 * no hay una cotización única con la que unificarlos.
 *
 * Los anulados se muestran aparte por el mismo motivo: siguen figurando en el
 * listado (tachados), así que sumarlos junto al resto daría un total que no
 * existe en la contabilidad.
 */
defineProps({
  totales: { type: Object, required: true },
})
</script>

<template>
  <div v-if="totales.porMoneda.length" class="card mt-4">
    <div class="card-header">
      <h3 class="card-title">Totales</h3>
      <span class="text-sm text-gray-500">{{ totales.cantidad }} asiento{{ totales.cantidad === 1 ? '' : 's' }}</span>
    </div>

    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-600">
          <tr>
            <th class="px-3 py-2 text-left font-semibold">Moneda</th>
            <th class="px-3 py-2 text-right font-semibold">Asientos</th>
            <th class="px-3 py-2 text-right font-semibold">Total vigente</th>
            <th class="px-3 py-2 text-right font-semibold">Total con anulados</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="t in totales.porMoneda" :key="t.moneda">
            <td class="px-3 py-2 font-medium text-gray-900">{{ t.moneda }}</td>
            <td class="px-3 py-2 text-right tabular-nums text-gray-700">{{ t.cantidad }}</td>
            <td class="px-3 py-2 text-right tabular-nums font-semibold text-gray-900">
              {{ formatearImporte(t.montoVigente) }}
            </td>
            <td class="px-3 py-2 text-right tabular-nums text-gray-500">{{ formatearImporte(t.monto) }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="card-footer text-xs text-gray-500">
      No se suma entre monedas: cada asiento está expresado en la suya y se valuó con la cotización de su fecha.
    </div>
  </div>
</template>
