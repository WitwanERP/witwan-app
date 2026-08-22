<script setup>
import { computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { formatearImporte } from '../calculo.js'

const props = defineProps({
  registros: { type: Object, required: true },
  config: { type: Object, required: true },
  vista: { type: String, default: 'listado' },
})

/**
 * Las columnas se declaran acá y no en el template para que la variante chilena
 * (que muestra menos) y el subdiario sean un filtro sobre una lista, en vez de
 * un v-if por celda como en el legacy.
 */
const COLUMNAS = {
  listado: [
    { key: 'proveedorNombre', label: 'Proveedor', tipo: 'texto' },
    { key: 'cuit', label: 'CUIT', tipo: 'texto' },
    { key: 'tipoDocumento', label: 'T.Doc.', tipo: 'texto' },
    { key: 'tipoFactura', label: 'Tipo', tipo: 'texto', soloAr: true },
    { key: 'numero', label: 'Número', tipo: 'texto' },
    { key: 'fecha', label: 'Fecha', tipo: 'fecha' },
    { key: 'fechaContable', label: 'F. Contable', tipo: 'fecha' },
    { key: 'moneda', label: 'Mon.', tipo: 'texto' },
    { key: 'montos.exento', label: 'Exento', tipo: 'importe' },
    { key: 'montos.noComputable', label: 'No Comp.', tipo: 'importe', soloAr: true },
    { key: 'montos.netoGravado', label: 'Neto Grav.', tipo: 'importe' },
    { key: 'ivas.i21', label: 'IVA', tipo: 'importe' },
    { key: 'ivas.i105', label: 'IVA 10.5%', tipo: 'importe', soloAr: true },
    { key: 'ivas.i27', label: 'IVA 27%', tipo: 'importe', soloAr: true },
    { key: 'ivas.i25', label: 'IVA 2.5%', tipo: 'importe', soloAr: true },
    { key: 'retper.retencionIva', label: 'Ret. IVA', tipo: 'importe', soloAr: true },
    { key: 'retper.percepcionIva', label: 'Per. IVA', tipo: 'importe', soloAr: true },
    { key: 'ivas.ivaTur', label: 'IVA TUR', tipo: 'importe', soloAr: true },
    { key: 'total', label: 'TOTAL', tipo: 'importe', destacada: true },
    { key: 'codigoReserva', label: 'Reserva', tipo: 'texto' },
    { key: 'usuario', label: 'Usuario', tipo: 'texto' },
  ],
  subdiario: [
    { key: 'proveedorNombre', label: 'Proveedor', tipo: 'texto' },
    { key: 'cuit', label: 'CUIT', tipo: 'texto' },
    { key: 'numero', label: 'Número', tipo: 'texto' },
    { key: 'fecha', label: 'Fecha', tipo: 'fecha' },
    { key: 'montos.exento', label: 'Exento', tipo: 'importe' },
    { key: 'montos.noComputable', label: 'No Comp.', tipo: 'importe' },
    { key: 'montos.netoGravado', label: 'Neto Gr.', tipo: 'importe' },
    { key: 'ivas.idi21', label: 'IVA', tipo: 'importe' },
    { key: 'ivas.iin21', label: 'IVA Gasto', tipo: 'importe' },
    { key: 'ivas.i105', label: 'IVA 10.5%', tipo: 'importe' },
    { key: 'ivas.i2527', label: 'IVA 27/2,5%', tipo: 'importe' },
    { key: 'ivas.ivaTur', label: 'IVA TUR', tipo: 'importe' },
    { key: 'total', label: 'TOTAL', tipo: 'importe', destacada: true },
  ],
}

const columnas = computed(() =>
  COLUMNAS[props.vista].filter((c) => !(c.soloAr && props.config.pais === 'CL'))
)

const valor = (fila, key) => key.split('.').reduce((o, k) => (o == null ? null : o[k]), fila)

function eliminar(fila) {
  // La baja es física, igual que en el legacy: no hay estado "anulada".
  if (!window.confirm(`¿Eliminar la factura ${fila.numero}? Esta acción no se puede deshacer.`)) return
  router.delete(`${props.config.baseUrl}/${fila.id}`, { preserveScroll: true })
}
</script>

<template>
  <div class="card">
    <!-- Las tablas anchas scrollean dentro de su contenedor: la página nunca
         scrollea horizontalmente. -->
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-600">
          <tr>
            <th v-for="c in columnas" :key="c.key" class="whitespace-nowrap px-3 py-2 text-left font-semibold">
              {{ c.label }}
            </th>
            <th class="px-3 py-2 text-right font-semibold">Acciones</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="fila in registros.data" :key="fila.id" class="hover:bg-blue-50/40">
            <td
              v-for="c in columnas"
              :key="c.key"
              class="whitespace-nowrap px-3 py-2"
              :class="[
                c.tipo === 'importe' ? 'text-right tabular-nums' : '',
                c.destacada ? 'font-semibold text-gray-900' : 'text-gray-700',
                c.tipo === 'importe' && Number(valor(fila, c.key)) < 0 ? 'text-red-600' : '',
              ]"
            >
              <template v-if="c.tipo === 'importe'">{{ formatearImporte(valor(fila, c.key)) }}</template>
              <template v-else>{{ valor(fila, c.key) || '—' }}</template>
            </td>
            <td class="whitespace-nowrap px-3 py-2 text-right">
              <Link :href="`${config.baseUrl}/${fila.id}`" class="btn btn-sm btn-secondary">Ver</Link>
              <Link
                v-if="config.permisos.edicion"
                :href="`${config.baseUrl}/${fila.id}/edit`"
                class="btn btn-sm btn-secondary ml-1"
                >Editar</Link
              >
              <button
                v-if="config.permisos.borrado"
                type="button"
                class="btn btn-sm btn-secondary ml-1 text-red-600"
                @click="eliminar(fila)"
              >
                Eliminar
              </button>
            </td>
          </tr>
          <tr v-if="!registros.data.length">
            <td :colspan="columnas.length + 1" class="px-3 py-8 text-center text-gray-500">
              No hay facturas para los filtros aplicados.
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="registros.links && registros.links.length > 3" class="card-footer flex flex-wrap items-center gap-2">
      <span class="text-sm text-gray-500">
        Mostrando {{ registros.from }}–{{ registros.to }} de {{ registros.total }}
      </span>
      <div class="ml-auto flex flex-wrap gap-1">
        <component
          :is="link.url ? 'Link' : 'span'"
          v-for="(link, i) in registros.links"
          :key="i"
          :href="link.url || undefined"
          preserve-scroll
          class="rounded px-3 py-1 text-sm"
          :class="link.active ? 'bg-blue-600 text-white' : link.url ? 'text-gray-700 hover:bg-gray-100' : 'text-gray-300'"
          v-html="link.label"
        />
      </div>
    </div>
  </div>
</template>
