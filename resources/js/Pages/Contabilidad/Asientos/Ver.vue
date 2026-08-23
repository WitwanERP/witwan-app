<script setup>
import { computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { formatearImporte, formatearFecha } from '@/lib/formato'

defineOptions({ layout: AppLayout })

const props = defineProps({
  config: { type: Object, required: true },
  asiento: { type: Object, required: true },
  movimientos: { type: Array, default: () => [] },
  totales: { type: Object, required: true },
  periodoAbierto: { type: Boolean, default: true },
})

const columnas = computed(() => {
  const cols = [{ key: 'cuenta', label: 'Cuenta' }]

  if (props.config.imputa.includes('cliente')) cols.push({ key: 'cliente', label: 'Cliente' })
  if (props.config.imputa.includes('proveedor')) cols.push({ key: 'proveedor', label: 'Proveedor' })

  cols.push({ key: 'descripcion', label: 'Leyenda' })

  if (props.config.imputa.includes('file')) cols.push({ key: 'file', label: 'File' })

  return cols
})

/**
 * Editar y anular necesitan, además del permiso, que el período contable siga
 * abierto para la fecha del asiento. El legacy mostraba los dos links siempre y
 * el control saltaba recién al hacer click, con un `die()` en pantalla blanca.
 */
const puedeAnular = computed(
  () => props.config.permisos.borrado && !props.asiento.anulado && props.periodoAbierto
)

const puedeEditar = computed(
  () => props.config.permisos.edicion && !props.asiento.anulado && props.periodoAbierto
)

// `window` no está en el allowlist de globales de las plantillas de Vue: la
// impresión tiene que salir de un método.
function imprimir() {
  window.print()
}

function anular() {
  const ok = window.confirm(
    `¿Anular el asiento N° ${props.asiento.numero} por ${props.asiento.moneda} ${formatearImporte(props.asiento.monto)}?`
  )

  if (!ok) return

  router.post(`${props.config.baseUrl}/${props.asiento.id}/anular`, {}, { preserveScroll: true })
}
</script>

<template>
  <div>
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">
          {{ config.titulo }} N° {{ asiento.numero }}
          <span v-if="asiento.anulado" class="badge badge-danger align-middle">Anulado</span>
        </h1>
        <p class="text-gray-500">
          {{ formatearFecha(asiento.fecha) }} · {{ asiento.moneda }}
          {{ formatearImporte(asiento.monto) }} · {{ asiento.usuario || 'sin usuario' }}
        </p>
      </div>

      <div class="flex flex-wrap gap-2 no-print">
        <Link :href="config.baseUrl" class="btn btn-secondary">Volver</Link>
        <button type="button" class="btn btn-secondary" @click="imprimir">Imprimir</button>
        <Link v-if="puedeEditar" :href="`${config.baseUrl}/${asiento.id}/edit`" class="btn btn-secondary">
          Editar
        </Link>
        <button v-if="puedeAnular" type="button" class="btn btn-danger" @click="anular">Anular</button>
      </div>
    </div>

    <!-- El período se puede haber cerrado después de grabar: conviene decirlo
         acá y no cuando el usuario apriete Anular. -->
    <div
      v-if="!periodoAbierto && !asiento.anulado"
      class="mb-4 rounded-lg border border-yellow-200 bg-yellow-50 px-4 py-3 text-sm text-yellow-800"
    >
      La fecha de este asiento quedó fuera del período contable abierto: no se puede editar ni anular.
    </div>

    <div class="card mb-4">
      <div class="card-body">
        <dl class="grid grid-cols-2 gap-4 text-sm md:grid-cols-4">
          <div>
            <dt class="text-gray-500">Estado</dt>
            <dd class="font-medium text-gray-900">{{ asiento.estado }}</dd>
          </div>
          <div>
            <dt class="text-gray-500">Fecha</dt>
            <dd class="font-medium text-gray-900">{{ formatearFecha(asiento.fecha) }}</dd>
          </div>
          <div>
            <dt class="text-gray-500">Moneda</dt>
            <dd class="font-medium text-gray-900">
              {{ asiento.moneda }}
              <span v-if="asiento.cotizacion" class="text-gray-500">
                (cotiz. {{ formatearImporte(asiento.cotizacion, 5) }})
              </span>
            </dd>
          </div>
          <div>
            <dt class="text-gray-500">Monto</dt>
            <dd class="tabular-nums font-medium text-gray-900">{{ formatearImporte(asiento.monto) }}</dd>
          </div>
          <div v-if="config.usaProyecto">
            <dt class="text-gray-500">Proyecto</dt>
            <dd class="font-medium text-gray-900">{{ asiento.proyecto || '—' }}</dd>
          </div>
          <div class="col-span-2">
            <dt class="text-gray-500">Observaciones</dt>
            <dd class="font-medium text-gray-900">{{ asiento.observaciones || '—' }}</dd>
          </div>
        </dl>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <h3 class="card-title">Movimientos</h3>
        <span class="text-sm text-gray-500">{{ movimientos.length }}</span>
      </div>

      <!-- Fondos se carga como ingreso/egreso, pero abajo son los mismos dos
           casilleros: acá se muestran como debe/haber, que es como el usuario
           los va a cotejar contra el mayor. -->

      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-600">
            <tr>
              <th v-for="c in columnas" :key="c.key" class="whitespace-nowrap px-3 py-2 text-left font-semibold">
                {{ c.label }}
              </th>
              <th class="px-3 py-2 text-right font-semibold">Debe</th>
              <th class="px-3 py-2 text-right font-semibold">Haber</th>
            </tr>
          </thead>

          <tbody class="divide-y divide-gray-100">
            <tr v-for="m in movimientos" :key="m.id" :class="m.vigente ? '' : 'text-gray-400 line-through'">
              <td v-for="c in columnas" :key="c.key" class="px-3 py-2 text-gray-700">
                <template v-if="c.key === 'cuenta'">
                  <span class="font-medium text-gray-900">{{ m.cuenta || `#${m.cuentaId}` }}</span>
                  <span v-if="m.cuentaCodigo" class="ml-1 text-xs text-gray-400">{{ m.cuentaCodigo }}</span>
                  <span v-if="m.fueraDeArqueo" class="ml-1 badge badge-gray">fuera de arqueo</span>
                </template>
                <template v-else>{{ m[c.key] || '—' }}</template>
              </td>
              <td class="px-3 py-2 text-right tabular-nums">
                {{ m.debe === null ? '' : formatearImporte(m.debe) }}
              </td>
              <td class="px-3 py-2 text-right tabular-nums">
                {{ m.haber === null ? '' : formatearImporte(m.haber) }}
              </td>
            </tr>

            <tr v-if="!movimientos.length">
              <td :colspan="columnas.length + 2" class="px-3 py-8 text-center text-gray-500">
                Este asiento no tiene movimientos.
                <template v-if="asiento.anulado">
                  Los movimientos de fondos se borran al anular; el detalle queda en la auditoría.
                </template>
              </td>
            </tr>
          </tbody>

          <tfoot class="border-t-2 border-gray-200 bg-gray-50">
            <tr>
              <th :colspan="columnas.length" class="px-3 py-2 text-left font-semibold text-gray-700">Totales</th>
              <th class="px-3 py-2 text-right tabular-nums font-semibold text-gray-900">
                {{ formatearImporte(totales.debe) }}
              </th>
              <th class="px-3 py-2 text-right tabular-nums font-semibold text-gray-900">
                {{ formatearImporte(totales.haber) }}
              </th>
            </tr>
          </tfoot>
        </table>
      </div>

      <!-- Un asiento anterior a esta pantalla puede estar descuadrado: el CI no
           validaba el balance en el servidor. Se dice en vez de esconderlo. -->
      <div v-if="!totales.balancea" class="card-footer text-sm text-red-600">
        Este asiento no balancea: hay una diferencia de
        {{ formatearImporte(Math.abs(totales.debe - totales.haber)) }}.
      </div>
    </div>
  </div>
</template>
