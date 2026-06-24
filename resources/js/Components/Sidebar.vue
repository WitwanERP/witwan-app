<script setup>
import { ref, computed, watch } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'

defineProps({
  open: Boolean,
  mobileOpen: Boolean,
})
defineEmits(['closeMobile'])

const page = usePage()
const user = computed(() => page.props.auth?.user ?? {})
const userName = computed(() => user.value.usuario_nombre ?? 'Usuario')
const userEmail = computed(() => user.value.usuario_mail ?? '')

// Botonera que arma el backend (MenuService) desde brain + permisos del rol.
const menu = computed(() => page.props.menu ?? [])

// Acordeón de 2 niveles: un sistema abierto y, dentro, un grupo abierto.
const STORAGE = 'witwan.sidebar.sistema'
const openSistema = ref(null)
const openGrupo = ref(null)

watch(
  menu,
  () => {
    const ids = menu.value.map((s) => s.sistema_id)
    const saved = Number(localStorage.getItem(STORAGE))
    openSistema.value = ids.includes(saved) ? saved : (ids[0] ?? null)
  },
  { immediate: true },
)

const toggleSistema = (id) => {
  openSistema.value = openSistema.value === id ? null : id
  openGrupo.value = null
  if (openSistema.value != null) localStorage.setItem(STORAGE, String(openSistema.value))
}
const toggleGrupo = (key) => {
  openGrupo.value = openGrupo.value === key ? null : key
}

const dashboardActiva = computed(() => page.url === '/app')
</script>

