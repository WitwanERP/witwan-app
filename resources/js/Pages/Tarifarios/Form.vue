<script setup>
import { computed } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { useEnvio } from '@/lib/envio'

defineOptions({ layout: AppLayout })

/**
 * Tarifario con sus filas de markup/comisión por alcance. Cada fila conserva
 * su id (el CI borraba y reinsertaba todo en cada guardado).
 */
const props = defineProps({
  config: { type: Object, required: true },
  registro: { type: Object, default: null },
  monedas: { type: Array, default: () => [] },
  submodulos: { type: Array, default: () => [] },
  paises: { type: Array, default: () => [] },
})

const esEdicion = computed(() => props.registro !== null)
const r = props.registro || {}
const fieldBase = 'w-full rounded-lg border border-gray-300 bg-gray-50 py-2 px-3 text-sm text-gray-800 focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20'
const cls = 'w-full rounded-md border border-gray-300 bg-gray-50 px-2 py-1 text-sm focus:border-blue-500 focus:bg-white focus:outline-none'

const filaVacia = (general = false) => ({ tarifariocomision_id: 0, fk_submodulo_id: '', fk_pais_id: 0, fk_ciudad_id: 0, fk_producto_id: 0, origen: '', divisor_markup: general ? 1 : '', porcentaje_comision: 0, vigencia_ini: '', vigencia_fin: '', general })

const comisiones = (r.comisiones ?? []).map((c) => ({
  tarifariocomision_id: c.tarifariocomision_id,
  fk_submodulo_id: ['0', ''].includes(String(c.fk_submodulo_id)) ? '' : c.fk_submodulo_id,
  fk_pais_id: Number(c.fk_pais_id),
  fk_ciudad_id: Number(c.fk_ciudad_id),
  fk_producto_id: Number(c.fk_producto_id),
  origen: c.origen ?? '',
  divisor_markup: Number(c.divisor_markup),
  porcentaje_comision: Number(c.porcentaje_comision),
  vigencia_ini: c.vigencia_ini ?? '',
  vigencia_fin: c.vigencia_fin ?? '',
  general: !!c.general,
}))
if (!comisiones.some((c) => c.general)) comisiones.unshift(filaVacia(true))

const form = useForm({
  tarifario_nombre: r.tarifario_nombre ?? '',
  fk_moneda_id: r.fk_moneda_id ?? (props.monedas.find((m) => m.basica)?.id ?? ''),
  cotizacion: Number(r.cotizacion ?? 0),
  interno: Number(r.interno ?? 0),
  orden: Number(r.orden ?? 0),
  comisiones,
})

const { enviando, enviar } = useEnvio()
function guardar() {
  enviar((op) => (esEdicion.value ? form.put(`${props.config.baseUrl}/${r.tarifario_id}`, op) : form.post(props.config.baseUrl, op)), { preserveScroll: true })
}

const err = (i, campo) => form.errors[`comisiones.${i}.${campo}`] || (campo === 'divisor_markup' ? form.errors[`comisiones.${i}`] : '')
</script>

