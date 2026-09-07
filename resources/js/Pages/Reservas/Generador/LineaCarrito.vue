<script setup>
import { computed } from 'vue'
import BuscadorRemoto from '@/Components/BuscadorRemoto.vue'
import { formatearFecha, formatearImporte } from '@/lib/formato'
import { inputSm, useGenerador } from './useGenerador'

/**
 * Una línea del carrito = una futura fila de `servicio`. Las líneas tarifadas
 * (origen TAR) vienen del buscador y sólo dejan tocar lo operativo (status,
 * confirmación, comentarios, vencimiento) y, para internos, los importes; las
 * manuales (APP) se editan completas.
 */
const props = defineProps({ linea: { type: Object, required: true }, indice: { type: Number, required: true } })
const g = useGenerador()
const l = props.linea
const manual = computed(() => l._origen !== 'TAR')
const editaImportes = computed(() => manual.value || g.props.esInterno)
const margen = computed(() => g.margenLinea(l))
const tipoNombre = computed(() => g.props.opciones.tipos.find((t) => t.value === l.fk_tipoproducto_id)?.label || l.fk_tipoproducto_id || '—')
const conPickup = computed(() => ['EXC', 'TRN', 'TRA', 'GUI'].includes(l.fk_tipoproducto_id))
const asignados = computed(() => l.pasajeros_ids.length)
</script>

