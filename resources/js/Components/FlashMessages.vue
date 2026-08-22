<script setup>
import { computed, ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'

// HandleInertiaRequests ya comparte `flash` en todas las páginas, pero hasta
// ahora nadie lo renderizaba: los ->with('success', ...) de Clientes, Pasajeros y
// los ABMs se perdían en silencio.
const page = usePage()
const visible = ref(false)

const mensaje = computed(() => {
  const flash = page.props.flash || {}
  if (flash.error) return { texto: flash.error, tipo: 'error' }
  if (flash.success) return { texto: flash.success, tipo: 'success' }
  return null
})

let timer = null
watch(
  mensaje,
  (m) => {
    clearTimeout(timer)
    visible.value = !!m
    // Los errores se quedan hasta que el usuario los cierre; los éxitos se van solos.
    if (m && m.tipo === 'success') timer = setTimeout(() => (visible.value = false), 5000)
  },
  { immediate: true }
)
</script>

<template>
  <Transition
    enter-active-class="transition duration-200 ease-out"
    enter-from-class="opacity-0 -translate-y-2"
    leave-active-class="transition duration-150 ease-in"
    leave-to-class="opacity-0"
  >
    <div
      v-if="visible && mensaje"
      class="fixed right-4 top-20 z-50 max-w-md rounded-lg border px-4 py-3 shadow-lg"
      :class="
        mensaje.tipo === 'error'
          ? 'border-red-200 bg-red-50 text-red-800'
          : 'border-green-200 bg-green-50 text-green-800'
      "
      role="status"
      aria-live="polite"
    >
      <div class="flex items-start gap-3">
        <span class="text-sm">{{ mensaje.texto }}</span>
        <button
          type="button"
          class="ml-auto text-lg leading-none opacity-60 hover:opacity-100"
          aria-label="Cerrar"
          @click="visible = false"
        >
          &times;
        </button>
      </div>
    </div>
  </Transition>
</template>
