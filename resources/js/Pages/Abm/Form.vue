<script setup>
import { computed } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
  config: { type: Object, required: true },
  // En edición llega el registro con sus columnas; en alta es null.
  registro: { type: Object, default: null },
})

const esEdicion = computed(() => props.registro !== null)
const registroId = computed(() => (props.registro ? props.registro[props.config.pk] : null))

const inputCls = 'w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500'

function valorInicial(campo) {
  if (props.registro && props.registro[campo.campo] !== undefined && props.registro[campo.campo] !== null) {
    const v = props.registro[campo.campo]
    return campo.tipo === 'number' || campo.tipo === 'checkbox' || campo.tipo === 'select' ? Number(v) || (campo.tipo === 'select' ? v : 0) : v
  }
  if (campo.default !== undefined) return campo.default
  return campo.tipo === 'number' || campo.tipo === 'checkbox' ? 0 : campo.tipo === 'select' ? 0 : ''
}

const inicial = {}
for (const c of props.config.campos) inicial[c.campo] = valorInicial(c)
const form = useForm(inicial)

const submit = () => {
  if (esEdicion.value) {
    form.put(`${props.config.baseUrl}/${registroId.value}`, { preserveScroll: true })
  } else {
    form.post(props.config.baseUrl, { preserveScroll: true })
  }
}
</script>

<template>
  <div>
    <nav class="flex items-center gap-2 text-sm text-gray-500 mb-4">
      <Link href="/app" class="hover:text-gray-700">Inicio</Link>
      <span>/</span>
      <span class="text-[#FF9900] font-medium">Configuración</span>
      <span>/</span>
      <Link :href="config.baseUrl" class="hover:text-gray-700">{{ config.titulo }}</Link>
      <span>/</span>
      <span class="text-gray-900 font-semibold">{{ esEdicion ? `Editar #${registroId}` : 'Nuevo' }}</span>
    </nav>

    <form @submit.prevent="submit">
      <div class="flex items-center gap-2 mb-4">
        <button type="submit" :disabled="form.processing" class="inline-flex items-center gap-1.5 rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
          {{ form.processing ? 'Guardando…' : 'Guardar' }}
        </button>
        <Link :href="config.baseUrl" class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancelar</Link>
      </div>

      <section class="bg-white rounded-lg shadow-sm border border-gray-200 mb-4">
        <div class="px-5 py-3 border-b border-gray-200"><h2 class="font-semibold text-gray-800">{{ esEdicion ? `Editar ${config.singular}` : `Nuevo ${config.singular}` }}</h2></div>
        <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-4">
          <div v-for="campo in config.campos" :key="campo.campo" :class="campo.tipo === 'textarea' ? 'md:col-span-2' : ''">
            <label class="block text-sm mb-1" :class="{ 'font-bold': campo.required }">{{ campo.label }}</label>

            <!-- checkbox -->
            <label v-if="campo.tipo === 'checkbox'" class="flex items-center gap-2 text-sm h-9">
              <input type="checkbox" :true-value="1" :false-value="0" v-model="form[campo.campo]" />
              <span class="text-gray-500">{{ campo.label }}</span>
            </label>

            <!-- select -->
            <select v-else-if="campo.tipo === 'select'" v-model.number="form[campo.campo]" :class="inputCls">
              <option :value="0">{{ campo.required ? 'Seleccione una opción' : '(sin asignar)' }}</option>
              <option v-for="o in config.opciones[campo.opciones] || []" :key="o.value" :value="o.value">{{ o.label }}</option>
            </select>

            <!-- textarea -->
            <textarea v-else-if="campo.tipo === 'textarea'" v-model="form[campo.campo]" rows="3" :class="inputCls"></textarea>

            <!-- number -->
            <input v-else-if="campo.tipo === 'number'" v-model.number="form[campo.campo]" type="number" :class="inputCls" />

            <!-- date -->
            <input v-else-if="campo.tipo === 'date'" v-model="form[campo.campo]" type="date" :class="inputCls" />

            <!-- text -->
            <input v-else v-model="form[campo.campo]" type="text" :class="inputCls" />

            <p v-if="campo.ayuda" class="text-xs text-gray-400 mt-1">{{ campo.ayuda }}</p>
            <p v-if="form.errors[campo.campo]" class="text-xs text-red-600 mt-1">{{ form.errors[campo.campo] }}</p>
          </div>
        </div>
      </section>

      <div class="flex items-center gap-2 mb-10">
        <button type="submit" :disabled="form.processing" class="inline-flex items-center gap-1.5 rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
          {{ form.processing ? 'Guardando…' : 'Guardar' }}
        </button>
        <Link :href="config.baseUrl" class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancelar</Link>
      </div>
    </form>
  </div>
</template>
