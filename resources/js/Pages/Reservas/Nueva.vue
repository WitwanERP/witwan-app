<script setup>
import { Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Stepper from './Generador/Stepper.vue'
import PasoContexto from './Generador/PasoContexto.vue'
import PasoBuscar from './Generador/PasoBuscar.vue'
import Carrito from './Generador/Carrito.vue'
import Confirmacion from './Generador/Confirmacion.vue'
import { crearGenerador } from './Generador/useGenerador'

defineOptions({ layout: AppLayout })

/**
 * Generador de reservas v2: asistente de venta en cuatro pasos.
 * El estado vive en useGenerador (provide/inject) y se persiste por área en
 * sessionStorage; el POST final es el contrato de GeneradorReservaService.
 */
const props = defineProps({
  area: { type: String, required: true },
  baseUrl: { type: String, required: true },
  idsistema: { type: Number, required: true },
  fechaMinima: { type: String, required: true },
  monedaDefault: { type: String, default: 'USD' },
  monedaBasica: { type: String, default: 'ARS' },
  cotizaciones: { type: Object, default: () => ({}) },
  statusFile: { type: String, default: 'CO' },
  esInterno: { type: Boolean, default: false },
  puedeForzarCredito: { type: Boolean, default: false },
  // [{ tipo, nombre, campos, requiere, pickup }] — tipos de producto con tarifas en el área.
  pestanas: { type: Array, default: () => [] },
  // { tipos, monedas, vendedores, escritorio, tiposPax }
  opciones: { type: Object, required: true },
})

const g = crearGenerador(props)
const { estado } = g
</script>

<template>
  <div>
    <nav class="flex items-center gap-2 text-sm text-gray-500 mb-3">
      <Link href="/app" class="hover:text-gray-700">Inicio</Link>
      <span>/</span>
      <Link :href="baseUrl" class="hover:text-gray-700">Reservas {{ area }}</Link>
      <span>/</span>
      <span class="text-gray-900 font-semibold">Nueva reserva</span>
    </nav>

    <div class="mb-4 flex items-start justify-between gap-4 flex-wrap">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Nueva reserva <span class="text-gray-400 font-normal text-lg">({{ area }})</span></h1>
        <p class="text-gray-500 text-sm">Cliente → productos → carrito → confirmar. El código, las cotizaciones y los totales los calcula el servidor al crear.</p>
      </div>
      <div class="flex items-center gap-2 text-sm">
        <span v-if="estado.restaurado" class="text-xs text-amber-700">Se restauró una venta en curso.</span>
        <button type="button" class="text-xs text-gray-500 hover:text-red-600 hover:underline" @click="g.reiniciar()">Empezar de nuevo</button>
        <Link :href="baseUrl" class="btn btn-secondary btn-sm">Cancelar</Link>
      </div>
    </div>

    <Stepper />

    <PasoContexto v-if="estado.paso === 'contexto'" />
    <PasoBuscar v-else-if="estado.paso === 'buscar'" />
    <Carrito v-else-if="estado.paso === 'carrito'" />
    <Confirmacion v-else-if="estado.paso === 'confirmar'" />
  </div>
</template>
