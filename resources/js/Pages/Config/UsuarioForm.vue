<script setup>
import { computed } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { useEnvio } from '@/lib/envio'

defineOptions({ layout: AppLayout })

const props = defineProps({
  registro: { type: Object, default: null },
  baseUrl: { type: String, required: true },
  // { tipos, idiomas, clientes, proveedores, cadenas, operadores } => [{ value, label }]
  opciones: { type: Object, required: true },
})

const esEdicion = computed(() => props.registro !== null)
const r = props.registro || {}
const yn = (v, def = 'N') => (v === 'Y' || v === '1' || v === 1 ? 'Y' : v === 'N' || v === '0' || v === 0 ? 'N' : def)

const form = useForm({
  habilitar: yn(r.habilitar, 'Y'),
  usuario_interno: yn(r.usuario_interno, 'Y'),
  usuario_nombre: r.usuario_nombre ?? '',
  usuario_apellido: r.usuario_apellido ?? '',
  usuario_mail: r.usuario_mail ?? '',
  usuario_password: '',
  solocotiza: yn(r.solocotiza, 'N'),
  fk_idioma_id: r.fk_idioma_id ?? 'es',
  fk_tipousuario_id: r.fk_tipousuario_id ?? '',
  fk_cliente_id: Number(r.fk_cliente_id) || 0,
  fk_proveedor_id: Number(r.fk_proveedor_id) || 0,
  fk_cadenacliente_id: Number(r.fk_cadenacliente_id) || 0,
  fk_operador_id: Number(r.fk_operador_id) || 0,
  firma_amadeus: r.firma_amadeus ?? '',
  firma_sabre: r.firma_sabre ?? '',
  usuario_responsable: Number(r.usuario_responsable) || 0,
  usuario_promo: Number(r.usuario_promo) || 0,
  usuario_telefono: r.usuario_telefono ?? '',
  usuario_celular: r.usuario_celular ?? '',
  usuario_domicilio: r.usuario_domicilio ?? '',
  ciudad: r.ciudad ?? '',
  usuario_sexo: r.usuario_sexo === 'F' ? 'F' : 'M',
})

const { enviando, enviar } = useEnvio()
const submit = () =>
  enviar(
    (opciones) => (esEdicion.value ? form.put(`${props.baseUrl}/${r.usuario_id}`, opciones) : form.post(props.baseUrl, opciones)),
    { preserveScroll: true },
  )

const inputCls = 'w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500'
</script>

