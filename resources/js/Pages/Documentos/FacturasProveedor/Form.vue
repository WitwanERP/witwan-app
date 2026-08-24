<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import SelectorProveedor from './components/SelectorProveedor.vue'
import ConceptosAdicionales from './components/ConceptosAdicionales.vue'
import GrillaImportes from './components/GrillaImportes.vue'
import ImputacionAreas from './components/ImputacionAreas.vue'
import PickerOcupaciones from './components/PickerOcupaciones.vue'
import ResumenTotales from './components/ResumenTotales.vue'
import { calcularTotales } from './calculo.js'
import { useEnvio } from '@/lib/envio'

defineOptions({ layout: AppLayout })

const props = defineProps({
  modo: { type: String, default: 'crear' },
  registro: { type: Object, default: null },
  opciones: { type: Object, required: true },
  monedas: { type: Array, default: () => [] },
  baseUrl: { type: String, required: true },
  editables: { type: Array, default: () => [] },
})

const esEdicion = computed(() => props.modo === 'editar')

const fieldBase =
  'w-full rounded-lg border border-gray-300 bg-gray-50 py-2 px-3 text-sm text-gray-800 transition focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 disabled:bg-gray-100 disabled:text-gray-500'

const r = props.registro || {}
const monedaBasica = props.monedas.find((m) => m.basica)?.id ?? ''

const form = useForm({
  facturaproveedor_nro: r.facturaproveedor_nro ?? '',
  facturaproveedor_tipodocumento: r.facturaproveedor_tipodocumento ?? 'Factura',
  facturaproveedor_tipofactura: r.facturaproveedor_tipofactura ?? '',
  fk_proveedor_id: r.fk_proveedor_id ?? '',
  fk_plancuenta_id: r.fk_plancuenta_id ?? '',
  fk_proyecto_id: r.fk_proyecto_id ?? '',
  fk_itemgasto_id: r.fk_itemgasto_id ?? '',
  fk_moneda_id: r.fk_moneda_id ?? monedaBasica,
  cotizacion: r.cotizacion ?? 1,
  tipomovimiento: r.tipomovimiento ?? 'Gasto',
  electronica: r.electronica ?? '',
  fecha: (r.fecha ?? '').slice(0, 10),
  fechacontable: (r.fechacontable ?? '').slice(0, 10),
  vencimiento: (r.vencimiento ?? '').slice(0, 10),
  exento: r.montoexento ?? 0,
  nocomputable: r.montonocomputable ?? 0,
  especial: r.montoespecial ?? 0,
  general: r.montogeneral ?? 0,
  monto27: r.monto27 ?? 0,
  monto25: r.monto25 ?? 0,
  ivatotal: r.ivatotal ?? 0,
  ivatur: r.ivatur ?? 0,
  retencioniva: r.retencioniva ?? 0,
  retencioniibb: r.retencioniibb ?? 0,
  percepcioniva: r.percepcioniva ?? 0,
  percepcioniibb: r.percepcioniibb ?? 0,
  retencionganancias: r.retencionganancias ?? 0,
  percepcionganancias: r.percepcionganancias ?? 0,
  otrosimpuestos: r.otrosimpuestos ?? 0,
  descripcion: r.descripcion ?? '',
  adicionales: r.adicionales ?? {},
  areaimputacion: r.areaimputacion ?? {},
  ocupacion: [],
  archivo: null,
})

/** En edición el legacy sólo permite tocar unos pocos campos. */
const bloqueado = (campo) => esEdicion.value && !props.editables.includes(campo)

const conceptos = computed(() => props.opciones.conceptos ?? [])
const exentoBloqueado = computed(() => conceptos.value.length > 0)

const totales = computed(() => calcularTotales(form, props.opciones.calculo, conceptos.value))

const sumasValidas = computed(() =>
  props.opciones.calculo?.ivatotalEditable ? [100, 200] : [100]
)

// --- Verificación contra el servidor ---------------------------------------
// El navegador calcula para dar respuesta inmediata, pero el servidor es la
// autoridad. Si difieren, se avisa: es la red que evita que las dos
// implementaciones se separen sin que nadie lo note, que es lo que pasó en el
// legacy entre el JS y el PHP.
const desvio = ref(0)
let debounceCalculo = null

watch(
  () => [form.general, form.especial, form.monto27, form.monto25, form.exento, form.nocomputable, form.ivatotal, form.ivatur],
  () => {
    clearTimeout(debounceCalculo)
    debounceCalculo = setTimeout(verificarConServidor, 600)
  }
)

async function verificarConServidor() {
  try {
    const r = await fetch(`${props.baseUrl}/calcular`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
      },
      body: JSON.stringify({ ...form.data(), adicionales: form.adicionales }),
    })
    if (!r.ok) return
    const servidor = await r.json()
    const diff = Math.round((servidor.montototal - totales.value.montototal) * 100) / 100
    desvio.value = Math.abs(diff) > 0.01 ? diff : 0
  } catch {
    // Sin conexión al endpoint no se bloquea la carga: el servidor recalcula
    // igual al guardar.
    desvio.value = 0
  }
}

