<script setup>
import { computed } from 'vue'

/**
 * Un campo del formulario de producto, renderizado según su `tipo` de
 * config/productos.php. Los tipos con persistencia propia (bases, facilidades,
 * destinos, ciudad) también se resuelven acá para que Form.vue no conozca la
 * lista de tipos.
 */
const props = defineProps({
  campo: { type: Object, required: true },
  modelValue: { default: null },
  opciones: { type: Object, default: () => ({}) },
  error: { type: String, default: '' },
})

const emit = defineEmits(['update:modelValue'])

const fieldBase =
  'w-full rounded-lg border border-gray-300 bg-gray-50 py-2 px-3 text-sm text-gray-800 transition focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20'

const valor = computed({
  get: () => props.modelValue,
  set: (v) => emit('update:modelValue', v),
})

/** Las listas vienen como [v1, v2] o {valor: label}. */
const lista = computed(() => {
  const l = props.campo.lista
  if (!l) return []
  return Array.isArray(l) ? l.map((x) => ({ value: x, label: String(x) })) : Object.entries(l).map(([value, label]) => ({ value: isNaN(Number(value)) ? value : Number(value), label }))
})

const catalogo = computed(() => (props.campo.opciones ? props.opciones[props.campo.opciones] || [] : []))

const BASES = ['1', '2', '3', '4', '5', '6', '7', '8', '9']

function toggleLista(arr, item) {
  const i = arr.indexOf(item)
  if (i >= 0) arr.splice(i, 1)
  else arr.push(item)
  emit('update:modelValue', [...arr])
}

function agregarDestino() {
  emit('update:modelValue', [...(props.modelValue || []), { ciudad_id: '', tipo: 'D' }])
}
function quitarDestino(i) {
  const copia = [...props.modelValue]
  copia.splice(i, 1)
  emit('update:modelValue', copia)
}
</script>

<template>
  <div v-if="campo.tipo === 'titulo'" class="md:col-span-2 mt-2 border-b border-gray-200 pb-1">
    <h4 class="text-sm font-semibold uppercase tracking-wide text-gray-500">{{ campo.label }}</h4>
  </div>

  <div v-else :class="['textarea', 'itinerario', 'destinos', 'facilidades'].includes(campo.tipo) ? 'md:col-span-2' : ''">
    <label class="form-label" :class="campo.required ? 'font-bold' : ''">{{ campo.label }}</label>

    <!-- boolean -->
    <label v-if="campo.tipo === 'boolean'" class="flex h-9 items-center gap-2 text-sm">
      <input type="checkbox" :true-value="1" :false-value="0" v-model="valor" />
      <span class="text-gray-500">Sí</span>
    </label>

    <!-- radio -->
    <div v-else-if="campo.tipo === 'radio'" class="flex flex-wrap gap-4 pt-1 text-sm">
      <label v-for="o in lista" :key="o.value" class="flex items-center gap-1.5"><input type="radio" :value="o.value" v-model="valor" />{{ o.label }}</label>
    </div>

    <!-- select con lista fija o catálogo -->
    <select v-else-if="campo.tipo === 'select' || campo.tipo === 'ciudad'" v-model="valor" :class="fieldBase">
      <option :value="campo.tipo === 'ciudad' || campo.opciones ? 0 : ''">{{ campo.required ? 'Seleccione…' : '—' }}</option>
      <option v-for="o in campo.lista ? lista : catalogo" :key="o.value" :value="o.value">{{ o.label }}</option>
    </select>

    <!-- bases: cantidad de adultos -->
    <div v-else-if="campo.tipo === 'bases'" class="flex flex-wrap gap-2">
      <label v-for="b in BASES" :key="b" class="cursor-pointer select-none rounded-md border px-3 py-1.5 text-sm" :class="(valor || []).map(String).includes(b) ? 'border-blue-600 bg-blue-600 text-white' : 'border-gray-300 bg-white text-gray-600'">
        <input type="checkbox" class="sr-only" :checked="(valor || []).map(String).includes(b)" @change="toggleLista((valor || []).map(String), b)" />{{ b }}
      </label>
      <p class="w-full form-hint">Cantidad de adultos por habitación que se tarifan (single, doble, triple…).</p>
    </div>

    <!-- facilidades: catálogo multi -->
    <div v-else-if="campo.tipo === 'facilidades'" class="grid grid-cols-2 gap-1 md:grid-cols-4">
      <label v-for="o in catalogo" :key="o.value" class="flex items-center gap-1.5 text-sm"><input type="checkbox" :checked="(valor || []).includes(o.value)" @change="toggleLista([...(valor || [])], o.value)" />{{ o.label }}</label>
      <p v-if="catalogo.length === 0" class="form-hint col-span-full">Sin facilidades cargadas en el catálogo.</p>
    </div>

    <!-- destinos: ciudades con tipo D/O -->
    <div v-else-if="campo.tipo === 'destinos'" class="space-y-2">
      <div v-for="(d, i) in valor || []" :key="i" class="flex items-center gap-2">
        <select v-model="d.ciudad_id" :class="fieldBase" class="flex-1"><option value="">Seleccione ciudad…</option><option v-for="o in catalogo" :key="o.value" :value="o.value">{{ o.label }}</option></select>
        <select v-model="d.tipo" class="w-32 rounded-lg border border-gray-300 bg-gray-50 py-2 px-3 text-sm"><option value="D">Destino</option><option value="O">Origen</option></select>
        <button type="button" class="text-sm text-red-600 hover:underline" @click="quitarDestino(i)">Quitar</button>
      </div>
      <button type="button" class="btn btn-secondary btn-sm" @click="agregarDestino">+ Ciudad</button>
    </div>

    <!-- textarea / itinerario -->
    <textarea v-else-if="campo.tipo === 'textarea' || campo.tipo === 'itinerario'" v-model="valor" :rows="campo.tipo === 'itinerario' ? 8 : 3" :class="fieldBase"></textarea>

    <!-- number -->
    <input v-else-if="campo.tipo === 'number'" v-model.number="valor" type="number" step="any" :class="fieldBase" />

    <!-- text / url -->
    <input v-else v-model="valor" :type="campo.tipo === 'url' ? 'url' : 'text'" :maxlength="campo.max || 255" :class="fieldBase" />

    <p v-if="campo.ayuda" class="form-hint">{{ campo.ayuda }}</p>
    <p v-if="error" class="form-error">{{ error }}</p>
  </div>
</template>
