<script setup>
import { computed } from 'vue'
import { formatearFecha, formatearImporte } from '@/lib/formato'
import { inputCls, useGenerador } from './useGenerador'

/**
 * Paso 4: la validación del servidor corre al entrar (misma lógica que el POST),
 * así los errores, avisos y el crédito se ven ANTES del único botón de crear.
 */
const g = useGenerador()
const { props, estado, totales, erroresTodos } = g
const c = estado.contexto
const listaErrores = computed(() => Object.values(erroresTodos.value))
const faltaTitular = computed(() => !c.titular_apellido.trim() || !c.titular_nombre.trim())
const agenteLabel = computed(() => {
  const lista = props.opciones.escritorio.length ? props.opciones.escritorio : props.opciones.vendedores
  return lista.find((u) => Number(u.value) === Number(c.agente))?.label || '(yo / vendedor del cliente)'
})
</script>

<template>
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    <div class="lg:col-span-2 space-y-4">
      <div v-if="estado.previa.corriendo" class="rounded-md border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-600">Validando con el servidor…</div>
      <div v-else-if="listaErrores.length" class="rounded-md border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800">
        <b>No se puede crear todavía:</b>
        <ul class="list-disc ml-5 mt-1"><li v-for="(e, k) in listaErrores" :key="k">{{ e }}</li></ul>
        <label v-if="erroresTodos.credito && props.puedeForzarCredito" class="flex items-center gap-2 mt-2 font-medium">
          <input type="checkbox" :true-value="1" :false-value="0" v-model="c.forzar_credito" @change="g.validarPrevia()" /> Crear igual excediendo el límite de crédito (queda auditado)
        </label>
      </div>
      <div v-else-if="estado.previa.hecha" class="rounded-md border border-green-300 bg-green-50 px-4 py-3 text-sm text-green-800">Validación correcta: se puede crear la reserva.</div>
      <div v-if="estado.previa.advertencias.length" class="rounded-md border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
        <b>Avisos:</b>
        <ul class="list-disc ml-5 mt-1"><li v-for="(a, k) in estado.previa.advertencias" :key="k">{{ a }}</li></ul>
      </div>

      <section class="card">
        <div class="card-header"><h2 class="card-title">Titular y observaciones</h2></div>
        <div class="card-body grid grid-cols-1 md:grid-cols-4 gap-4">
          <div><label class="block text-sm mb-1 font-bold">Apellido</label><input v-model="c.titular_apellido" type="text" maxlength="150" :class="inputCls" @blur="g.validarPrevia()" /></div>
          <div><label class="block text-sm mb-1 font-bold">Nombre</label><input v-model="c.titular_nombre" type="text" maxlength="150" :class="inputCls" @blur="g.validarPrevia()" /></div>
          <div><label class="block text-sm mb-1">Email</label><input v-model="c.titular_email" type="email" maxlength="50" :class="inputCls" /></div>
          <div><label class="block text-sm mb-1">Celular</label><input v-model="c.titular_celular" type="text" maxlength="50" :class="inputCls" /></div>
          <div class="md:col-span-4"><label class="block text-sm mb-1">Observaciones del file</label><textarea v-model="c.observaciones" rows="2" :class="inputCls"></textarea></div>
        </div>
      </section>

      <section class="card">
        <div class="card-header"><h2 class="card-title">Servicios ({{ estado.carrito.length }})</h2></div>
        <div class="card-body overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead><tr class="text-left text-xs text-gray-500"><th class="pb-1 pr-3">#</th><th class="pb-1 pr-3">Servicio</th><th class="pb-1 pr-3">Fechas</th><th class="pb-1 pr-3 text-right">Pax</th><th class="pb-1 pr-3">Status</th><th class="pb-1 pr-3 text-right">Costo</th><th class="pb-1 pr-3 text-right">Venta</th><th class="pb-1 text-right">Margen</th></tr></thead>
            <tbody>
              <tr v-for="(l, i) in estado.carrito" :key="l._uid" class="border-t border-gray-100">
                <td class="py-1 pr-3 text-gray-400">{{ i + 1 }}</td>
                <td class="py-1 pr-3"><div class="font-medium text-gray-800">{{ l.servicio_nombre }}</div><div class="text-xs text-gray-500">{{ l.fk_tipoproducto_id }}<span v-if="l.ciudad_label"> · {{ l.ciudad_label }}</span><span v-if="l._detalle?.nombre"> · {{ l._detalle.nombre }}</span> · {{ l.pasajeros_ids.length }} en nómina</div></td>
                <td class="py-1 pr-3 whitespace-nowrap">{{ formatearFecha(l.vigencia_ini) }}<span v-if="l.vigencia_fin && l.vigencia_fin !== l.vigencia_ini"> → {{ formatearFecha(l.vigencia_fin) }}</span></td>
                <td class="py-1 pr-3 text-right tabular-nums">{{ g.paxLinea(l) }}</td>
                <td class="py-1 pr-3"><span class="badge" :class="l.status === 'CO' ? 'badge-success' : 'badge-warning'">{{ l.status }}</span></td>
                <td class="py-1 pr-3 text-right tabular-nums">{{ formatearImporte((Number(l.costo) || 0) + (Number(l.iva_costo) || 0) + (Number(l.impuestos) || 0)) }} <span class="text-xs text-gray-500">{{ l.moneda_costo }}</span></td>
                <td class="py-1 pr-3 text-right tabular-nums font-semibold">{{ formatearImporte(l.total) }} <span class="text-xs text-gray-500">{{ l.fk_moneda_id }}</span></td>
                <td class="py-1 text-right tabular-nums" :class="g.margenLinea(l).valor < 0 ? 'text-red-600' : 'text-green-700'">{{ formatearImporte(g.margenLinea(l).valor) }}</td>
              </tr>
            </tbody>
            <tfoot>
              <tr class="border-t-2 border-gray-200 font-semibold">
                <td colspan="6" class="py-2 pr-3 text-right">Total del file en {{ totales.file.moneda }}</td>
                <td class="py-2 pr-3 text-right tabular-nums">{{ formatearImporte(totales.file.venta) }}</td>
                <td class="py-2 text-right tabular-nums" :class="totales.file.margen < 0 ? 'text-red-600' : 'text-green-700'">{{ formatearImporte(totales.file.margen) }}</td>
              </tr>
            </tfoot>
          </table>
          <p class="text-xs text-gray-400 mt-2">Los totales definitivos (cotizaciones, renta) los calcula el servidor al crear.</p>
        </div>
      </section>
    </div>

    <aside class="space-y-4">
      <section class="card">
        <div class="card-header"><h2 class="card-title">Resumen del file</h2></div>
        <div class="card-body text-sm space-y-2">
          <div><div class="text-xs text-gray-500">Cliente</div><div class="font-medium">{{ c.cliente_label }}</div></div>
          <div><div class="text-xs text-gray-500">Pasajeros</div><div>{{ c.residente === 'R' ? 'Residentes' : 'Extranjeros' }}</div></div>
          <div><div class="text-xs text-gray-500">Tarifario</div><div>{{ estado.clienteInfo?.tarifario?.nombre || 'Sin tarifario' }}</div></div>
          <div><div class="text-xs text-gray-500">{{ props.opciones.escritorio.length ? 'Escritorio' : 'Vendedor' }}</div><div>{{ agenteLabel }}</div></div>
          <div><div class="text-xs text-gray-500">Moneda del file · status inicial</div><div>{{ c.fk_moneda_id }} · {{ props.statusFile }}</div></div>
          <div><div class="text-xs text-gray-500">Vencimiento</div><div>{{ c.fecha_vencimiento ? formatearFecha(c.fecha_vencimiento) : 'hoy' }}</div></div>
          <div v-if="estado.clienteInfo?.credito?.habilitado" class="pt-2 border-t border-gray-100">
            <div class="text-xs text-gray-500">Crédito disponible</div>
            <div class="tabular-nums" :class="estado.clienteInfo.credito.disponible < 0 ? 'text-red-700' : ''">{{ formatearImporte(estado.clienteInfo.credito.disponible) }} {{ estado.clienteInfo.credito.moneda }}</div>
          </div>
        </div>
        <div class="card-footer space-y-2">
          <button type="button" class="btn btn-primary w-full" :disabled="g.enviando.value || estado.previa.corriendo || listaErrores.length > 0 || faltaTitular || !estado.carrito.length" @click="g.crear()">
            {{ g.enviando.value ? 'Creando…' : 'Crear reserva' }}
          </button>
          <p v-if="faltaTitular" class="text-xs text-amber-700 text-center">Falta el titular.</p>
          <button type="button" class="btn btn-secondary w-full" :disabled="estado.previa.corriendo" @click="g.validarPrevia()">Volver a validar</button>
        </div>
      </section>
      <button type="button" class="btn btn-secondary w-full" @click="g.irA('carrito')">← Volver al carrito</button>
    </aside>
  </div>
</template>
