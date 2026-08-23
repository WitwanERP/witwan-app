<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import FiltrosAsiento from './components/FiltrosAsiento.vue'
import TablaAsientos from './components/TablaAsientos.vue'
import TotalesPorMoneda from './components/TotalesPorMoneda.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
  config: { type: Object, required: true },
  opciones: { type: Object, required: true },
  filtros: { type: Object, default: () => ({}) },
  registros: { type: Object, required: true },
  totales: { type: Object, default: () => ({ porMoneda: [], cantidad: 0 }) },
})

const hayFiltros = computed(() => Object.keys(props.filtros).length > 0)

const queryActual = computed(() => new URLSearchParams(props.filtros).toString())
</script>

<template>
  <div>
    <!-- Los tres tipos son la misma tabla discriminada por `ordenadmin.tipo`.
         En el CI son tres ítems separados del menú y para saltar de uno a otro
         había que volver a la botonera. -->
    <nav class="mb-4 flex flex-wrap gap-1 border-b border-gray-200">
      <Link
        v-for="t in config.tipos"
        :key="t.slug"
        :href="t.url"
        class="-mb-px border-b-2 px-4 py-2 text-sm font-medium"
        :class="
          t.activo
            ? 'border-blue-600 text-blue-700'
            : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'
        "
      >
        {{ t.titulo }}
      </Link>
    </nav>

    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">{{ config.titulo }}</h1>
        <p class="text-gray-500">
          <template v-if="hayFiltros">
            {{ registros.total }} asiento{{ registros.total === 1 ? '' : 's' }}
          </template>
          <template v-else>Aplicá un filtro para ver resultados</template>
        </p>
      </div>

      <div class="flex flex-wrap gap-2">
        <a v-if="hayFiltros" :href="`${config.baseUrl}/export?${queryActual}`" class="btn btn-secondary">
          Exportar CSV
        </a>
        <Link v-if="config.permisos.alta" :href="`${config.baseUrl}/create`" class="btn btn-primary">
          Nuevo {{ config.singular }}
        </Link>
      </div>
    </div>

    <FiltrosAsiento :filtros="filtros" :opciones="opciones" :config="config" />

    <!-- Fiel al legacy (`_filterbefore = true`): sin filtros no se consulta.
         `ordenadmin` acumula años de asientos y barrerla entera no le sirve a
         nadie. -->
    <div v-if="!hayFiltros" class="card">
      <div class="card-body py-12 text-center text-gray-500">
        Ingresá al menos un filtro para ver el listado.
      </div>
    </div>

    <template v-else>
      <TablaAsientos :registros="registros" :config="config" />
      <TotalesPorMoneda :totales="totales" />
    </template>

    <p v-if="config.primeraFechaOperable" class="mt-4 text-xs text-gray-500">
      Período contable abierto desde el {{ config.primeraFechaOperable }} (último cierre de caja).
    </p>
  </div>
</template>
