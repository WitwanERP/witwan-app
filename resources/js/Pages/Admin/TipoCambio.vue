<script setup>
import { reactive } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { useEnvio } from '@/lib/envio'

defineOptions({ layout: AppLayout })

const props = defineProps({
  monedas: { type: Array, required: true },
  monedaBasica: { type: String, required: true },
  puedeEditar: { type: Boolean, default: false },
  fecha: { type: String, default: '' },
})

// Valores editables por moneda (venta / costo), inicializados con la cotización vigente.
const valores = reactive({})
for (const m of props.monedas) valores[m.moneda] = { valor: m.venta, valor2: m.costo }

const { enviando, enviar } = useEnvio()

function actualizar(moneda) {
  const v = valores[moneda]
  enviar(
    (opciones) => router.post('/app/admin/tipo-cambio', { moneda, valor: v.valor, valor2: v.valor2 }, opciones),
    { preserveScroll: true },
  )
}

const inputCls = 'w-32 rounded-md border border-gray-300 px-3 py-1.5 text-sm text-right focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-gray-100 disabled:text-gray-500'
</script>

<template>
  <div>
    <nav class="flex items-center gap-2 text-sm text-gray-500 mb-3">
      <Link href="/app" class="hover:text-gray-700">Inicio</Link>
      <span>/</span>
      <span class="font-medium" style="color: #c9a300">Administración</span>
      <span>/</span>
      <span>Monedas</span>
      <span>/</span>
      <span class="text-gray-900 font-semibold">Tipo de cambio</span>
    </nav>

    <div class="mb-6 flex items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Tipo de cambio</h1>
        <p class="text-gray-500">Cotizaciones vigentes al {{ fecha }}. Moneda básica: <b>{{ monedaBasica }}</b></p>
      </div>
      <Link href="/app/admin/cotizaciones" class="btn btn-secondary">Ver todas las cotizaciones</Link>
    </div>

    <div class="rounded-md border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 mb-4">
      Configure las relaciones de cambio entre monedas. El sistema opera con una moneda básica, que es la base del cálculo de la tasa entre dicha moneda y el resto; la básica tiene valor 1 y su cotización no es actualizable.
      Para modificar los coeficientes edite el valor y haga clic en <b>Actualizar cotización</b>: se graba la cotización de hoy para esa moneda.
      <span v-if="!puedeEditar" class="block mt-1 font-medium">Su perfil no tiene permiso de edición: los valores son de sólo lectura.</span>
    </div>

    <div class="card">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Moneda</th>
              <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Venta</th>
              <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Costo</th>
              <th class="px-4 py-3"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 bg-white">
            <tr v-for="m in monedas" :key="m.moneda" class="hover:bg-gray-50">
              <td class="px-4 py-2 text-sm text-gray-800"><b>{{ m.moneda }}</b> <span class="text-gray-500">{{ m.nombre }}</span></td>
              <td class="px-4 py-2 text-right">
                <input v-model="valores[m.moneda].valor" type="number" step="any" min="0" :class="inputCls" :disabled="!puedeEditar || m.basica" />
              </td>
              <td class="px-4 py-2 text-right">
                <input v-model="valores[m.moneda].valor2" type="number" step="any" min="0" :class="inputCls" :disabled="!puedeEditar || m.basica" />
              </td>
              <td class="px-4 py-2 text-right whitespace-nowrap">
                <button v-if="puedeEditar && !m.basica" type="button" class="btn btn-sm btn-success" :disabled="enviando" @click="actualizar(m.moneda)">Actualizar cotización</button>
                <span v-else-if="m.basica" class="text-xs text-gray-400">moneda básica</span>
              </td>
            </tr>
            <tr v-if="monedas.length === 0">
              <td colspan="4" class="px-4 py-12 text-center text-gray-500">No hay monedas cargadas.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
