<script setup>
import { inputSm, useGenerador } from './useGenerador'

/**
 * Nómina única del file: cada pasajero se carga una vez y se asigna a los
 * servicios con un tilde (el CI y la v1 pedían una tabla de pasajeros por
 * servicio, con el titular copiado a mano en cada una). Al crear, `payload()`
 * expande la matriz a `servicios[i].pasajeros` que es lo que graba
 * servicio_nomina.
 */
const g = useGenerador()
const { estado } = g
const etiquetaLinea = (l, i) => `S${i + 1}`
</script>

<template>
  <section class="card">
    <div class="card-header">
      <h2 class="card-title">Nómina de pasajeros <span class="text-gray-400 font-normal">({{ estado.pasajeros.length }})</span></h2>
      <div class="flex gap-3 text-xs">
        <button type="button" class="text-blue-600 hover:underline" :disabled="!estado.contexto.titular_apellido && !estado.contexto.titular_nombre" @click="g.agregarTitular()">+ Titular</button>
        <button type="button" class="text-blue-600 hover:underline" @click="g.agregarPasajero()">+ Pasajero</button>
      </div>
    </div>
    <div class="card-body overflow-x-auto">
      <p v-if="!estado.pasajeros.length" class="text-sm text-gray-400">Sin pasajeros cargados. Se pueden agregar después desde el file.</p>
      <table v-else class="min-w-full text-sm">
        <thead>
          <tr class="text-left text-xs text-gray-500">
            <th class="pb-1 pr-2">Apellido</th>
            <th class="pb-1 pr-2">Nombre</th>
            <th class="pb-1 pr-2 w-24">Tipo</th>
            <th class="pb-1 pr-2 w-32">Documento</th>
            <th class="pb-1 pr-2 w-28">Nacionalidad</th>
            <th class="pb-1 pr-2 w-36">Nacimiento</th>
            <th v-for="(l, i) in estado.carrito" :key="l._uid" class="pb-1 px-1 text-center" :title="l.servicio_nombre">{{ etiquetaLinea(l, i) }}</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="p in estado.pasajeros" :key="p._uid" class="align-top">
            <td class="pr-2 pb-1"><input v-model="p.apellido" type="text" maxlength="100" :class="inputSm" /></td>
            <td class="pr-2 pb-1"><input v-model="p.nombre" type="text" maxlength="100" :class="inputSm" /></td>
            <td class="pr-2 pb-1"><select v-model="p.tipopax" :class="inputSm"><option v-for="t in g.props.opciones.tiposPax" :key="t.value" :value="t.value">{{ t.label }}</option></select></td>
            <td class="pr-2 pb-1"><input v-model="p.documento" type="text" maxlength="50" :class="inputSm" /></td>
            <td class="pr-2 pb-1"><input v-model="p.nacionalidad" type="text" maxlength="50" :class="inputSm" /></td>
            <td class="pr-2 pb-1"><input v-model="p.nacimiento" type="date" :class="inputSm" /></td>
            <td v-for="l in estado.carrito" :key="l._uid" class="px-1 pb-1 text-center">
              <input type="checkbox" class="mt-2" :checked="g.asignado(l, p._uid)" @change="g.alternarAsignacion(l, p._uid)" />
            </td>
            <td class="pb-1"><button type="button" class="text-xs text-red-600 hover:underline mt-2" @click="g.quitarPasajero(p._uid)">quitar</button></td>
          </tr>
        </tbody>
      </table>
      <p v-if="estado.pasajeros.length && estado.carrito.length" class="text-xs text-gray-400 mt-2">Tildá en qué servicios viaja cada pasajero. S1, S2… son los servicios del carrito en orden.</p>
    </div>
  </section>
</template>
