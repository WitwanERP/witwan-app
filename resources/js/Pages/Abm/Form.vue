<script setup>
import { computed, watch } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { useEnvio } from '@/lib/envio'

defineOptions({ layout: AppLayout })

const props = defineProps({
  config: { type: Object, required: true },
  // En edición llega el registro con sus columnas; en alta es null.
  registro: { type: Object, default: null },
})

const esEdicion = computed(() => props.registro !== null)
const registroId = computed(() => (props.registro ? props.registro[props.config.pk] : null))

// Color del área en el breadcrumb (réplica de los folders coloreados de la botonera del CI).
const COLOR_AREA = { Configuración: '#FF9900', Administración: '#c9a300', Receptivo: '#66CC00', Operador: '#FF33FF', Minorista: '#00B5B5' }
const colorArea = computed(() => COLOR_AREA[props.config.area] || '#FF9900')

const inputCls = 'w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 disabled:bg-gray-100 disabled:text-gray-500'

// Opciones de un select/radio: lista inline o clave del catálogo; con dependeDe
// se filtran por el valor del campo padre (país -> ciudad, como el 'update' del CI).
function todasLasOpciones(campo) {
  if (Array.isArray(campo.opciones)) return campo.opciones
  return props.config.opciones[campo.opciones] || []
}

function opcionesDe(campo) {
  const todas = todasLasOpciones(campo)
  if (!campo.dependeDe) return todas
  const padre = form[campo.dependeDe]
  if (padre === '' || padre === null || padre === undefined || padre === 0) return []
  return todas.filter((o) => String(o.padre) === String(padre))
}

// Valor "vacío" del select: 0 si las opciones son numéricas (FKs legacy usan 0), '' si son códigos.
function vacio(campo) {
  if (campo.vacio !== undefined) return campo.vacio
  const primera = todasLasOpciones(campo)[0]
  return primera && typeof primera.value === 'string' ? '' : 0
}

function valorInicial(campo) {
  if (props.registro && props.registro[campo.campo] !== undefined && props.registro[campo.campo] !== null) {
    const v = props.registro[campo.campo]
    if (campo.tipo === 'select' || campo.tipo === 'radio') {
      const opcion = todasLasOpciones(campo).find((o) => String(o.value) === String(v))
      return opcion ? opcion.value : v === '' || v === '0' || v === 0 ? vacio(campo) : v
    }
    if (campo.tipo === 'number' || campo.tipo === 'checkbox') return Number(v) || 0
    if (campo.tipo === 'date') return String(v).slice(0, 10)
    return v
  }
  if (campo.default !== undefined) return campo.default
  if (campo.tipo === 'select' || campo.tipo === 'radio') return vacio(campo)
  return campo.tipo === 'number' || campo.tipo === 'checkbox' ? 0 : ''
}

const inicial = {}
for (const c of props.config.campos) inicial[c.campo] = valorInicial(c)
const form = useForm(inicial)

// Si cambia el padre de un select dependiente y el valor actual ya no aplica, se limpia.
for (const c of props.config.campos) {
  if (!c.dependeDe) continue
  watch(
    () => form[c.dependeDe],
    () => {
      const validas = opcionesDe(c).map((o) => String(o.value))
      if (!validas.includes(String(form[c.campo]))) form[c.campo] = vacio(c)
    },
  )
}

const { enviando, enviar } = useEnvio()

const submit = () => {
  enviar(
    (opciones) =>
      esEdicion.value
        ? form.put(`${props.config.baseUrl}/${registroId.value}`, opciones)
        : form.post(props.config.baseUrl, opciones),
    { preserveScroll: true },
  )
}
</script>

