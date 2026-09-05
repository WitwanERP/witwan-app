<script setup>
import { computed, reactive, ref } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { useEnvio } from '@/lib/envio'
import { formatearFecha } from '@/lib/formato'
import CampoProducto from './components/CampoProducto.vue'
import Habitaciones from './components/Habitaciones.vue'

defineOptions({ layout: AppLayout })

/**
 * Alta/edición de un producto de cualquier tipo. Los campos vienen del server
 * (ProductoFormulario, desde config/productos.php): acá no hay un `if` por
 * tipo. Reemplaza a productos/{tipo}/edit del CI.
 */
const props = defineProps({
  config: { type: Object, required: true },
  campos: { type: Array, required: true },
  opciones: { type: Object, default: () => ({}) },
  registro: { type: Object, default: null },
  vigencias: { type: Array, default: () => [] },
})

const esEdicion = computed(() => props.registro !== null)
const r = props.registro || {}

function inicial(campo) {
  const v = r[campo.campo]
  switch (campo.tipo) {
    case 'titulo':
      return undefined
    case 'boolean':
      return v === undefined || v === null ? (campo.default ?? 0) : Number(v) ? 1 : 0
    case 'bases':
      return (r.bases ?? []).map(String)
    case 'facilidades':
      return [...(r.facilidades ?? [])]
    case 'destinos':
      return (r.destinos ?? []).map((d) => ({ ciudad_id: d.ciudad_id, tipo: d.tipo || 'D' }))
    case 'ciudad':
      return Number(r.destino ?? 0)
    case 'select':
      return v === undefined || v === null || v === '' ? (campo.opciones ? 0 : '') : isNaN(Number(v)) ? v : Number(v)
    case 'number':
      return v === undefined || v === null || v === '' ? '' : Number(v)
    default:
      return v ?? (campo.default ?? '')
  }
}

const datos = {}
for (const c of props.campos) {
  const v = inicial(c)
  if (v !== undefined) datos[c.campo] = v
}
datos.habitaciones = (r.habitaciones ?? []).map((h) => ({
  alojamientohabitacion_id: h.alojamientohabitacion_id,
  fk_tarifacategoria_id: h.fk_tarifacategoria_id,
  nombre: h.nombre,
  textolibre: h.textolibre ?? '',
  capacidad: h.capacidad,
  min_adultos: h.min_adultos,
  max_adultos: h.max_adultos,
  max_child: h.max_child,
  max_adultos_child: h.max_adultos_child,
  orden: h.orden ?? 0,
  habilitar: Number(h.habilitar) ? 1 : 0,
  tiene_tarifas: !!h.tiene_tarifas,
}))
datos.galeria = (r.galeria ?? []).map((g, i) => ({ productogaleria_archivo: g.productogaleria_archivo, orden: g.orden ?? i }))

const form = useForm(datos)

// Un campo "!edadesmenores" abre una sección nueva: se agrupan para la UI.
const secciones = computed(() => {
  const out = [{ titulo: 'Datos', campos: [] }]
  for (const c of props.campos) {
    if (c.tipo === 'titulo') out.push({ titulo: c.label, campos: [] })
    else out[out.length - 1].campos.push(c)
  }
  return out.filter((s) => s.campos.length)
})

const nuevaFoto = ref('')
function agregarFoto() {
  if (!nuevaFoto.value.trim()) return
  form.galeria.push({ productogaleria_archivo: nuevaFoto.value.trim(), orden: form.galeria.length })
  nuevaFoto.value = ''
}

const { enviando, enviar } = useEnvio()

function guardar() {
  const payload = form.data()
  enviar(
    (op) => (esEdicion.value ? form.transform(() => payload).put(`${props.config.baseUrl}/${r.producto_id}`, op) : form.transform(() => payload).post(props.config.baseUrl, op)),
    { preserveScroll: true },
  )
}

const clonar = reactive({ abierto: false, con_vigencias: true, nombre: '' })
function confirmarClonar() {
  enviar((op) => router.post(`${props.config.baseUrl}/${r.producto_id}/clonar`, { con_vigencias: clonar.con_vigencias ? 1 : 0, nombre: clonar.nombre }, op))
}

const erroresSueltos = computed(() => {
  const conocidos = new Set(props.campos.map((c) => c.campo))
  return Object.entries(form.errors).filter(([k]) => !conocidos.has(k) && !k.startsWith('habitaciones')).map(([k, m]) => `${k}: ${m}`)
})
</script>

