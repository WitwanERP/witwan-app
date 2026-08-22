<script setup>
import { ref, computed, watch } from 'vue'
import { formatearImporte } from '../calculo.js'

const props = defineProps({
  modelValue: { type: Array, default: () => [] },
  proveedorId: { type: [Number, String], default: '' },
  tipoMovimiento: { type: String, default: '' },
  baseUrl: { type: String, required: true },
  totalFactura: { type: Number, default: 0 },
})

const emit = defineEmits(['update:modelValue'])

const grupos = ref([])
const modo = ref('plano')
const cargando = ref(false)
const codigo = ref('')
const abiertos = ref({})

// Sólo estos tipos imputan servicios de reserva; el resto genera su propio
// servicio contable (factura3ero.php:998-1016).
const aplica = computed(() => ['Servicio', 'BSP'].includes(props.tipoMovimiento))

const seleccion = computed(() => {
  const mapa = {}
  for (const o of props.modelValue) mapa[o.id] = o
  return mapa
})

const totalSeleccionado = computed(() =>
  props.modelValue.reduce((acc, o) => acc + (Number(o.monto) || 0), 0)
)

// El legacy avisaba si la factura y los servicios no cerraban, pero su condición
// era imposible de cumplir (`> 10 && < -10`), así que el aviso nunca aparecía.
const diferencia = computed(() => Math.round((props.totalFactura - totalSeleccionado.value) * 100) / 100)
const descuadrada = computed(() => props.modelValue.length > 0 && Math.abs(diferencia.value) > 10)

async function cargar() {
  if (!aplica.value || !props.proveedorId) {
    grupos.value = []
    return
  }
  cargando.value = true
  try {
    const params = new URLSearchParams({ proveedor: String(props.proveedorId) })
    if (codigo.value) params.set('codigo', codigo.value)
    const r = await fetch(`${props.baseUrl}/ocupaciones?${params}`)
    const data = r.ok ? await r.json() : { modo: 'plano', grupos: [] }
    modo.value = data.modo
    grupos.value = data.grupos
    // Los grupos BSP arrancan cerrados: pueden ser decenas de semanas.
    abiertos.value = Object.fromEntries(data.grupos.map((g, i) => [g.clave ?? `g${i}`, data.modo === 'plano']))
  } finally {
    cargando.value = false
  }
}

// Se recarga entera al cambiar proveedor o tipo, como el legacy: mantener
// selecciones de otro proveedor sería peor que perderlas.
watch(
  () => [props.proveedorId, props.tipoMovimiento],
  () => {
    emit('update:modelValue', [])
    cargar()
  }
)

function alternar(servicio) {
  const actual = [...props.modelValue]
  const i = actual.findIndex((o) => o.id === servicio.id)
  if (i >= 0) actual.splice(i, 1)
  else actual.push({ id: servicio.id, monto: servicio.montoSugerido })
  emit('update:modelValue', actual)
}

function cambiarMonto(servicio, valor) {
  const actual = props.modelValue.map((o) => (o.id === servicio.id ? { ...o, monto: Number(valor) } : o))
  emit('update:modelValue', actual)
}

function alternarGrupo(grupo) {
  const clave = grupo.clave ?? 'sin'
  const todos = grupo.servicios.every((s) => seleccion.value[s.id])
  let actual = [...props.modelValue]
  if (todos) {
    const ids = new Set(grupo.servicios.map((s) => s.id))
    actual = actual.filter((o) => !ids.has(o.id))
  } else {
    for (const s of grupo.servicios) {
      if (!seleccion.value[s.id]) actual.push({ id: s.id, monto: s.montoSugerido })
    }
  }
  abiertos.value[clave] = true
  emit('update:modelValue', actual)
}

function excede(servicio) {
  const o = seleccion.value[servicio.id]
  if (!o) return false
  return Math.abs(Number(o.monto)) > Math.abs(servicio.costoFinal) + 0.01
}
</script>

