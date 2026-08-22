<script setup>
import { h, computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { formatearImporte } from './calculo.js'

/**
 * Con ?embed=1 la página se renderiza sin layout, para poder incrustarla desde
 * otros módulos. Inertia acepta el layout como función, que es la forma de
 * decidirlo según las props.
 */
defineOptions({
  layout: (hh, page) => (page.props.embed ? page : h(AppLayout, () => page)),
})

const props = defineProps({
  detalle: { type: Object, required: true },
  embed: { type: Boolean, default: false },
  baseUrl: { type: String, required: true },
})

const f = computed(() => props.detalle.factura)

const fecha = (v) => (v && !String(v).startsWith('0000') ? String(v).slice(0, 10) : '—')

// Sólo se muestran los importes con valor: el legacy pintaba las 15 filas
// siempre, la mayoría en cero.
const importes = computed(() =>
  [
    ['Exento', f.value.montoexento],
    ['No computable', f.value.montonocomputable],
    ['Gravado 10,5%', f.value.montoespecial],
    ['Gravado', f.value.montogeneral],
    ['Gravado 27%', f.value.monto27],
    ['Gravado 2,5%', f.value.monto25],
    ['IVA', f.value.ivatotal],
    ['IVA turismo', f.value.ivatur],
    ['Retención IVA', f.value.retencioniva],
    ['Percepción IVA', f.value.percepcioniva],
    ['Retención IIBB', f.value.retencioniibb],
    ['Percepción IIBB', f.value.percepcioniibb],
    ['Retención Ganancias', f.value.retencionganancias],
    ['Percepción Ganancias', f.value.percepcionganancias],
    ['Otros impuestos', f.value.otrosimpuestos],
  ].filter(([, v]) => Number(v) !== 0)
)

// `window` no existe en el scope del template.
function imprimir() {
  window.print()
}

const adicionales = computed(() => Object.entries(props.detalle.adicionales || {}).filter(([, v]) => Number(v) !== 0))
const imputacion = computed(() => Object.entries(props.detalle.imputacion || {}).filter(([, v]) => Number(v) !== 0))
</script>

<template>
  <div class="mx-auto max-w-4xl">
    <div class="no-print mb-6 flex flex-wrap items-center justify-between gap-3">
      <h1 class="text-2xl font-bold text-gray-900">
        Factura de tercero {{ f.facturaproveedor_nro }}
      </h1>
      <div class="flex gap-2">
        <Link v-if="!embed" :href="baseUrl" class="btn btn-secondary">Volver</Link>
        <Link v-if="!embed" :href="`${baseUrl}/${f.facturaproveedor_id}/edit`" class="btn btn-secondary">
          Editar
        </Link>
        <button type="button" class="btn btn-primary" @click="imprimir">Imprimir</button>
      </div>
    </div>

    <div class="card">
      <div class="card-body">
        <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm md:grid-cols-3">
          <div>
            <dt class="text-gray-500">Proveedor</dt>
            <dd class="font-medium text-gray-900">{{ detalle.proveedor?.razonsocial || detalle.proveedor?.proveedor_nombre || '—' }}</dd>
          </div>
          <div>
            <dt class="text-gray-500">CUIT / RUT</dt>
            <dd class="font-medium text-gray-900">{{ detalle.proveedor?.cuit || '—' }}</dd>
          </div>
          <div>
            <dt class="text-gray-500">Tipo de documento</dt>
            <dd class="font-medium text-gray-900">
              {{ f.facturaproveedor_tipodocumento }} {{ f.facturaproveedor_tipofactura }}
            </dd>
          </div>
          <div>
            <dt class="text-gray-500">Fecha</dt>
            <dd class="font-medium text-gray-900">{{ fecha(f.fecha) }}</dd>
          </div>
          <div>
            <dt class="text-gray-500">Fecha contable</dt>
            <dd class="font-medium text-gray-900">{{ fecha(f.fechacontable) }}</dd>
          </div>
          <div>
            <dt class="text-gray-500">Vencimiento</dt>
            <dd class="font-medium text-gray-900">{{ fecha(f.vencimiento) }}</dd>
          </div>
          <div>
            <dt class="text-gray-500">Moneda</dt>
            <dd class="font-medium text-gray-900">{{ f.fk_moneda_id }} (cotiz. {{ formatearImporte(f.cotizacion, 4) }})</dd>
          </div>
          <div>
            <dt class="text-gray-500">Tipo de gasto</dt>
            <dd class="font-medium text-gray-900">{{ f.tipomovimiento }}</dd>
          </div>
          <div>
            <dt class="text-gray-500">Cuenta contable</dt>
            <dd class="font-medium text-gray-900">{{ detalle.cuentaContable || '—' }}</dd>
          </div>
          <div>
            <dt class="text-gray-500">Proyecto</dt>
            <dd class="font-medium text-gray-900">{{ detalle.proyecto || '—' }}</dd>
          </div>
          <div>
            <dt class="text-gray-500">Cargada por</dt>
            <dd class="font-medium text-gray-900">{{ detalle.usuario || '—' }}</dd>
          </div>
          <div>
            <dt class="text-gray-500">Fecha de carga</dt>
            <dd class="font-medium text-gray-900">{{ fecha(f.fechacarga) }}</dd>
          </div>
        </dl>

        <p v-if="f.descripcion" class="mt-4 rounded-lg bg-gray-50 p-3 text-sm text-gray-700">
          {{ f.descripcion }}
        </p>

        <a
          v-if="f.archivo"
          :href="`${baseUrl}/${f.facturaproveedor_id}/archivo`"
          class="no-print btn btn-secondary mt-4 inline-flex"
        >
          Descargar adjunto
        </a>
      </div>
    </div>

    <div class="card mt-4">
      <div class="card-header"><h3 class="card-title">Importes</h3></div>
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <tbody class="divide-y divide-gray-100">
            <tr v-for="[label, valor] in importes" :key="label">
              <td class="px-4 py-2 text-gray-600">{{ label }}</td>
              <td class="px-4 py-2 text-right tabular-nums text-gray-900">{{ formatearImporte(valor) }}</td>
            </tr>
            <tr class="bg-amber-50 font-semibold">
              <td class="px-4 py-2 text-gray-900">TOTAL</td>
              <td class="px-4 py-2 text-right tabular-nums text-gray-900">
                {{ f.fk_moneda_id }} {{ formatearImporte(f.montototal) }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div v-if="adicionales.length" class="card mt-4">
      <div class="card-header"><h3 class="card-title">Conceptos adicionales</h3></div>
      <div class="card-body">
        <dl class="grid grid-cols-2 gap-3 text-sm md:grid-cols-3">
          <div v-for="[clave, valor] in adicionales" :key="clave">
            <dt class="text-gray-500">{{ clave }}</dt>
            <dd class="tabular-nums font-medium text-gray-900">{{ formatearImporte(valor) }}</dd>
          </div>
        </dl>
      </div>
    </div>

    <div v-if="imputacion.length" class="card mt-4">
      <div class="card-header"><h3 class="card-title">Imputación</h3></div>
      <div class="card-body">
        <dl class="grid grid-cols-2 gap-3 text-sm md:grid-cols-4">
          <div v-for="[area, pct] in imputacion" :key="area">
            <dt class="text-gray-500">Área {{ area }}</dt>
            <dd class="tabular-nums font-medium text-gray-900">{{ formatearImporte(pct, 2) }}%</dd>
          </div>
        </dl>
      </div>
    </div>

    <div v-if="detalle.servicios.length" class="card mt-4">
      <div class="card-header"><h3 class="card-title">Servicios imputados</h3></div>
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-600">
            <tr>
              <th class="px-3 py-2 text-left font-semibold">Servicio</th>
              <th class="px-3 py-2 text-left font-semibold">Confirmación</th>
              <th class="px-3 py-2 text-left font-semibold">Reserva</th>
              <th class="px-3 py-2 text-left font-semibold">Desde</th>
              <th class="px-3 py-2 text-right font-semibold">Monto</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="s in detalle.servicios" :key="s.id">
              <td class="px-3 py-2 text-gray-800">{{ s.nombre }}</td>
              <td class="px-3 py-2 text-gray-600">{{ s.nroConfirmacion || '—' }}</td>
              <td class="px-3 py-2 text-gray-600">{{ s.codigo || '—' }}</td>
              <td class="px-3 py-2 text-gray-600">{{ s.vigenciaIni || '—' }}</td>
              <td class="px-3 py-2 text-right tabular-nums text-gray-900">
                {{ s.moneda }} {{ formatearImporte(s.monto) }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
