<script setup>
import { ref, watch } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
  opciones: { type: Object, default: () => ({}) },
  ciudades: { type: Array, default: () => [] },
})

// Clases reutilizables (estilo CI: inputs full-width, labels en negrita = obligatorio).
const inputCls = 'w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500'

const form = useForm({
  // Cliente
  cliente_pasajerodirecto: 0,
  cliente_razonsocial: '',
  cliente_nombre: '',
  cliente_legajo: '',
  fk_usuario_vendedor: 0,
  habilita: 'Y',
  clienteminorista: 0,
  comentarios: '',
  // Domicilio
  cliente_direccionfiscal: '',
  cliente_codigopostal: '',
  fk_pais_id: 0,
  fk_ciudad_id: 0,
  cliente_provincia: '',
  cliente_ciudad: '',
  // Contacto
  cliente_telefono: '',
  cliente_fax: '',
  cliente_email: '',
  cliente_email2: '',
  cliente_emailadmin: '',
  // Fiscal
  cuit: '',
  cuit_confirmado: 0,
  cuit_internacional: '',
  fk_tipoclavefiscal_id: 0,
  nro_clavefiscal: '',
  fk_condicioniva_id: 0,
  fk_tipofactura_id: 0,
  iata: '',
  // Comercial
  fk_idioma_id: '',
  fk_moneda_id: '',
  limite_credito: 0,
  plazo_pago: 0,
  fk_cadenacliente_id: 0,
  consolidador: 'N',
  // Tarifarios
  fk_tarifario1_id: 0,
  fk_tarifario2_id: 0,
  fk_tarifario3_id: 0,
  // Vendedores / promotores
  fk_usuario_promotor1: 0,
  fk_usuario_promotor2: 0,
  fk_usuario_promotor3: 0,
  fk_usuario_promotor4: 0,
  // Gastos de reserva
  fk_moneda_id_gastos: '',
  gastos_iva: 0,
  gastos_porcentaje_1: 0,
  gastos_porcentaje_2: 0,
  gastos_porcentaje_3: 0,
  gastos_fijo_1: 0,
  gastos_fijo_2: 0,
  gastos_fijo_3: 0,
  gastos_fijo_moneda: '',
  // Flags
  freelance: 'N',
  representante_geografico: 'N',
  usar_logo: 'N',
  autorizaws: 0,
  cliente_promo: 0,
  cliente_web: 0,
  nombre_representante: '',
  // Repetibles
  contactos: [],
  tarjetas: [],
})

// Cliente existente con el mismo CUIT (null = sin conflicto). Mientras esté seteado,
// el alta queda bloqueada hasta tildar el checkbox de confirmación (form.cuit_confirmado).
const cuitDuplicado = ref(null)

const limpiarCuit = (v) => (v || '').replace(/[-.\s]/g, '')

// Chequeo en vivo: al salir del campo CUIT consultamos si ya existe.
const chequearCuit = async () => {
  const cuit = limpiarCuit(form.cuit)
  if (!cuit) {
    cuitDuplicado.value = null
    return
  }
  try {
    const res = await fetch(`/app/clientes/chequear-cuit?cuit=${encodeURIComponent(cuit)}`, {
      headers: { Accept: 'application/json' },
    })
    const data = res.ok ? await res.json() : { existe: false }
    cuitDuplicado.value = data.existe ? { nombre: data.nombre, cliente_id: data.cliente_id } : null
  } catch (e) {
    // Si el chequeo falla, no bloqueamos: el backend valida la unicidad igual.
    cuitDuplicado.value = null
  }
}

// Al editar el CUIT se invalida el aviso y la confirmación previa (se re-chequea al próximo blur).
watch(
  () => form.cuit,
  () => {
    cuitDuplicado.value = null
    form.cuit_confirmado = 0
  },
)

// Ciudad depende del país: al cambiar, recargo solo la prop `ciudades`.
watch(
  () => form.fk_pais_id,
  (pais) => {
    form.fk_ciudad_id = 0
    router.reload({ only: ['ciudades'], data: { pais_id: pais }, preserveState: true, preserveScroll: true })
  },
)

const addContacto = () =>
  form.contactos.push({ cliente_cont_nombre: '', cliente_cont_cargo: '', cliente_email: '', cliente_telefono: '', cliente_cont_emailsend: 0 })