<template>
  <div v-if="aplica" class="card mb-4">
    <div class="card-header flex flex-wrap items-center justify-between gap-2">
      <h3 class="card-title">Servicios a imputar</h3>
      <div class="flex items-center gap-2">
        <input
          v-model="codigo"
          type="text"
          placeholder="Código de reserva…"
          class="rounded-lg border border-gray-300 bg-gray-50 px-3 py-1.5 text-sm"
          @keyup.enter="cargar"
        />
        <button type="button" class="btn btn-sm btn-secondary" @click="cargar">Buscar</button>
      </div>
    </div>

    <div class="card-body">
      <p v-if="!proveedorId" class="text-sm text-gray-500">Elegí un proveedor para ver sus servicios pendientes.</p>
      <p v-else-if="cargando" class="text-sm text-gray-500">Cargando servicios…</p>
      <p v-else-if="!grupos.length" class="text-sm text-gray-500">
        Este proveedor no tiene servicios pendientes de facturar.
      </p>

      <div v-for="(grupo, gi) in grupos" :key="grupo.clave ?? gi" class="mb-3">
        <div v-if="modo === 'bsp'" class="mb-1 flex items-center gap-2 rounded bg-gray-50 px-3 py-2">
          <input
            type="checkbox"
            :checked="grupo.servicios.every((s) => seleccion[s.id])"
            @change="alternarGrupo(grupo)"
          />
          <button
            type="button"
            class="text-sm font-semibold text-gray-800"
            @click="abiertos[grupo.clave ?? 'sin'] = !abiertos[grupo.clave ?? 'sin']"
          >
            {{ grupo.label }}
          </button>
          <span class="text-xs text-gray-500">{{ grupo.cantidad }} boleto/s</span>
        </div>

        <div v-show="modo === 'plano' || abiertos[grupo.clave ?? 'sin']" class="divide-y divide-gray-100">
          <div
            v-for="s in grupo.servicios"
            :key="s.id"
            class="flex flex-wrap items-center gap-3 py-2 text-sm"
          >
            <input type="checkbox" :checked="!!seleccion[s.id]" @change="alternar(s)" />
            <input
              v-if="seleccion[s.id]"
              :value="seleccion[s.id].monto"
              type="number"
              step="0.01"
              class="w-28 rounded border border-gray-300 px-2 py-1 text-right text-sm"
              :class="excede(s) ? 'border-red-400 bg-red-50' : ''"
              @input="cambiarMonto(s, $event.target.value)"
            />
            <span class="text-gray-800">{{ s.nombre }}</span>
            <span class="text-gray-500">{{ s.nroConfirmacion }}</span>
            <span class="text-gray-500">({{ s.codigo }} / {{ s.titular }})</span>
            <span class="text-gray-500">{{ s.vigenciaIni }}</span>
            <span class="ml-auto tabular-nums text-gray-700">
              {{ s.moneda }} {{ formatearImporte(s.costoFinal) }}
              <span v-if="s.montoCargado" class="text-xs text-gray-400">
                (ya facturado {{ formatearImporte(s.montoCargado) }})
              </span>
            </span>
            <span v-if="excede(s)" class="w-full text-xs text-red-600">
              El monto supera el saldo del servicio.
            </span>
          </div>
        </div>
      </div>

      <div v-if="modelValue.length" class="mt-3 rounded-lg bg-gray-50 p-3 text-sm">
        <div class="flex justify-between">
          <span class="text-gray-600">Total servicios seleccionados</span>
          <span class="tabular-nums font-semibold">{{ formatearImporte(totalSeleccionado) }}</span>
        </div>
        <div class="flex justify-between" :class="descuadrada ? 'text-red-600' : 'text-gray-500'">
          <span>Diferencia con el total de la factura</span>
          <span class="tabular-nums">{{ formatearImporte(diferencia) }}</span>
        </div>
      </div>
    </div>
  </div>
</template>
