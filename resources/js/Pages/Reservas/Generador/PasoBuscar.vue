<script setup>
import { useGenerador } from './useGenerador'

/**
 * Paso 2: búsqueda por tipo de producto. Cada tipo tiene su formulario propio
 * (hotel por noches y habitaciones, traslado por origen/destino, excursión por
 * día…), aunque todo termine como una fila de `servicio`. Las pestañas las
 * decide el servidor según qué tipos tienen productos habilitados en el área.
 */
const { props, estado, agregarLinea, irA } = useGenerador()

function servicioManual() {
  agregarLinea({ _abierto: true })
  irA('carrito')
}
</script>

<template>
  <div>
    <section class="card mb-4">
      <div class="card-header">
        <h2 class="card-title">Buscar productos</h2>
        <button type="button" class="text-xs text-blue-600 hover:underline" @click="servicioManual">+ Cargar un servicio manual (sin tarifa)</button>
      </div>
      <div v-if="!props.pestanas.length" class="card-body text-sm text-gray-500">
        No hay tipos de producto con tarifas cargadas para {{ props.area }}. Podés cargar servicios manuales en el carrito.
      </div>
      <div v-else class="card-body text-sm text-gray-500">La búsqueda por tipo de producto se habilita en la próxima etapa.</div>
    </section>

    <div class="flex items-center justify-between">
      <button type="button" class="btn btn-secondary" @click="irA('contexto')">← Cliente</button>
      <button type="button" class="btn btn-primary" @click="irA('carrito')">Carrito ({{ estado.carrito.length }}) →</button>
    </div>
  </div>
</template>
