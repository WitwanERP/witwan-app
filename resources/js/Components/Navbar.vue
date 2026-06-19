<script setup>
import { ref, computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

defineProps({ sidebarOpen: Boolean })
const emit = defineEmits(['toggleSidebar', 'toggleMobileSidebar', 'logout'])

const page = usePage()
const userName = computed(() => page.props.auth?.user?.usuario_nombre ?? 'Usuario')
const userEmail = computed(() => page.props.auth?.user?.usuario_mail ?? '')
const pais = computed(() => page.props.tenant?.pais ?? '')

const userMenuOpen = ref(false)
</script>

<template>
  <header class="h-16 bg-white shadow-sm flex items-center justify-between px-4 lg:px-6">
    <!-- Izquierda -->
    <div class="flex items-center gap-4">
      <button class="lg:hidden p-2 rounded-lg hover:bg-gray-100" @click="emit('toggleMobileSidebar')">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
        </svg>
      </button>
      <button class="hidden lg:block p-2 rounded-lg hover:bg-gray-100" @click="emit('toggleSidebar')">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
        </svg>
      </button>
      <nav class="hidden sm:flex items-center text-sm text-gray-500">
        <span>Dashboard</span>
      </nav>
    </div>

    <!-- Derecha -->
    <div class="flex items-center gap-4">
      <span
        v-if="pais"
        class="hidden sm:inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800"
      >
        {{ pais }}
      </span>

      <div class="relative">
        <button
          class="flex items-center gap-2 p-2 rounded-lg hover:bg-gray-100"
          @click="userMenuOpen = !userMenuOpen"
        >
          <div class="w-8 h-8 rounded-full bg-blue-500 text-white flex items-center justify-center text-sm font-medium">
            {{ userName.charAt(0) }}
          </div>
          <span class="hidden md:block text-sm font-medium text-gray-700">{{ userName }}</span>
          <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
          </svg>
        </button>

        <div
          v-if="userMenuOpen"
          class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 py-1 z-50"
          @click="userMenuOpen = false"
        >
          <div class="px-4 py-2 border-b border-gray-100">
            <p class="text-sm font-medium text-gray-900">{{ userName }}</p>
            <p class="text-xs text-gray-500">{{ userEmail }}</p>
          </div>
          <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Mi Perfil</a>
          <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Configuración</a>
          <button
            class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-gray-100"
            @click="emit('logout')"
          >
            Cerrar Sesión
          </button>
        </div>
      </div>
    </div>
  </header>

  <div v-if="userMenuOpen" class="fixed inset-0 z-40" @click="userMenuOpen = false" />
</template>
