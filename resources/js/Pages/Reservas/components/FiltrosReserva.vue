<script setup>
import { ref } from 'vue'

const props = defineProps({
  form: { type: Object, required: true },
  config: { type: Object, required: true },
  opciones: { type: Object, required: true },
})
const emit = defineEmits(['aplicar', 'limpiar'])

const abierto = ref(true)
const field =
  'w-full rounded-lg border border-gray-300 bg-gray-50 py-2 px-3 text-sm text-gray-800 focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20'

// Autocomplete de cliente
const clienteResultados = ref([])
let debounce = null
function buscarCliente(e) {
  const q = e.target.value
  props.form.clienteLabel = q
  clearTimeout(debounce)
  if (!q || q.length < 2) {
    clienteResultados.value = []
    return
  }
  debounce = setTimeout(async () => {
    const res = await fetch(`${props.config.baseUrl}/clientes?q=${encodeURIComponent(q)}`, {
      headers: { Accept: 'application/json' },
    })
    clienteResultados.value = res.ok ? await res.json() : []
  }, 300)
}
function elegirCliente(c) {
  props.form.cliente = c.value
  props.form.clienteLabel = c.label
  clienteResultados.value = []
}
function limpiarCliente() {
  props.form.cliente = ''
  props.form.clienteLabel = ''
  clienteResultados.value = []
}
</script>

