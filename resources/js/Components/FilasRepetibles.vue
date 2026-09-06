<script setup>
/**
 * Tabla editable de filas repetibles (contactos, documentos, teléfonos…):
 * réplica de los bloques "agregar fila" de los forms de cliente/pasajero del CI.
 * v-model: array de objetos con las claves de `columnas[].campo`.
 */
const props = defineProps({
  modelValue: { type: Array, default: () => [] },
  // [{ campo, label, tipo: 'text'|'select'|'date'|'checkbox'|'number', opciones: [{value,label}], ancho }]
  columnas: { type: Array, required: true },
  titulo: { type: String, default: '' },
  agregarLabel: { type: String, default: 'Agregar fila' },
})
const emit = defineEmits(['update:modelValue'])

const vacia = () => Object.fromEntries(props.columnas.map((c) => [c.campo, c.tipo === 'checkbox' ? 0 : '']))
const filas = () => props.modelValue.map((f) => ({ ...vacia(), ...f }))

function agregar() {
  emit('update:modelValue', [...filas(), vacia()])
}
function quitar(i) {
  emit('update:modelValue', filas().filter((_, k) => k !== i))
}
function set(i, campo, valor) {
  const f = filas()
  f[i] = { ...f[i], [campo]: valor }
  emit('update:modelValue', f)
}
const inputCls = 'w-full rounded-md border border-gray-300 px-2 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500'
</script>

<template>
  <div>
    <div v-if="titulo" class="flex items-center justify-between mb-2">
      <h3 class="text-sm font-semibold text-gray-800">{{ titulo }}</h3>
      <button type="button" class="text-xs text-blue-600 hover:underline" @click="agregar">+ {{ agregarLabel }}</button>
    </div>
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead v-if="modelValue.length">
          <tr class="text-left text-xs text-gray-500">
            <th v-for="c in columnas" :key="c.campo" class="pb-1 pr-2" :style="c.ancho ? { width: c.ancho } : null">{{ c.label }}</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(f, i) in modelValue" :key="i">
            <td v-for="c in columnas" :key="c.campo" class="pr-2 pb-1 align-top">
              <select v-if="c.tipo === 'select'" :value="f[c.campo] ?? ''" :class="inputCls" @change="set(i, c.campo, $event.target.value)">
                <option value="">--</option>
                <option v-for="o in c.opciones || []" :key="o.value" :value="o.value">{{ o.label }}</option>
              </select>
              <input v-else-if="c.tipo === 'checkbox'" type="checkbox" :checked="Number(f[c.campo]) === 1" class="mt-2" @change="set(i, c.campo, $event.target.checked ? 1 : 0)" />
              <input v-else :type="c.tipo === 'date' ? 'date' : c.tipo === 'number' ? 'number' : 'text'" :value="f[c.campo] ?? ''" :class="inputCls" :placeholder="c.placeholder || ''" @input="set(i, c.campo, $event.target.value)" />
            </td>
            <td class="pb-1 align-top"><button type="button" class="text-xs text-red-600 hover:underline mt-2" @click="quitar(i)">quitar</button></td>
          </tr>
        </tbody>
      </table>
      <div v-if="!modelValue.length" class="text-xs text-gray-400 py-1">Sin filas. <button type="button" class="text-blue-600 hover:underline" @click="agregar">{{ agregarLabel }}</button></div>
      <div v-else-if="!titulo" class="pt-1"><button type="button" class="text-xs text-blue-600 hover:underline" @click="agregar">+ {{ agregarLabel }}</button></div>
    </div>
  </div>
</template>
