<script setup>
import BuscadorRemoto from '@/Components/BuscadorRemoto.vue'
import ResumenCliente from './ResumenCliente.vue'
import { inputCls, useGenerador } from './useGenerador'

/**
 * Paso 1: quién compra. El cliente y el tipo de pasajero (residente/extranjero)
 * fijan el tarifario, el markup y qué vigencias aplican, por eso van antes que
 * cualquier producto. El cliente se busca por autocomplete remoto: el combo del
 * CI no escala con bases de miles de clientes.
 */
const { props, estado, elegirCliente, contextoListo, faltantesContexto, siguiente } = useGenerador()
const c = estado.contexto
</script>

<template>
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    <section class="card lg:col-span-2">
      <div class="card-header"><h2 class="card-title">Cliente y tipo de pasajero</h2></div>
      <div class="card-body grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="md:col-span-3">
          <label class="block text-sm mb-1 font-bold">Cliente</label>
          <BuscadorRemoto
            :model-value="c.fk_cliente_id"
            :etiqueta="c.cliente_label"
            :url="`${props.baseUrl}/nueva/clientes`"
            placeholder="Nombre, razón social o número de cliente…"
            :clase-input="inputCls"
            @update:model-value="(id) => !id && elegirCliente(0, '')"
            @elegido="(op) => elegirCliente(op.id, op.label)"
          />
          <p v-if="estado.errorCliente" class="text-xs text-red-600 mt-1">{{ estado.errorCliente }}</p>
          <p v-else class="text-xs text-gray-400 mt-1">Sólo clientes habilitados para reservar en {{ props.area }}.</p>
        </div>
        <div>
          <label class="block text-sm mb-1 font-bold">Pasajeros</label>
          <div class="flex rounded-md border border-gray-300 overflow-hidden text-sm">
            <button type="button" class="flex-1 px-2 py-2" :class="c.residente === 'N' ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'" @click="c.residente = 'N'">Extranjeros</button>
            <button type="button" class="flex-1 px-2 py-2 border-l border-gray-300" :class="c.residente === 'R' ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'" @click="c.residente = 'R'">Residentes</button>
          </div>
          <p class="text-xs text-gray-400 mt-1">Define qué vigencias y qué IVA aplican.</p>
        </div>

        <div>
          <label class="block text-sm mb-1 font-bold">Moneda del file</label>
          <select v-model="c.fk_moneda_id" :class="inputCls"><option v-for="m in props.opciones.monedas" :key="m.value" :value="m.value">{{ m.label }}</option></select>
        </div>
        <div>
          <label class="block text-sm mb-1">{{ props.opciones.escritorio.length ? 'Escritorio' : 'Vendedor' }}</label>
          <select v-model="c.agente" :class="inputCls">
            <option :value="0">(yo / vendedor del cliente)</option>
            <option v-for="u in props.opciones.escritorio.length ? props.opciones.escritorio : props.opciones.vendedores" :key="u.value" :value="u.value">{{ u.label }}</option>
          </select>
        </div>
        <div>
          <label class="block text-sm mb-1">Vencimiento del file</label>
          <input v-model="c.fecha_vencimiento" type="date" :class="inputCls" />
          <span class="text-xs text-gray-400">Vacío = hoy.</span>
        </div>
        <div class="md:col-span-4 border-t border-gray-100 pt-3 mt-1">
          <h3 class="text-sm font-semibold text-gray-800 mb-2">Titular <span class="text-gray-400 font-normal">(se puede completar al confirmar)</span></h3>
          <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div><label class="block text-sm mb-1">Apellido</label><input v-model="c.titular_apellido" type="text" maxlength="150" :class="inputCls" /></div>
            <div><label class="block text-sm mb-1">Nombre</label><input v-model="c.titular_nombre" type="text" maxlength="150" :class="inputCls" /></div>
            <div><label class="block text-sm mb-1">Email</label><input v-model="c.titular_email" type="email" maxlength="50" :class="inputCls" /></div>
            <div><label class="block text-sm mb-1">Celular</label><input v-model="c.titular_celular" type="text" maxlength="50" :class="inputCls" /></div>
          </div>
        </div>
      </div>
      <div class="card-footer flex items-center justify-between gap-3">
        <ul v-if="faltantesContexto.length" class="text-xs text-amber-700 list-disc ml-4"><li v-for="f in faltantesContexto" :key="f">{{ f }}</li></ul>
        <span v-else class="text-xs text-green-700">Listo para buscar productos.</span>
        <button type="button" class="btn btn-primary" :disabled="!contextoListo" @click="siguiente">Buscar productos →</button>
      </div>
    </section>

    <ResumenCliente />
  </div>
</template>
