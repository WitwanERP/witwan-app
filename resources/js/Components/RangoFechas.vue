<script setup>
import { computed } from 'vue'

/**
 * Rango de fechas como UN control, no dos inputs sueltos.
 *
 * El legacy los presenta pegados con el widget `input-daterange` de
 * bootstrap-datepicker, pero por debajo el filtro se descarta entero si el
 * "desde" viene vacío (Form.php:1112, el guardia `!empty($value)`): cargás sólo
 * el "hasta" y te devuelve todo, sin avisar. Ese es el resultado fallido.
 *
 * Acá los dos extremos se completan solos y, sobre todo, se completan A LA VISTA:
 * el valor aparece en el input, así el usuario ve exactamente qué se va a
 * consultar y lo puede cambiar. Rellenarlo por detrás en el servidor volvería a
 * esconder el criterio real.
 */
const props = defineProps({
  modelValue: { type: Object, default: () => ({ desde: '', hasta: '' }) },
  label: { type: String, required: true },
  /** Meses hacia atrás con los que se completa el "desde" si sólo se cargó el "hasta". */
  mesesAtras: { type: Number, default: 12 },
})

const emit = defineEmits(['update:modelValue'])

const desde = computed(() => props.modelValue?.desde ?? '')
const hasta = computed(() => props.modelValue?.hasta ?? '')

const hayAlgo = computed(() => !!desde.value || !!hasta.value)

const hoy = () => new Date().toISOString().slice(0, 10)

function restarMeses(iso, meses) {
  const d = new Date(iso + 'T00:00:00')
  d.setMonth(d.getMonth() - meses)
  return d.toISOString().slice(0, 10)
}

function emitir(nuevoDesde, nuevoHasta) {
  emit('update:modelValue', { desde: nuevoDesde, hasta: nuevoHasta })
}

function cambiarDesde(valor) {
  if (!valor) return emitir('', hasta.value)

  // Sin el otro extremo, el rango quedaría abierto: se cierra en hoy, que es lo
  // que hace el legacy cuando el "hasta" viene vacío (format_date('') devuelve
  // la fecha actual).
  let nuevoHasta = hasta.value || hoy()
  if (nuevoHasta < valor) nuevoHasta = valor

  emitir(valor, nuevoHasta)
}

function cambiarHasta(valor) {
  if (!valor) return emitir(desde.value, '')

  let nuevoDesde = desde.value || restarMeses(valor, props.mesesAtras)
  if (nuevoDesde > valor) nuevoDesde = valor

  emitir(nuevoDesde, valor)
}

function limpiar() {
  emitir('', '')
}
</script>

<template>
  <div>
    <label class="form-label">{{ label }}</label>

    <!-- El borde y el anillo de foco van en el contenedor: los dos campos se
         leen como una sola cosa. -->
    <div
      class="flex items-center rounded-lg border border-gray-300 bg-gray-50 transition focus-within:border-blue-500 focus-within:bg-white focus-within:ring-2 focus-within:ring-blue-500/20"
    >
      <input
        :value="desde"
        type="date"
        class="w-full min-w-0 border-0 bg-transparent px-3 py-2 text-sm text-gray-800 focus:outline-none"
        :max="hasta || undefined"
        :aria-label="`${label}: desde`"
        @change="cambiarDesde($event.target.value)"
      />

      <span class="select-none px-1 text-gray-400" aria-hidden="true">→</span>

      <input
        :value="hasta"
        type="date"
        class="w-full min-w-0 border-0 bg-transparent px-3 py-2 text-sm text-gray-800 focus:outline-none"
        :min="desde || undefined"
        :aria-label="`${label}: hasta`"
        @change="cambiarHasta($event.target.value)"
      />

      <button
        v-if="hayAlgo"
        type="button"
        class="px-2 text-gray-400 hover:text-gray-700"
        :aria-label="`Limpiar ${label}`"
        title="Limpiar el rango"
        @click="limpiar"
      >
        &times;
      </button>
    </div>
  </div>
</template>
