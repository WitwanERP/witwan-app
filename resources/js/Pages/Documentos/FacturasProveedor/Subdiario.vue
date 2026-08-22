<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import FiltrosFactura from './components/FiltrosFactura.vue'
import TablaFacturas from './components/TablaFacturas.vue'
import TotalesPorTipo from './components/TotalesPorTipo.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
  config: { type: Object, required: true },
  opciones: { type: Object, required: true },
  filtros: { type: Object, default: () => ({}) },
  registros: { type: Object, required: true },
  totales: { type: Object, default: () => ({ porTipo: [], general: null }) },
})

const hayFiltros = computed(() => Object.keys(props.filtros).length > 0)
const queryActual = computed(() => new URLSearchParams({ ...props.filtros, vista: 'subdiario' }).toString())

// El título del legacy incluye el rango de fechas consultado.
const subtitulo = computed(() => {
  const d = props.filtros.fecha_desde
  const h = props.filtros.fecha_hasta
  return d && h ? `${d} al ${h}` : 'Aplicá un filtro para ver resultados'
})
</script>

<template>
  <div>
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Subdiario de Compras</h1>
        <p class="text-gray-500">{{ subtitulo }}</p>
      </div>
      <div class="flex flex-wrap gap-2">
        <Link :href="config.baseUrl" class="btn btn-secondary">Volver al listado</Link>
        <a v-if="hayFiltros" :href="`${config.baseUrl}/export?${queryActual}`" class="btn btn-secondary">
          Exportar CSV
        </a>
      </div>
    </div>

    <FiltrosFactura
      :filtros="filtros"
      :opciones="opciones"
      :base-url="config.baseUrl"
      :destino="`${config.baseUrl}/subdiario`"
    />

    <div v-if="!hayFiltros" class="card">
      <div class="card-body py-12 text-center text-gray-500">
        Ingresá al menos un filtro para ver el subdiario.
      </div>
    </div>

    <template v-else>
      <TablaFacturas :registros="registros" :config="config" vista="subdiario" />
      <TotalesPorTipo :totales="totales" vista="subdiario" :pais="config.pais" />
    </template>
  </div>
</template>
