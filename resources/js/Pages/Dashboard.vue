<script setup>
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

// Layout persistente de Inertia.
defineOptions({ layout: AppLayout })

const props = defineProps({
  stats: {
    type: Object,
    default: () => ({ reservasHoy: 0, reservasPendientes: 0, facturacionMes: 0, clientesActivos: 0 }),
  },
})

const page = usePage()
const user = computed(() => page.props.auth?.user ?? {})
const userName = computed(() => user.value.usuario_nombre ?? 'Usuario')
const fullName = computed(() =>
  [user.value.usuario_nombre, user.value.usuario_apellido].filter(Boolean).join(' ') || 'Usuario'
)
const initials = computed(() =>
  fullName.value.split(' ').filter(Boolean).map((s) => s.charAt(0)).slice(0, 2).join('').toUpperCase() || 'U'
)
const pais = computed(() => page.props.tenant?.pais ?? 'AR')

function formatCurrency(value) {
  const p = pais.value
  const locale = p === 'CL' ? 'es-CL' : p === 'DO' ? 'es-DO' : 'es-AR'
  const currency = p === 'CL' ? 'CLP' : p === 'DO' ? 'DOP' : 'ARS'
  return new Intl.NumberFormat(locale, {
    style: 'currency',
    currency,
    minimumFractionDigits: p === 'CL' ? 0 : 2,
  }).format(value)
}
</script>

<template>
  <div>
    <!-- Encabezado -->
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-gray-900">Dashboard</h1>
      <p class="text-gray-500">Bienvenido, {{ userName }}</p>
    </div>

    <!-- Información del usuario logueado -->
    <div class="card mb-6">
      <div class="card-body">
        <div class="flex items-center gap-4">
          <div class="w-16 h-16 rounded-full bg-blue-500 text-white flex items-center justify-center text-xl font-semibold shrink-0">
            {{ initials }}
          </div>
          <div class="flex-1 min-w-0">
            <div class="flex flex-wrap items-center gap-2">
              <h2 class="text-lg font-bold text-gray-900">{{ fullName }}</h2>
              <span
                v-if="user.rol"
                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800"
              >
                {{ user.rol }}
              </span>
              <span
                v-if="user.interno"
                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800"
              >
                Interno
              </span>
            </div>
            <dl class="mt-3 grid grid-cols-1 sm:grid-cols-3 gap-x-6 gap-y-2 text-sm">
              <div>
                <dt class="text-gray-500">Email</dt>
                <dd class="font-medium text-gray-900 truncate">{{ user.usuario_mail || '—' }}</dd>
              </div>
              <div>
                <dt class="text-gray-500">Usuario</dt>
                <dd class="font-medium text-gray-900 truncate">{{ user.usuario_login || '—' }}</dd>
              </div>
              <div>
                <dt class="text-gray-500">ID</dt>
                <dd class="font-medium text-gray-900">#{{ user.usuario_id ?? '—' }}</dd>
              </div>
            </dl>
          </div>
        </div>
      </div>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
      <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="flex items-center">
          <div class="p-3 rounded-full bg-blue-100 text-blue-600">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
          </div>
          <div class="ml-4">
            <p class="text-sm font-medium text-gray-500">Reservas Hoy</p>
            <p class="text-2xl font-bold text-gray-900">{{ stats.reservasHoy }}</p>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="flex items-center">
          <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <div class="ml-4">
            <p class="text-sm font-medium text-gray-500">Pendientes</p>
            <p class="text-2xl font-bold text-gray-900">{{ stats.reservasPendientes }}</p>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="flex items-center">
          <div class="p-3 rounded-full bg-green-100 text-green-600">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <div class="ml-4">
            <p class="text-sm font-medium text-gray-500">Facturación Mes</p>
            <p class="text-2xl font-bold text-gray-900">{{ formatCurrency(stats.facturacionMes) }}</p>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="flex items-center">
          <div class="p-3 rounded-full bg-purple-100 text-purple-600">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
          </div>
          <div class="ml-4">
            <p class="text-sm font-medium text-gray-500">Clientes Activos</p>
            <p class="text-2xl font-bold text-gray-900">{{ stats.clientesActivos }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Acciones rápidas -->
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">
          <svg class="w-5 h-5 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
          </svg>
          Acciones Rápidas
        </h3>
      </div>
      <div class="card-body">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
          <Link href="/app/reservas" class="flex flex-col items-center p-4 rounded-lg border border-gray-200 hover:border-blue-500 hover:bg-blue-50 transition-colors">
            <svg class="w-8 h-8 text-blue-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
            </svg>
            <span class="text-sm font-medium text-gray-700">Nueva Reserva</span>
          </Link>

          <Link href="/app/clientes/create" class="flex flex-col items-center p-4 rounded-lg border border-gray-200 hover:border-green-500 hover:bg-green-50 transition-colors">
            <svg class="w-8 h-8 text-green-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
            </svg>
            <span class="text-sm font-medium text-gray-700">Nuevo Cliente</span>
          </Link>

          <a href="#" class="flex flex-col items-center p-4 rounded-lg border border-gray-200 hover:border-yellow-500 hover:bg-yellow-50 transition-colors">
            <svg class="w-8 h-8 text-yellow-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <span class="text-sm font-medium text-gray-700">Nueva Factura</span>
          </a>

          <a href="#" class="flex flex-col items-center p-4 rounded-lg border border-gray-200 hover:border-purple-500 hover:bg-purple-50 transition-colors">
            <svg class="w-8 h-8 text-purple-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <span class="text-sm font-medium text-gray-700">Reportes</span>
          </a>
        </div>
      </div>
    </div>
  </div>
</template>
