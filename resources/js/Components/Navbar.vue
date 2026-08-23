<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { usePage } from '@inertiajs/vue3'
import {
  BellIcon,
  ChevronDownIcon,
  CurrencyDollarIcon,
  LifebuoyIcon,
  ListBulletIcon,
  ArrowRightStartOnRectangleIcon,
  UserIcon,
} from '@heroicons/vue/24/outline'

defineProps({ sidebarOpen: Boolean })
const emit = defineEmits(['toggleSidebar', 'toggleMobileMenu', 'toggleQuickSidebar', 'logout'])

const page = usePage()

// El header del CI muestra "Apellido Nombre".
const userName = computed(() => {
  const u = page.props.auth?.user ?? {}
  return [u.usuario_apellido, u.usuario_nombre].filter(Boolean).join(' ') || 'Usuario'
})

// Logo del tenant: lo sirve el CI desde /upfiles/{licencia}/logos/logo.png.
const logo = computed(() => page.props.tenant?.logo ?? null)
const logoRoto = ref(false)

// Cotizaciones del día (mismo bloque que el dropdown fa-usd del CI).
const cotizaciones = computed(() => page.props.cotizaciones ?? null)

// Un solo dropdown abierto por vez ('user' | 'moneda'), como el data-close-others del CI.
const abierto = ref(null)
const toggle = (menu) => (abierto.value = abierto.value === menu ? null : menu)

const raiz = ref(null)
const cerrarSiEsAfuera = (e) => {
  if (raiz.value && !raiz.value.contains(e.target)) abierto.value = null
}
onMounted(() => document.addEventListener('click', cerrarSiEsAfuera))
onBeforeUnmount(() => document.removeEventListener('click', cerrarSiEsAfuera))

function salir() {
  abierto.value = null
  if (window.confirm('¿Está seguro?')) emit('logout')
}
</script>

<template>
  <header
    ref="raiz"
    class="page-header fixed inset-x-0 top-0 z-40 flex h-[42px] bg-mt-dark text-sm text-white"
  >
    <!-- Logo + toggler del sidebar (page-logo) -->
    <div
      class="hidden h-full shrink-0 items-center justify-between gap-2 pl-4 pr-3 transition-all duration-200 lg:flex"
      :class="sidebarOpen ? 'w-[235px]' : 'w-[45px] px-0 justify-center'"
    >
      <a v-if="sidebarOpen" href="/app" class="flex min-w-0 items-center">
        <img
          v-if="logo && !logoRoto"
          :src="logo"
          alt="logo"
          class="max-h-[26px] max-w-[150px] object-contain"
          @error="logoRoto = true"
        />
        <span v-else class="truncate text-base font-bold tracking-wide">WITWAN</span>
      </a>

      <!-- menu-toggler: las 3 rayitas del Metronic -->
      <button
        type="button"
        class="flex h-[42px] w-[30px] shrink-0 flex-col items-center justify-center gap-[3px] text-mt-text hover:text-white"
        :title="sidebarOpen ? 'Contraer menú' : 'Expandir menú'"
        @click="emit('toggleSidebar')"
      >
        <span class="block h-[2px] w-[16px] bg-current"></span>
        <span class="block h-[2px] w-[16px] bg-current"></span>
        <span class="block h-[2px] w-[16px] bg-current"></span>
      </button>
    </div>

    <!-- Toggler responsive (el menú baja debajo del header, como en el CI) -->
    <div class="flex items-center gap-3 pl-4 lg:hidden">
      <a href="/app" class="flex items-center">
        <img
          v-if="logo && !logoRoto"
          :src="logo"
          alt="logo"
          class="max-h-[24px] max-w-[130px] object-contain"
          @error="logoRoto = true"
        />
        <span v-else class="text-base font-bold tracking-wide">WITWAN</span>
      </a>
      <button
        type="button"
        class="flex h-[42px] w-[30px] flex-col items-center justify-center gap-[3px] text-mt-text hover:text-white"
        title="Menú"
        @click="emit('toggleMobileMenu')"
      >
        <span class="block h-[2px] w-[16px] bg-current"></span>
        <span class="block h-[2px] w-[16px] bg-current"></span>
        <span class="block h-[2px] w-[16px] bg-current"></span>
      </button>
    </div>

    <!-- Menú superior derecho (top-menu) -->
    <nav class="ml-auto flex items-stretch">
      <!-- Usuario -->
      <div class="relative flex items-stretch">
        <button
          type="button"
          class="flex items-center gap-2 px-3 transition-colors hover:bg-mt-hover"
          :class="{ 'bg-mt-hover': abierto === 'user' }"
          @click="toggle('user')"
        >
          <UserIcon class="h-4 w-4" />
          <span class="hidden max-w-[180px] truncate md:inline">{{ userName }}</span>
          <ChevronDownIcon class="h-3.5 w-3.5" />
        </button>

        <ul
          v-if="abierto === 'user'"
          class="absolute right-0 top-[42px] z-50 w-56 border border-mt-border bg-mt-dark py-1 shadow-lg"
        >
          <li>
            <a
              href="/users/perfil"
              class="flex items-center gap-2 px-4 py-2 text-mt-text hover:bg-mt-hover hover:text-white"
            >
              <ListBulletIcon class="h-4 w-4" /> Mi perfil
            </a>
          </li>
          <li>
            <button
              type="button"
              class="flex w-full items-center gap-2 px-4 py-2 text-left text-mt-text hover:bg-mt-hover hover:text-white"
              @click="salir"
            >
              <ArrowRightStartOnRectangleIcon class="h-4 w-4" /> Salir del sistema
            </button>
          </li>
        </ul>
      </div>

      <!-- Cotizaciones del día -->
      <div v-if="cotizaciones?.monedas?.length" class="relative flex items-stretch">
        <button
          type="button"
          class="flex items-center px-3 transition-colors hover:bg-mt-hover"
          :class="{ 'bg-mt-hover': abierto === 'moneda' }"
          title="Cotizaciones"
          @click="toggle('moneda')"
        >
          <CurrencyDollarIcon class="h-4.5 w-4.5" />
        </button>

        <ul
          v-if="abierto === 'moneda'"
          class="absolute right-0 top-[42px] z-50 w-52 border border-mt-border bg-mt-dark py-1 shadow-lg"
        >
          <li class="px-4 py-2 text-white">{{ cotizaciones.fecha }}</li>
          <li
            v-for="m in cotizaciones.monedas"
            :key="m.moneda"
            class="flex justify-between gap-3 px-4 py-2 text-mt-text"
          >
            <span>{{ m.moneda }}:</span>
            <span class="tabular-nums text-white">{{ m.valor }}</span>
          </li>
        </ul>
      </div>

      <!-- Alertas / vencimientos: el contador todavía lo calcula el CI. -->
      <a
        href="/users/alertas"
        class="flex items-center px-3 transition-colors hover:bg-mt-hover"
        title="Vencimientos / Alertas"
      >
        <BellIcon class="h-4.5 w-4.5" />
      </a>

      <!-- Ayuda (abre el quick sidebar) -->
      <button
        type="button"
        class="flex items-center px-3 transition-colors hover:bg-mt-hover"
        title="Ayuda"
        @click="emit('toggleQuickSidebar')"
      >
        <LifebuoyIcon class="h-5 w-5" />
      </button>
    </nav>
  </header>
</template>
