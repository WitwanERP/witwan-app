<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import Sidebar from '@/Components/Sidebar.vue'
import Navbar from '@/Components/Navbar.vue'

const sidebarOpen = ref(true)
const mobileSidebarOpen = ref(false)

const toggleSidebar = () => (sidebarOpen.value = !sidebarOpen.value)
const toggleMobileSidebar = () => (mobileSidebarOpen.value = !mobileSidebarOpen.value)

function handleLogout() {
  // El logout real (sesión web + propagación al CI) se cablea con el auth.
  router.post('/app/logout')
}
</script>

<template>
  <div class="min-h-screen bg-gray-100">
    <!-- Backdrop mobile -->
    <div
      v-if="mobileSidebarOpen"
      class="fixed inset-0 z-20 bg-black/50 lg:hidden"
      @click="mobileSidebarOpen = false"
    />

    <Sidebar
      :open="sidebarOpen"
      :mobile-open="mobileSidebarOpen"
      @close-mobile="mobileSidebarOpen = false"
    />

    <div class="transition-all duration-300" :class="sidebarOpen ? 'lg:ml-64' : 'lg:ml-20'">
      <Navbar
        :sidebar-open="sidebarOpen"
        @toggle-sidebar="toggleSidebar"
        @toggle-mobile-sidebar="toggleMobileSidebar"
        @logout="handleLogout"
      />

      <main class="p-4 lg:p-6">
        <slot />
      </main>
    </div>
  </div>
</template>
