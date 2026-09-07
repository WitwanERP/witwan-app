<script setup>
import BuscadorRemoto from '@/Components/BuscadorRemoto.vue'
import CamposHabitaciones from './CamposHabitaciones.vue'
import { inputCls, inputSm, useGenerador } from './useGenerador'

/**
 * Formulario de búsqueda armado desde `pestana.campos` (config/reservas_busqueda.php):
 * hotel pide noches y habitaciones, traslado origen/destino, excursión un día y
 * pax, asistencia cobertura y mayores de 70. Un solo componente para todos los
 * tipos; lo particular está en la config y en el buscador del servidor.
 */
const g = useGenerador()
const { props, estado, tiene, faltantesBusqueda } = g
const f = estado.busqueda.form

const alternarStar = (s) => {
  f.stars = f.stars.includes(s) ? f.stars.filter((x) => x !== s) : [...f.stars, s].sort()
}
function setMenores(cantidad) {
  const n = Math.min(20, Math.max(0, Number(cantidad) || 0))
  const mn = [...f.mn]
  while (mn.length < n) mn.push(8)
  f.mn = mn.slice(0, n)
}
function setEdad(k, v) {
  const mn = [...f.mn]
  mn[k] = Math.min(17, Math.max(0, Number(v) || 0))
  f.mn = mn
}
const urlCiudades = () => `${props.baseUrl}/nueva/ciudades?tipo=${estado.busqueda.tipo}`
const urlProductos = () => `${props.baseUrl}/nueva/productos?tipo=${estado.busqueda.tipo}`
function enter(e) {
  if (e.key === 'Enter' && !faltantesBusqueda.value.length) g.buscar()
}
</script>

<template>
  <form class="grid grid-cols-2 md:grid-cols-6 gap-3 items-start" @submit.prevent="g.buscar()" @keydown="enter">
    <div v-if="tiene('from')">
      <label class="block text-sm mb-1 font-bold">{{ tiene('to') ? 'Desde' : 'Fecha' }}</label>
      <input v-model="f.from" type="date" :min="props.fechaMinima" :class="inputCls" />
    </div>
    <div v-if="tiene('to')">
      <label class="block text-sm mb-1 font-bold">Hasta</label>
      <input v-model="f.to" type="date" :min="f.from || props.fechaMinima" :class="inputCls" />
    </div>

    <div v-if="tiene('ciudad')" class="md:col-span-2">
      <label class="block text-sm mb-1 font-bold">Ciudad / destino</label>
      <BuscadorRemoto v-model="f.ciudad" v-model:etiqueta="f.ciudad_label" :url="urlCiudades()" placeholder="Buscar ciudad con productos de este tipo…" :clase-input="inputCls" />
    </div>
    <div v-if="tiene('origen')" class="md:col-span-2">
      <label class="block text-sm mb-1 font-bold">Origen</label>
      <BuscadorRemoto v-model="f.origen" v-model:etiqueta="f.origen_label" :url="`${props.baseUrl}/nueva/ciudades`" placeholder="Ciudad de origen…" :clase-input="inputCls" />
    </div>
    <div v-if="tiene('destino')" class="md:col-span-2">
      <label class="block text-sm mb-1">Destino</label>
      <BuscadorRemoto v-model="f.destino" v-model:etiqueta="f.destino_label" :url="`${props.baseUrl}/nueva/ciudades`" placeholder="Opcional" :clase-input="inputCls" />
    </div>
    <div v-if="tiene('nombre')" class="md:col-span-2">
      <label class="block text-sm mb-1">Nombre contiene</label>
      <input v-model="f.nombre" type="text" maxlength="100" placeholder="Parte del nombre del producto" :class="inputCls" />
    </div>
    <div class="md:col-span-2">
      <label class="block text-sm mb-1">Producto puntual</label>
      <BuscadorRemoto v-model="f.producto_id" v-model:etiqueta="f.producto_label" :url="urlProductos()" placeholder="Opcional: un producto concreto" :clase-input="inputCls" />
    </div>

    <div v-if="tiene('stars')" class="md:col-span-2">
      <label class="block text-sm mb-1">Estrellas</label>
      <div class="flex gap-1">
        <button v-for="s in [1, 2, 3, 4, 5]" :key="s" type="button" class="flex-1 rounded-md border px-2 py-2 text-sm" :class="f.stars.includes(s) ? 'border-amber-400 bg-amber-50 text-amber-800 font-semibold' : 'border-gray-300 bg-white text-gray-600 hover:bg-gray-50'" @click="alternarStar(s)">{{ s }}★</button>
      </div>
    </div>

    <div v-if="tiene('habitaciones')" class="col-span-2 md:col-span-6">
      <label class="block text-sm mb-1 font-bold">Habitaciones y pasajeros</label>
      <CamposHabitaciones v-model="f.habitaciones" />
    </div>

    <template v-if="tiene('ad')">
      <div><label class="block text-sm mb-1 font-bold">Adultos</label><input v-model.number="f.ad" type="number" min="1" max="50" :class="inputCls" /></div>
      <div v-if="tiene('mn')"><label class="block text-sm mb-1">Menores</label><input type="number" min="0" max="20" :value="f.mn.length" :class="inputCls" @input="setMenores($event.target.value)" /></div>
      <div v-if="tiene('mn') && f.mn.length" class="md:col-span-2 flex flex-wrap gap-2 items-end">
        <div v-for="(e, k) in f.mn" :key="k" class="w-16"><label class="block text-[11px] text-gray-500">Edad {{ k + 1 }}</label><input type="number" min="0" max="17" :value="e" :class="inputSm" @input="setEdad(k, $event.target.value)" /></div>
      </div>
      <div v-if="tiene('mayores70')"><label class="block text-sm mb-1">Mayores de 70</label><input v-model.number="f.mayores70" type="number" min="0" :max="f.ad" :class="inputCls" /><span class="text-xs text-gray-400">Incluidos en los adultos.</span></div>
    </template>

    <div class="col-span-2 md:col-span-6 flex items-center justify-between gap-3 flex-wrap pt-1">
      <p v-if="faltantesBusqueda.length" class="text-xs text-amber-700">Falta: {{ faltantesBusqueda.join(', ') }}.</p>
      <p v-else class="text-xs text-gray-400">Tarifario {{ estado.clienteInfo?.tarifario?.nombre || 'sin tarifario' }} · {{ estado.contexto.residente === 'R' ? 'residentes' : 'extranjeros' }}.</p>
      <button type="submit" class="btn btn-primary" :disabled="estado.busqueda.corriendo || faltantesBusqueda.length > 0">{{ estado.busqueda.corriendo ? 'Buscando…' : 'Buscar' }}</button>
    </div>
  </form>
</template>
