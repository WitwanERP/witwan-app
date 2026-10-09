<script setup>
import { computed, h, nextTick, ref } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import { formatearFecha, formatearImporte } from '@/lib/formato.js'

/**
 * Mapa contable del file. Con ?embed=1 se renderiza sin layout para abrirlo
 * desde la ficha del CI (mismo patrón que FacturasProveedor/Ver).
 */
defineOptions({
  layout: (hh, page) => (page.props.embed ? page : h(AppLayout, () => page)),
})

const props = defineProps({
  mapa: { type: Object, required: true },
  embed: { type: Boolean, default: false },
})

const m = computed(() => props.mapa)
const dec = computed(() => m.value.decimales)
const basica = computed(() => m.value.moneda_basica)
const t = computed(() => m.value.economia.totales)
const eco = computed(() => m.value.economia.servicios)

const imp = (v, d = dec.value) => formatearImporte(v ?? 0, d)
const tasa = (v) => (v ? formatearImporte(v, 4) : '—')
const signo = (v) => (v > 0.005 ? 'text-green-700' : v < -0.005 ? 'text-red-700' : 'text-gray-500')
const origenes = { tabla: 'tabla', implicita: 'implícita', faltante: 'falta' }
const destino = computed(() => (props.embed ? '_top' : '_self'))

// ---- ¿Cuánto se gana? --------------------------------------------------
const cascada = computed(() => [
  { k: 'renta_presupuestada', label: 'Renta presupuestada', ayuda: 'Servicios a su propia cotización (cotventa / cotcosto). Es la renta con que se vendió.', fuerte: true },
  { k: 'dif_precio_venta', label: 'Precio de venta', ayuda: 'Lo facturado (FC − NC + ND) contra los servicios facturados, neto de IVA: descuentos, recargos, NC parciales.' },
  { k: 'dif_precio_costo', label: 'Precio de costo', ayuda: 'Lo que facturó (o se le pagó) al proveedor contra el costo cargado en el servicio.' },
  { k: 'renta_comercial', label: 'Renta comercial real', ayuda: 'Renta con las cantidades reales, todavía a la cotización del servicio.', total: true },
  { k: 'dc_servicio_factura', label: 'Cambio: servicio → factura', ayuda: 'Venta neta × (TC de la factura − cotventa).' },
  { k: 'dc_factura_cobranza', label: 'Cambio: factura → cobranza', ayuda: 'Lo cobrado de lo facturado en moneda extranjera × (TC del recibo − TC de la factura).' },
  { k: 'dc_servicio_fc3', label: 'Cambio: servicio → factura del proveedor', ayuda: 'Lo facturado por el proveedor × (cotcosto − TC de su factura).' },
  { k: 'dc_fc3_pago', label: 'Cambio: factura del proveedor → pago', ayuda: 'Lo pagado de lo facturado × (TC de la factura del proveedor − TC de la OP).' },
  { k: 'dc_servicio_pago', label: 'Cambio: servicio → pago (sin factura de proveedor)', ayuda: 'Lo pagado × (cotcosto − TC de la OP), cuando el proveedor no facturó.' },
  { k: 'renta_real', label: 'Resultado real del file', ayuda: 'Lo que efectivamente se gana: renta comercial real más todas las diferencias de cambio.', total: true, final: true },
])

const comparar = computed(() => {
  const co = m.value.contable
  return [
    { label: 'Renta congelada al facturar', valor: t.value.renta_congelada, ayuda: 'serviciofactura.renta × cotventa. La que usa rentamt.' },
    {
      label: 'Renta contable (mayor)',
      valor: co.sin_cuentas_renta || co.renta_ambigua ? null : co.renta,
      ayuda: co.sin_cuentas_renta
        ? 'No hay cuentas de renta configuradas.'
        : co.renta_ambigua
          ? 'No separable del costo: el file usa una misma cuenta como costo y como renta.'
          : `Cuentas de renta, ${co.renta_movimientos} movimiento(s).`,
    },
    { label: 'Diferencia de cambio asentada', valor: co.dif_cambio_asentada, ayuda: co.cuentas_dif_cambio.length ? co.cuentas_dif_cambio.join(', ') : 'Ningún asiento del file toca una cuenta de diferencia de cambio.' },
    { label: 'Caja: cobrado − pagado', valor: t.value.caja, ayuda: `Cobrado ${imp(t.value.cobrado)} − pagado a proveedores ${imp(t.value.pagado)} (incluye IVA y lo pendiente).` },
  ]
})