// --- Cotización -------------------------------------------------------------
watch(
  () => [form.fk_moneda_id, form.fecha],
  async () => {
    if (!form.fk_moneda_id) return
    const params = new URLSearchParams({ moneda: form.fk_moneda_id })
    if (form.fecha) params.set('fecha', form.fecha)
    const r = await fetch(`${props.baseUrl}/cotizacion?${params}`)
    if (r.ok) form.cotizacion = (await r.json()).cotizacion
  }
)

// --- Duplicados -------------------------------------------------------------
const duplicada = ref(false)

async function chequearDuplicado() {
  if (!form.facturaproveedor_nro || !form.fk_proveedor_id) return
  const params = new URLSearchParams({
    nro: form.facturaproveedor_nro,
    proveedor: String(form.fk_proveedor_id),
    tipo: form.facturaproveedor_tipodocumento,
  })
  const r = await fetch(`${props.baseUrl}/duplicado?${params}`)
  if (r.ok) duplicada.value = !(await r.json()).ok
}

function cuentaSugerida(cuenta) {
  // `proveedor.iata` guarda la cuenta contable sugerida del proveedor.
  if (cuenta && !form.fk_plancuenta_id) form.fk_plancuenta_id = cuenta
}

const { enviando, enviar } = useEnvio()

function guardar() {
  enviar(
    (opciones) =>
      esEdicion.value
        ? form.put(`${props.baseUrl}/${props.registro.facturaproveedor_id}`, opciones)
        : form.post(props.baseUrl, opciones),
    { preserveScroll: true },
  )
}
</script>