const addTarjeta = () =>
  form.tarjetas.push({ cliente_tarjeta_num: '', cliente_tarjeta_banco: '', cliente_tarjeta_venc: '', cliente_tarjeta_cs: '', cliente_tarjeta_empresa: '' })

// Antes de guardar: si el CUIT existe y no se confirmó, mostramos el aviso y no enviamos.
// El formato (CUIT/CUIL en AR, RUT en CL) lo valida el backend y vuelve como form.errors.cuit.
const submit = async () => {
  // Re-chequeo por si el usuario no disparó el blur (ej. apretó Enter).
  if (cuitDuplicado.value === null) {
    await chequearCuit()
  }

  if (cuitDuplicado.value && !form.cuit_confirmado) {
    return // queda visible la advertencia + checkbox
  }

  form.post('/app/clientes', { preserveScroll: true })
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
      <Link href="/app/clientes" class="hover:text-gray-700">Clientes</Link>
      <span>/</span>
      <span class="text-gray-900 font-semibold">Nuevo</span>
    </nav>

    <!-- Aviso obligatorios -->
    <div class="mb-4 rounded-md bg-amber-50 border border-amber-200 px-4 py-2.5 text-sm text-amber-800">
      Los campos en <b>negrita</b> son obligatorios.
    </div>

    <form @submit.prevent="submit">
      <!-- Barra de acciones -->
      <div class="flex items-center gap-2 mb-4">
        <button type="submit" :disabled="form.processing || (cuitDuplicado && !form.cuit_confirmado)" class="inline-flex items-center gap-1.5 rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
          {{ form.processing ? 'Guardando…' : 'Guardar' }}
        </button>
        <Link href="/app/clientes" class="inline-flex items-center gap-1.5 rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
          Cancelar
        </Link>
      </div>

      <!-- Cliente -->
      <section class="bg-white rounded-lg shadow-sm border border-gray-200 mb-4">
        <div class="px-5 py-3 border-b border-gray-200"><h2 class="font-semibold text-gray-800">Cliente</h2></div>
        <div class="p-5 grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="block text-sm mb-1 font-bold">Tipo de cliente</label>
            <select v-model.number="form.cliente_pasajerodirecto" :class="inputCls">
              <option :value="0">Empresa</option>
              <option :value="1">Pasajero directo</option>
            </select>
          </div>
          <div>
            <label class="block text-sm mb-1 font-bold">Nombre / Razón social</label>
            <input v-model="form.cliente_razonsocial" type="text" :class="inputCls" />
            <p v-if="form.errors.cliente_razonsocial" class="text-xs text-red-600 mt-1">{{ form.errors.cliente_razonsocial }}</p>
          </div>
          <div>
            <label class="block text-sm mb-1 font-bold">Nombre de fantasía</label>
            <input v-model="form.cliente_nombre" type="text" :class="inputCls" />
            <p v-if="form.errors.cliente_nombre" class="text-xs text-red-600 mt-1">{{ form.errors.cliente_nombre }}</p>
          </div>
          <div>
            <label class="block text-sm mb-1">Legajo</label>
            <input v-model="form.cliente_legajo" type="text" :class="inputCls" />
          </div>
          <div>
            <label class="block text-sm mb-1 font-bold">Vendedor</label>
            <select v-model.number="form.fk_usuario_vendedor" :class="inputCls">
              <option :value="0">Seleccione una opción</option>
              <option v-for="v in opciones.vendedores" :key="v.usuario_id" :value="v.usuario_id">{{ v.nombre }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm mb-1 font-bold">Habilitado</label>
            <select v-model="form.habilita" :class="inputCls">
              <option value="Y">Sí</option>
              <option value="N">No</option>
            </select>
          </div>
          <div>
            <label class="block text-sm mb-1">Minorista</label>
            <select v-model.number="form.clienteminorista" :class="inputCls">
              <option :value="0">No</option>
              <option :value="1">Sí</option>
            </select>
          </div>
          <div class="md:col-span-3">
            <label class="block text-sm mb-1">Comentarios</label>
            <input v-model="form.comentarios" type="text" :class="inputCls" />
          </div>
        </div>
      </section>

      <!-- Domicilio -->
      <section class="bg-white rounded-lg shadow-sm border border-gray-200 mb-4">
        <div class="px-5 py-3 border-b border-gray-200"><h2 class="font-semibold text-gray-800 uppercase">Domicilio</h2></div>
        <div class="p-5 grid grid-cols-1 md:grid-cols-3 gap-4">
          <div class="md:col-span-2">
            <label class="block text-sm mb-1 font-bold">Dirección</label>
            <input v-model="form.cliente_direccionfiscal" type="text" :class="inputCls" />
            <p v-if="form.errors.cliente_direccionfiscal" class="text-xs text-red-600 mt-1">{{ form.errors.cliente_direccionfiscal }}</p>
          </div>
          <div>
            <label class="block text-sm mb-1">Código postal</label>
            <input v-model="form.cliente_codigopostal" type="text" :class="inputCls" />
          </div>
          <div>
            <label class="block text-sm mb-1 font-bold">País</label>
            <select v-model.number="form.fk_pais_id" :class="inputCls">
              <option :value="0">Seleccione una opción</option>
              <option v-for="p in opciones.paises" :key="p.pais_id" :value="p.pais_id">{{ p.pais_nombre }}</option>
            </select>
            <p v-if="form.errors.fk_pais_id" class="text-xs text-red-600 mt-1">{{ form.errors.fk_pais_id }}</p>
          </div>
          <div>
            <label class="block text-sm mb-1 font-bold">Ciudad</label>
            <select v-model.number="form.fk_ciudad_id" :class="inputCls" :disabled="!ciudades.length">
              <option :value="0">{{ ciudades.length ? 'Seleccione una opción' : 'Elegí un país primero' }}</option>
              <option v-for="c in ciudades" :key="c.ciudad_id" :value="c.ciudad_id">{{ c.ciudad_nombre }}</option>
            </select>
            <p v-if="form.errors.fk_ciudad_id" class="text-xs text-red-600 mt-1">{{ form.errors.fk_ciudad_id }}</p>
          </div>
          <div>
            <label class="block text-sm mb-1">Provincia</label>
            <input v-model="form.cliente_provincia" type="text" :class="inputCls" />
          </div>
        </div>
      </section>

      <!-- Contacto -->
      <section class="bg-white rounded-lg shadow-sm border border-gray-200 mb-4">
        <div class="px-5 py-3 border-b border-gray-200"><h2 class="font-semibold text-gray-800 uppercase">Contacto</h2></div>
        <div class="p-5 grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="block text-sm mb-1">Teléfono</label>
            <input v-model="form.cliente_telefono" type="text" :class="inputCls" />
          </div>
          <div>
            <label class="block text-sm mb-1">Fax</label>
            <input v-model="form.cliente_fax" type="text" :class="inputCls" />
          </div>
          <div>
            <label class="block text-sm mb-1 font-bold">Email</label>
            <input v-model="form.cliente_email" type="email" :class="inputCls" />
            <p v-if="form.errors.cliente_email" class="text-xs text-red-600 mt-1">{{ form.errors.cliente_email }}</p>
          </div>
          <div>
            <label class="block text-sm mb-1">Email para reservas</label>
            <input v-model="form.cliente_email2" type="email" :class="inputCls" />
          </div>
          <div>
            <label class="block text-sm mb-1">Email administración</label>
            <input v-model="form.cliente_emailadmin" type="email" :class="inputCls" />
          </div>
        </div>
      </section>

      <!-- Fiscal -->
      <section class="bg-white rounded-lg shadow-sm border border-gray-200 mb-4">
        <div class="px-5 py-3 border-b border-gray-200"><h2 class="font-semibold text-gray-800 uppercase">Fiscal</h2></div>
        <div class="p-5 grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="block text-sm mb-1 font-bold">Tipo de clave fiscal</label>
            <select v-model.number="form.fk_tipoclavefiscal_id" :class="inputCls">
              <option :value="0">Seleccione una opción</option>
              <option v-for="t in opciones.tiposClaveFiscal" :key="t.tipoclavefiscal_id" :value="t.tipoclavefiscal_id">{{ t.tipoclavefiscal_nombre }}</option>
            </select>
            <p v-if="form.errors.fk_tipoclavefiscal_id" class="text-xs text-red-600 mt-1">{{ form.errors.fk_tipoclavefiscal_id }}</p>
          </div>
          <div>
            <label class="block text-sm mb-1 font-bold">CUIT</label>
            <input v-model="form.cuit" @blur="chequearCuit" type="text" :class="inputCls" />
            <p v-if="form.errors.cuit" class="text-xs text-red-600 mt-1">{{ form.errors.cuit }}</p>
            <!-- Aviso de CUIT repetido: hay que tildar para poder guardar igual (queda auditado). -->
            <div v-if="cuitDuplicado" class="mt-2 rounded-md bg-amber-50 border border-amber-300 px-3 py-2 text-xs text-amber-800">
              <p class="mb-1.5">
                Ya existe un cliente con este CUIT<template v-if="cuitDuplicado.nombre">: <b>{{ cuitDuplicado.nombre }}</b></template>.
              </p>
              <label class="flex items-start gap-2 cursor-pointer">
                <input type="checkbox" :true-value="1" :false-value="0" v-model="form.cuit_confirmado" class="mt-0.5" />
                <span>Confirmo crear un cliente con un CUIT ya existente.</span>
              </label>
            </div>
          </div>
          <div>
            <label class="block text-sm mb-1">Nro. clave fiscal</label>
            <input v-model="form.nro_clavefiscal" type="text" :class="inputCls" />
          </div>
          <div>
            <label class="block text-sm mb-1 font-bold">Condición IVA</label>
            <select v-model.number="form.fk_condicioniva_id" :class="inputCls">
              <option :value="0">Seleccione una opción</option>
              <option v-for="c in opciones.condicionesIva" :key="c.condicioniva_id" :value="c.condicioniva_id">{{ c.condicioniva_nombre }}</option>
            </select>
            <p v-if="form.errors.fk_condicioniva_id" class="text-xs text-red-600 mt-1">{{ form.errors.fk_condicioniva_id }}</p>
          </div>
          <div>
            <label class="block text-sm mb-1 font-bold">Tipo de factura</label>
            <select v-model.number="form.fk_tipofactura_id" :class="inputCls">
              <option :value="0">Seleccione una opción</option>
              <option v-for="t in opciones.tiposFactura" :key="t.tipofactura_id" :value="t.tipofactura_id">{{ t.tipofactura_nombre }}</option>
            </select>
            <p v-if="form.errors.fk_tipofactura_id" class="text-xs text-red-600 mt-1">{{ form.errors.fk_tipofactura_id }}</p>
          </div>
          <div>
            <label class="block text-sm mb-1">CUIT internacional</label>
            <input v-model="form.cuit_internacional" type="text" :class="inputCls" />
          </div>
          <div>
            <label class="block text-sm mb-1">IATA</label>
            <input v-model="form.iata" type="text" :class="inputCls" />
          </div>
        </div>
      </section>

      <!-- Comercial -->
      <section class="bg-white rounded-lg shadow-sm border border-gray-200 mb-4">
        <div class="px-5 py-3 border-b border-gray-200"><h2 class="font-semibold text-gray-800 uppercase">Comercial</h2></div>
        <div class="p-5 grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="block text-sm mb-1">Idioma</label>
            <select v-model="form.fk_idioma_id" :class="inputCls">
              <option value="">Seleccione una opción</option>
              <option v-for="i in opciones.idiomas" :key="i.idioma_id" :value="i.idioma_id">{{ i.idioma_nombre }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm mb-1">Cadena</label>
            <select v-model.number="form.fk_cadenacliente_id" :class="inputCls">
              <option :value="0">Sin cadena</option>
              <option v-for="c in opciones.cadenas" :key="c.cadenacliente_id" :value="c.cadenacliente_id">{{ c.cadenacliente_nombre }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm mb-1">Límite de crédito</label>
            <input v-model.number="form.limite_credito" type="number" step="0.01" :class="inputCls" />
          </div>
          <div>
            <label class="block text-sm mb-1">Plazo de pago (días)</label>
            <input v-model.number="form.plazo_pago" type="number" :class="inputCls" />
          </div>
          <div>
            <label class="block text-sm mb-1">Consolidador</label>
            <select v-model="form.consolidador" :class="inputCls">
              <option value="N">No</option>
              <option value="S">Sí</option>
            </select>
          </div>
        </div>
      </section>

      <!-- Tarifarios -->
      <section class="bg-white rounded-lg shadow-sm border border-gray-200 mb-4">
        <div class="px-5 py-3 border-b border-gray-200"><h2 class="font-semibold text-gray-800 uppercase">Tarifarios</h2></div>
        <div class="p-5 grid grid-cols-1 md:grid-cols-3 gap-4">
          <div v-for="(t, key) in { fk_tarifario1_id: 'Minorista', fk_tarifario2_id: 'Mayorista', fk_tarifario3_id: 'Receptivo' }" :key="key">
            <label class="block text-sm mb-1">{{ t }}</label>
            <select v-model.number="form[key]" :class="inputCls">
              <option :value="0">Sin tarifario</option>
              <option v-for="tf in opciones.tarifarios" :key="tf.tarifario_id" :value="tf.tarifario_id">{{ tf.tarifario_nombre }}</option>
            </select>
          </div>
        </div>
      </section>

      <!-- Vendedores asociados / Promotores -->
      <section class="bg-white rounded-lg shadow-sm border border-gray-200 mb-4">
        <div class="px-5 py-3 border-b border-gray-200"><h2 class="font-semibold text-gray-800 uppercase">Vendedores asociados / Promotores</h2></div>
        <div class="p-5 grid grid-cols-1 md:grid-cols-4 gap-4">
          <div v-for="n in 4" :key="n">
            <label class="block text-sm mb-1">Promotor {{ n }}</label>
            <select v-model.number="form['fk_usuario_promotor' + n]" :class="inputCls">
              <option :value="0">Seleccione una opción</option>
              <option v-for="v in opciones.vendedores" :key="v.usuario_id" :value="v.usuario_id">{{ v.nombre }}</option>
            </select>
          </div>
        </div>
      </section>

      <!-- Gastos de reserva -->
      <section class="bg-white rounded-lg shadow-sm border border-gray-200 mb-4">
        <div class="px-5 py-3 border-b border-gray-200"><h2 class="font-semibold text-gray-800 uppercase">Gastos de reserva</h2></div>
        <div class="p-5 grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="block text-sm mb-1">Moneda gastos</label>
            <select v-model="form.fk_moneda_id" :class="inputCls">
              <option value="">Seleccione una opción</option>
              <option v-for="m in opciones.monedas" :key="m.moneda_id" :value="m.moneda_id">{{ m.moneda_nombre }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm mb-1">IVA gastos (%)</label>
            <input v-model.number="form.gastos_iva" type="number" step="0.01" :class="inputCls" />
          </div>
          <div></div>
          <div v-for="n in 3" :key="'pct' + n">
            <label class="block text-sm mb-1">Gasto % {{ n }}</label>
            <input v-model.number="form['gastos_porcentaje_' + n]" type="number" step="0.01" :class="inputCls" />
          </div>
          <div v-for="n in 3" :key="'fij' + n">
            <label class="block text-sm mb-1">Gasto fijo {{ n }}</label>
            <input v-model.number="form['gastos_fijo_' + n]" type="number" step="0.01" :class="inputCls" />
          </div>
        </div>
      </section>

      <!-- Opciones -->
      <section class="bg-white rounded-lg shadow-sm border border-gray-200 mb-4">
        <div class="px-5 py-3 border-b border-gray-200"><h2 class="font-semibold text-gray-800 uppercase">Opciones</h2></div>
        <div class="p-5 grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
          <label class="flex items-center gap-2"><input type="checkbox" :true-value="'Y'" :false-value="'N'" v-model="form.freelance" /> Freelance</label>
          <label class="flex items-center gap-2"><input type="checkbox" :true-value="'Y'" :false-value="'N'" v-model="form.representante_geografico" /> Representante geográfico</label>
          <label class="flex items-center gap-2"><input type="checkbox" :true-value="'Y'" :false-value="'N'" v-model="form.usar_logo" /> Usar logo</label>
          <label class="flex items-center gap-2"><input type="checkbox" :true-value="1" :false-value="0" v-model="form.autorizaws" /> Autoriza WS</label>
          <label class="flex items-center gap-2"><input type="checkbox" :true-value="1" :false-value="0" v-model="form.cliente_promo" /> Promociones</label>
          <label class="flex items-center gap-2"><input type="checkbox" :true-value="1" :false-value="0" v-model="form.cliente_web" /> Acceso web</label>
          <div class="col-span-2">
            <label class="block text-xs text-gray-500 mb-1">Nombre representante</label>
            <input v-model="form.nombre_representante" type="text" :class="inputCls" />
          </div>
        </div>
      </section>

      <!-- Contactos -->
      <section class="bg-white rounded-lg shadow-sm border border-gray-200 mb-4">
        <div class="px-5 py-3 border-b border-gray-200 flex items-center justify-between">
          <h2 class="font-semibold text-gray-800 uppercase">Contactos</h2>
          <button type="button" @click="addContacto" class="text-sm text-blue-600 hover:text-blue-800">+ Agregar contacto</button>
        </div>
        <div class="p-5 space-y-3">
          <p v-if="!form.contactos.length" class="text-sm text-gray-400">Sin contactos.</p>
          <div v-for="(c, i) in form.contactos" :key="i" class="grid grid-cols-1 md:grid-cols-5 gap-3 items-end border-b border-gray-100 pb-3">
            <div><label class="block text-xs text-gray-500 mb-1">Nombre</label><input v-model="c.cliente_cont_nombre" type="text" :class="inputCls" /></div>
            <div><label class="block text-xs text-gray-500 mb-1">Cargo</label><input v-model="c.cliente_cont_cargo" type="text" :class="inputCls" /></div>
            <div><label class="block text-xs text-gray-500 mb-1">Email</label><input v-model="c.cliente_email" type="email" :class="inputCls" /></div>
            <div><label class="block text-xs text-gray-500 mb-1">Teléfono</label><input v-model="c.cliente_telefono" type="text" :class="inputCls" /></div>
            <button type="button" @click="form.contactos.splice(i, 1)" class="text-sm text-red-600 hover:text-red-800 pb-2">Quitar</button>
          </div>
        </div>
      </section>

      <!-- Tarjetas -->
      <section class="bg-white rounded-lg shadow-sm border border-gray-200 mb-4">
        <div class="px-5 py-3 border-b border-gray-200 flex items-center justify-between">
          <h2 class="font-semibold text-gray-800 uppercase">Tarjetas</h2>
          <button type="button" @click="addTarjeta" class="text-sm text-blue-600 hover:text-blue-800">+ Agregar tarjeta</button>
        </div>
        <div class="p-5 space-y-3">
          <p v-if="!form.tarjetas.length" class="text-sm text-gray-400">Sin tarjetas.</p>
          <div v-for="(t, i) in form.tarjetas" :key="i" class="grid grid-cols-1 md:grid-cols-5 gap-3 items-end border-b border-gray-100 pb-3">
            <div><label class="block text-xs text-gray-500 mb-1">Número</label><input v-model="t.cliente_tarjeta_num" type="text" :class="inputCls" /></div>
            <div><label class="block text-xs text-gray-500 mb-1">Banco</label><input v-model="t.cliente_tarjeta_banco" type="text" :class="inputCls" /></div>
            <div><label class="block text-xs text-gray-500 mb-1">Vencimiento</label><input v-model="t.cliente_tarjeta_venc" type="text" placeholder="MM/AA" :class="inputCls" /></div>
            <div><label class="block text-xs text-gray-500 mb-1">Empresa</label><input v-model="t.cliente_tarjeta_empresa" type="text" :class="inputCls" /></div>
            <button type="button" @click="form.tarjetas.splice(i, 1)" class="text-sm text-red-600 hover:text-red-800 pb-2">Quitar</button>
          </div>
        </div>
      </section>

      <div class="flex items-center gap-2 mb-10">
        <button type="submit" :disabled="form.processing || (cuitDuplicado && !form.cuit_confirmado)" class="inline-flex items-center gap-1.5 rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
          {{ form.processing ? 'Guardando…' : 'Guardar' }}
        </button>
        <Link href="/app/clientes" class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancelar</Link>
      </div>
    </form>
  </div>
</template>
