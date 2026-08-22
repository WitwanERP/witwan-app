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

const queryActual = computed(() => new URLSearchParams(props.filtros).toString())
</script>

<template>
  <div>
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Facturas de Terceros</h1>
        <p class="text-gray-500">
          <template v-if="hayFiltros">{{ registros.total }} factura{{ registros.total === 1 ? '' : 's' }}</template>
          <template v-else>Aplicá un filtro para ver resultados</template>
        </p>
      </div>

      <div class="flex flex-wrap gap-2">
        <Link :href="`${config.baseUrl}/subdiario`" class="btn btn-secondary">Subdiario de compras</Link>
        <a
          v-if="hayFiltros"
          :href="`${config.baseUrl}/export?${queryActual}`"
          class="btn btn-secondary"
          >Exportar CSV</a
        >
        <Link v-if="config.permisos.alta" :href="`${config.baseUrl}/multiple`" class="btn btn-secondary">
          Carga múltiple
        </Link>
        <Link v-if="config.permisos.alta" :href="`${config.baseUrl}/create`" class="btn btn-primary">
          Nueva factura
        </Link>
      </div>
    </div>

    <FiltrosFactura
      :filtros="filtros"
      :opciones="opciones"
      :base-url="config.baseUrl"
      :destino="config.baseUrl"
    />

    <!-- Fiel al legacy: sin filtros no se consulta. El listado abarca decenas de
         miles de facturas y barrerlo entero no le sirve a nadie. -->
    <div v-if="!hayFiltros" class="card">
      <div class="card-body py-12 text-center text-gray-500">
        Ingresá al menos un filtro para ver el listado.
      </div>
    </div>

    <template v-else>
      <TablaFacturas :registros="registros" :config="config" vista="listado" />
      <TotalesPorTipo :totales="totales" vista="listado" :pais="config.pais" />
    </template>
  </div>
</template>
