<script setup>
import { computed, watch } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
  opciones: { type: Object, default: () => ({}) },
  ciudades: { type: Array, default: () => [] },
  // En edición llega el pasajero con sus columnas; en alta es null.
  pasajero: { type: Object, default: null },
})

const esEdicion = computed(() => props.pasajero !== null)
const pasajeroId = props.pasajero?.pasajero_id ?? null
const yaEsCliente = computed(() => Number(props.pasajero?.fk_cliente_id ?? 0) > 0)

const inputCls = 'w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500'

const defaults = {
  // Datos personales
  pasajero_apellido: '',
  pasajero_nombre: '',
  pasajero_apodo: '',
  pasajero_nacionalidad: '',
  pasajero_nacimiento: '',
  pasajero_sexo: 'M',
  pasajero_email: '',
  fk_usuario_vendedor: 0,
  cargo: '',
  habilita: 'Y',
  freelance: 'N',
  observaciones: '',
  // Documento principal
  tipodoc: '',
  nrodoc: '',
  emisordoc: 0,
  emisorfecha: '',
  vencimientodoc: '',
  // Domicilio fiscal
  pasajero_direccionfiscal: '',
  pasajero_codigopostal: '',
  fk_pais_id: 0,
  pasajero_ciudad: '',
  fk_ciudad_id: 0,
  // Fiscal
  fk_tipoclavefiscal_id: 0,
  nro_clavefiscal: '',
  fk_condicioniva_id: 0,
  // Gastos
  fk_tarifario1_id: 0,
  fk_tarifario2_id: 0,
  fk_moneda_id: '',
  gastos_iva: 0,
  gastos_porcentaje_1: 0,
  gastos_fijo_1: 0,
  // Tilde crear cliente
  es_cliente: 0,
}

function valoresIniciales() {
  const base = { ...defaults }
  if (!props.pasajero) return base
  for (const k of Object.keys(defaults)) {
    const v = props.pasajero[k]
    if (v !== undefined && v !== null) {
      base[k] = typeof defaults[k] === 'number' ? Number(v) : v
    }
  }
  return base
}

const form = useForm(valoresIniciales())

// Ciudad depende del país: al cambiar, recargo solo la prop `ciudades`.
watch(
  () => form.fk_pais_id,
  (pais) => {
    form.fk_ciudad_id = 0
    router.reload({ only: ['ciudades'], data: { pais_id: pais }, preserveState: true, preserveScroll: true })
  },
)

const submit = () => {
  if (esEdicion.value) {
    form.put(`/app/pasajeros/${pasajeroId}`, { preserveScroll: true })
  } else {
    form.post('/app/pasajeros', { preserveScroll: true })
  }
}
</script>

