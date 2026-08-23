<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import GrillaDebeHaber from './components/GrillaDebeHaber.vue'
import GrillaFondos from './components/GrillaFondos.vue'
import { formatearImporte, hoyIso } from '@/lib/formato'

defineOptions({ layout: AppLayout })

const props = defineProps({
  config: { type: Object, required: true },
  opciones: { type: Object, required: true },
})

const esDebeHaber = computed(() => props.config.grilla === 'debe-haber')

const lineaVacia = () =>
  esDebeHaber.value
    ? {
        cuenta: 0,
        cuentaLabel: '',
        descripcion: '',
        debe: '',
        haber: '',
        cliente: 0,
        clienteLabel: '',
        proveedor: 0,
        proveedorLabel: '',
        file: 0,
        fileLabel: '',
      }
    : { monto: '', ingreso: '', egreso: '' }

const monedaInicial =
  props.opciones.monedas.find((m) => m.basica)?.id ?? props.opciones.monedas[0]?.id ?? ''

const form = useForm({
  fecha: hoyIso(),
  fk_moneda_id: monedaInicial,
  // El legacy arranca la cotización en 1 y ahí la deja (formasientocontable.php:60).
  // Se mantiene el default, pero al lado se muestra la sugerida del día: cambiar
  // el valor grabado en silencio revaluaría todo asiento en moneda extranjera, y
  // esa decisión es del contador, no del port.
  cotizacion: 1,
  observaciones: '',
  descripcion: '',
  arqueo: true,
  afecta_cobranza: props.config.afectaCobranzaPorDefecto,
  fk_proyecto_id: '',
  lineas: esDebeHaber.value ? [lineaVacia(), lineaVacia()] : [lineaVacia()],
})

const grilla = ref(null)

// --- Cotización sugerida -----------------------------------------------------

const sugerida = reactive({ valor: null, cargando: false })

async function traerSugerida() {
  if (!esDebeHaber.value) return

  const esBasica = props.opciones.monedas.find((m) => m.id === form.fk_moneda_id)?.basica

  if (esBasica) {
    sugerida.valor = 1
    return
  }

  sugerida.cargando = true
  try {
    const r = await fetch(
      `${props.config.baseUrl}/cotizacion?moneda=${encodeURIComponent(form.fk_moneda_id)}&fecha=${form.fecha}`,
      { headers: { Accept: 'application/json' } }
    )
    sugerida.valor = r.ok ? Number((await r.json()).cotizacion) : null
  } catch {
    sugerida.valor = null
  } finally {
    sugerida.cargando = false
  }
}

watch(() => [form.fk_moneda_id, form.fecha], traerSugerida, { immediate: true })

const usarSugerida = () => {
  if (sugerida.valor) form.cotizacion = sugerida.valor
}

// --- Validación de envío -----------------------------------------------------

const puedeGuardar = computed(() => {
  if (!form.fecha || !form.fk_moneda_id) return false

  return esDebeHaber.value
    ? grilla.value?.balancea && grilla.value?.totalDebe > 0
    : grilla.value?.total > 0 && !grilla.value?.hayError
})

/**
 * El campo fecha no baja del último cierre de caja: el legacy lo dejaba elegir y
 * recién al guardar te mandaba a /dashboard/errormov, perdiendo todo lo cargado.
 */
const fechaMinima = computed(() => props.config.primeraFechaOperable || undefined)

function agregar() {
  form.lineas.push(lineaVacia())
}

function quitar(i) {
  form.lineas.splice(i, 1)
}

function enviar() {
  form.post(props.config.baseUrl, { preserveScroll: true })
}

const fieldBase =
  'w-full rounded-lg border border-gray-300 bg-gray-50 py-2 px-3 text-sm text-gray-800 transition focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20'
</script>

