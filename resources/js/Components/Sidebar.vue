<script setup>
import { ref, computed, watch } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { ChevronRightIcon, HomeIcon } from '@heroicons/vue/24/outline'
import MenuIcon from '@/Components/MenuIcon.vue'

defineProps({
  open: Boolean,
  mobileOpen: Boolean,
})
const emit = defineEmits(['closeMobile'])

const page = usePage()

// Botonera que arma el backend (MenuService) desde brain + permisos del rol.
const menu = computed(() => page.props.menu ?? [])

// Acordeón de 2 niveles (data-keep-expanded="false" del CI): un sistema abierto
// y, dentro, un grupo abierto.
const STORAGE = 'witwan.sidebar.sistema'
const openSistema = ref(null)
const openGrupo = ref(null)

const dashboardActiva = computed(() => page.url === '/app')

/** Sección activa: la migrada a /app cuya URL matchea la del navegador. */
const esActiva = (url) =>
  typeof url === 'string' &&
  url.startsWith('/app') &&
  (page.url === url || page.url.startsWith(url + '/') || page.url.startsWith(url + '?'))

// Al cargar (o al cambiar de página) se abre la rama de la sección activa; si no
// hay ninguna, la última que el usuario dejó abierta.
watch(
  [menu, () => page.url],
  () => {
    for (const sistema of menu.value) {
      for (const grupo of sistema.grupos) {
        if (grupo.items.some((i) => esActiva(i.url))) {
          openSistema.value = sistema.sistema_id
          openGrupo.value = sistema.sistema_id + ':' + grupo.grupo
          return
        }
      }
    }

    const ids = menu.value.map((s) => s.sistema_id)
    const guardado = Number(localStorage.getItem(STORAGE))
    if (openSistema.value === null && ids.includes(guardado)) openSistema.value = guardado
  },
  { immediate: true },
)

const toggleSistema = (id) => {
  openSistema.value = openSistema.value === id ? null : id
  openGrupo.value = null
  if (openSistema.value != null) localStorage.setItem(STORAGE, String(openSistema.value))
}
const toggleGrupo = (key) => (openGrupo.value = openGrupo.value === key ? null : key)
const claveGrupo = (sistema, grupo) => sistema.sistema_id + ':' + grupo.grupo
</script>