<template>
  <div>
    <nav class="flex items-center gap-2 text-sm text-gray-500 mb-4">
      <Link href="/app" class="hover:text-gray-700">Inicio</Link>
      <span>/</span>
      <span class="font-medium" style="color: #ff9900">Configuración</span>
      <span>/</span>
      <Link :href="baseUrl" class="hover:text-gray-700">Usuarios</Link>
      <span>/</span>
      <span class="text-gray-900 font-semibold">{{ esEdicion ? `Editar #${r.usuario_id}` : 'Nuevo' }}</span>
    </nav>

    <form @submit.prevent="submit">
      <div class="flex items-center gap-2 mb-4">
        <button type="submit" :disabled="enviando" class="btn btn-primary">{{ enviando ? 'Guardando…' : 'Guardar' }}</button>
        <Link :href="baseUrl" class="btn btn-secondary">Cancelar</Link>
      </div>

      <div class="rounded-md border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 mb-4">Los campos en <b>negrita</b> son obligatorios.</div>

      <section class="card mb-4">
        <div class="card-header"><h2 class="card-title">Datos generales</h2></div>
        <div class="card-body grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="block text-sm mb-1">Habilitar <span class="block text-xs text-gray-400">Si se deshabilita, el usuario no tendrá acceso al sistema.</span></label>
            <div class="flex gap-4 text-sm h-9 items-center">
              <label class="flex items-center gap-1"><input type="radio" value="Y" v-model="form.habilitar" /> Sí</label>
              <label class="flex items-center gap-1"><input type="radio" value="N" v-model="form.habilitar" /> No</label>
            </div>
          </div>
          <div>
            <label class="block text-sm mb-1">¿Usuario interno o externo? <span class="block text-xs text-gray-400">El interno puede reservar a favor de clientes relacionados.</span></label>
            <div class="flex gap-4 text-sm h-9 items-center">
              <label class="flex items-center gap-1"><input type="radio" value="Y" v-model="form.usuario_interno" /> Interno</label>
              <label class="flex items-center gap-1"><input type="radio" value="N" v-model="form.usuario_interno" /> Externo</label>
            </div>
          </div>
          <div>
            <label class="block text-sm mb-1">Sólo cotiza <span class="block text-xs text-gray-400">Sólo permitir cotizar, no reservar.</span></label>
            <div class="flex gap-4 text-sm h-9 items-center">
              <label class="flex items-center gap-1"><input type="radio" value="Y" v-model="form.solocotiza" /> Sí</label>
              <label class="flex items-center gap-1"><input type="radio" value="N" v-model="form.solocotiza" /> No</label>
            </div>
          </div>

          <div>
            <label class="block text-sm mb-1 font-bold">Nombre</label>
            <input v-model="form.usuario_nombre" type="text" maxlength="100" :class="inputCls" />
            <p v-if="form.errors.usuario_nombre" class="text-xs text-red-600 mt-1">{{ form.errors.usuario_nombre }}</p>
          </div>
          <div>
            <label class="block text-sm mb-1 font-bold">Apellido</label>
            <input v-model="form.usuario_apellido" type="text" maxlength="100" :class="inputCls" />
            <p v-if="form.errors.usuario_apellido" class="text-xs text-red-600 mt-1">{{ form.errors.usuario_apellido }}</p>
          </div>
          <div>
            <label class="block text-sm mb-1 font-bold">Email</label>
            <input v-model="form.usuario_mail" type="email" maxlength="150" :class="inputCls" autocomplete="off" />
            <p v-if="form.errors.usuario_mail" class="text-xs text-red-600 mt-1">{{ form.errors.usuario_mail }}</p>
          </div>

          <div>
            <label class="block text-sm mb-1" :class="{ 'font-bold': !esEdicion }">Contraseña <span v-if="esEdicion" class="text-xs text-gray-400">(dejar vacío para no cambiarla)</span></label>
            <input v-model="form.usuario_password" type="password" :class="inputCls" autocomplete="new-password" />
            <p v-if="form.errors.usuario_password" class="text-xs text-red-600 mt-1">{{ form.errors.usuario_password }}</p>
          </div>
          <div>
            <label class="block text-sm mb-1 font-bold">Tipo de usuario</label>
            <select v-model="form.fk_tipousuario_id" :class="inputCls">
              <option value="">Seleccione una opción</option>
              <option v-for="o in opciones.tipos" :key="o.value" :value="o.value">{{ o.label }} ({{ o.value }})</option>
            </select>
            <p v-if="form.errors.fk_tipousuario_id" class="text-xs text-red-600 mt-1">{{ form.errors.fk_tipousuario_id }}</p>
          </div>
          <div>
            <label class="block text-sm mb-1">Idioma</label>
            <select v-model="form.fk_idioma_id" :class="inputCls">
              <option v-for="o in opciones.idiomas" :key="o.value" :value="o.value">{{ o.label }}</option>
            </select>
          </div>

          <div>
            <label class="block text-sm mb-1">Cliente relacionado <span class="block text-xs text-gray-400">Para tipos de usuario = Cliente.</span></label>
            <select v-model="form.fk_cliente_id" :class="inputCls">
              <option :value="0">(sin asignar)</option>
              <option v-for="o in opciones.clientes" :key="o.value" :value="o.value">{{ o.label }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm mb-1">Proveedor relacionado <span class="block text-xs text-gray-400">Para tipos de usuario = Proveedores.</span></label>
            <select v-model="form.fk_proveedor_id" :class="inputCls">
              <option :value="0">(sin asignar)</option>
              <option v-for="o in opciones.proveedores" :key="o.value" :value="o.value">{{ o.label }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm mb-1">Cadena relacionada</label>
            <select v-model="form.fk_cadenacliente_id" :class="inputCls">
              <option :value="0">(sin asignar)</option>
              <option v-for="o in opciones.cadenas" :key="o.value" :value="o.value">{{ o.label }}</option>
            </select>
          </div>

          <div>
            <label class="block text-sm mb-1">Usuario relacionado <span class="block text-xs text-gray-400">Brinda a otro usuario acceso a las reservas de este usuario.</span></label>
            <select v-model="form.fk_operador_id" :class="inputCls">
              <option :value="0">(sin asignar)</option>
              <option v-for="o in opciones.operadores" :key="o.value" :value="o.value">{{ o.label }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm mb-1">Firma AMADEUS <span class="block text-xs text-gray-400">Para tipos de usuario = Aéreos.</span></label>
            <input v-model="form.firma_amadeus" type="text" maxlength="50" :class="inputCls" />
          </div>
          <div>
            <label class="block text-sm mb-1">Firma SABRE <span class="block text-xs text-gray-400">Para tipos de usuario = Aéreos.</span></label>
            <input v-model="form.firma_sabre" type="text" maxlength="50" :class="inputCls" />
          </div>

          <div class="md:col-span-3 flex flex-wrap gap-6">
            <label class="flex items-center gap-2 text-sm">
              <input type="checkbox" :true-value="1" :false-value="0" v-model="form.usuario_responsable" />
              Responsable de reservas <span class="text-xs text-gray-400">(puede ser definido como responsable de una reserva)</span>
            </label>
            <label class="flex items-center gap-2 text-sm">
              <input type="checkbox" :true-value="1" :false-value="0" v-model="form.usuario_promo" />
              Promotor
            </label>
          </div>
        </div>
      </section>

      <section class="card mb-4">
        <div class="card-header"><h2 class="card-title">Datos personales</h2></div>
        <div class="card-body grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="block text-sm mb-1">Teléfono</label>
            <input v-model="form.usuario_telefono" type="text" maxlength="50" :class="inputCls" />
          </div>
          <div>
            <label class="block text-sm mb-1">Teléfono celular</label>
            <input v-model="form.usuario_celular" type="text" maxlength="50" :class="inputCls" />
          </div>
          <div>
            <label class="block text-sm mb-1">Género</label>
            <select v-model="form.usuario_sexo" :class="inputCls">
              <option value="M">Masculino</option>
              <option value="F">Femenino</option>
            </select>
          </div>
          <div class="md:col-span-2">
            <label class="block text-sm mb-1">Domicilio</label>
            <input v-model="form.usuario_domicilio" type="text" maxlength="255" :class="inputCls" />
          </div>
          <div>
            <label class="block text-sm mb-1">Ciudad</label>
            <input v-model="form.ciudad" type="text" maxlength="100" :class="inputCls" />
          </div>
        </div>
      </section>

      <div class="flex items-center gap-2 mb-10">
        <button type="submit" :disabled="enviando" class="btn btn-primary">{{ enviando ? 'Guardando…' : 'Guardar' }}</button>
        <Link :href="baseUrl" class="btn btn-secondary">Cancelar</Link>
      </div>
    </form>
  </div>
</template>
