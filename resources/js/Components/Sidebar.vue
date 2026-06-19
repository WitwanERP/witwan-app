<script setup>
import { computed } from 'vue'
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

// Menú (equivalente al sidebar.php de CI). Hrefs bajo /app.
const menuItems = computed(() => [
  {
    title: 'Dashboard',
    href: '/app',
    match: '/app',
    icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
  },
  {
    title: 'Reservas',
    href: '/app/reservas',
    match: '/app/reservas',
    color: 'text-ww-receptivo',
    icon: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
  },
  {
    title: 'Clientes',
    href: '/app/clientes',
    match: '/app/clientes',
    icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z',
  },
])

const isActive = (item) =>
  item.match === '/app' ? page.url === '/app' : page.url.startsWith(item.match)
</script>

<template>
  <!-- Sidebar desktop -->
  <aside
    class="fixed inset-y-0 left-0 z-30 bg-gray-900 text-white transition-all duration-300 hidden lg:block"
    :class="open ? 'w-64' : 'w-20'"
  >
    <div class="h-16 flex items-center justify-center border-b border-gray-800">
      <span class="text-xl font-bold">{{ open ? 'WitWan' : 'W' }}</span>
    </div>

    <nav class="mt-4 px-2">
      <Link
        v-for="item in menuItems"
        :key="item.href"
        :href="item.href"
        class="flex items-center px-4 py-3 mb-1 rounded-lg transition-colors"
        :class="[
          isActive(item) ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white',
          item.color || '',
        ]"
      >
        <svg class="w-6 h-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="item.icon" />
        </svg>
        <span v-if="open" class="ml-3">{{ item.title }}</span>
      </Link>
    </nav>

    <div class="absolute bottom-0 left-0 right-0 p-4 border-t border-gray-800">
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
    class="fixed inset-y-0 left-0 z-30 w-64 bg-gray-900 text-white transform transition-transform duration-300 lg:hidden"
    :class="mobileOpen ? 'translate-x-0' : '-translate-x-full'"
  >
    <div class="h-16 flex items-center justify-between px-4 border-b border-gray-800">
      <span class="text-xl font-bold">WitWan</span>
      <button class="p-2 rounded-lg hover:bg-gray-800" @click="$emit('closeMobile')">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </button>
    </div>

    <nav class="mt-4 px-2">
      <Link
        v-for="item in menuItems"
        :key="item.href"
        :href="item.href"
        class="flex items-center px-4 py-3 mb-1 rounded-lg transition-colors"
        :class="isActive(item) ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-gray-800'"
        @click="$emit('closeMobile')"
      >
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="item.icon" />
        </svg>
        <span class="ml-3">{{ item.title }}</span>
      </Link>
    </nav>
  </aside>
</template>