<template>
  <!--
    page-sidebar: fijo bajo el header (42px). En desktop mide 235px, o 45px
    colapsado (solo íconos, con flyout al pasar el mouse). En mobile baja como
    panel debajo del header, igual que el navbar-collapse del CI.
  -->
  <aside
    class="page-sidebar fixed bottom-0 left-0 top-[42px] z-30 overflow-y-auto overflow-x-hidden bg-mt-dark text-[13px] transition-all duration-200"
    :class="[
      mobileOpen ? 'block w-full' : 'hidden',
      open ? 'lg:block lg:w-[235px]' : 'lg:block lg:w-[45px] lg:overflow-visible',
    ]"
  >
    <ul class="py-2">
      <!-- INICIO -->
      <li>
        <Link
          href="/app"
          class="flex items-center gap-3 border-l-[3px] px-3 py-2.5 transition-colors"
          :class="[
            dashboardActiva
              ? 'border-mt-accent bg-mt-hover text-white'
              : 'border-transparent text-mt-text hover:bg-mt-hover hover:text-white',
            open ? '' : 'lg:justify-center lg:px-0',
          ]"
          :title="open ? null : 'Inicio'"
          @click="emit('closeMobile')"
        >
          <HomeIcon class="h-4 w-4 shrink-0" />
          <span v-show="open || mobileOpen" class="truncate font-medium uppercase">Inicio</span>
        </Link>
      </li>

      <li class="my-2 border-t border-mt-border/40"></li>

      <!-- Sistemas -->
      <li v-for="sistema in menu" :key="sistema.sistema_id" class="group relative">
        <button
          type="button"
          class="flex w-full items-center gap-3 border-l-[3px] px-3 py-2.5 text-left transition-colors"
          :class="[
            openSistema === sistema.sistema_id
              ? 'border-transparent bg-mt-hover text-white'
              : 'border-transparent text-mt-text hover:bg-mt-hover hover:text-white',
            open ? '' : 'lg:justify-center lg:px-0',
          ]"
          :title="open ? null : sistema.sistema"
          @click="toggleSistema(sistema.sistema_id)"
        >
          <MenuIcon name="folder" class="h-4 w-4 shrink-0" :style="{ color: sistema.color }" />
          <span v-show="open || mobileOpen" class="min-w-0 flex-1 truncate font-medium uppercase">
            {{ sistema.sistema }}
          </span>
          <ChevronRightIcon
            v-show="open || mobileOpen"
            class="h-3.5 w-3.5 shrink-0 transition-transform"
            :class="openSistema === sistema.sistema_id ? 'rotate-90' : ''"
          />
        </button>

        <!-- Grupos + secciones (acordeón; oculto cuando el sidebar está colapsado) -->
        <ul
          v-show="openSistema === sistema.sistema_id && (open || mobileOpen)"
          class="bg-mt-sub"
        >
          <li v-for="grupo in sistema.grupos" :key="grupo.grupo">
            <button
              type="button"
              class="flex w-full items-center gap-3 py-2 pl-8 pr-3 text-left transition-colors"
              :class="
                openGrupo === claveGrupo(sistema, grupo)
                  ? 'text-white'
                  : 'text-mt-text hover:text-white'
              "
              @click="toggleGrupo(claveGrupo(sistema, grupo))"
            >
              <MenuIcon
                :name="grupo.icono"
                class="h-4 w-4 shrink-0"
                :style="{ color: sistema.color }"
              />
              <span class="min-w-0 flex-1 truncate">{{ grupo.grupo }}</span>
              <ChevronRightIcon
                class="h-3 w-3 shrink-0 transition-transform"
                :class="openGrupo === claveGrupo(sistema, grupo) ? 'rotate-90' : ''"
              />
            </button>

            <ul v-show="openGrupo === claveGrupo(sistema, grupo)">
              <li v-for="item in grupo.items" :key="item.seccion_id">
                <a
                  :href="item.url"
                  class="block border-l-[3px] py-1.5 pl-14 pr-3 transition-colors"
                  :class="
                    esActiva(item.url)
                      ? 'border-mt-accent bg-mt-hover text-white'
                      : 'border-transparent text-mt-text hover:bg-mt-hover hover:text-white'
                  "
                  @click="emit('closeMobile')"
                >
                  {{ item.label }}
                </a>
              </li>
            </ul>
          </li>
        </ul>

        <!-- Flyout del sidebar colapsado (solo desktop) -->
        <div
          v-if="!open"
          class="invisible absolute left-full top-0 z-40 hidden max-h-[70vh] w-[240px] overflow-y-auto border border-mt-border bg-mt-sub opacity-0 shadow-lg transition-opacity group-hover:visible group-hover:opacity-100 lg:block"
        >
          <div class="bg-mt-hover px-3 py-2 font-medium uppercase text-white">
            {{ sistema.sistema }}
          </div>
          <div v-for="grupo in sistema.grupos" :key="grupo.grupo">
            <div class="flex items-center gap-2 px-3 py-2 text-mt-muted">
              <MenuIcon
                :name="grupo.icono"
                class="h-4 w-4 shrink-0"
                :style="{ color: sistema.color }"
              />
              <span class="truncate">{{ grupo.grupo }}</span>
            </div>
            <a
              v-for="item in grupo.items"
              :key="item.seccion_id"
              :href="item.url"
              class="block py-1.5 pl-9 pr-3 transition-colors"
              :class="
                esActiva(item.url)
                  ? 'bg-mt-hover text-white'
                  : 'text-mt-text hover:bg-mt-hover hover:text-white'
              "
            >
              {{ item.label }}
            </a>
          </div>
        </div>
      </li>
    </ul>
  </aside>
</template>