<template>
  <div>
    <nav class="mb-4 flex items-center gap-2 text-sm text-gray-500">
      <Link href="/app" class="hover:text-gray-700">Inicio</Link><span>/</span>
      <span class="capitalize">{{ config.sistema }}</span><span>/</span>
      <Link :href="config.baseUrl" class="hover:text-gray-700">{{ config.titulo }}</Link><span>/</span>
      <span class="font-semibold text-gray-900">{{ esEdicion ? r.producto_nombre : 'Nuevo' }}</span>
    </nav>

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">{{ esEdicion ? r.producto_nombre : `Nuevo ${config.titulo.toLowerCase().replace(/s$/, '')}` }}</h1>
        <p class="text-gray-500">{{ config.titulo }} · <span class="capitalize">{{ config.sistema }}</span><span v-if="esEdicion"> · #{{ r.producto_id }}</span></p>
      </div>
      <div class="flex flex-wrap gap-2">
        <Link v-if="esEdicion" :href="`/app/productos/${r.producto_id}/vigencias`" class="btn btn-secondary">Tarifas ({{ vigencias.length }})</Link>
        <button v-if="esEdicion && config.permisos.alta" type="button" class="btn btn-secondary" @click="clonar.abierto = !clonar.abierto">Clonar…</button>
        <button type="submit" form="formproducto" class="btn btn-primary" :disabled="enviando">{{ enviando ? 'Guardando…' : 'Guardar' }}</button>
      </div>
    </div>

    <div v-if="clonar.abierto" class="card mb-4 border-blue-200">
      <div class="card-header"><h3 class="card-title">Clonar producto</h3></div>
      <div class="card-body flex flex-wrap items-end gap-4">
        <div class="min-w-64 flex-1"><label class="form-label">Nombre de la copia</label><input v-model="clonar.nombre" type="text" class="w-full rounded-lg border border-gray-300 bg-gray-50 py-2 px-3 text-sm" :placeholder="`${r.producto_nombre} (copia)`" /></div>
        <label class="flex items-center gap-2 pb-2 text-sm"><input v-model="clonar.con_vigencias" type="checkbox" />Copiar vigencias futuras con sus tarifas</label>
        <button type="button" class="btn btn-primary" :disabled="enviando" @click="confirmarClonar">Crear copia</button>
      </div>
    </div>

    <div v-if="erroresSueltos.length" class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
      <p v-for="(e, i) in erroresSueltos" :key="i">{{ e }}</p>
    </div>

    <form id="formproducto" @submit.prevent="guardar">
      <div v-for="s in secciones" :key="s.titulo" class="card mb-4">
        <div class="card-header"><h3 class="card-title">{{ s.titulo }}</h3></div>
        <div class="card-body grid grid-cols-1 gap-4 md:grid-cols-2">
          <CampoProducto v-for="c in s.campos" :key="c.campo" :campo="c" v-model="form[c.campo]" :opciones="opciones" :error="form.errors[c.campo]" />
        </div>
      </div>

      <Habitaciones v-if="config.habitaciones" :habitaciones="form.habitaciones" :categorias="opciones.tarifacategorias || []" :producto-id="Number(r.producto_id || 0)" :errors="form.errors" />

      <div class="card mb-4">
        <div class="card-header"><h3 class="card-title">Galería</h3></div>
        <div class="card-body">
          <ul class="mb-3 space-y-1 text-sm">
            <li v-for="(g, i) in form.galeria" :key="g.productogaleria_archivo" class="flex items-center gap-3">
              <span class="w-6 text-right tabular-nums text-gray-400">{{ i + 1 }}</span>
              <span class="flex-1 truncate font-mono text-xs">{{ g.productogaleria_archivo }}</span>
              <button type="button" class="text-xs text-red-600 hover:underline" @click="form.galeria.splice(i, 1)">Quitar</button>
            </li>
            <li v-if="form.galeria.length === 0" class="text-gray-500">Sin fotos.</li>
          </ul>
          <div class="flex gap-2">
            <input v-model="nuevaFoto" type="text" class="flex-1 rounded-lg border border-gray-300 bg-gray-50 py-2 px-3 text-sm" placeholder="nombre-del-archivo.jpg (ya subido a /upfiles)" @keydown.enter.prevent="agregarFoto" />
            <button type="button" class="btn btn-secondary" @click="agregarFoto">Agregar</button>
          </div>
          <p class="form-hint">La subida de archivos sigue en el CI por ahora; acá se ordena y se quita.</p>
        </div>
      </div>

      <div v-if="esEdicion" class="card mb-6">
        <div class="card-header">
          <h3 class="card-title">Vigencias</h3>
          <Link :href="`/app/productos/${r.producto_id}/vigencias/create`" class="btn btn-secondary btn-sm">Nueva vigencia</Link>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500"><tr><th class="px-4 py-2 text-left">Descripción</th><th class="px-4 py-2 text-left">Estadía</th><th class="px-4 py-2 text-right">Prior.</th><th class="px-4 py-2 text-left">Moneda</th><th class="px-4 py-2 text-right">Tarifas</th></tr></thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="v in vigencias" :key="v.vigencia_id">
                <td class="px-4 py-2"><Link :href="`/app/productos/${r.producto_id}/vigencias/${v.vigencia_id}/edit`" class="text-blue-600 hover:underline">{{ v.vigencia_descripcion || `#${v.vigencia_id}` }}</Link></td>
                <td class="px-4 py-2 tabular-nums">{{ formatearFecha(v.vigencia_ini) }} – {{ formatearFecha(v.vigencia_fin) }}</td>
                <td class="px-4 py-2 text-right">{{ v.vigencia_prioridad }}</td>
                <td class="px-4 py-2">{{ v.moneda_costo || '—' }}</td>
                <td class="px-4 py-2 text-right tabular-nums">{{ v.tarifas }}</td>
              </tr>
              <tr v-if="vigencias.length === 0"><td colspan="5" class="px-4 py-6 text-center text-gray-500">Sin vigencias.</td></tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="mb-10 flex gap-2">
        <button type="submit" class="btn btn-primary" :disabled="enviando">{{ enviando ? 'Guardando…' : 'Guardar' }}</button>
        <Link :href="config.baseUrl" class="btn btn-secondary">Cancelar</Link>
      </div>
    </form>
  </div>
</template>