<template>
  <div>
    <!-- Breadcrumb -->
    <nav class="flex items-center gap-2 text-sm text-gray-500 mb-4">
      <Link href="/app" class="hover:text-gray-700">Inicio</Link>
      <span>/</span>
      <span class="text-[#FF9900] font-medium">Configuración</span>
      <span>/</span>
      <Link href="/app/pasajeros" class="hover:text-gray-700">Pasajeros</Link>
      <span>/</span>
      <span class="text-gray-900 font-semibold">{{ esEdicion ? `Editar #${pasajeroId}` : 'Nuevo' }}</span>
    </nav>

    <div class="mb-4 rounded-md bg-amber-50 border border-amber-200 px-4 py-2.5 text-sm text-amber-800">
      Los campos en <b>negrita</b> son obligatorios.
    </div>

    <form @submit.prevent="submit">
      <!-- Barra de acciones -->
      <div class="flex items-center gap-2 mb-4">
        <button type="submit" :disabled="form.processing" class="inline-flex items-center gap-1.5 rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
          {{ form.processing ? 'Guardando…' : 'Guardar' }}
        </button>
        <Link href="/app/pasajeros" class="inline-flex items-center gap-1.5 rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
          Cancelar
        </Link>
      </div>

      <!-- Datos personales -->
      <section class="bg-white rounded-lg shadow-sm border border-gray-200 mb-4">
        <div class="px-5 py-3 border-b border-gray-200"><h2 class="font-semibold text-gray-800 uppercase">Datos personales</h2></div>
        <div class="p-5 grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="block text-sm mb-1 font-bold">Apellido</label>
            <input v-model="form.pasajero_apellido" type="text" :class="inputCls" />
            <p v-if="form.errors.pasajero_apellido" class="text-xs text-red-600 mt-1">{{ form.errors.pasajero_apellido }}</p>
          </div>
          <div>
            <label class="block text-sm mb-1 font-bold">Nombre</label>
            <input v-model="form.pasajero_nombre" type="text" :class="inputCls" />
            <p v-if="form.errors.pasajero_nombre" class="text-xs text-red-600 mt-1">{{ form.errors.pasajero_nombre }}</p>
          </div>
          <div>
            <label class="block text-sm mb-1">Apodo</label>
            <input v-model="form.pasajero_apodo" type="text" :class="inputCls" />
          </div>
          <div>
            <label class="block text-sm mb-1">Nacionalidad</label>
            <select v-model="form.pasajero_nacionalidad" :class="inputCls">
              <option value="">Seleccione una opción</option>
              <option v-for="p in opciones.paises" :key="p.pais_id" :value="p.pais_nombre">{{ p.pais_nombre }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm mb-1">Fecha de nacimiento</label>
            <input v-model="form.pasajero_nacimiento" type="text" placeholder="dd/mm/aaaa" :class="inputCls" />
          </div>
          <div>
            <label class="block text-sm mb-1">Sexo</label>
            <select v-model="form.pasajero_sexo" :class="inputCls">
              <option value="M">Masculino</option>
              <option value="F">Femenino</option>
            </select>
          </div>
          <div>
            <label class="block text-sm mb-1 font-bold">Email</label>
            <input v-model="form.pasajero_email" type="email" :class="inputCls" />
            <p v-if="form.errors.pasajero_email" class="text-xs text-red-600 mt-1">{{ form.errors.pasajero_email }}</p>
          </div>
          <div>
            <label class="block text-sm mb-1 font-bold">Vendedor</label>
            <select v-model.number="form.fk_usuario_vendedor" :class="inputCls">
              <option :value="0">Seleccione una opción</option>
              <option v-for="v in opciones.vendedores" :key="v.usuario_id" :value="v.usuario_id">{{ v.nombre }}</option>
            </select>
            <p v-if="form.errors.fk_usuario_vendedor" class="text-xs text-red-600 mt-1">{{ form.errors.fk_usuario_vendedor }}</p>
          </div>
          <div>
            <label class="block text-sm mb-1">Cargo</label>
            <input v-model="form.cargo" type="text" :class="inputCls" />
          </div>
          <div>
            <label class="block text-sm mb-1">Habilitado</label>
            <select v-model="form.habilita" :class="inputCls">
              <option value="Y">Sí</option>
              <option value="N">No</option>
            </select>
          </div>
          <div class="md:col-span-3">
            <label class="block text-sm mb-1">Observaciones</label>
            <input v-model="form.observaciones" type="text" :class="inputCls" />
          </div>
        </div>
      </section>

      <!-- Documento -->
      <section class="bg-white rounded-lg shadow-sm border border-gray-200 mb-4">
        <div class="px-5 py-3 border-b border-gray-200"><h2 class="font-semibold text-gray-800 uppercase">Documento</h2></div>
        <div class="p-5 grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="block text-sm mb-1">Tipo</label>
            <select v-model="form.tipodoc" :class="inputCls">
              <option value="">Seleccione una opción</option>
              <option value="DNI">DNI</option>
              <option value="Pasaporte">Pasaporte</option>
              <option value="Cedula de identidad">Cédula de identidad</option>
              <option value="Otro">Otro</option>
            </select>
          </div>
          <div>
            <label class="block text-sm mb-1">Número</label>
            <input v-model="form.nrodoc" type="text" :class="inputCls" />
          </div>
          <div>
            <label class="block text-sm mb-1">País emisor</label>
            <select v-model.number="form.emisordoc" :class="inputCls">
              <option :value="0">Seleccione una opción</option>
              <option v-for="p in opciones.paises" :key="p.pais_id" :value="p.pais_id">{{ p.pais_nombre }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm mb-1">Fecha de emisión</label>
            <input v-model="form.emisorfecha" type="text" placeholder="dd/mm/aaaa" :class="inputCls" />
          </div>
          <div>
            <label class="block text-sm mb-1">Vencimiento</label>
            <input v-model="form.vencimientodoc" type="text" placeholder="dd/mm/aaaa" :class="inputCls" />
          </div>
        </div>
      </section>

      <!-- Domicilio fiscal -->
      <section class="bg-white rounded-lg shadow-sm border border-gray-200 mb-4">
        <div class="px-5 py-3 border-b border-gray-200"><h2 class="font-semibold text-gray-800 uppercase">Domicilio fiscal</h2></div>
        <div class="p-5 grid grid-cols-1 md:grid-cols-3 gap-4">
          <div class="md:col-span-2">
            <label class="block text-sm mb-1">Dirección</label>
            <input v-model="form.pasajero_direccionfiscal" type="text" :class="inputCls" />
          </div>
          <div>
            <label class="block text-sm mb-1">Código postal</label>
            <input v-model="form.pasajero_codigopostal" type="text" :class="inputCls" />
          </div>
          <div>
            <label class="block text-sm mb-1">País</label>
            <select v-model.number="form.fk_pais_id" :class="inputCls">
              <option :value="0">Seleccione una opción</option>
              <option v-for="p in opciones.paises" :key="p.pais_id" :value="p.pais_id">{{ p.pais_nombre }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm mb-1">Ciudad</label>
            <select v-model.number="form.fk_ciudad_id" :class="inputCls" :disabled="!ciudades.length">
              <option :value="0">{{ ciudades.length ? 'Seleccione una opción' : 'Elegí un país primero' }}</option>
              <option v-for="c in ciudades" :key="c.ciudad_id" :value="c.ciudad_id">{{ c.ciudad_nombre }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm mb-1">Ciudad (texto)</label>
            <input v-model="form.pasajero_ciudad" type="text" :class="inputCls" />
          </div>
        </div>
      </section>

      <!-- Fiscal -->
      <section class="bg-white rounded-lg shadow-sm border border-gray-200 mb-4">
        <div class="px-5 py-3 border-b border-gray-200"><h2 class="font-semibold text-gray-800 uppercase">Fiscal</h2></div>
        <div class="p-5 grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="block text-sm mb-1">Tipo de clave fiscal</label>
            <select v-model.number="form.fk_tipoclavefiscal_id" :class="inputCls">
              <option :value="0">Seleccione una opción</option>
              <option v-for="t in opciones.tiposClaveFiscal" :key="t.tipoclavefiscal_id" :value="t.tipoclavefiscal_id">{{ t.tipoclavefiscal_nombre }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm mb-1">Nro. clave fiscal</label>
            <input v-model="form.nro_clavefiscal" type="text" :class="inputCls" />
          </div>
          <div>
            <label class="block text-sm mb-1">Condición IVA</label>
            <select v-model.number="form.fk_condicioniva_id" :class="inputCls">
              <option :value="0">Seleccione una opción</option>
              <option v-for="c in opciones.condicionesIva" :key="c.condicioniva_id" :value="c.condicioniva_id">{{ c.condicioniva_nombre }}</option>
            </select>
          </div>
        </div>
      </section>

      <!-- Gastos de reserva -->
      <section class="bg-white rounded-lg shadow-sm border border-gray-200 mb-4">
        <div class="px-5 py-3 border-b border-gray-200"><h2 class="font-semibold text-gray-800 uppercase">Gastos de reserva</h2></div>
        <div class="p-5 grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="block text-sm mb-1">Tarifario receptivo</label>
            <select v-model.number="form.fk_tarifario1_id" :class="inputCls">
              <option :value="0">Sin tarifario</option>
              <option v-for="t in opciones.tarifarios" :key="t.tarifario_id" :value="t.tarifario_id">{{ t.tarifario_nombre }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm mb-1">Tarifario mayorista</label>
            <select v-model.number="form.fk_tarifario2_id" :class="inputCls">
              <option :value="0">Sin tarifario</option>
              <option v-for="t in opciones.tarifarios" :key="t.tarifario_id" :value="t.tarifario_id">{{ t.tarifario_nombre }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm mb-1">Moneda</label>
            <select v-model="form.fk_moneda_id" :class="inputCls">
              <option value="">Seleccione una opción</option>
              <option v-for="m in opciones.monedas" :key="m.moneda_id" :value="m.moneda_id">{{ m.moneda_nombre }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm mb-1">IVA gastos (%)</label>
            <input v-model.number="form.gastos_iva" type="number" step="0.01" :class="inputCls" />
          </div>
          <div>
            <label class="block text-sm mb-1">Gasto % 1</label>
            <input v-model.number="form.gastos_porcentaje_1" type="number" step="0.01" :class="inputCls" />
          </div>
          <div>
            <label class="block text-sm mb-1">Gasto fijo 1</label>
            <input v-model.number="form.gastos_fijo_1" type="number" step="0.01" :class="inputCls" />
          </div>
        </div>
      </section>

      <!-- Crear cliente a partir del pasajero -->
      <section class="bg-white rounded-lg shadow-sm border border-gray-200 mb-4">
        <div class="px-5 py-3 border-b border-gray-200"><h2 class="font-semibold text-gray-800 uppercase">Cliente</h2></div>
        <div class="p-5">
          <p v-if="yaEsCliente" class="text-sm text-green-700">
            Este pasajero ya está vinculado a un cliente (#{{ pasajero.fk_cliente_id }}).
          </p>
          <label v-else class="flex items-start gap-2 cursor-pointer text-sm">
            <input type="checkbox" :true-value="1" :false-value="0" v-model="form.es_cliente" class="mt-0.5" />
            <span>
              Crear un cliente a partir de este pasajero.
              <span class="block text-xs text-gray-500">Si ya existe un cliente con el mismo nombre o documento fiscal, se vincula a ese (no se duplica).</span>
            </span>
          </label>
        </div>
      </section>

      <div class="flex items-center gap-2 mb-10">
        <button type="submit" :disabled="form.processing" class="inline-flex items-center gap-1.5 rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
          {{ form.processing ? 'Guardando…' : 'Guardar' }}
        </button>
        <Link href="/app/pasajeros" class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancelar</Link>
      </div>
    </form>
  </div>
</template>