<template>
  <div class="card">
    <div class="card-header">
      <h3 class="card-title">
        <button type="button" class="hover:text-blue-600" @click="abierto = !abierto">
          {{ abierto ? '▾' : '▸' }} Filtros
        </button>
      </h3>
      <div class="flex gap-2">
        <button type="button" class="btn btn-secondary btn-sm" @click="emit('limpiar')">Limpiar</button>
        <button type="button" class="btn btn-primary btn-sm" @click="emit('aplicar')">Filtrar</button>
      </div>
    </div>

    <div v-show="abierto" class="card-body space-y-4">
      <!-- Código + tipos + estados -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div>
          <label class="form-label">Código</label>
          <input v-model="form.codigo" type="text" :class="field" placeholder="Código (use * para varios)" @keyup.enter="emit('aplicar')" />
        </div>

        <div v-if="config.interno">
          <label class="form-label">Tipo de reserva</label>
          <div class="flex flex-wrap gap-2">
            <label v-for="t in opciones.tipos" :key="t" class="inline-flex items-center gap-1 text-sm">
              <input v-model="form.tipo" type="checkbox" :value="t" /> {{ t }}
            </label>
          </div>
        </div>

        <div>
          <label class="form-label">Estado</label>
          <div class="flex flex-wrap gap-3">
            <label v-for="e in opciones.estados" :key="e.value" class="inline-flex items-center gap-1 text-sm">
              <input v-model="form.status" type="checkbox" :value="e.value" /> {{ e.label }}
            </label>
          </div>
        </div>
      </div>

      <!-- Selects internos -->
      <div v-if="config.interno" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
        <div class="relative">
          <label class="form-label">Cliente</label>
          <input :value="form.clienteLabel" type="text" :class="field" placeholder="Buscar cliente…" @input="buscarCliente" />
          <button v-if="form.cliente" type="button" class="absolute right-2 top-8 text-xs text-red-500" @click="limpiarCliente">✕</button>
          <ul v-if="clienteResultados.length" class="absolute z-10 mt-1 w-full max-h-48 overflow-auto rounded-lg border border-gray-200 bg-white shadow">
            <li v-for="c in clienteResultados" :key="c.value" class="cursor-pointer px-3 py-1.5 text-sm hover:bg-blue-50" @click="elegirCliente(c)">{{ c.label }}</li>
          </ul>
        </div>
        <div>
          <label class="form-label">Vendedor asignado</label>
          <select v-model="form.vendedor" :class="field"><option value="">Todos</option><option v-for="o in opciones.vendedores" :key="o.value" :value="o.value">{{ o.label }}</option></select>
        </div>
        <div>
          <label class="form-label">Reserva efectuada por</label>
          <select v-model="form.usuario" :class="field"><option value="">Todos</option><option v-for="o in opciones.vendedores" :key="o.value" :value="o.value">{{ o.label }}</option></select>
        </div>
        <div>
          <label class="form-label">Responsable</label>
          <select v-model="form.responsable" :class="field"><option value="">Todos</option><option v-for="o in opciones.responsables" :key="o.value" :value="o.value">{{ o.label }}</option></select>
        </div>
        <div>
          <label class="form-label">Proveedor</label>
          <select v-model="form.proveedor" :class="field"><option value="">Todos</option><option v-for="o in opciones.proveedores" :key="o.value" :value="o.value">{{ o.label }}</option></select>
        </div>
        <div>
          <label class="form-label">Prestador</label>
          <select v-model="form.prestador" :class="field"><option value="">Todos</option><option v-for="o in opciones.proveedores" :key="o.value" :value="o.value">{{ o.label }}</option></select>
        </div>
        <div>
          <label class="form-label">Cadena hotelera</label>
          <select v-model="form.cadena" :class="field"><option value="">Todas</option><option v-for="o in opciones.cadenas" :key="o.value" :value="o.value">{{ o.label }}</option></select>
        </div>
        <div>
          <label class="form-label">Representante</label>
          <select v-model="form.representante" :class="field"><option value="">Todos</option><option v-for="o in opciones.representantes" :key="o.value" :value="o.value">{{ o.label }}</option></select>
        </div>
        <div>
          <label class="form-label">Negocio</label>
          <select v-model="form.negocio" :class="field"><option value="">Todos</option><option v-for="o in opciones.negocios" :key="o.value" :value="o.value">{{ o.label }}</option></select>
        </div>
        <div v-if="config.flags.cadenacliente_operativo">
          <label class="form-label">Cadena Cliente</label>
          <select v-model="form.cadenacliente" :class="field"><option value="">Todas</option><option v-for="o in opciones.cadenacliente || []" :key="o.value" :value="o.value">{{ o.label }}</option></select>
        </div>
        <div v-if="config.flags.operativos">
          <label class="form-label">Operativo</label>
          <select v-model="form.operativo" :class="field"><option value="">Todos</option><option v-for="o in opciones.operativos || []" :key="o.value" :value="o.value">{{ o.label }}</option></select>
        </div>
      </div>

      <!-- Búsquedas de texto -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
        <div><label class="form-label">Titular</label><input v-model="form.titular" type="text" :class="field" @keyup.enter="emit('aplicar')" /></div>
        <div><label class="form-label">Ticket</label><input v-model="form.ticket" type="text" :class="field" @keyup.enter="emit('aplicar')" /></div>
        <div><label class="form-label">Rec. Locator</label><input v-model="form.recloc" type="text" :class="field" @keyup.enter="emit('aplicar')" /></div>
        <div><label class="form-label">Nro. confirmación</label><input v-model="form.nro_confirmacion" type="text" :class="field" @keyup.enter="emit('aplicar')" /></div>
        <div v-if="config.interno"><label class="form-label">Factura</label><input v-model="form.factura" type="text" :class="field" @keyup.enter="emit('aplicar')" /></div>
        <div><label class="form-label">Código externo</label><input v-model="form.codigo_externo" type="text" :class="field" @keyup.enter="emit('aplicar')" /></div>
        <div>
          <label class="form-label">Tipo de producto</label>
          <select v-model="form.tipoproducto" :class="field"><option value="">Todos</option><option v-for="o in opciones.tipoproducto" :key="o.value" :value="o.value">{{ o.label }}</option></select>
        </div>
      </div>

      <!-- Fechas -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
        <div>
          <label class="form-label">Tipo de fecha</label>
          <select v-model="form.tipofecha" :class="field"><option v-for="o in opciones.tipofecha" :key="o.value" :value="o.value">{{ o.label }}</option></select>
        </div>
        <div><label class="form-label">Desde</label><input v-model="form.from" type="date" :class="field" /></div>
        <div><label class="form-label">Hasta</label><input v-model="form.to" type="date" :class="field" /></div>
      </div>
      <div v-if="config.interno" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
        <div><label class="form-label">Factura desde</label><input v-model="form.facturafrom" type="date" :class="field" /></div>
        <div><label class="form-label">Factura hasta</label><input v-model="form.facturato" type="date" :class="field" /></div>
      </div>

      <!-- Radios / checkboxes -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <div v-if="config.interno && !config.flags.pericia">
          <label class="form-label">Facturación</label>
          <div class="flex flex-wrap gap-3 text-sm">
            <label class="inline-flex items-center gap-1"><input v-model="form.solofacturado" type="radio" value="1" /> Facturado</label>
            <label v-if="!config.flags.facturado_med" class="inline-flex items-center gap-1"><input v-model="form.solofacturado" type="radio" value="3" /> Parcial</label>
            <label class="inline-flex items-center gap-1"><input v-model="form.solofacturado" type="radio" value="2" /> Sin facturar</label>
            <label class="inline-flex items-center gap-1"><input v-model="form.solofacturado" type="radio" value="" /> Ind.</label>
          </div>
        </div>
        <div>
          <label class="form-label">Residente</label>
          <div class="flex flex-wrap gap-3 text-sm">
            <label class="inline-flex items-center gap-1"><input v-model="form.residente" type="radio" value="1" /> Sí</label>
            <label class="inline-flex items-center gap-1"><input v-model="form.residente" type="radio" value="0" /> No</label>
            <label class="inline-flex items-center gap-1"><input v-model="form.residente" type="radio" value="" /> Ind.</label>
          </div>
        </div>
        <div>
          <label class="form-label">Cobranza</label>
          <div class="flex flex-wrap gap-3 text-sm">
            <label class="inline-flex items-center gap-1"><input v-model="form.solopagos" type="radio" value="1" /> Con cobro</label>
            <label class="inline-flex items-center gap-1"><input v-model="form.solopagos" type="radio" value="0" /> Sin cobro</label>
            <label class="inline-flex items-center gap-1"><input v-model="form.solopagos" type="radio" value="" /> Ind.</label>
          </div>
        </div>
        <div v-if="config.flags.marcar_reprogramado && config.interno">
          <label class="form-label">Enviado a devolución</label>
          <div class="flex flex-wrap gap-3 text-sm">
            <label class="inline-flex items-center gap-1"><input v-model="form.mostrarreprogramados" type="radio" value="1" /> Sí</label>
            <label class="inline-flex items-center gap-1"><input v-model="form.mostrarreprogramados" type="radio" value="2" /> No</label>
            <label class="inline-flex items-center gap-1"><input v-model="form.mostrarreprogramados" type="radio" value="" /> Todos</label>
          </div>
        </div>
        <div v-if="config.flags.fileauditado">
          <label class="form-label">Auditados</label>
          <div class="flex flex-wrap gap-3 text-sm">
            <label class="inline-flex items-center gap-1"><input v-model="form.auditado" type="radio" value="1" /> Auditados</label>
            <label class="inline-flex items-center gap-1"><input v-model="form.auditado" type="radio" value="0" /> No auditados</label>
            <label class="inline-flex items-center gap-1"><input v-model="form.auditado" type="radio" value="2" /> Todos</label>
          </div>
        </div>
        <div class="flex items-end gap-4">
          <label v-if="config.interno" class="inline-flex items-center gap-1 text-sm"><input v-model="form.soloovencidas" type="checkbox" value="1" /> Solo vencidas</label>
          <label v-if="config.interno && !config.flags.pericia" class="inline-flex items-center gap-1 text-sm"><input v-model="form.soloocultas" type="checkbox" value="1" /> Solo ocultas</label>
        </div>
      </div>
    </div>
  </div>
</template>
