<script setup>
import { computed } from 'vue'
import {
  ShoppingBagIcon,
  BriefcaseIcon,
  ClockIcon,
  ListBulletIcon,
  PaperAirplaneIcon,
  ChartBarIcon,
  CalculatorIcon,
  BookOpenIcon,
  WalletIcon,
  CreditCardIcon,
  IdentificationIcon,
  CurrencyDollarIcon,
  FlagIcon,
  BuildingOffice2Icon,
  UsersIcon,
  WrenchIcon,
  LinkIcon,
  HeartIcon,
  ArrowDownTrayIcon,
  PencilSquareIcon,
  Squares2X2Icon,
} from '@heroicons/vue/24/outline'

// Mapea el ícono de CI (ya normalizado por MenuService: 'basket', 'plane', …)
// a su equivalente Heroicon. Lo no mapeado cae en un genérico.
const MAP = {
  basket: ShoppingBagIcon,
  briefcase: BriefcaseIcon,
  clock: ClockIcon,
  list: ListBulletIcon,
  plane: PaperAirplaneIcon,
  'bar-chart-o': ChartBarIcon,
  calculator: CalculatorIcon,
  'book-open': BookOpenIcon,
  wallet: WalletIcon,
  'credit-card': CreditCardIcon,
  badge: IdentificationIcon,
  usd: CurrencyDollarIcon,
  flag: FlagIcon,
  'building-o': BuildingOffice2Icon,
  users: UsersIcon,
  wrench: WrenchIcon,
  chain: LinkIcon,
  'heart-o': HeartIcon,
  save: ArrowDownTrayIcon,
  edit: PencilSquareIcon,
}

const props = defineProps({
  name: { type: String, default: '' },
})

const icon = computed(() => MAP[props.name] || Squares2X2Icon)
</script>

<template>
  <component :is="icon" />
</template>
