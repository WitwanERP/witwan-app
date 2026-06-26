<script setup>
defineProps({
  totales: { type: Object, default: () => ({}) },
  config: { type: Object, required: true },
})

function num(v) {
  return new Intl.NumberFormat('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(v ?? 0)
}
</script>

<template>
  <div v-if="Object.keys(totales).length" class="card mt-4">
    <div class="card-header"><h3 class="card-title">Totales de la página (por moneda)</h3></div>
    <div class="card-body">
      <table class="min-w-full text-sm">
        <thead>
          <tr class="text-left text-xs font-semibold uppercase text-gray-500">
            <th class="px-3 py-2">Moneda</th>
            <th class="px-3 py-2 text-right">Reservas</th>
            <th class="px-3 py-2 text-right">Total</th>
            <th class="px-3 py-2 text-right">Saldo</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(v, moneda) in totales" :key="moneda" class="border-t border-gray-100">
            <td class="px-3 py-2 font-medium">{{ moneda }}</td>
            <td class="px-3 py-2 text-right">{{ v.cant }}</td>
            <td class="px-3 py-2 text-right">{{ num(v.total) }}</td>
            <td class="px-3 py-2 text-right">
              <span class="badge" :class="v.saldo > 0 ? 'badge-danger' : 'badge-success'">{{ num(v.saldo) }}</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
