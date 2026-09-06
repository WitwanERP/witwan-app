<script setup>
import { computed, reactive } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { useEnvio } from '@/lib/envio'

defineOptions({ layout: AppLayout })

const props = defineProps({
  registro: { type: Object, default: null },
  copiaDe: { type: Object, default: null },
  // [{ sistema_id, sistema, color, grupos: [{ grupo, secciones: [{ id, label, raiz, key }] }] }]
  arbol: { type: Array, required: true },
  // ["12-acceso", "12-alta", "125-cerrar_reserva", ...]
  activos: { type: Array, default: () => [] },
  permisosRaiz: { type: Array, default: () => ['acceso', 'alta', 'edicion', 'borrado'] },
  baseUrl: { type: String, required: true },
})

const esEdicion = computed(() => props.registro !== null)

// perms[seccion_id][permiso] = 0|1 (mismo nombre de campo que el form del CI).
const perms = reactive({})
const activos = new Set(props.activos)
for (const s of props.arbol) {
  for (const g of s.grupos) {
    for (const sec of g.secciones) {
      perms[sec.id] = {}
      if (sec.raiz) {
        for (const p of props.permisosRaiz) perms[sec.id][p] = activos.has(`${sec.id}-${p}`) ? 1 : 0
      } else if (sec.key) {
        perms[sec.id][sec.key] = activos.has(`${sec.id}-${sec.key}`) ? 1 : 0
      }
    }
  }
}

const form = useForm({
  tipousuario_id: props.registro?.tipousuario_id ?? '',
  tipousuario_nombre: props.registro?.tipousuario_nombre ?? (props.copiaDe ? `${props.copiaDe.nombre} (copia)` : ''),
  inicio: props.registro?.inicio ?? '',
  perms,
})

// "Todo" de una sección raíz: marca/desmarca los cuatro permisos.
function todoMarcado(sec) {
  return props.permisosRaiz.every((p) => perms[sec.id][p] === 1)
}
function toggleTodo(sec) {
  const valor = todoMarcado(sec) ? 0 : 1
  for (const p of props.permisosRaiz) perms[sec.id][p] = valor
}
// "Todo" de un grupo entero.
function toggleGrupo(grupo, valor) {
  for (const sec of grupo.secciones) {
    for (const k of Object.keys(perms[sec.id])) perms[sec.id][k] = valor
  }
}

const { enviando, enviar } = useEnvio()
const submit = () =>
  enviar(
    (opciones) => (esEdicion.value ? form.put(`${props.baseUrl}/${props.registro.tipousuario_id}`, opciones) : form.post(props.baseUrl, opciones)),
    { preserveScroll: true },
  )

const inputCls = 'w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-gray-100 disabled:text-gray-500'
const cap = (s) => s.charAt(0).toUpperCase() + s.slice(1)
</script>

