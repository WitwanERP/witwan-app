<script setup>
import { reactive, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import FiltrosReserva from './components/FiltrosReserva.vue'
import TablaReservas from './components/TablaReservas.vue'
import TotalesMoneda from './components/TotalesMoneda.vue'
import ModalResumen from './components/ModalResumen.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
  config: { type: Object, required: true },
  opciones: { type: Object, required: true },
  registros: { type: Object, required: true },
  totales: { type: Object, default: () => ({}) },
  filtros: { type: Object, default: () => ({}) },
})

// Estado del formulario de filtros (inicializado desde los filtros aplicados).
const form = reactive({
  codigo: props.filtros.codigo ?? '',
  tipo: toArray(props.filtros.tipo),
  status: toArray(props.filtros.status),
  cliente: props.filtros.cliente ?? '',
  clienteLabel: '',
  vendedor: props.filtros.vendedor ?? '',
  usuario: props.filtros.usuario ?? '',
  responsable: props.filtros.responsable ?? '',
  proveedor: props.filtros.proveedor ?? '',
  prestador: props.filtros.prestador ?? '',
  cadena: props.filtros.cadena ?? '',
  representante: props.filtros.representante ?? '',
  cadenacliente: props.filtros.cadenacliente ?? '',
  operativo: props.filtros.operativo ?? '',
  negocio: props.filtros.negocio ?? '',
  titular: props.filtros.titular ?? '',
  ticket: props.filtros.ticket ?? '',
  recloc: props.filtros.recloc ?? '',
  factura: props.filtros.factura ?? '',
  nro_confirmacion: props.filtros.nro_confirmacion ?? '',
  codigo_externo: props.filtros.codigo_externo ?? '',
  tipoproducto: props.filtros.tipoproducto ?? '',
  tipofecha: props.filtros.tipofecha ?? 'alta',
  from: props.filtros.from ?? '',
  to: props.filtros.to ?? '',
  facturafrom: props.filtros.facturafrom ?? '',
  facturato: props.filtros.facturato ?? '',
  residente: props.filtros.residente ?? '',
  solopagos: props.filtros.solopagos ?? '',
  solofacturado: props.filtros.solofacturado ?? '',
  soloocultas: props.filtros.soloocultas ?? '',
  soloovencidas: props.filtros.soloovencidas ?? '',
  mostrarreprogramados: props.filtros.mostrarreprogramados ?? '',
  auditado: props.filtros.auditado ?? '',
})

function toArray(v) {
  if (Array.isArray(v)) return v
  if (v === undefined || v === null || v === '') return []
  return String(v).split('-')
}

function aplicar() {
  const params = {}
  for (const [k, v] of Object.entries(form)) {
    if (k === 'clienteLabel') continue
    if (Array.isArray(v)) {
      if (v.length) params[k] = v
    } else if (v !== '' && v !== null) {
      params[k] = v
    }
  }
  router.get(props.config.baseUrl, params, { preserveState: true, preserveScroll: true })
}

function limpiar() {
  router.get(props.config.baseUrl, {}, { preserveScroll: true })
}

// Modal resumen
const resumenAbierto = ref(false)
const resumenId = ref(null)
function abrirResumen(id) {
  resumenId.value = id
  resumenAbierto.value = true
}

function exportarUrl() {
  const params = new URLSearchParams()
  for (const [k, v] of Object.entries(form)) {
    if (k === 'clienteLabel') continue
    if (Array.isArray(v)) v.forEach((x) => params.append(`${k}[]`, x))
    else if (v !== '' && v !== null) params.append(k, v)
  }
  return `${props.config.baseUrl}/export?${params.toString()}`
}
</script>

<template>
  <div>
    <div class="mb-6 flex items-center justify-between gap-4 flex-wrap">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">{{ config.titulo }}</h1>
        <p class="text-gray-500">
          {{ registros.total }} reserva{{ registros.total === 1 ? '' : 's' }} · {{ config.area }}
        </p>
      </div>
      <div class="flex items-center gap-2">
        <a :href="exportarUrl()" class="btn btn-secondary btn-sm">Exportar CSV</a>
      </div>
    </div>

    <FiltrosReserva
      :form="form"
      :config="config"
      :opciones="opciones"
      @aplicar="aplicar"
      @limpiar="limpiar"
    />

    <TablaReservas
      class="mt-4"
      :registros="registros"
      :config="config"
      @resumen="abrirResumen"
    />

    <TotalesMoneda :totales="totales" :config="config" />

    <ModalResumen
      v-if="resumenAbierto"
      :id="resumenId"
      :base-url="config.baseUrl"
      @cerrar="resumenAbierto = false"
    />
  </div>
</template>
