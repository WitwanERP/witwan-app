<script setup>
import { Link, router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { useEnvio } from '@/lib/envio'

defineOptions({ layout: AppLayout })

const props = defineProps({
  area: { type: String, required: true },
  reserva: { type: Object, required: true },
  servicios: { type: Array, default: () => [] },
  opciones: { type: Object, required: true },
})

const base = `/app/operaciones/autorizar/${props.area}`
const form = useForm({
  fk_guia_id: props.reserva.fk_guia_id || 0,
  servicios: props.servicios.map((s) => ({
    servicio_id: s.servicio_id,
    ocultaritinerario: s.ocultaritinerario,
    fk_proveedor_id: s.fk_proveedor_id || 0,
    horario: s.horario,
    vuelo: s.vuelo,
  })),
})

const { enviando, enviar } = useEnvio()
const actualizar = () => enviar((o) => form.post(`${base}/${props.reserva.id}/actualizar`, o), { preserveScroll: true })
function autorizar() {
  if (!window.confirm(`¿Autorizar el file ${props.reserva.codigo}?`)) return
  enviar((o) => router.post(`${base}/${props.reserva.id}/autorizar`, {}, o))
}

const fmt = new Intl.NumberFormat('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const inputCls = 'w-full rounded-md border border-gray-300 px-2 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500'
</script>

<template>
  <div>
    <nav class="flex items-center gap-2 text-sm text-gray-500 mb-3">
      <Link href="/app" class="hover:text-gray-700">Inicio</Link>
      <span>/</span>
      <span class="font-medium" style="color: #66cc00">Operaciones</span>
      <span>/</span>
      <Link :href="base" class="hover:text-gray-700">Autorizar reservas</Link>
      <span>/</span>
      <span class="text-gray-900 font-semibold">{{ reserva.codigo }}</span>
    </nav>

    <div class="mb-6 flex items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Autorizar file {{ reserva.codigo }}</h1>
        <p class="text-gray-500">{{ reserva.titular }} · {{ reserva.cliente }} <span v-if="reserva.sistema">· {{ reserva.sistema }}</span></p>
      </div>
      <span v-if="reserva.autorizado" class="badge badge-success">File autorizado</span>
    </div>

    <form @submit.prevent="actualizar">
      <section class="card mb-4">
        <div class="card-body grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="form-label">Área</label>
            <div class="text-sm py-2">{{ reserva.sistema || '—' }}</div>
          </div>
          <div>
            <label class="form-label">Guía asignado</label>
            <select v-model.number="form.fk_guia_id" :class="inputCls">
              <option :value="0">--</option>
              <option v-for="g in opciones.guias" :key="g.value" :value="g.value">{{ g.label }}</option>
            </select>
          </div>
          <div class="flex items-end">
            <a :href="`/reserva/itinerario/${reserva.id}`" target="_blank" class="btn btn-secondary btn-sm">Itinerario</a>
          </div>
          <div v-if="reserva.observaciones" class="md:col-span-3 text-sm text-gray-600 whitespace-pre-line"><b>Observaciones:</b> {{ reserva.observaciones }}</div>
        </div>
      </section>

      <section class="card mb-4">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Producto</th>
                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Proveedor</th>
                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">In / Out</th>
                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Tipo</th>
                <th class="px-3 py-2 text-right text-xs font-semibold text-gray-500 uppercase">Costo</th>
                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Voucher</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white">
              <tr v-for="(s, i) in servicios" :key="s.servicio_id" class="align-top">
                <td class="px-3 py-2">
                  <div class="font-medium">{{ s.nombre }} <span class="text-gray-400">({{ s.tipo }})</span></div>
                  <div class="text-xs text-gray-500">{{ s.pax }} pax · {{ s.status }}</div>
                  <label class="flex items-center gap-1.5 text-xs mt-1">
                    <input type="checkbox" :true-value="1" :false-value="0" v-model="form.servicios[i].ocultaritinerario" /> Ocultar del itinerario
                  </label>
                </td>
                <td class="px-3 py-2 min-w-[220px]">
                  <template v-if="s.editable">
                    <select v-model.number="form.servicios[i].fk_proveedor_id" :class="inputCls">
                      <option :value="0">N/A</option>
                      <option v-for="p in opciones.proveedores" :key="p.value" :value="p.value">{{ p.label }}</option>
                    </select>
                    <label class="block text-xs text-gray-500 mt-1">Horario</label>
                    <input v-model="form.servicios[i].horario" type="text" :class="inputCls" />
                    <template v-if="s.pide_vuelo">
                      <label class="block text-xs text-gray-500 mt-1">N° de vuelo</label>
                      <input v-model="form.servicios[i].vuelo" type="text" :class="inputCls" />
                    </template>
                  </template>
                  <template v-else>{{ s.proveedor || '—' }}</template>
                </td>
                <td class="px-3 py-2 whitespace-nowrap">{{ s.in }}<br />{{ s.out }}</td>
                <td class="px-3 py-2">{{ s.tipo }}</td>
                <td class="px-3 py-2 text-right tabular-nums whitespace-nowrap">{{ s.moneda_costo }} {{ fmt.format(s.costo) }}</td>
                <td class="px-3 py-2">
                  <a :href="`/reserva/servicio/${s.servicio_id}/${reserva.id}/solo`" target="_blank" class="btn btn-sm btn-secondary">Ver voucher</a>
                  <div v-if="s.nro_confirmacion" class="text-xs text-gray-500 mt-1">Conf.: {{ s.nro_confirmacion }}</div>
                </td>
              </tr>
              <tr v-if="servicios.length === 0">
                <td colspan="6" class="px-4 py-8 text-center text-gray-500">El file no tiene servicios activos.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <div class="flex items-center gap-2 mb-10">
        <button type="submit" class="btn btn-primary" :disabled="enviando">Actualizar datos</button>
        <button v-if="!reserva.autorizado" type="button" class="btn btn-success" :disabled="enviando" @click="autorizar">Autorizar</button>
        <Link :href="base" class="btn btn-secondary">Cancelar</Link>
      </div>
    </form>
  </div>
</template>
