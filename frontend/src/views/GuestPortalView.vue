<template>
  <div class="max-w-4xl mx-auto space-y-6 pb-16">
    <!-- Guest Header & VIP Card -->
    <div class="bg-gradient-to-r from-[#0B4D34] via-[#073826] to-[#042418] text-white p-6 sm:p-8 rounded-3xl shadow-xl flex flex-col sm:flex-row items-center justify-between gap-6 relative overflow-hidden">
      <!-- Decorative traditional background pattern -->
      <div class="absolute -right-10 -bottom-10 opacity-10 text-9xl select-none font-serif">🇪🇹</div>

      <div class="flex items-center gap-4 z-10">
        <div class="w-16 h-16 rounded-2xl bg-[#D4A329] text-slate-900 font-extrabold flex items-center justify-center text-2xl shadow-lg border-2 border-white/20">
          {{ guestInitials }}
        </div>
        <div>
          <div class="flex items-center gap-2">
            <h1 class="text-2xl font-black tracking-tight">{{ guestData?.full_name || 'Valued Guest' }}</h1>
            <span class="text-[10px] uppercase font-bold tracking-wider px-2.5 py-0.5 rounded-full bg-[#D4A329] text-slate-900">
              VIP Member
            </span>
          </div>
          <p class="text-xs text-emerald-200 mt-1">{{ guestData?.email }} · {{ guestData?.phone }}</p>
        </div>
      </div>

      <div class="z-10 flex items-center gap-3">
        <router-link 
          to="/rooms" 
          class="px-5 py-2.5 bg-[#D4A329] hover:bg-[#B8861B] text-slate-900 text-xs font-bold rounded-full shadow-md transition flex items-center gap-1.5">
          <span>🛎️</span> Book Another Stay
        </router-link>
      </div>
    </div>

    <!-- Active Stay Digital Keycard Section -->
    <div v-if="activeStay" class="bg-white p-6 sm:p-8 rounded-3xl shadow-sm border border-slate-100 space-y-6">
      <div class="flex items-center justify-between border-b border-slate-100 pb-4">
        <div>
          <span class="text-xs font-bold text-emerald-700 uppercase tracking-wider block">Current Active Reservation</span>
          <h2 class="text-xl font-black text-slate-900 mt-0.5">{{ activeStay.room_title }} (Room #{{ activeStay.room_number }})</h2>
        </div>

        <span class="px-3 py-1 bg-emerald-100 text-emerald-800 text-xs font-bold rounded-full uppercase">
          {{ activeStay.booking_status }}
        </span>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-center">
        <!-- Digital Mobile Key QR Code -->
        <div class="bg-gradient-to-br from-slate-900 to-[#0B4D34] text-white p-6 rounded-2xl text-center space-y-3 shadow-lg relative">
          <div class="flex justify-between items-center text-xs text-emerald-200 pb-2 border-b border-white/10">
            <span>Room Door Access</span>
            <span class="font-mono font-bold">{{ activeStay.reference_code }}</span>
          </div>

          <div class="bg-white p-3 rounded-2xl w-44 h-44 mx-auto shadow-inner flex items-center justify-center">
            <img 
              :src="'https://api.qrserver.com/v1/create-qr-code/?size=160x160&data=' + encodeURIComponent(activeStay.qr_key_hash)"
              alt="Digital Room Key QR"
              class="w-full h-full object-contain" />
          </div>

          <p class="text-xs text-slate-200 font-medium">
            Scan at Room {{ activeStay.room_number }} electronic lock or front desk kiosk
          </p>
        </div>

        <!-- In-Room Amenities & Butler Concierge -->
        <div class="space-y-4">
          <!-- WiFi Credentials -->
          <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200 space-y-2 text-xs">
            <div class="font-bold text-slate-800 flex items-center gap-1.5">
              <span>📶</span> High-Speed Guest WiFi
            </div>
            <div class="flex justify-between text-slate-600">
              <span>Network:</span>
              <span class="font-mono font-bold text-slate-900">{{ activeStay.wifi_ssid }}</span>
            </div>
            <div class="flex justify-between text-slate-600">
              <span>Password:</span>
              <span class="font-mono font-bold text-[#0B4D34]">{{ activeStay.wifi_pass }}</span>
            </div>
          </div>

          <!-- Stay Dates -->
          <div class="grid grid-cols-2 gap-3 text-xs">
            <div class="p-3 bg-slate-50 rounded-xl border border-slate-100">
              <span class="text-slate-400 block text-[10px]">Check-In</span>
              <span class="font-bold text-slate-800">{{ activeStay.check_in_date }}</span>
            </div>
            <div class="p-3 bg-slate-50 rounded-xl border border-slate-100">
              <span class="text-slate-400 block text-[10px]">Check-Out</span>
              <span class="font-bold text-slate-800">{{ activeStay.check_out_date }}</span>
            </div>
          </div>

          <!-- Quick Service Requests -->
          <div class="grid grid-cols-2 gap-2 text-xs">
            <button 
              @click="requestService('Housekeeping Service')"
              class="p-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded-xl transition flex items-center justify-center gap-1.5">
              <span>🧹</span> Clean Room
            </button>
            <button 
              @click="requestService('In-Room Dining')"
              class="p-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded-xl transition flex items-center justify-center gap-1.5">
              <span>☕</span> Coffee Ceremony
            </button>
            <button 
              @click="requestService('Airport Transfer Shuttle')"
              class="p-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded-xl transition flex items-center justify-center gap-1.5">
              <span>🚐</span> Airport Shuttle
            </button>
            <button 
              @click="openInvoiceModal"
              class="p-2.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 font-semibold rounded-xl transition flex items-center justify-center gap-1.5">
              <span>📄</span> Tax Invoice
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- No Active Stay Banner -->
    <div v-else-if="!loading" class="bg-white p-8 rounded-3xl shadow-sm border border-slate-100 text-center space-y-3">
      <div class="text-4xl">🏨</div>
      <h3 class="text-base font-bold text-slate-800">No Active In-House Stay</h3>
      <p class="text-xs text-slate-500 max-w-sm mx-auto">
        You do not currently have a room checked-in. Explore our boutique Ethiopian suites and reserve your luxury retreat.
      </p>
      <router-link 
        to="/rooms" 
        class="inline-block px-5 py-2.5 bg-[#0B4D34] hover:bg-[#073826] text-white text-xs font-bold rounded-full transition shadow">
        Browse Available Suites
      </router-link>
    </div>

    <!-- Official Tax Invoice Modal -->
    <TaxInvoiceModal 
      :is-open="showInvoiceModal" 
      :invoice-data="activeInvoiceData" 
      @close="showInvoiceModal = false" />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import TaxInvoiceModal from '../components/TaxInvoiceModal.vue';

const guestData = ref(null);
const activeStay = ref(null);
const pastStays = ref([]);
const loading = ref(false);
const showInvoiceModal = ref(false);
const activeInvoiceData = ref(null);

const guestInitials = computed(() => {
  const name = guestData.value?.full_name || 'Valued Guest';
  const parts = name.split(' ');
  return parts.length > 1 ? (parts[0][0] + parts[1][0]).toUpperCase() : name.slice(0, 2).toUpperCase();
});

async function fetchPortalSummary() {
  loading.value = true;
  try {
    const token = localStorage.getItem('hotel_auth_token');
    const headers = token ? { 'Authorization': `Bearer ${token}` } : {};

    const res = await fetch('/api/v1/guest/portal-summary', { headers });
    const data = await res.json();
    guestData.value = data.guest || null;
    activeStay.value = data.active_stay || null;
    pastStays.value = data.past_stays || [];
  } catch (err) {
    console.error('Failed to load guest portal data:', err);
  } finally {
    loading.value = false;
  }
}

function requestService(serviceName) {
  alert(`🔔 Concierge notification dispatched! Our staff is preparing your ${serviceName}.`);
}

async function openInvoiceModal() {
  if (!activeStay.value) return;
  try {
    const token = localStorage.getItem('hotel_auth_token');
    const headers = token ? { 'Authorization': `Bearer ${token}` } : {};

    const res = await fetch(`/api/v1/bookings/${activeStay.value.id}/invoice`, { headers });
    const data = await res.json();
    if (data.invoice) {
      activeInvoiceData.value = data.invoice;
      showInvoiceModal.value = true;
    }
  } catch (err) {
    console.error('Failed to load invoice:', err);
  }
}

onMounted(() => {
  fetchPortalSummary();
});
</script>