<template>
  <div class="card">
    <div class="card-header !py-2">
      <div class="flex items-center gap-3 min-w-0">
        <span class="badge" :class="manual ? 'badge-gray' : 'badge-info'">{{ manual ? 'Manual' : 'Tarifa' }}</span>
        <div class="min-w-0">
          <div class="text-sm font-semibold text-gray-900 truncate">{{ indice + 1 }}. {{ l.servicio_nombre || '(sin nombre)' }}</div>
          <div class="text-xs text-gray-500 truncate">
            {{ tipoNombre }} · {{ formatearFecha(l.vigencia_ini) }}<span v-if="l.vigencia_fin && l.vigencia_fin !== l.vigencia_ini"> → {{ formatearFecha(l.vigencia_fin) }}</span>
            · {{ g.paxLinea(l) }} pax<span v-if="l.ciudad_label"> · {{ l.ciudad_label }}</span><span v-if="l._detalle?.nombre"> · {{ l._detalle.nombre }}</span>
            · <span :class="asignados ? '' : 'text-amber-700'">{{ asignados }} en nómina</span>
          </div>
        </div>
      </div>
      <div class="flex items-center gap-4 shrink-0">
        <div class="text-right">
          <div class="text-sm font-semibold tabular-nums">{{ formatearImporte(l.total) }} <span class="text-xs text-gray-500">{{ l.fk_moneda_id }}</span></div>
          <div class="text-xs tabular-nums" :class="margen.valor < 0 ? 'text-red-600' : 'text-green-700'">margen {{ formatearImporte(margen.valor) }} ({{ margen.pct.toFixed(1) }}%)</div>
        </div>
        <span class="badge" :class="l.status === 'CO' ? 'badge-success' : 'badge-warning'">{{ l.status }}</span>
        <div class="flex gap-2 text-xs">
          <button type="button" class="text-blue-600 hover:underline" @click="l._abierto = !l._abierto">{{ l._abierto ? 'Cerrar' : 'Editar' }}</button>
          <button type="button" class="text-blue-600 hover:underline" @click="g.duplicarLinea(l._uid)">Duplicar</button>
          <button type="button" class="text-red-600 hover:underline" @click="g.quitarLinea(l._uid)">Quitar</button>
        </div>
      </div>
    </div>

    <div v-show="l._abierto" class="card-body grid grid-cols-2 md:grid-cols-6 gap-3">
      <div>
        <label class="block text-xs text-gray-600 mb-1 font-semibold">Tipo</label>
        <select v-model="l.fk_tipoproducto_id" :class="inputSm" :disabled="!manual"><option value="">--</option><option v-for="t in g.props.opciones.tipos" :key="t.value" :value="t.value">{{ t.label }}</option></select>
      </div>
      <div class="md:col-span-3"><label class="block text-xs text-gray-600 mb-1 font-semibold">Nombre del servicio</label><input v-model="l.servicio_nombre" type="text" maxlength="200" :class="inputSm" /></div>
      <div class="md:col-span-2">
        <label class="block text-xs text-gray-600 mb-1">Proveedor</label>
        <BuscadorRemoto v-if="manual" v-model="l.fk_proveedor_id" v-model:etiqueta="l.proveedor_label" :url="`${g.props.baseUrl}/nueva/proveedores`" placeholder="Buscar proveedor…" :clase-input="inputSm" />
        <input v-else type="text" :value="l.proveedor_label || (l.fk_proveedor_id ? '#' + l.fk_proveedor_id : 'del producto')" :class="inputSm" disabled />
      </div>

      <div class="md:col-span-2">
        <label class="block text-xs text-gray-600 mb-1">Ciudad</label>
        <BuscadorRemoto v-if="manual" v-model="l.fk_ciudad_id" v-model:etiqueta="l.ciudad_label" :url="`${g.props.baseUrl}/nueva/ciudades`" placeholder="Buscar ciudad…" :clase-input="inputSm" />
        <input v-else type="text" :value="l.ciudad_label" :class="inputSm" disabled />
      </div>
      <div><label class="block text-xs text-gray-600 mb-1 font-semibold">Inicio</label><input v-model="l.vigencia_ini" type="date" :min="g.props.fechaMinima" :class="inputSm" :disabled="!manual" /></div>
      <div><label class="block text-xs text-gray-600 mb-1">Fin</label><input v-model="l.vigencia_fin" type="date" :min="l.vigencia_ini" :class="inputSm" :disabled="!manual" /></div>
      <div><label class="block text-xs text-gray-600 mb-1">Status</label><select v-model="l.status" :class="inputSm"><option value="CO">CO · Confirmado</option><option value="RQ">RQ · A confirmar</option></select></div>
      <div><label class="block text-xs text-gray-600 mb-1">Vence pago prov.</label><input v-model="l.vencimiento_proveedor" type="date" :class="inputSm" /></div>

      <div><label class="block text-xs text-gray-600 mb-1">Adultos</label><input v-model.number="l.adultos" type="number" min="0" :class="inputSm" :disabled="!manual" /></div>
      <div><label class="block text-xs text-gray-600 mb-1">Menores</label><input v-model.number="l.menores" type="number" min="0" :class="inputSm" :disabled="!manual" /></div>
      <div><label class="block text-xs text-gray-600 mb-1">Infantes</label><input v-model.number="l.infante" type="number" min="0" :class="inputSm" :disabled="!manual" /></div>
      <div><label class="block text-xs text-gray-600 mb-1">Juniors</label><input v-model.number="l.juniors" type="number" min="0" :class="inputSm" :disabled="!manual" /></div>
      <div class="md:col-span-2"><label class="block text-xs text-gray-600 mb-1">Nro. confirmación</label><input v-model="l.nro_confirmacion" type="text" maxlength="200" :class="inputSm" /></div>

      <div><label class="block text-xs text-gray-600 mb-1 font-semibold">Moneda venta</label><select v-model="l.fk_moneda_id" :class="inputSm" :disabled="!editaImportes"><option v-for="m in g.props.opciones.monedas" :key="m.value" :value="m.value">{{ m.value }}</option></select></div>
      <div><label class="block text-xs text-gray-600 mb-1 font-semibold">Total venta</label><input v-model.number="l.total" type="number" step="0.01" min="0" :class="inputSm" :disabled="!editaImportes" /></div>
      <div><label class="block text-xs text-gray-600 mb-1">IVA venta</label><input v-model.number="l.iva" type="number" step="0.01" min="0" :class="inputSm" :disabled="!editaImportes" /></div>
      <div><label class="block text-xs text-gray-600 mb-1">Moneda costo</label><select v-model="l.moneda_costo" :class="inputSm" :disabled="!editaImportes"><option v-for="m in g.props.opciones.monedas" :key="m.value" :value="m.value">{{ m.value }}</option></select></div>
      <div><label class="block text-xs text-gray-600 mb-1">Costo</label><input v-model.number="l.costo" type="number" step="0.01" min="0" :class="inputSm" :disabled="!editaImportes" /></div>
      <div><label class="block text-xs text-gray-600 mb-1">IVA costo</label><input v-model.number="l.iva_costo" type="number" step="0.01" min="0" :class="inputSm" :disabled="!editaImportes" /></div>
      <div><label class="block text-xs text-gray-600 mb-1">Impuestos</label><input v-model.number="l.impuestos" type="number" step="0.01" min="0" :class="inputSm" :disabled="!editaImportes" /></div>

      <template v-if="conPickup">
        <div class="md:col-span-2"><label class="block text-xs text-gray-600 mb-1">Pick-up</label><input v-model="l.servicio_extra.pickup" type="text" maxlength="200" placeholder="Hotel / dirección" :class="inputSm" /></div>
        <div><label class="block text-xs text-gray-600 mb-1">Hora</label><input v-model="l.servicio_extra.hora_pickup" type="time" :class="inputSm" /></div>
        <div class="md:col-span-2"><label class="block text-xs text-gray-600 mb-1">Drop-off</label><input v-model="l.servicio_extra.dropoff" type="text" maxlength="200" :class="inputSm" /></div>
      </template>
      <div :class="conPickup ? 'md:col-span-6' : 'md:col-span-5'"><label class="block text-xs text-gray-600 mb-1">Comentarios</label><input v-model="l.comentarios" type="text" :class="inputSm" /></div>

      <div v-if="l._detalle" class="md:col-span-6 text-xs text-gray-500 border-t border-gray-100 pt-2 flex flex-wrap gap-x-4 gap-y-1">
        <span v-if="l._detalle.nombre">Opción: <b>{{ l._detalle.nombre }}</b></span>
        <span v-if="l._detalle.regimen_nombre">Régimen: <b>{{ l._detalle.regimen_nombre }}</b></span>
        <span v-if="l._detalle.noches">Noches: <b>{{ l._detalle.noches }}</b></span>
        <span v-if="l._detalle.textodescuento" class="text-green-700">Promo {{ l._detalle.textodescuento }}</span>
        <span v-if="l._detalle.disponibilidad">Disponibilidad: <b>{{ l._detalle.disponibilidad }}</b></span>
        <span v-if="l._detalle.comision">Comisión: <b>{{ formatearImporte(l._detalle.comision) }}</b></span>
      </div>
    </div>
  </div>
</template>
