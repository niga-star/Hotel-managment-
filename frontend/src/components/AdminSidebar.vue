<template>
  <aside class="w-64 bg-[#0B4D34] text-white flex-shrink-0 min-h-[calc(100vh-80px)] p-4 flex flex-col justify-between shadow-lg">
    <div>
      <div class="px-3 py-2 mb-4">
        <h2 class="text-xs font-semibold uppercase tracking-wider text-[#D4A329]">Operations Menu</h2>
      </div>

      <nav class="space-y-1.5">
        <button
          v-for="item in navItems"
          :key="item.id"
          @click="navigate(item)"
          :class="[
            isActive(item)
              ? 'bg-[#107550] text-white font-semibold shadow-inner border-l-4 border-[#D4A329]'
              : 'text-emerald-100/90 hover:bg-[#064E3B] hover:text-white'
          ]"
          class="w-full flex items-center space-x-3 px-3.5 py-3 rounded-lg text-sm font-medium transition-all group cursor-pointer">
          <span class="text-lg opacity-90 group-hover:scale-110 transition-transform">{{ item.icon }}</span>
          <span>{{ item.label }}</span>
          <span 
            v-if="item.badge" 
            class="ml-auto text-[11px] font-bold px-2 py-0.5 rounded-full bg-[#D4A329] text-slate-900">
            {{ item.badge }}
          </span>
        </button>
      </nav>
    </div>

    <!-- Front Desk / Staff Profile snippet -->
    <div class="pt-4 border-t border-emerald-800/80">
      <div class="bg-[#064E3B] p-3 rounded-xl flex items-center space-x-3 border border-emerald-700/50">
        <div class="w-9 h-9 rounded-full bg-[#D4A329] text-slate-900 font-bold flex items-center justify-center text-sm shadow">
          HA
        </div>
        <div class="overflow-hidden">
          <p class="text-xs font-semibold text-white truncate">Staff Concierge</p>
          <p class="text-[11px] text-emerald-200/80 truncate">Reception & Finance</p>
        </div>
      </div>
    </div>
  </aside>
</template>

<script setup>
import { computed } from 'vue';
import { useRouter, useRoute } from 'vue-router';

const router = useRouter();
const route = useRoute();

const props = defineProps({
  currentView: {
    type: String,
    default: 'dashboard'
  }
});

const emit = defineEmits(['select-view']);

const navItems = [
  { id: 'dashboard', label: 'Dashboard', icon: '📊', path: '/admin/dashboard' },
  { id: 'floor-map', label: 'Visual Floor Map', icon: '🏢', path: '/admin/floor-map' },
  { id: 'manage-rooms', label: 'Manage Rooms', icon: '🛏️', path: '/admin/rooms' },
  { id: 'bookings', label: 'Bookings Desk', icon: '📅', path: '/admin/bookings' },
  { id: 'check-in', label: 'Check-In Desk', icon: '🛎️', path: '/admin/check-in' },
  { id: 'check-out', label: 'Check-Out Desk', icon: '🚪', path: '/admin/check-out' },
  { id: 'housekeeping', label: 'Housekeeping', icon: '🧹', path: '/admin/housekeeping' },
  { id: 'payments', label: 'Payments', icon: '💳', path: '/admin/payments' },
  { id: 'reports', label: 'Reports', icon: '📈', path: '/admin/reports' }
];

function isActive(item) {
  if (route && route.path === item.path) return true;
  return props.currentView === item.id;
}

function navigate(item) {
  emit('select-view', item.id);
  if (router && item.path) {
    router.push(item.path);
  }
}
</script>
