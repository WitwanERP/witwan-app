<script setup>
import { Link, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { useEnvio } from '@/lib/envio'

defineOptions({ layout: AppLayout })

const props = defineProps({
  // [{ titulo, items: [{ clave, label, valor, adicionales?: [{concepto, valor}] }] }]
  grupos: { type: Array, required: true },
  // [{ value, label }] plan de cuentas
  cuentas: { type: Array, required: true },
})

// valores[clave] = plancuenta_id | '' ; adicionales[clave][concepto] = plancuenta_id | ''
const valores = {}
const adicionales = {}
for (const g of props.grupos) {
  for (const it of g.items) {
    if (it.adicionales) {
      adicionales[it.clave] = {}
      for (const a of it.adicionales) adicionales[it.clave][a.concepto] = a.valor ?? ''
    } else {
      valores[it.clave] = it.valor ?? ''
    }
  }
}
const form = useForm({ valores, adicionales })

const { enviando, enviar } = useEnvio()
const submit = () => enviar((opciones) => form.post('/app/admin/parametros-contables', opciones), { preserveScroll: true })

const selectCls = 'w-full rounded-md border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500'
const humanizar = (c) => c.replace(/_/g, ' ')
</script>

<template>
  <div>
    <nav class="flex items-center gap-2 text-sm text-gray-500 mb-3">
      <Link href="/app" class="hover:text-gray-700">Inicio</Link>
      <span>/</span>
      <span class="font-medium" style="color: #c9a300">Administración</span>
      <span>/</span>
      <span>Contabilidad</span>
      <span>/</span>
      <span class="text-gray-900 font-semibold">Parámetros contables</span>
    </nav>

    <form @submit.prevent="submit">
      <div class="mb-6 flex items-center justify-between gap-4">
        <div>
          <h1 class="text-2xl font-bold text-gray-900">Parámetros contables</h1>
          <p class="text-gray-500">Cuenta del plan asignada a cada concepto contable de la licencia.</p>
        </div>
        <button type="submit" class="btn btn-primary" :disabled="enviando">{{ enviando ? 'Guardando…' : 'Guardar' }}</button>
      </div>

      <div v-for="g in grupos" :key="g.titulo" class="card mb-4">
        <div class="card-header"><h3 class="card-title">{{ g.titulo }}</h3></div>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider w-1/2">Tipo</th>
                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Cuenta</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white">
              <template v-for="it in g.items" :key="it.clave">
                <!-- Adicionales: un select por concepto (JSON en sysconfig) -->
                <template v-if="it.adicionales">
                  <tr class="bg-emerald-50">
                    <td colspan="2" class="px-4 py-2 text-sm font-semibold text-emerald-900">{{ it.label }}</td>
                  </tr>
                  <tr v-for="a in it.adicionales" :key="a.concepto">
                    <td class="px-4 py-2 text-sm text-gray-700 pl-8">{{ humanizar(a.concepto) }}</td>
                    <td class="px-4 py-2">
                      <select v-model="form.adicionales[it.clave][a.concepto]" :class="selectCls">
                        <option value="">--</option>
                        <option v-for="c in cuentas" :key="c.value" :value="c.value">{{ c.label }}</option>
                      </select>
                    </td>
                  </tr>
                  <tr v-if="it.adicionales.length === 0">
                    <td colspan="2" class="px-4 py-2 text-xs text-gray-400 pl-8">Sin conceptos adicionales cargados.</td>
                  </tr>
                </template>
                <tr v-else>
                  <td class="px-4 py-2 text-sm text-gray-700">{{ it.label }} <span class="text-xs text-gray-400">({{ it.clave }})</span></td>
                  <td class="px-4 py-2">
                    <select v-model="form.valores[it.clave]" :class="selectCls">
                      <option value="">--</option>
                      <option v-for="c in cuentas" :key="c.value" :value="c.value">{{ c.label }}</option>
                    </select>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
      </div>

      <div class="mb-10">
        <button type="submit" class="btn btn-primary" :disabled="enviando">{{ enviando ? 'Guardando…' : 'Guardar' }}</button>
      </div>
    </form>
  </div>
</template>