<template>
  <div>
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">
          {{ esEdicion ? 'Editar factura de tercero' : 'Nueva factura de tercero' }}
        </h1>
        <p class="text-gray-500">Los campos en negrita son obligatorios.</p>
      </div>
      <Link :href="baseUrl" class="btn btn-secondary">Cancelar</Link>
    </div>

    <div v-if="esEdicion" class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
      En edición sólo se pueden cambiar la cuenta contable, el proyecto, el item de gasto, la imputación y el
      número. Al guardar, el asiento contable se vuelve a generar con los datos nuevos.
    </div>

    <form @submit.prevent="guardar">
      <!-- Proveedor y datos de facturación -->
      <div class="card mb-4">
        <div class="card-header"><h3 class="card-title">Datos de facturación</h3></div>
        <div class="card-body grid grid-cols-1 gap-4 md:grid-cols-3">
          <SelectorProveedor
            v-model="form.fk_proveedor_id"
            :base-url="baseUrl"
            :disabled="bloqueado('fk_proveedor_id')"
            :error="form.errors.fk_proveedor_id"
            @cuenta="cuentaSugerida"
          />

          <div>
            <label class="form-label font-bold">Tipo de gasto</label>
            <select v-model="form.tipomovimiento" :class="fieldBase" :disabled="bloqueado('tipomovimiento')">
              <option v-for="(label, valor) in opciones.tiposMovimiento" :key="valor" :value="valor">
                {{ label }}
              </option>
            </select>
            <p v-if="form.errors.tipomovimiento" class="form-error">{{ form.errors.tipomovimiento }}</p>
          </div>

          <div>
            <label class="form-label font-bold">Cuenta contable</label>
            <select v-model="form.fk_plancuenta_id" :class="fieldBase" :disabled="bloqueado('fk_plancuenta_id')">
              <option value="">Seleccione una opción</option>
              <option v-for="(nombre, id) in opciones.plancuenta" :key="id" :value="id">{{ nombre }}</option>
            </select>
            <p v-if="form.errors.fk_plancuenta_id" class="form-error">{{ form.errors.fk_plancuenta_id }}</p>
          </div>

          <div>
            <label class="form-label font-bold">Tipo de documento</label>
            <select
              v-model="form.facturaproveedor_tipodocumento"
              :class="fieldBase"
              :disabled="bloqueado('facturaproveedor_tipodocumento')"
              @change="chequearDuplicado"
            >
              <option v-for="(label, valor) in opciones.tiposDocumento" :key="valor" :value="valor">
                {{ label }}
              </option>
            </select>
          </div>

          <div>
            <label class="form-label">Tipo de factura</label>
            <select
              v-model="form.facturaproveedor_tipofactura"
              :class="fieldBase"
              :disabled="bloqueado('facturaproveedor_tipofactura')"
            >
              <option value="">—</option>
              <option v-for="(label, valor) in opciones.tiposFactura" :key="valor" :value="valor">
                {{ label }}
              </option>
            </select>
          </div>

          <div>
            <label class="form-label font-bold">Número</label>
            <input
              v-model="form.facturaproveedor_nro"
              type="text"
              :class="fieldBase"
              :disabled="bloqueado('facturaproveedor_nro')"
              :placeholder="opciones.mascaraNumero || ''"
              @blur="chequearDuplicado"
            />
            <p v-if="duplicada" class="form-error">
              Ya existe una factura con ese número, proveedor y tipo de documento.
            </p>
            <p v-if="form.errors.facturaproveedor_nro" class="form-error">
              {{ form.errors.facturaproveedor_nro }}
            </p>
          </div>

          <div>
            <label class="form-label font-bold">Fecha de factura</label>
            <input v-model="form.fecha" type="date" :class="fieldBase" :disabled="bloqueado('fecha')" />
            <p v-if="form.errors.fecha" class="form-error">{{ form.errors.fecha }}</p>
          </div>

          <div>
            <label class="form-label">Fecha contable</label>
            <input
              v-model="form.fechacontable"
              type="date"
              :class="fieldBase"
              :disabled="bloqueado('fechacontable')"
            />
            <p class="form-hint">Si se deja vacía se usa la fecha de factura.</p>
          </div>

          <div>
            <label class="form-label">Vencimiento</label>
            <input
              v-model="form.vencimiento"
              type="date"
              :class="fieldBase"
              :disabled="bloqueado('vencimiento')"
            />
          </div>

          <div>
            <label class="form-label font-bold">Moneda</label>
            <select v-model="form.fk_moneda_id" :class="fieldBase" :disabled="bloqueado('fk_moneda_id')">
              <option v-for="m in monedas" :key="m.id" :value="m.id">{{ m.id }} — {{ m.label }}</option>
            </select>
          </div>

          <div>
            <label class="form-label">Cotización</label>
            <input
              v-model="form.cotizacion"
              type="number"
              step="0.0001"
              :class="fieldBase"
              :disabled="bloqueado('cotizacion')"
            />
          </div>

          <div>
            <label class="form-label">Proyecto</label>
            <select v-model="form.fk_proyecto_id" :class="fieldBase" :disabled="bloqueado('fk_proyecto_id')">
              <option value="">—</option>
              <option v-for="(nombre, id) in opciones.proyectos" :key="id" :value="id">{{ nombre }}</option>
            </select>
          </div>

          <div v-if="Object.keys(opciones.itemsGasto || {}).length">
            <label class="form-label" :class="opciones.itemgastoObligatorio ? 'font-bold' : ''">Item de gasto</label>
            <select v-model="form.fk_itemgasto_id" :class="fieldBase" :disabled="bloqueado('fk_itemgasto_id')">
              <option value="">—</option>
              <option v-for="(nombre, id) in opciones.itemsGasto" :key="id" :value="id">{{ nombre }}</option>
            </select>
            <p v-if="form.errors.fk_itemgasto_id" class="form-error">{{ form.errors.fk_itemgasto_id }}</p>
          </div>

          <div v-if="opciones.electronicaVisible">
            <label class="form-label">Comprobante</label>
            <select v-model="form.electronica" :class="fieldBase" :disabled="bloqueado('electronica')">
              <option value="Y">Electrónico</option>
              <option value="N">Papel</option>
            </select>
          </div>

          <div v-if="opciones.adjunto && !esEdicion">
            <label class="form-label">Archivo adjunto</label>
            <input type="file" class="w-full text-sm" @input="form.archivo = $event.target.files[0]" />
            <p class="form-hint">PDF o imagen del comprobante, hasta 8 MB.</p>
            <p v-if="form.errors.archivo" class="form-error">{{ form.errors.archivo }}</p>
          </div>
        </div>
      </div>

      <ConceptosAdicionales
        v-model="form.adicionales"
        :conceptos="conceptos"
        :disabled="esEdicion"
      />

      <GrillaImportes
        :form="form"
        :tasas="opciones.calculo"
        :totales="totales"
        :pais="opciones.calculo.ivatotalEditable ? 'CL' : 'AR'"
        :exento-bloqueado="exentoBloqueado"
        :disabled="esEdicion"
        :errors="form.errors"
      />

      <ImputacionAreas
        v-model="form.areaimputacion"
        :config="opciones.imputacion"
        :sumas-validas="sumasValidas"
        :disabled="esEdicion && !editables.includes('areaimputacion')"
      />

      <PickerOcupaciones
        v-if="!esEdicion"
        v-model="form.ocupacion"
        :proveedor-id="form.fk_proveedor_id"
        :tipo-movimiento="form.tipomovimiento"
        :base-url="baseUrl"
        :total-factura="totales.montototal"
      />

      <div class="card mb-4">
        <div class="card-header"><h3 class="card-title">Observaciones</h3></div>
        <div class="card-body">
          <textarea
            v-model="form.descripcion"
            rows="3"
            :class="fieldBase"
            :disabled="bloqueado('descripcion')"
          />
        </div>
      </div>

      <ResumenTotales
        :totales="totales"
        :moneda="form.fk_moneda_id"
        :desvio="desvio"
        :procesando="enviando"
        :modo="modo"
        @guardar="guardar"
      />
    </form>
  </div>
</template>
