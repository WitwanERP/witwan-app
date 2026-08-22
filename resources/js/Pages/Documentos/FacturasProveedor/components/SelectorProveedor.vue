<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
  modelValue: { type: [Number, String], default: '' },
  baseUrl: { type: String, required: true },
  disabled: { type: Boolean, default: false },
  error: { type: String, default: '' },
})

// `cuenta` es el `proveedor.iata`, que el legacy usaba como data-cuenta para
// preseleccionar la cuenta contable al elegir proveedor.
const emit = defineEmits(['update:modelValue', 'cuenta'])

const texto = ref('')
const sugerencias = ref([])
const abierto = ref(false)
const buscando = ref(false)
let debounce = null

const fieldBase =
  'w-full rounded-lg border border-gray-300 bg-gray-50 py-2 px-3 text-sm text-gray-800 placeholder-gray-400 transition focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20'

function buscar() {
  clearTimeout(debounce)
  abierto.value = true
  debounce = setTimeout(async () => {
    if (texto.value.trim().length < 2) {
      sugerencias.value = []
      return
    }
    buscando.value = true
    try {
      const r = await fetch(`${props.baseUrl}/proveedores?q=${encodeURIComponent(texto.value)}`)
      sugerencias.value = r.ok ? await r.json() : []
    } finally {
      buscando.value = false
    }
  }, 300)
}

function elegir(p) {
  emit('update:modelValue', p.id)
  emit('cuenta', p.cuenta)
  texto.value = p.label
  sugerencias.value = []
  abierto.value = false
}

function limpiar() {
  emit('update:modelValue', '')
  texto.value = ''
  sugerencias.value = []
}

watch(
  () => props.modelValue,
  (v) => {
    if (!v) texto.value = ''
  }
)
</script>

<template>
  <div class="relative">
    <label class="form-label font-bold">Proveedor</label>
    <input
      v-model="texto"
      type="text"
      :class="fieldBase"
      :disabled="disabled"
      placeholder="Buscar por nombre o CUIT…"
      autocomplete="off"
      @input="buscar"
      @focus="abierto = true"
    />
    <button
      v-if="modelValue && !disabled"
      type="button"
      class="absolute right-2 top-8 text-gray-400 hover:text-gray-700"
      aria-label="Quitar proveedor"
      @click="limpiar"
    >
      &times;
    </button>

    <ul
      v-if="abierto && sugerencias.length"
      class="absolute z-30 mt-1 max-h-56 w-full overflow-auto rounded-lg border border-gray-200 bg-white shadow-lg"
    >
      <li
        v-for="p in sugerencias"
        :key="p.id"
        class="cursor-pointer px-3 py-2 text-sm hover:bg-blue-50"
        @click="elegir(p)"
      >
        {{ p.label }}
      </li>
    </ul>

    <p v-if="buscando" class="form-hint">Buscando…</p>
    <p v-if="error" class="form-error">{{ error }}</p>
  </div>
</template>
