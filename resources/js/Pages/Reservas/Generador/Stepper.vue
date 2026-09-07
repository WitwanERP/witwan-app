<script setup>
import { useGenerador } from './useGenerador'

const { estado, PASOS, puedeIr, irA } = useGenerador()
const indice = (key) => PASOS.findIndex((p) => p.key === key)
</script>

<template>
  <ol class="flex flex-wrap items-stretch gap-2 mb-5">
    <li v-for="(p, i) in PASOS" :key="p.key" class="flex-1 min-w-[160px]">
      <button
        type="button"
        class="w-full text-left rounded-lg border px-3 py-2 transition"
        :class="[
          estado.paso === p.key ? 'border-blue-500 bg-blue-50 shadow-sm' : indice(estado.paso) > i ? 'border-green-300 bg-green-50' : 'border-gray-200 bg-white',
          puedeIr(p.key) ? 'cursor-pointer hover:border-blue-400' : 'cursor-not-allowed opacity-60',
        ]"
        :disabled="!puedeIr(p.key)"
        @click="irA(p.key)"
      >
        <div class="flex items-center gap-2">
          <span
            class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-bold"
            :class="estado.paso === p.key ? 'bg-blue-600 text-white' : indice(estado.paso) > i ? 'bg-green-600 text-white' : 'bg-gray-200 text-gray-700'"
          >{{ i + 1 }}</span>
          <span class="text-sm font-semibold text-gray-900">{{ p.label }}</span>
        </div>
        <p class="text-xs text-gray-500 mt-1 truncate">{{ p.descripcion }}</p>
      </button>
    </li>
  </ol>
</template>
