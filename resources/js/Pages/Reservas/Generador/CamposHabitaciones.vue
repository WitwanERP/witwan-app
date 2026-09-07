<script setup>
import { inputSm } from './useGenerador'

/**
 * Habitaciones de la búsqueda de alojamiento: adultos y edades de los menores
 * por habitación (el Tarifador cotiza cada una por separado y deriva INF/MN/JNR
 * de la edad). Réplica del bloque `habitaciones` de filtro_hoteles.php.
 */
const props = defineProps({ modelValue: { type: Array, required: true }, max: { type: Number, default: 6 } })
const emit = defineEmits(['update:modelValue'])

const set = (v) => emit('update:modelValue', v)
function agregar() {
  if (props.modelValue.length >= props.max) return
  set([...props.modelValue, { ad: 2, mn: [] }])
}
function quitar(i) {
  if (props.modelValue.length === 1) return
  set(props.modelValue.filter((_, k) => k !== i))
}
function setAd(i, v) {
  const h = props.modelValue.map((x) => ({ ...x }))
  h[i].ad = Math.min(9, Math.max(1, Number(v) || 1))
  set(h)
}
function setMenores(i, cantidad) {
  const h = props.modelValue.map((x) => ({ ...x, mn: [...x.mn] }))
  const n = Math.min(5, Math.max(0, Number(cantidad) || 0))
  while (h[i].mn.length < n) h[i].mn.push(8)
  h[i].mn = h[i].mn.slice(0, n)
  set(h)
}
function setEdad(i, k, v) {
  const h = props.modelValue.map((x) => ({ ...x, mn: [...x.mn] }))
  h[i].mn[k] = Math.min(17, Math.max(0, Number(v) || 0))
  set(h)
}
</script>

<template>
  <div class="space-y-2">
    <div v-for="(h, i) in modelValue" :key="i" class="flex flex-wrap items-end gap-2 rounded-md border border-gray-200 bg-gray-50 px-2 py-2">
      <span class="text-xs font-semibold text-gray-600 w-full sm:w-auto">Hab. {{ i + 1 }}</span>
      <div class="w-20"><label class="block text-[11px] text-gray-500">Adultos</label><input type="number" min="1" max="9" :value="h.ad" :class="inputSm" @input="setAd(i, $event.target.value)" /></div>
      <div class="w-20"><label class="block text-[11px] text-gray-500">Menores</label><input type="number" min="0" max="5" :value="h.mn.length" :class="inputSm" @input="setMenores(i, $event.target.value)" /></div>
      <div v-for="(e, k) in h.mn" :key="k" class="w-16"><label class="block text-[11px] text-gray-500">Edad {{ k + 1 }}</label><input type="number" min="0" max="17" :value="e" :class="inputSm" @input="setEdad(i, k, $event.target.value)" /></div>
      <button v-if="modelValue.length > 1" type="button" class="text-xs text-red-600 hover:underline ml-auto" @click="quitar(i)">quitar</button>
    </div>
    <button v-if="modelValue.length < max" type="button" class="text-xs text-blue-600 hover:underline" @click="agregar">+ Habitación</button>
  </div>
</template>