<template>
  <div>
    <nav class="mb-4 flex items-center gap-2 text-sm text-gray-500">
      <Link href="/app" class="hover:text-gray-700">Inicio</Link><span>/</span>
      <Link :href="config.baseUrl" class="hover:text-gray-700">Tarifarios</Link><span>/</span>
      <span class="font-semibold text-gray-900">{{ esEdicion ? r.tarifario_nombre : 'Nuevo' }}</span>
    </nav>
    <div class="mb-4 flex items-center justify-between gap-3">
      <h1 class="text-2xl font-bold text-gray-900">{{ esEdicion ? 'Editar tarifario' : 'Nuevo tarifario' }}</h1>
      <button type="submit" form="formtarifario" class="btn btn-primary" :disabled="enviando">{{ enviando ? 'Guardando…' : 'Guardar' }}</button>
    </div>

    <form id="formtarifario" @submit.prevent="guardar">
      <div class="card mb-4">
        <div class="card-header"><h3 class="card-title">Datos</h3></div>
        <div class="card-body grid grid-cols-2 gap-4 md:grid-cols-5">
          <div class="col-span-2"><label class="form-label font-bold">Nombre</label><input v-model="form.tarifario_nombre" type="text" maxlength="100" :class="fieldBase" /><p v-if="form.errors.tarifario_nombre" class="form-error">{{ form.errors.tarifario_nombre }}</p></div>
          <div><label class="form-label font-bold">Moneda</label><select v-model="form.fk_moneda_id" :class="fieldBase"><option v-for="m in monedas" :key="m.id" :value="m.id">{{ m.id }}</option></select><p v-if="form.errors.fk_moneda_id" class="form-error">{{ form.errors.fk_moneda_id }}</p></div>
          <div><label class="form-label">Cotización fija</label><input v-model.number="form.cotizacion" type="number" step="0.00001" min="0" :class="fieldBase" /><p class="form-hint">0 = usa la del día.</p></div>
          <div class="flex flex-col gap-2 pt-6 text-sm">
            <label class="flex items-center gap-2"><input v-model="form.interno" type="checkbox" :true-value="1" :false-value="0" />Interno (no se ofrece en carga manual)</label>
          </div>
        </div>
      </div>

      <div class="card mb-6">
        <div class="card-header">
          <h3 class="card-title">Markup y comisión por alcance</h3>
          <button type="button" class="btn btn-secondary btn-sm" @click="form.comisiones.push(filaVacia())">+ Fila</button>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
              <tr><th class="px-3 py-2 text-left">Submódulo</th><th class="px-3 py-2 text-left">País</th><th class="px-3 py-2 text-left">Ciudad (id)</th><th class="px-3 py-2 text-left">Producto (id)</th><th class="px-3 py-2 text-left">Origen</th><th class="px-3 py-2 text-right">Divisor markup</th><th class="px-3 py-2 text-right">Comisión %</th><th class="px-3 py-2 text-left">Vigencia</th><th></th></tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="(c, i) in form.comisiones" :key="i" :class="c.general ? 'bg-amber-50/60' : ''">
                <td class="px-3 py-1.5" style="min-width: 150px">
                  <span v-if="c.general" class="text-sm font-medium text-amber-800">General (todos)</span>
                  <select v-else v-model="c.fk_submodulo_id" :class="cls"><option value="">Todos</option><option v-for="s in submodulos" :key="s.value" :value="s.value">{{ s.label }}</option></select>
                </td>
                <td class="px-3 py-1.5" style="min-width: 140px"><select v-if="!c.general" v-model.number="c.fk_pais_id" :class="cls"><option :value="0">Todos</option><option v-for="p in paises" :key="p.value" :value="p.value">{{ p.label }}</option></select></td>
                <td class="px-3 py-1.5"><input v-if="!c.general" v-model.number="c.fk_ciudad_id" type="number" min="0" :class="cls" style="width: 6rem" /></td>
                <td class="px-3 py-1.5"><input v-if="!c.general" v-model.number="c.fk_producto_id" type="number" min="0" :class="cls" style="width: 6rem" /></td>
                <td class="px-3 py-1.5"><input v-if="!c.general" v-model="c.origen" type="text" maxlength="3" :class="cls" style="width: 4rem" /></td>
                <td class="px-3 py-1.5"><input v-model.number="c.divisor_markup" type="number" step="0.0001" min="0.0001" :class="[cls, 'text-right', err(i, 'divisor_markup') ? 'border-red-400' : '']" style="width: 6rem" :title="err(i, 'divisor_markup')" /></td>
                <td class="px-3 py-1.5"><input v-model.number="c.porcentaje_comision" type="number" step="0.01" min="0" max="100" :class="[cls, 'text-right', err(i, 'porcentaje_comision') ? 'border-red-400' : '']" style="width: 5rem" /></td>
                <td class="whitespace-nowrap px-3 py-1.5"><template v-if="!c.general"><input v-model="c.vigencia_ini" type="date" :class="cls" style="width: 9rem" /> – <input v-model="c.vigencia_fin" type="date" :class="cls" style="width: 9rem" /></template></td>
                <td class="px-3 py-1.5 text-right"><button v-if="!c.general" type="button" class="text-xs text-red-600 hover:underline" @click="form.comisiones.splice(i, 1)">Quitar</button></td>
              </tr>
            </tbody>
          </table>
        </div>
        <p class="px-4 py-3 text-sm text-gray-500">La fila más específica gana: producto &gt; ciudad &gt; país &gt; submódulo &gt; general. Venta = costo ÷ divisor (0,8 = 25 % sobre el costo). Las fechas de vigencia se guardan pero hoy no filtran (decisión pendiente del negocio).</p>
        <p v-for="(m, k) in form.errors" v-show="String(k).startsWith('comisiones')" :key="k" class="px-4 pb-2 text-sm text-red-600">{{ m }}</p>
      </div>

      <div class="mb-10 flex gap-2">
        <button type="submit" class="btn btn-primary" :disabled="enviando">{{ enviando ? 'Guardando…' : 'Guardar' }}</button>
        <Link :href="config.baseUrl" class="btn btn-secondary">Cancelar</Link>
      </div>
    </form>
  </div>
</template>