<template>
  <div>
    <nav class="flex items-center gap-2 text-sm text-gray-500 mb-4">
      <Link href="/app" class="hover:text-gray-700">Inicio</Link>
      <span>/</span>
      <span class="font-medium" :style="{ color: colorArea }">{{ config.area }}</span>
      <span>/</span>
      <Link :href="config.baseUrl" class="hover:text-gray-700">{{ config.titulo }}</Link>
      <span>/</span>
      <span class="text-gray-900 font-semibold">{{ esEdicion ? `Editar #${registroId}` : 'Nuevo' }}</span>
    </nav>

    <form @submit.prevent="submit">
      <div class="flex items-center gap-2 mb-4">
        <button type="submit" :disabled="enviando" class="inline-flex items-center gap-1.5 rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
          {{ enviando ? 'Guardando…' : 'Guardar' }}
        </button>
        <Link :href="config.baseUrl" class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancelar</Link>
      </div>

      <section class="bg-white rounded-lg shadow-sm border border-gray-200 mb-4">
        <div class="px-5 py-3 border-b border-gray-200"><h2 class="font-semibold text-gray-800">{{ esEdicion ? `Editar ${config.singular}` : `Nuevo ${config.singular}` }}</h2></div>
        <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-4">
          <div v-for="campo in config.campos" :key="campo.campo" :class="campo.tipo === 'textarea' || campo.ancho === 'completo' ? 'md:col-span-2' : ''">
            <label class="block text-sm mb-1" :class="{ 'font-bold': campo.required }">{{ campo.label }}</label>

            <!-- checkbox -->
            <label v-if="campo.tipo === 'checkbox'" class="flex items-center gap-2 text-sm h-9">
              <input type="checkbox" :true-value="1" :false-value="0" v-model="form[campo.campo]" :disabled="esEdicion && campo.soloAlta" />
              <span class="text-gray-500">{{ campo.label }}</span>
            </label>

            <!-- radio -->
            <div v-else-if="campo.tipo === 'radio'" class="flex flex-wrap items-center gap-4 h-9 text-sm">
              <label v-for="o in opcionesDe(campo)" :key="o.value" class="flex items-center gap-1.5">
                <input type="radio" :name="campo.campo" :value="o.value" v-model="form[campo.campo]" :disabled="esEdicion && campo.soloAlta" />
                <span>{{ o.label }}</span>
              </label>
            </div>

            <!-- select -->
            <select v-else-if="campo.tipo === 'select'" v-model="form[campo.campo]" :class="inputCls" :disabled="esEdicion && campo.soloAlta">
              <option :value="vacio(campo)">{{ campo.required ? 'Seleccione una opción' : '(sin asignar)' }}</option>
              <option v-for="o in opcionesDe(campo)" :key="o.value" :value="o.value">{{ o.label }}</option>
            </select>

            <!-- textarea -->
            <textarea v-else-if="campo.tipo === 'textarea'" v-model="form[campo.campo]" rows="3" :class="inputCls" :disabled="esEdicion && campo.soloAlta"></textarea>

            <!-- number -->
            <input v-else-if="campo.tipo === 'number'" v-model.number="form[campo.campo]" type="number" :class="inputCls" :disabled="esEdicion && campo.soloAlta" />

            <!-- decimal -->
            <input v-else-if="campo.tipo === 'decimal'" v-model="form[campo.campo]" type="number" step="any" :class="inputCls" :disabled="esEdicion && campo.soloAlta" />

            <!-- date -->
            <input v-else-if="campo.tipo === 'date'" v-model="form[campo.campo]" type="date" :class="inputCls" :disabled="esEdicion && campo.soloAlta" />

            <!-- text -->
            <input v-else v-model="form[campo.campo]" type="text" :class="inputCls" :maxlength="campo.max || undefined" :disabled="esEdicion && campo.soloAlta" />

            <p v-if="campo.ayuda" class="text-xs text-gray-400 mt-1">{{ campo.ayuda }}</p>
            <p v-if="form.errors[campo.campo]" class="text-xs text-red-600 mt-1">{{ form.errors[campo.campo] }}</p>
          </div>
        </div>
      </section>

      <div class="flex items-center gap-2 mb-10">
        <button type="submit" :disabled="enviando" class="inline-flex items-center gap-1.5 rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
          {{ enviando ? 'Guardando…' : 'Guardar' }}
        </button>
        <Link :href="config.baseUrl" class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancelar</Link>
      </div>
    </form>
  </div>
</template>