<template>
  <form @submit.prevent="enviar">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Nuevo {{ config.singular }}</h1>
        <p class="text-gray-500">El número se asigna al guardar.</p>
      </div>
      <Link :href="config.baseUrl" class="btn btn-secondary">Volver al listado</Link>
    </div>

    <div class="card mb-4">
      <div class="card-body">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
          <div>
            <label class="form-label">Fecha</label>
            <input v-model="form.fecha" type="date" :class="fieldBase" :min="fechaMinima" required />
            <p v-if="form.errors.fecha" class="form-error">{{ form.errors.fecha }}</p>
            <p v-else-if="fechaMinima" class="form-hint">Período abierto desde el {{ fechaMinima }}.</p>
          </div>

          <div>
            <label class="form-label">Moneda</label>
            <select v-model="form.fk_moneda_id" :class="fieldBase" required>
              <option v-for="m in opciones.monedas" :key="m.id" :value="m.id">{{ m.id }}</option>
            </select>
            <p v-if="form.errors.fk_moneda_id" class="form-error">{{ form.errors.fk_moneda_id }}</p>
          </div>

          <div v-if="esDebeHaber">
            <label class="form-label">Cotización</label>
            <input v-model="form.cotizacion" type="number" step="0.00001" min="0" :class="fieldBase" required />
            <p v-if="form.errors.cotizacion" class="form-error">{{ form.errors.cotizacion }}</p>
            <p v-else-if="sugerida.cargando" class="form-hint">Buscando la cotización del día…</p>
            <p v-else-if="sugerida.valor === 0" class="form-error">
              No hay cotización cargada para {{ form.fk_moneda_id }} a esa fecha.
            </p>
            <p v-else-if="sugerida.valor && Number(sugerida.valor) !== Number(form.cotizacion)" class="form-hint">
              Sugerida: {{ formatearImporte(sugerida.valor, 5) }}
              <button type="button" class="text-blue-600 hover:underline" @click="usarSugerida">usar</button>
            </p>
          </div>

          <div v-if="config.usaProyecto">
            <label class="form-label">Proyecto</label>
            <select v-model="form.fk_proyecto_id" :class="fieldBase">
              <option value="">—</option>
              <option v-for="p in opciones.proyectos" :key="p.id" :value="p.id">{{ p.label }}</option>
            </select>
          </div>

          <div v-if="!esDebeHaber" class="lg:col-span-2">
            <label class="form-label">Descripción</label>
            <input v-model="form.descripcion" type="text" :class="fieldBase" placeholder="Concepto del movimiento" />
            <p class="form-hint">Se copia en los dos movimientos de cada operación.</p>
          </div>

          <div v-if="config.usaArqueo" class="flex items-end">
            <label class="flex items-center gap-2 text-sm text-gray-700">
              <input v-model="form.arqueo" type="checkbox" class="rounded border-gray-300" />
              Tocar arqueo
            </label>
          </div>

          <div v-if="config.usaAfectaCobranza" class="flex items-end">
            <label class="flex items-center gap-2 text-sm text-gray-700">
              <input v-model="form.afecta_cobranza" type="checkbox" class="rounded border-gray-300" />
              Afecta cobranza
            </label>
          </div>
        </div>

        <div class="mt-4">
          <label class="form-label">Observaciones</label>
          <textarea v-model="form.observaciones" rows="2" :class="fieldBase"></textarea>
        </div>
      </div>
    </div>

    <GrillaDebeHaber
      v-if="esDebeHaber"
      ref="grilla"
      :lineas="form.lineas"
      :config="config"
      :base-url="config.baseUrl"
      :moneda="form.fk_moneda_id"
      :errores="form.errors"
      @agregar="agregar"
      @quitar="quitar"
    />

    <GrillaFondos
      v-else
      ref="grilla"
      :lineas="form.lineas"
      :cuentas="opciones.cuentas"
      :moneda="form.fk_moneda_id"
      :errores="form.errors"
      @agregar="agregar"
      @quitar="quitar"
    />

    <div class="mt-4 flex flex-wrap items-center gap-2">
      <button type="submit" class="btn btn-primary" :disabled="!puedeGuardar || form.processing">
        {{ form.processing ? 'Guardando…' : 'Guardar' }}
      </button>
      <Link :href="config.baseUrl" class="btn btn-secondary">Cancelar</Link>

      <span v-if="!puedeGuardar" class="text-sm text-gray-500">
        <template v-if="esDebeHaber">El asiento tiene que balancear y tener al menos un importe.</template>
        <template v-else>Cargá al menos una operación con monto y las dos cuentas.</template>
      </span>
    </div>
  </form>
</template>
