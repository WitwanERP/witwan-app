<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'
import { router } from '@inertiajs/vue3'
import Sidebar from '@/Components/Sidebar.vue'
import Navbar from '@/Components/Navbar.vue'
import QuickSidebar from '@/Components/QuickSidebar.vue'
import FlashMessages from '@/Components/FlashMessages.vue'
import { ArrowUpIcon } from '@heroicons/vue/24/outline'

// Estructura del Metronic del CI: header fijo (42px), sidebar fijo debajo
// (235px, o 45px colapsado), contenido corrido y footer al pie.
const STORAGE = 'witwan.sidebar.open'

const sidebarOpen = ref(true)
const mobileMenuOpen = ref(false)
const quickSidebarOpen = ref(false)
const scrolleado = ref(false)

const toggleSidebar = () => {
  sidebarOpen.value = !sidebarOpen.value
  localStorage.setItem(STORAGE, sidebarOpen.value ? '1' : '0')
}

const onScroll = () => (scrolleado.value = window.scrollY > 200)
const irArriba = () => window.scrollTo({ top: 0, behavior: 'smooth' })

onMounted(() => {
  sidebarOpen.value = localStorage.getItem(STORAGE) !== '0'
  window.addEventListener('scroll', onScroll, { passive: true })
})
onBeforeUnmount(() => window.removeEventListener('scroll', onScroll))

function handleLogout() {
  // El logout real (sesión web + propagación al CI) se cablea con el auth.
  router.post('/app/logout')
}
</script>

<template>
  <div class="min-h-screen bg-mt-content">
    <Navbar
      :sidebar-open="sidebarOpen"
      @toggle-sidebar="toggleSidebar"
      @toggle-mobile-menu="mobileMenuOpen = !mobileMenuOpen"
      @toggle-quick-sidebar="quickSidebarOpen = !quickSidebarOpen"
      @logout="handleLogout"
    />

    <!-- page-container -->
    <div class="page-container pt-[42px]">
      <Sidebar
        :open="sidebarOpen"
        :mobile-open="mobileMenuOpen"
        @close-mobile="mobileMenuOpen = false"
      />

      <!-- page-content-wrapper -->
      <div
        class="page-content-wrapper flex min-h-[calc(100vh-42px)] flex-col transition-all duration-200"
        :class="sidebarOpen ? 'lg:ml-[235px]' : 'lg:ml-[45px]'"
      >
        <main class="flex-1 p-4 lg:p-5">
          <FlashMessages />
          <slot />
        </main>

        <footer class="page-footer bg-mt-dark px-5 py-2 text-[12px] text-mt-muted">
          {{ new Date().getFullYear() }} ©
          <a href="http://www.witwan.com" class="hover:text-white">WITWAN by LATconsulting</a>.
        </footer>
      </div>
    </div>

    <QuickSidebar :open="quickSidebarOpen" @close="quickSidebarOpen = false" />

    <!-- scroll-to-top -->
    <button
      v-show="scrolleado"
      type="button"
      class="no-print fixed bottom-4 right-4 z-40 flex h-8 w-8 items-center justify-center bg-mt-dark/80 text-white transition-colors hover:bg-mt-dark"
      title="Ir arriba"
      @click="irArriba"
    >
      <ArrowUpIcon class="h-4 w-4" />
    </button>
  </div>
</template>