<template>
  <div>
    <nav class="flex items-center gap-2 text-sm text-gray-500 mb-4">
      <Link href="/app" class="hover:text-gray-700">Inicio</Link>
      <span>/</span>
      <span class="font-medium" style="color: #ff9900">Configuración</span>
      <span>/</span>
      <Link :href="baseUrl" class="hover:text-gray-700">Tipos de usuario</Link>
      <span>/</span>
      <span class="text-gray-900 font-semibold">{{ esEdicion ? `Editar ${registro.tipousuario_id}` : copiaDe ? `Copiar ${copiaDe.id}` : 'Nuevo' }}</span>
    </nav>

    <form @submit.prevent="submit">
      <div class="flex items-center gap-2 mb-4">
        <button type="submit" :disabled="enviando" class="btn btn-primary">{{ enviando ? 'Guardando…' : 'Guardar' }}</button>
        <Link :href="baseUrl" class="btn btn-secondary">Cancelar</Link>
      </div>

      <section class="card mb-4">
        <div class="card-header"><h2 class="card-title">{{ esEdicion ? 'Editar tipo de usuario' : 'Nuevo tipo de usuario' }}</h2></div>
        <div class="card-body grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="block text-sm mb-1 font-bold">ID (3 letras, único)</label>
            <input v-model="form.tipousuario_id" type="text" maxlength="3" :class="inputCls" :disabled="esEdicion" style="text-transform: uppercase" />
            <p v-if="form.errors.tipousuario_id" class="text-xs text-red-600 mt-1">{{ form.errors.tipousuario_id }}</p>
          </div>
          <div>
            <label class="block text-sm mb-1 font-bold">Nombre identificatorio</label>
            <input v-model="form.tipousuario_nombre" type="text" maxlength="150" :class="inputCls" />
            <p v-if="form.errors.tipousuario_nombre" class="text-xs text-red-600 mt-1">{{ form.errors.tipousuario_nombre }}</p>
          </div>
          <div>
            <label class="block text-sm mb-1">Pantalla de inicio</label>
            <input v-model="form.inicio" type="text" maxlength="150" :class="inputCls" placeholder="(opcional)" />
          </div>
        </div>
      </section>

      <div class="rounded-md border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 mb-4">
        A continuación se despliegan todas las secciones del sistema agrupadas por área. Tilde los permisos a habilitar para este tipo de usuario:
        <b>Acceso</b> (sólo lectura), <b>Alta</b> (crear), <b>Edición</b> (modificar), <b>Borrado</b> (eliminar) y <b>Todo</b> (los cuatro).
        Las secciones sin URI son permisos puntuales con una sola casilla.
      </div>

      <section v-for="s in arbol" :key="s.sistema_id" class="mb-6">
        <div class="flex items-center gap-2 rounded-t-md border px-3 py-2 bg-gray-100 text-lg font-semibold" :style="{ borderColor: s.color || '#ddd' }">
          <span class="inline-block w-3 h-3 rounded-sm" :style="{ backgroundColor: s.color || '#999' }"></span>
          {{ s.sistema }}
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-3 border border-t-0 rounded-b-md p-3" :style="{ borderColor: s.color || '#ddd' }">
          <div v-for="g in s.grupos" :key="g.grupo" class="rounded-md border border-gray-200 bg-white">
            <div class="flex items-center justify-between px-3 py-1.5 bg-gray-50 border-b border-gray-200 text-sm font-semibold">
              <span>{{ g.grupo }}</span>
              <span class="text-xs font-normal text-gray-500">
                <button type="button" class="hover:text-blue-700" @click="toggleGrupo(g, 1)">todo</button>
                ·
                <button type="button" class="hover:text-red-700" @click="toggleGrupo(g, 0)">nada</button>
              </span>
            </div>
            <div class="px-3 py-2 space-y-2">
              <div v-for="sec in g.secciones" :key="sec.id" class="text-sm">
                <template v-if="sec.raiz">
                  <div class="font-medium text-gray-800 border-b border-gray-100 pb-0.5 mb-1">{{ sec.label }} <span class="text-xs text-gray-400">#{{ sec.id }}</span></div>
                  <div class="flex flex-wrap gap-x-3 gap-y-1 text-xs">
                    <label class="flex items-center gap-1 font-semibold">
                      <input type="checkbox" :checked="todoMarcado(sec)" @change="toggleTodo(sec)" /> Todo
                    </label>
                    <label v-for="p in permisosRaiz" :key="p" class="flex items-center gap-1">
                      <input type="checkbox" :true-value="1" :false-value="0" v-model="form.perms[sec.id][p]" /> {{ cap(p) }}
                    </label>
                  </div>
                </template>
                <label v-else-if="sec.key" class="flex items-center gap-1.5 text-xs text-gray-700">
                  <input type="checkbox" :true-value="1" :false-value="0" v-model="form.perms[sec.id][sec.key]" /> {{ sec.label }}
                </label>
              </div>
            </div>
          </div>
        </div>
      </section>

      <div class="flex items-center gap-2 mb-10">
        <button type="submit" :disabled="enviando" class="btn btn-primary">{{ enviando ? 'Guardando…' : 'Guardar' }}</button>
        <Link :href="baseUrl" class="btn btn-secondary">No guardar y volver</Link>
      </div>
    </form>
  </div>
</template>
