<script setup>
import { ref, watch } from 'vue'

/**
 * Autocomplete contra un endpoint JSON que devuelve `[{id, label}]`.
 *
 * Reemplaza a los cuatro autocompletes del legacy (`.autoc`, `.autocli`,
 * `.autopro`, `.autofile` de scriptasientocta.js), que eran el mismo jQuery UI
 * cuatro veces con la URL cambiada.
 *
 * El valor del modelo es el id; el texto visible se guarda aparte. Eso importa:
 * en el legacy el input de texto y el hidden del id se desincronizaban —borrabas
 * el nombre a mano y el id seguía puesto, así que la línea se imputaba a una
 * cuenta que ya no decía en pantalla—. Acá borrar el texto limpia el id.
 */
const props = defineProps({
  modelValue: { type: [Number, String], default: 0 },
  etiqueta: { type: String, default: '' },
  url: { type: String, required: true },
  placeholder: { type: String, default: 'Buscar…' },
  minimo: { type: Number, default: 2 },
  disabled: { type: Boolean, default: false },
  claseInput: { type: String, default: '' },
})

const emit = defineEmits(['update:modelValue', 'update:etiqueta', 'elegido'])

const texto = ref(props.etiqueta ?? '')
const sugerencias = ref([])
const abierto = ref(false)
const buscando = ref(false)
let debounce = null

watch(
  () => props.etiqueta,
  (v) => {
    if (v !== texto.value) texto.value = v ?? ''
  }
)

function buscar() {
  // Si el usuario edita el texto, el id anterior deja de valer: si no, la línea
  // se guardaría contra algo distinto de lo que se ve.
  if (props.modelValue) {
    emit('update:modelValue', 0)
    emit('update:etiqueta', texto.value)
  }

  clearTimeout(debounce)

  if (texto.value.length < props.minimo) {
    sugerencias.value = []
    abierto.value = false

    return
  }

  debounce = setTimeout(async () => {
    buscando.value = true
    try {
      const r = await fetch(`${props.url}?q=${encodeURIComponent(texto.value)}`, {
        headers: { Accept: 'application/json' },
      })
      sugerencias.value = r.ok ? await r.json() : []
      abierto.value = true
    } catch {
      sugerencias.value = []
    } finally {
      buscando.value = false
    }
  }, 250)
}

function elegir(op) {
  texto.value = op.label
  sugerencias.value = []
  abierto.value = false
  emit('update:modelValue', op.id)
  emit('update:etiqueta', op.label)
  emit('elegido', op)
}

/**
 * El cierre al perder el foco va diferido: sin la demora, el blur del input
 * desmonta la lista antes de que el click sobre una opción llegue a dispararse.
 */
function cerrarDiferido() {
  setTimeout(() => (abierto.value = false), 150)
}

function limpiar() {
  texto.value = ''
  sugerencias.value = []
  abierto.value = false
  emit('update:modelValue', 0)
  emit('update:etiqueta', '')
}
</script>

<template>
  <div class="relative">
    <input
      v-model="texto"
      type="text"
      autocomplete="off"
      :disabled="disabled"
      :placeholder="placeholder"
      :class="claseInput"
      :aria-expanded="abierto"
      @input="buscar"
      @focus="sugerencias.length && (abierto = true)"
      @blur="cerrarDiferido"
    />

    <button
      v-if="texto && !disabled"
      type="button"
      class="absolute right-1 top-1/2 -translate-y-1/2 px-1 text-gray-400 hover:text-gray-700"
      aria-label="Limpiar"
      tabindex="-1"
      @mousedown.prevent="limpiar"
    >
      &times;
    </button>

    <ul
      v-if="abierto && sugerencias.length"
      class="absolute z-30 mt-1 max-h-56 w-full min-w-max overflow-auto rounded-lg border border-gray-200 bg-white text-sm shadow-lg"
    >
      <li
        v-for="op in sugerencias"
        :key="op.id"
        class="cursor-pointer px-3 py-2 hover:bg-blue-50"
        @mousedown.prevent="elegir(op)"
      >
        {{ op.label }}
      </li>
    </ul>

    <span v-if="buscando" class="absolute right-6 top-1/2 -translate-y-1/2 text-xs text-gray-400">…</span>
  </div>
</template>
