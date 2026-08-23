<script setup>
import { reactive, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import RangoFechas from '@/Components/RangoFechas.vue'

/**
 * Filtros del listado de asientos.
 *
 * El CI sólo deja filtrar por número y por fecha (`display` con 'filter' en
 * asientocontable.php:33 y :43). Usuario, moneda y estado se muestran en la
 * grilla pero no se pueden filtrar, así que encontrar "el asiento que anuló
 * Fulano en marzo" era mirar página por página. Acá están los cinco.
 */
const props = defineProps({
  filtros: { type: Object, default: () => ({}) },
  opciones: { type: Object, required: true },
  config: { type: Object, required: true },
})

const abierto = ref(true)

const form = reactive({
  numero: props.filtros.numero ?? '',
  usuario: props.filtros.usuario ?? '',
  moneda: props.filtros.moneda ?? '',
  status: props.filtros.status ?? '',
  proyecto: props.filtros.proyecto ?? '',
  observaciones: props.filtros.observaciones ?? '',
})

const rango = reactive({
  desde: props.filtros.fecha_desde ?? '',
  hasta: props.filtros.fecha_hasta ?? '',
})

const fieldBase =
  'w-full rounded-lg border border-gray-300 bg-gray-50 py-2 px-3 text-sm text-gray-800 placeholder-gray-400 transition focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20'

function aplicar() {
  const params = {}
  for (const [k, v] of Object.entries(form)) if (v !== '' && v !== null) params[k] = v
  if (rango.desde) params.fecha_desde = rango.desde
  if (rango.hasta) params.fecha_hasta = rango.hasta

  router.get(props.config.baseUrl, params, { preserveState: true, preserveScroll: true, replace: true })
}

function limpiar() {
  for (const k of Object.keys(form)) form[k] = ''
  rango.desde = ''
  rango.hasta = ''
  router.get(props.config.baseUrl, {}, { preserveState: true, preserveScroll: true, replace: true })
}

/** Atajo: el mes en curso, que es el filtro que se usa el 90% de las veces. */
function esteMes() {
  const hoy = new Date()
  const iso = (d) =>
    [d.getFullYear(), String(d.getMonth() + 1).padStart(2, '0'), String(d.getDate()).padStart(2, '0')].join('-')

  rango.desde = iso(new Date(hoy.getFullYear(), hoy.getMonth(), 1))
  rango.hasta = iso(hoy)
  aplicar()
}
</script>

<template>
  <div class="card mb-4">
    <div class="card-header flex items-center justify-between">
      <h3 class="card-title">Filtros</h3>
      <button type="button" class="text-sm text-blue-600 hover:underline" @click="abierto = !abierto">
        {{ abierto ? 'Ocultar' : 'Mostrar' }}
      </button>
    </div>

    <div v-show="abierto" class="card-body">
      <form @submit.prevent="aplicar">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3 lg:grid-cols-4">
          <RangoFechas v-model="rango" label="Fecha del asiento" />

          <div>
            <label class="form-label">Número</label>
            <input v-model="form.numero" type="text" :class="fieldBase" placeholder="Contiene…" />
          </div>

          <div>
            <label class="form-label">Usuario</label>
            <select v-model="form.usuario" :class="fieldBase">
              <option value="">Todos</option>
              <option v-for="u in opciones.usuarios" :key="u.id" :value="u.id">{{ u.label }}</option>
            </select>
          </div>

          <div>
            <label class="form-label">Moneda</label>
            <select v-model="form.moneda" :class="fieldBase">
              <option value="">Todas</option>
              <option v-for="m in opciones.monedas" :key="m.id" :value="m.id">{{ m.id }}</option>
            </select>
          </div>

          <div>
            <label class="form-label">Estado</label>
            <select v-model="form.status" :class="fieldBase">
              <option value="">Todos</option>
              <option v-for="(label, valor) in opciones.estados" :key="valor" :value="valor">{{ label }}</option>
            </select>
          </div>

          <div v-if="config.usaProyecto">
            <label class="form-label">Proyecto</label>
            <select v-model="form.proyecto" :class="fieldBase">
              <option value="">Todos</option>
              <option v-for="p in opciones.proyectos" :key="p.id" :value="p.id">{{ p.label }}</option>
            </select>
          </div>

          <div>
            <label class="form-label">Observaciones</label>
            <input v-model="form.observaciones" type="text" :class="fieldBase" placeholder="Contiene…" />
          </div>
        </div>

        <p class="form-hint mt-3">
          Al cargar un extremo del rango, el otro se completa solo para que la consulta quede acotada.
        </p>

        <div class="mt-4 flex flex-wrap gap-2">
          <button type="submit" class="btn btn-primary">Filtrar</button>
          <button type="button" class="btn btn-secondary" @click="esteMes">Este mes</button>
          <button type="button" class="btn btn-secondary" @click="limpiar">Limpiar</button>
        </div>
      </form>
    </div>
  </div>
</template>
