<script setup>
import { formatearImporte } from '@/lib/formato'
import LineaCarrito from './LineaCarrito.vue'
import Nomina from './Nomina.vue'
import { useGenerador } from './useGenerador'

/** Paso 3: servicios elegidos con margen por línea y total en la moneda del file, más la nómina única. */
const g = useGenerador()
const { estado, totales } = g
function manual() {
  g.agregarLinea({ _abierto: true })
}
</script>

<template>
  <div class="space-y-4">
    <div v-if="!estado.carrito.length" class="card">
      <div class="card-body text-sm text-gray-500 flex items-center justify-between gap-3 flex-wrap">
        <span>El carrito está vacío. Buscá productos o cargá un servicio manual.</span>
        <div class="flex gap-2">
          <button type="button" class="btn btn-secondary btn-sm" @click="g.irA('buscar')">Buscar productos</button>
          <button type="button" class="btn btn-secondary btn-sm" @click="manual">+ Servicio manual</button>
        </div>
      </div>
    </div>

    <LineaCarrito v-for="(l, i) in estado.carrito" :key="l._uid" :linea="l" :indice="i" />

    <div v-if="estado.carrito.length" class="card">
      <div class="card-body flex items-center justify-between gap-4 flex-wrap">
        <div class="flex gap-2">
          <button type="button" class="btn btn-secondary btn-sm" @click="g.irA('buscar')">+ Buscar más</button>
          <button type="button" class="btn btn-secondary btn-sm" @click="manual">+ Servicio manual</button>
        </div>
        <div class="text-sm text-gray-700 flex flex-wrap gap-x-5 gap-y-1 items-baseline">
          <span v-for="(t, m) in totales.porMoneda" :key="m" class="text-xs text-gray-500"><b class="text-gray-700">{{ m }}</b> venta {{ formatearImporte(t.venta) }} · costo {{ formatearImporte(t.costo) }}</span>
          <span class="text-base">
            <b>Total {{ totales.file.moneda }}</b>: <span class="tabular-nums font-semibold">{{ formatearImporte(totales.file.venta) }}</span>
            <span class="text-xs ml-2" :class="totales.file.margen < 0 ? 'text-red-600' : 'text-green-700'">margen {{ formatearImporte(totales.file.margen) }}</span>
          </span>
        </div>
      </div>
    </div>

    <Nomina />

    <div class="flex items-center justify-between">
      <button type="button" class="btn btn-secondary" @click="g.irA('buscar')">← Buscar</button>
      <button type="button" class="btn btn-primary" :disabled="!estado.carrito.length" @click="g.irA('confirmar')">Confirmar →</button>
    </div>
  </div>
</template>