<template>
  <!-- Sidebar desktop -->
  <aside
    class="fixed inset-y-0 left-0 z-30 bg-gray-900 text-white transition-all duration-300 hidden lg:flex lg:flex-col"
    :class="open ? 'w-64' : 'w-20'"
  >
    <div class="h-16 flex items-center justify-center border-b border-gray-800 shrink-0">
      <span class="text-xl font-bold">{{ open ? 'WitWan' : 'W' }}</span>
    </div>

    <!-- Dashboard -->
    <div class="px-2 pt-3 shrink-0">
      <Link
        href="/app"
        class="flex items-center px-4 py-2.5 rounded-lg transition-colors"
        :class="dashboardActiva ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white'"
      >
        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
        </svg>
        <span v-if="open" class="ml-3 text-sm font-medium">Dashboard</span>
      </Link>
    </div>

    <!-- Acordeón sistema → grupo → sección (solo expandido) -->
    <nav v-if="open" class="flex-1 overflow-y-auto px-2 py-3 space-y-1">
      <div v-for="sistema in menu" :key="sistema.sistema_id">
        <button
          class="w-full flex items-center justify-between px-3 py-2 rounded-lg text-sm font-semibold text-gray-200 hover:bg-gray-800 transition-colors"
          @click="toggleSistema(sistema.sistema_id)"
        >
          <span>{{ sistema.sistema }}</span>
          <svg
            class="w-4 h-4 transition-transform" :class="openSistema === sistema.sistema_id ? 'rotate-90' : ''"
            fill="none" stroke="currentColor" viewBox="0 0 24 24"
          >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
          </svg>
        </button>

        <!-- Grupos del sistema abierto -->
        <div v-if="openSistema === sistema.sistema_id" class="mt-1 ml-2 border-l border-gray-800 pl-2 space-y-0.5">
          <div v-for="grupo in sistema.grupos" :key="grupo.grupo">
            <button
              class="w-full flex items-center justify-between px-3 py-1.5 rounded-md text-sm text-gray-300 hover:bg-gray-800 hover:text-white transition-colors"
              @click="toggleGrupo(sistema.sistema_id + ':' + grupo.grupo)"
            >
              <span>{{ grupo.grupo }}</span>
              <svg
                class="w-3.5 h-3.5 transition-transform"
                :class="openGrupo === sistema.sistema_id + ':' + grupo.grupo ? 'rotate-90' : ''"
                fill="none" stroke="currentColor" viewBox="0 0 24 24"
              >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
              </svg>
            </button>

            <!-- Secciones del grupo abierto -->
            <div v-if="openGrupo === sistema.sistema_id + ':' + grupo.grupo" class="ml-3 border-l border-gray-800 pl-2 py-0.5 space-y-0.5">
              <a
                v-for="item in grupo.items"
                :key="item.seccion_id"
                :href="item.url"
                class="block px-3 py-1.5 rounded-md text-sm text-gray-400 hover:bg-gray-800 hover:text-white transition-colors"
              >
                {{ item.label }}
              </a>
            </div>
          </div>
        </div>
      </div>
    </nav>
    <div v-else class="flex-1"></div>

    <!-- Usuario -->
    <div class="p-4 border-t border-gray-800 shrink-0">
      <div class="flex items-center" :class="{ 'justify-center': !open }">
        <div class="w-8 h-8 rounded-full bg-blue-500 flex items-center justify-center text-sm font-medium">
          {{ userName.charAt(0) }}
        </div>
        <div v-if="open" class="ml-3 overflow-hidden">
          <p class="text-sm font-medium truncate">{{ userName }}</p>
          <p class="text-xs text-gray-400 truncate">{{ userEmail }}</p>
        </div>
      </div>
    </div>
  </aside>

  <!-- Sidebar mobile -->
  <aside
    class="fixed inset-y-0 left-0 z-30 w-64 bg-gray-900 text-white transform transition-transform duration-300 lg:hidden flex flex-col"
    :class="mobileOpen ? 'translate-x-0' : '-translate-x-full'"
  >
    <div class="h-16 flex items-center justify-between px-4 border-b border-gray-800 shrink-0">
      <span class="text-xl font-bold">WitWan</span>
      <button class="p-2 rounded-lg hover:bg-gray-800" @click="$emit('closeMobile')">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </button>
    </div>

    <div class="px-2 pt-3 shrink-0">
      <Link
        href="/app"
        class="flex items-center px-4 py-2.5 rounded-lg transition-colors"
        :class="dashboardActiva ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-gray-800'"
        @click="$emit('closeMobile')"
      >
        <span class="text-sm font-medium">Dashboard</span>
      </Link>
    </div>

    <nav class="flex-1 overflow-y-auto px-2 py-3 space-y-1">
      <div v-for="sistema in menu" :key="sistema.sistema_id">
        <button
          class="w-full flex items-center justify-between px-3 py-2 rounded-lg text-sm font-semibold text-gray-200 hover:bg-gray-800 transition-colors"
          @click="toggleSistema(sistema.sistema_id)"
        >
          <span>{{ sistema.sistema }}</span>
          <svg
            class="w-4 h-4 transition-transform" :class="openSistema === sistema.sistema_id ? 'rotate-90' : ''"
            fill="none" stroke="currentColor" viewBox="0 0 24 24"
          >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
          </svg>
        </button>

        <div v-if="openSistema === sistema.sistema_id" class="mt-1 ml-2 border-l border-gray-800 pl-2 space-y-0.5">
          <div v-for="grupo in sistema.grupos" :key="grupo.grupo">
            <button
              class="w-full flex items-center justify-between px-3 py-1.5 rounded-md text-sm text-gray-300 hover:bg-gray-800 hover:text-white transition-colors"
              @click="toggleGrupo(sistema.sistema_id + ':' + grupo.grupo)"
            >
              <span>{{ grupo.grupo }}</span>
              <svg
                class="w-3.5 h-3.5 transition-transform"
                :class="openGrupo === sistema.sistema_id + ':' + grupo.grupo ? 'rotate-90' : ''"
                fill="none" stroke="currentColor" viewBox="0 0 24 24"
              >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
              </svg>
            </button>

            <div v-if="openGrupo === sistema.sistema_id + ':' + grupo.grupo" class="ml-3 border-l border-gray-800 pl-2 py-0.5 space-y-0.5">
              <a
                v-for="item in grupo.items"
                :key="item.seccion_id"
                :href="item.url"
                class="block px-3 py-1.5 rounded-md text-sm text-gray-400 hover:bg-gray-800 hover:text-white transition-colors"
                @click="$emit('closeMobile')"
              >
                {{ item.label }}
              </a>
            </div>
          </div>
        </div>
      </div>
    </nav>
  </aside>
</template>