// ---- Desvíos -------------------------------------------------------------
const severidades = { alta: 'badge-danger', media: 'badge-warning', info: 'badge-info' }
const areas = { venta: 'Venta', costo: 'Costo', cambio: 'Cambio', renta: 'Renta', contable: 'Contabilidad' }
const filtroSev = ref('')
const desvios = computed(() => m.value.desvios.filter((d) => !filtroSev.value || d.severidad === filtroSev.value))
const conteo = computed(() => m.value.desvios.reduce((a, d) => ({ ...a, [d.severidad]: (a[d.severidad] || 0) + 1 }), {}))

async function irA(d) {
  if (!d.ref || d.ref.tipo !== 'servicio') return
  abiertos.value = new Set([...abiertos.value, d.ref.id])
  await nextTick()
  document.getElementById(`srv-${d.ref.id}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' })
}

// ---- Servicios -----------------------------------------------------------
const abiertos = ref(new Set())
function alternar(id) {
  const s = new Set(abiertos.value)
  s.has(id) ? s.delete(id) : s.add(id)
  abiertos.value = s
}
const facturas = computed(() => Object.fromEntries(m.value.venta.facturas.map((f) => [f.id, f])))
const cambioServicio = (e) => e.dc_servicio_factura + e.dc_servicio_fc3 + e.dc_fc3_pago + e.dc_servicio_pago

// ---- Contabilidad --------------------------------------------------------
const gruposAbiertos = ref(new Set())
function alternarGrupo(c) {
  const s = new Set(gruposAbiertos.value)
  s.has(c) ? s.delete(c) : s.add(c)
  gruposAbiertos.value = s
}
const caminos = { fk_file_id: 'file', rel_filefactura: 'ficha', servicio: 'servicios', factura: 'factura' }
const docsVenta = computed(() => [...m.value.venta.facturas, ...m.value.venta.notas_credito, ...m.value.venta.notas_debito])
const motivosCobranza = {
  sin_facturas: 'Sin facturas.',
  moneda_basica: 'Facturado en moneda básica: no hay diferencia de cambio en la cobranza.',
  varias_monedas: 'Facturado en más de una moneda extranjera: no se calcula.',
  mixta: 'Hay facturas en moneda básica y extranjera: aproximado.',
}
</script>

<template>
  <div class="mx-auto max-w-7xl space-y-6" :class="embed ? 'p-4' : ''">
    <!-- Encabezado -->
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Mapa contable · File {{ m.file.codigo }}</h1>
        <p class="mt-1 text-sm text-gray-600">
          {{ m.file.cliente || 'Sin cliente' }} · {{ m.file.titular }} · Estado {{ m.file.estado }} · Moneda del file {{ m.file.moneda }} · Alta
          {{ formatearFecha(m.file.fecha_alta) }}
          <span v-if="m.file.files_agrupados.length"> · Incluye files agrupados {{ m.file.files_agrupados.join(', ') }}</span>
        </p>
        <p class="text-xs text-gray-500">Importes en {{ basica }} salvo indicación. Tolerancia de desvío: {{ imp(m.tolerancia) }}.</p>
      </div>
      <a :href="m.file.link" :target="destino" class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">Abrir ficha del file</a>
    </div>

    <div class="grid gap-6 lg:grid-cols-5">
      <!-- ¿Cuánto se gana? -->
      <div class="card lg:col-span-3">
        <div class="card-header"><h3 class="card-title">¿Cuánto se gana?</h3></div>
        <div class="card-body p-0">
          <table class="w-full text-sm">
            <tbody>
              <tr v-for="r in cascada" :key="r.k" :class="[r.total ? 'bg-gray-50 font-semibold' : '', r.final ? 'text-base' : '']" class="border-b border-gray-100">
                <td class="px-4 py-2">
                  <div :class="r.total || r.fuerte ? 'text-gray-900' : 'pl-4 text-gray-700'">{{ r.label }}</div>
                  <div class="text-xs font-normal text-gray-500" :class="r.total || r.fuerte ? '' : 'pl-4'">{{ r.ayuda }}</div>
                </td>
                <td class="whitespace-nowrap px-4 py-2 text-right tabular-nums" :class="r.total || r.fuerte ? 'text-gray-900' : signo(t[r.k])">
                  {{ !r.total && !r.fuerte && t[r.k] > 0 ? '+' : '' }}{{ imp(t[r.k]) }}
                </td>
              </tr>
            </tbody>
          </table>
          <div class="border-t border-gray-200 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Para comparar</div>
          <table class="w-full text-sm">
            <tbody>
              <tr v-for="c in comparar" :key="c.label" class="border-b border-gray-100">
                <td class="px-4 py-2">
                  <div class="text-gray-700">{{ c.label }}</div>
                  <div class="text-xs text-gray-500">{{ c.ayuda }}</div>
                </td>
                <td class="whitespace-nowrap px-4 py-2 text-right tabular-nums text-gray-900">{{ c.valor === null || c.valor === undefined ? '—' : imp(c.valor) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Desvíos -->
      <div class="card lg:col-span-2">
        <div class="card-header">
          <h3 class="card-title">Desvíos ({{ m.desvios.length }})</h3>
          <div class="flex gap-1 text-xs">
            <button type="button" class="rounded px-2 py-0.5" :class="filtroSev === '' ? 'bg-gray-800 text-white' : 'text-gray-600 hover:bg-gray-100'" @click="filtroSev = ''">Todos</button>
            <button
              v-for="(c, s) in severidades"
              :key="s"
              type="button"
              class="rounded px-2 py-0.5"
              :class="filtroSev === s ? 'bg-gray-800 text-white' : 'text-gray-600 hover:bg-gray-100'"
              @click="filtroSev = s"
            >
              {{ s }} ({{ conteo[s] || 0 }})
            </button>
          </div>
        </div>
        <div class="card-body max-h-[36rem] space-y-3 overflow-auto">
          <p v-if="!desvios.length" class="py-6 text-center text-sm text-gray-500">Sin desvíos.</p>
          <div
            v-for="(d, i) in desvios"
            :key="i"
            class="rounded-md border border-gray-200 p-3"
            :class="d.ref?.tipo === 'servicio' ? 'cursor-pointer hover:bg-gray-50' : ''"
            @click="irA(d)"
          >
            <div class="flex items-start justify-between gap-2">
              <div class="flex items-center gap-2">
                <span class="badge" :class="severidades[d.severidad]">{{ d.severidad }}</span>
                <span class="text-xs uppercase tracking-wide text-gray-400">{{ areas[d.area] }}</span>
              </div>
              <span v-if="d.importe !== null" class="whitespace-nowrap text-sm tabular-nums" :class="signo(d.importe)">{{ imp(d.importe) }}</span>
            </div>
            <div class="mt-1 text-sm font-medium text-gray-900">{{ d.titulo }}</div>
            <div class="mt-0.5 text-xs text-gray-600">{{ d.detalle }}</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Servicios -->
    <div class="card">
      <div class="card-header"><h3 class="card-title">Servicios</h3><span class="text-xs text-gray-500">Click en un servicio para ver sus comprobantes y el detalle de cambio</span></div>
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50 text-xs uppercase text-gray-500">
            <tr>
              <th class="px-3 py-2 text-left">Servicio</th>
              <th class="px-3 py-2 text-right">Venta</th>
              <th class="px-3 py-2 text-right">Facturado a</th>
              <th class="px-3 py-2 text-right">Costo</th>
              <th class="px-3 py-2 text-right">Fact. proveedor</th>
              <th class="px-3 py-2 text-right">Pagado</th>
              <th class="px-3 py-2 text-right">Renta presup.</th>
              <th class="px-3 py-2 text-right">Precio</th>
              <th class="px-3 py-2 text-right">Cambio</th>
              <th class="px-3 py-2 text-right">Renta real</th>
            </tr>
          </thead>
          <tbody>
            <template v-for="s in m.servicios" :key="s.id">
              <tr :id="`srv-${s.id}`" class="cursor-pointer border-t border-gray-100 hover:bg-gray-50" :class="s.cancelado ? 'text-gray-400' : ''" @click="alternar(s.id)">
                <td class="px-3 py-2">
                  <div class="font-medium" :class="s.cancelado ? 'line-through' : 'text-gray-900'">{{ s.nombre }}</div>
                  <div class="text-xs text-gray-500">{{ s.proveedor || 'Sin proveedor' }} · {{ s.tipo }} · {{ formatearFecha(s.vigencia) }} · {{ s.status }}</div>
                </td>
                <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums">
                  {{ s.moneda_venta }} {{ imp(s.total) }}
                  <div class="text-xs text-gray-500">a {{ tasa(s.cv) }} <sup v-if="origenes[s.cv_origen]">{{ origenes[s.cv_origen] }}</sup></div>
                </td>
                <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums">
                  <template v-if="s.factura_vigente">
                    {{ facturas[s.factura_vigente]?.numero }}
                    <div class="text-xs text-gray-500">a {{ tasa(s.tasa_factura) }} <sup v-if="origenes[s.tasa_factura_origen]">{{ origenes[s.tasa_factura_origen] }}</sup></div>
                  </template>
                  <span v-else class="text-xs text-gray-400">sin factura</span>
                </td>
                <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums">
                  {{ s.moneda_costo }} {{ imp(s.costo_operado) }}
                  <div class="text-xs text-gray-500">a {{ tasa(s.cc) }} <sup v-if="origenes[s.cc_origen]">{{ origenes[s.cc_origen] }}</sup></div>
                </td>
                <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums">
                  <template v-if="s.fc3.length">
                    {{ imp(eco[s.id].cantidades.facturado_proveedor) }}
                    <div class="text-xs text-gray-500">a {{ tasa(eco[s.id].cantidades.tasa_fc3) }}</div>
                  </template>
                  <span v-else class="text-xs text-gray-400">—</span>
                </td>
                <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums">
                  <template v-if="eco[s.id].cantidades.pagado">
                    {{ imp(eco[s.id].cantidades.pagado) }}
                    <div class="text-xs text-gray-500">a {{ tasa(eco[s.id].cantidades.tasa_pago) }}</div>
                  </template>
                  <span v-else class="text-xs text-gray-400">—</span>
                </td>
                <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums">{{ imp(eco[s.id].renta_presupuestada) }}</td>
                <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums" :class="signo(eco[s.id].dif_precio_costo)">{{ imp(eco[s.id].dif_precio_costo) }}</td>
                <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums" :class="signo(cambioServicio(eco[s.id]))">{{ imp(cambioServicio(eco[s.id])) }}</td>
                <td class="whitespace-nowrap px-3 py-2 text-right font-semibold tabular-nums" :class="eco[s.id].renta_real < 0 ? 'text-red-700' : 'text-gray-900'">{{ imp(eco[s.id].renta_real) }}</td>
              </tr>
              <tr v-if="abiertos.has(s.id)" class="bg-gray-50">
                <td colspan="10" class="px-6 py-4">
                  <div class="grid gap-6 text-xs md:grid-cols-3">
                    <div>
                      <div class="mb-1 font-semibold text-gray-700">Renta del servicio</div>
                      <dl class="space-y-1">
                        <div class="flex justify-between"><dt>Venta neta presupuestada</dt><dd class="tabular-nums">{{ imp(eco[s.id].venta_neta) }}</dd></div>
                        <div class="flex justify-between"><dt>Costo presupuestado</dt><dd class="tabular-nums">{{ imp(eco[s.id].costo) }}</dd></div>
                        <div class="flex justify-between font-medium"><dt>Renta presupuestada</dt><dd class="tabular-nums">{{ imp(eco[s.id].renta_presupuestada) }}</dd></div>
                        <div class="flex justify-between"><dt>Precio de costo</dt><dd class="tabular-nums" :class="signo(eco[s.id].dif_precio_costo)">{{ imp(eco[s.id].dif_precio_costo) }}</dd></div>
                        <div class="flex justify-between"><dt>Cambio servicio → factura</dt><dd class="tabular-nums" :class="signo(eco[s.id].dc_servicio_factura)">{{ imp(eco[s.id].dc_servicio_factura) }}</dd></div>
                        <div class="flex justify-between"><dt>Cambio servicio → fact. proveedor</dt><dd class="tabular-nums" :class="signo(eco[s.id].dc_servicio_fc3)">{{ imp(eco[s.id].dc_servicio_fc3) }}</dd></div>
                        <div class="flex justify-between"><dt>Cambio fact. proveedor → pago</dt><dd class="tabular-nums" :class="signo(eco[s.id].dc_fc3_pago)">{{ imp(eco[s.id].dc_fc3_pago) }}</dd></div>
                        <div class="flex justify-between"><dt>Cambio servicio → pago</dt><dd class="tabular-nums" :class="signo(eco[s.id].dc_servicio_pago)">{{ imp(eco[s.id].dc_servicio_pago) }}</dd></div>
                        <div class="flex justify-between font-medium"><dt>Renta real</dt><dd class="tabular-nums">{{ imp(eco[s.id].renta_real) }}</dd></div>
                        <div class="flex justify-between text-gray-500"><dt>Renta congelada al facturar</dt><dd class="tabular-nums">{{ eco[s.id].renta_congelada === null ? '—' : imp(eco[s.id].renta_congelada) }}</dd></div>
                        <div class="flex justify-between text-gray-500"><dt>Saldo con el proveedor ({{ s.moneda_costo }})</dt><dd class="tabular-nums">{{ imp(eco[s.id].cantidades.saldo_proveedor) }}</dd></div>
                      </dl>
                      <p v-if="s.impuestos && s.tiene_pnr" class="mt-2 text-gray-500">Tiene PNR: los impuestos ({{ imp(s.impuestos) }}) no se suman al costo, igual que rentamt.</p>
                    </div>
                    <div>
                      <div class="mb-1 font-semibold text-gray-700">Venta</div>
                      <ul class="space-y-1">
                        <li v-for="fid in s.facturas" :key="`f${fid}`">
                          <a :href="facturas[fid].link" :target="destino" class="text-blue-700 hover:underline">FC {{ facturas[fid].numero }}</a>
                          · {{ facturas[fid].status }} · {{ facturas[fid].moneda }} {{ imp(facturas[fid].total) }} a {{ tasa(facturas[fid].tasa) }}
                          <span v-if="fid === s.factura_vigente" class="badge badge-success ml-1">vigente</span>
                        </li>
                        <li v-for="nid in s.notas_credito" :key="`n${nid}`">NC #{{ nid }}</li>
                        <li v-if="!s.facturas.length && !s.notas_credito.length" class="text-gray-400">Sin comprobantes de venta.</li>
                      </ul>
                    </div>
                    <div>
                      <div class="mb-1 font-semibold text-gray-700">Costo</div>
                      <ul class="space-y-1">
                        <li v-for="(x, i) in s.fc3" :key="`p${i}`">Fact. proveedor {{ x.numero }} · {{ x.moneda }} {{ imp(x.monto) }} → {{ s.moneda_costo }} {{ imp(x.cantidad) }} a {{ tasa(x.tasa) }} <sup v-if="origenes[x.tasa_origen]">{{ origenes[x.tasa_origen] }}</sup></li>
                        <li v-for="(x, i) in s.ordenes_servicio" :key="`s${i}`" class="text-gray-500">OS {{ x.numero }} ({{ x.status }}) · {{ x.moneda }} {{ imp(x.monto) }}</li>
                        <li v-for="(x, i) in s.pagos" :key="`o${i}`">OP {{ x.numero }} · {{ x.moneda }} {{ imp(x.monto) }} → {{ s.moneda_costo }} {{ imp(x.cantidad) }} a {{ tasa(x.tasa) }} <sup v-if="origenes[x.tasa_origen]">{{ origenes[x.tasa_origen] }}</sup></li>
                        <li v-for="(x, i) in s.comisiones" :key="`c${i}`" class="text-gray-500">Comisión del proveedor en OP {{ x.numero }} · {{ x.moneda }} {{ imp(x.monto) }} (no suma al pagado)</li>
                        <li v-if="!s.fc3.length && !s.pagos.length && !s.ordenes_servicio.length" class="text-gray-400">Sin comprobantes de costo.</li>
                      </ul>
                    </div>
                  </div>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Circuitos -->
    <div class="grid gap-6 xl:grid-cols-2">
      <div class="card">
        <div class="card-header"><h3 class="card-title">Circuito de venta</h3></div>
        <div class="card-body space-y-5 text-sm">
          <div>
            <div class="mb-1 text-xs font-semibold uppercase text-gray-500">Facturas, notas de crédito y débito</div>
            <table class="w-full">
              <tbody>
                <tr v-for="d in docsVenta" :key="`${d.tipo}${d.id}`" class="border-b border-gray-100 align-top">
                  <td class="py-1.5 pr-2">
                    <a :href="d.link" :target="destino" class="text-blue-700 hover:underline">{{ d.tipo }} {{ d.numero }}</a>
                    <div class="text-xs text-gray-500">
                      {{ formatearFecha(d.fecha) }} · {{ d.status }}
                      <span v-if="d.caminos"> · por {{ d.caminos.map((c) => caminos[c] || c).join(', ') }}</span>
                      <span v-if="d.share < 1"> · {{ Math.round(d.share * 100) }}% de este file</span>
                    </div>
                  </td>
                  <td class="whitespace-nowrap py-1.5 text-right tabular-nums">
                    {{ d.moneda }} {{ imp(d.total) }}
                    <div class="text-xs text-gray-500">a {{ tasa(d.tasa) }} <sup v-if="origenes[d.tasa_origen]">{{ origenes[d.tasa_origen] }}</sup> = {{ imp(d.base) }}</div>
                  </td>
                </tr>
                <tr v-if="!docsVenta.length"><td class="py-2 text-gray-400">Sin comprobantes.</td></tr>
              </tbody>
            </table>
          </div>
          <div>
            <div class="mb-1 text-xs font-semibold uppercase text-gray-500">Recibos</div>
            <table class="w-full">
              <tbody>
                <tr v-for="r in m.venta.recibos" :key="`r${r.id}`" class="border-b border-gray-100 align-top" :class="r.anulado ? 'text-gray-400 line-through' : ''">
                  <td class="py-1.5 pr-2">
                    <a :href="r.link" :target="destino" class="text-blue-700 hover:underline">Recibo {{ r.numero }}</a>
                    <div class="text-xs text-gray-500">{{ formatearFecha(r.fecha) }} · {{ r.status }}<span v-if="r.otros_files.length"> · compartido con otros files</span></div>
                  </td>
                  <td class="whitespace-nowrap py-1.5 text-right tabular-nums">
                    {{ r.moneda }} {{ imp(r.monto) }}
                    <div class="text-xs text-gray-500">a {{ tasa(r.tasa) }} <sup v-if="origenes[r.tasa_origen]">{{ origenes[r.tasa_origen] }}</sup> = {{ imp(r.base) }}</div>
                  </td>
                </tr>
                <tr v-for="a in m.venta.asientos" :key="`a${a.movimiento_id}`" class="border-b border-gray-100">
                  <td class="py-1.5 pr-2">
                    <a :href="a.link" :target="destino" class="text-blue-700 hover:underline">Asiento en cta. cte. {{ a.numero }}</a>
                    <div class="text-xs text-gray-500">{{ formatearFecha(a.fecha) }} · {{ a.descripcion }}</div>
                  </td>
                  <td class="whitespace-nowrap py-1.5 text-right tabular-nums">{{ a.signo < 0 ? '−' : '' }}{{ a.moneda }} {{ imp(a.monto) }}</td>
                </tr>
                <tr v-if="!m.venta.recibos.length && !m.venta.asientos.length"><td class="py-2 text-gray-400">Sin cobros.</td></tr>
              </tbody>
            </table>
          </div>
          <div class="rounded-md bg-gray-50 p-3 text-xs text-gray-600">
            <template v-if="m.economia.cobranza.moneda">
              Facturado {{ m.economia.cobranza.moneda }} {{ imp(m.economia.cobranza.facturado) }} a {{ tasa(m.economia.cobranza.tasa_factura) }} · cobrado
              {{ m.economia.cobranza.moneda }} {{ imp(m.economia.cobranza.cobrado) }} a {{ tasa(m.economia.cobranza.tasa_cobro) }} · diferencia de cambio
              <span :class="signo(m.economia.cobranza.diferencia)">{{ imp(m.economia.cobranza.diferencia) }}</span>.
              <span v-if="m.economia.cobranza.motivo"> {{ motivosCobranza[m.economia.cobranza.motivo] }}</span>
            </template>
            <template v-else>{{ motivosCobranza[m.economia.cobranza.motivo] }}</template>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h3 class="card-title">Circuito de costo</h3></div>
        <div class="card-body space-y-5 text-sm">
          <div>
            <div class="mb-1 text-xs font-semibold uppercase text-gray-500">Facturas de proveedor</div>
            <table class="w-full">
              <tbody>
                <tr v-for="f in m.costo.facturas_proveedor" :key="`fp${f.id}`" class="border-b border-gray-100 align-top">
                  <td class="py-1.5 pr-2">
                    <a :href="f.link" :target="destino" class="text-blue-700 hover:underline">{{ f.numero }}</a>
                    <div class="text-xs text-gray-500">{{ f.proveedor }} · {{ formatearFecha(f.fecha) }}<span v-if="f.servicios_ajenos"> · también imputa {{ f.servicios_ajenos }} servicio(s) de otros files</span></div>
                  </td>
                  <td class="whitespace-nowrap py-1.5 text-right tabular-nums">
                    {{ f.moneda }} {{ imp(f.items.reduce((a, x) => a + x.monto, 0)) }}
                    <div class="text-xs text-gray-500">de {{ imp(f.total) }} · a {{ tasa(f.tasa) }} <sup v-if="origenes[f.tasa_origen]">{{ origenes[f.tasa_origen] }}</sup></div>
                  </td>
                </tr>
                <tr v-if="!m.costo.facturas_proveedor.length"><td class="py-2 text-gray-400">Sin facturas de proveedor.</td></tr>
              </tbody>
            </table>
          </div>
          <div>
            <div class="mb-1 text-xs font-semibold uppercase text-gray-500">Órdenes de servicio y de pago</div>
            <table class="w-full">
              <tbody>
                <tr v-for="o in m.costo.ordenes" :key="`o${o.id}`" class="border-b border-gray-100 align-top" :class="o.status === 'AN' ? 'text-gray-400 line-through' : ''">
                  <td class="py-1.5 pr-2">
                    <a :href="o.link" :target="destino" class="text-blue-700 hover:underline">{{ o.tipo === 'S' ? 'OS' : 'OP' }} {{ o.numero }}</a>
                    <div class="text-xs text-gray-500">
                      {{ o.proveedor }} · {{ formatearFecha(o.fecha) }} · {{ o.status }}
                      <span v-if="o.servicios_ajenos"> · paga {{ o.servicios_ajenos }} servicio(s) de otros files</span>
                    </div>
                  </td>
                  <td class="whitespace-nowrap py-1.5 text-right tabular-nums">
                    {{ o.moneda }} {{ imp(o.items.filter((x) => !x.comision).reduce((a, x) => a + x.monto, 0)) }}
                    <div class="text-xs text-gray-500">
                      a {{ tasa(o.cotizacion) }}<span v-if="o.tipo === 'P' && o.cotizacion_os"> (OS {{ tasa(o.cotizacion_os) }})</span>
                    </div>
                  </td>
                </tr>
                <tr v-for="a in m.costo.asientos" :key="`ap${a.movimiento_id}`" class="border-b border-gray-100">
                  <td class="py-1.5 pr-2">
                    <a :href="a.link" :target="destino" class="text-blue-700 hover:underline">Asiento en cta. cte. {{ a.numero }}</a>
                    <div class="text-xs text-gray-500">{{ formatearFecha(a.fecha) }} · {{ a.descripcion }}</div>
                  </td>
                  <td class="whitespace-nowrap py-1.5 text-right tabular-nums">{{ a.moneda }} {{ imp(a.monto) }}</td>
                </tr>
                <tr v-if="!m.costo.ordenes.length && !m.costo.asientos.length"><td class="py-2 text-gray-400">Sin órdenes.</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- Contabilidad -->
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">Asientos por comprobante</h3>
        <span class="text-xs text-gray-500">Debe y haber en {{ basica }} · sólo movimientos válidos (sin órdenes ni recibos anulados)</span>
      </div>
      <div class="divide-y divide-gray-100">
        <div v-for="g in m.contable.grupos" :key="g.clave">
          <button type="button" class="flex w-full items-center justify-between gap-3 px-4 py-2 text-left text-sm hover:bg-gray-50" @click="alternarGrupo(g.clave)">
            <span class="flex items-center gap-2">
              <span class="text-gray-400">{{ gruposAbiertos.has(g.clave) ? '▾' : '▸' }}</span>
              <span class="font-medium text-gray-900">{{ g.etiqueta }}</span>
              <span v-if="g.compartido" class="badge badge-gray">compartido</span>
              <span class="badge" :class="Math.abs(g.diferencia) > m.tolerancia ? 'badge-danger' : 'badge-success'">{{ Math.abs(g.diferencia) > m.tolerancia ? 'no balancea' : 'balancea' }}</span>
            </span>
            <span class="whitespace-nowrap tabular-nums text-gray-600">D {{ imp(g.debe) }} · H {{ imp(g.haber) }}</span>
          </button>
          <div v-if="gruposAbiertos.has(g.clave)" class="overflow-x-auto bg-gray-50 px-4 pb-3">
            <a v-if="g.link" :href="g.link" :target="destino" class="mb-2 inline-block text-xs text-blue-700 hover:underline">Ver comprobante</a>
            <table class="min-w-full text-xs">
              <thead class="text-gray-500">
                <tr>
                  <th class="py-1 text-left">Fecha</th>
                  <th class="py-1 text-left">Cuenta</th>
                  <th class="py-1 text-center">D/H</th>
                  <th class="py-1 text-right">Importe</th>
                  <th class="py-1 text-right">Cotización</th>
                  <th class="py-1 text-right">{{ basica }}</th>
                  <th class="py-1 text-left pl-3">File · descripción</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="x in g.movimientos" :key="x.id" class="border-t border-gray-200" :class="x.valido ? '' : 'text-gray-400 line-through'">
                  <td class="py-1">{{ formatearFecha(x.fecha) }}</td>
                  <td class="py-1">{{ x.cuenta_codigo }} {{ x.cuenta_nombre || `#${x.cuenta}` }}</td>
                  <td class="py-1 text-center">{{ x.dh }}</td>
                  <td class="whitespace-nowrap py-1 text-right tabular-nums">{{ x.moneda }} {{ imp(x.monto) }}</td>
                  <td class="py-1 text-right tabular-nums">{{ tasa(x.cotizacion) }}</td>
                  <td class="py-1 text-right tabular-nums">{{ imp(x.base) }}</td>
                  <td class="py-1 pl-3">{{ x.file_id || '—' }} · {{ x.descripcion }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
        <p v-if="!m.contable.grupos.length" class="px-4 py-6 text-center text-sm text-gray-500">El file no tiene asientos.</p>
      </div>
    </div>
  </div>
</template>
