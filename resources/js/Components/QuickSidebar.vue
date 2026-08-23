<script setup>
import { XMarkIcon } from '@heroicons/vue/24/outline'

defineProps({ open: Boolean })
defineEmits(['close'])
</script>

<template>
  <!-- page-quick-sidebar: panel de "Ayuda en línea" que abre el ícono de soporte. -->
  <div
    class="fixed bottom-0 right-0 top-[42px] z-40 w-[280px] transform bg-mt-sub text-[13px] text-mt-text shadow-2xl transition-transform duration-300"
    :class="open ? 'translate-x-0' : 'translate-x-full'"
  >
    <button
      type="button"
      class="absolute right-2 top-2 p-1 text-mt-muted hover:text-white"
      title="Cerrar"
      @click="$emit('close')"
    >
      <XMarkIcon class="h-5 w-5" />
    </button>

    <div class="px-4 pt-10">
      <h3 class="font-bold uppercase text-white">Ayuda en línea</h3>
      <p class="mt-4">
        <a
          href="https://ayuda.witwan.com"
          target="_blank"
          rel="noopener"
          class="text-white underline-offset-2 hover:underline"
        >
          Ingresá a nuestra Ayuda en línea &raquo;
        </a>
      </p>
    </div>
  </div>
</template>
