<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-5 rounded-2xl shadow-sm border border-slate-100">
      <div>
        <h2 class="text-2xl font-bold text-[#0B4D34]">Guest Check-Out & Settlement Desk</h2>
        <p class="text-xs text-slate-500">Departure clearance console, invoice finalization & room release management.</p>
      </div>

      <div class="flex items-center gap-2">
        <span class="text-xs font-bold px-3 py-1 rounded-full bg-amber-50 text-amber-800 border border-amber-200">
          {{ eligibleDepartures.length }} Active Occupant{{ eligibleDepartures.length === 1 ? '' : 's' }}
        </span>
        <button 
          @click="fetchDepartures"
          class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-full transition">
          🔄 Refresh Departures
        </button>
      </div>
    </div>

    <!-- Search Bar -->
    <div class="flex items-center justify-between gap-4">
      <div class="relative w-full sm:w-96">
        <input 
          v-model="searchQuery" 
          @input="fetchDepartures"
          type="text" 
          placeholder="Search by Reference (#ETH-XXXXX), Guest, or Room..." 
          class="w-full pl-9 pr-4 py-2.5 bg-white border border-slate-200 rounded-full text-xs focus:outline-none focus:border-[#0B4D34] shadow-sm" />
        <span class="absolute left-3.5 top-3 text-xs text-slate-400">🔍</span>
      </div>
    </div>

    <!-- Active In-House Guests Table -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
      <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
        <h3 class="text-base font-bold text-slate-900">Current Occupants Pending Departure</h3>
        <span class="text-xs text-slate-400">Filter: booking_status = 'checked_in'</span>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-sm">
          <thead>
            <tr class="bg-slate-50 text-slate-600 text-xs font-semibold uppercase tracking-wider border-b border-slate-100">
              <th class="py-3 px-5">Ref Code</th>
              <th class="py-3 px-5">Guest Name & Phone</th>
              <th class="py-3 px-5">Occupied Room</th>
              <th class="py-3 px-5">Arrival Timestamp</th>
              <th class="py-3 px-5">Scheduled Departure</th>
              <th class="py-3 px-5">Total Stay Bill</th>
              <th class="py-3 px-5">Outstanding Balance</th>
              <th class="py-3 px-5 text-right">Action</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 text-slate-700">
            <tr v-if="loading" class="text-center">
              <td colspan="8" class="py-8 text-slate-400 text-xs">Loading active occupants from database...</td>
            </tr>
            <tr v-else-if="eligibleDepartures.length === 0" class="text-center">
              <td colspan="8" class="py-8 text-slate-400 text-xs">No active checked-in guests in hotel rooms.</td>
            </tr>
            <tr 
              v-else
              v-for="b in eligibleDepartures" 
              :key="b.id" 
              class="hover:bg-slate-50/50 transition">
              <!-- Ref Code -->
              <td class="py-3.5 px-5 font-mono font-bold text-slate-900 text-xs">
                {{ b.reference_code }}
              </td>
              <!-- Guest -->
              <td class="py-3.5 px-5">
                <div class="font-bold text-slate-900 text-xs">{{ b.guest_name }}</div>
                <div class="text-[11px] text-slate-400 font-mono">{{ b.guest_phone }}</div>
              </td>
              <!-- Room -->
              <td class="py-3.5 px-5">
                <div class="font-bold text-slate-800 text-xs">Room {{ b.room_number }}</div>
                <div class="text-[10px] text-slate-400">{{ b.category_name }}</div>
              </td>
              <!-- Arrival -->
              <td class="py-3.5 px-5 text-xs text-slate-600 font-mono">
                {{ b.actual_check_in_at ? formatDateTime(b.actual_check_in_at) : b.check_in_date }}
              </td>
              <!-- Departure -->
              <td class="py-3.5 px-5 text-xs text-slate-600">
                {{ b.check_out_date }}
              </td>
              <!-- Total -->
              <td class="py-3.5 px-5 font-bold text-slate-900 text-xs">
                ETB {{ b.total_price_etb?.toLocaleString() }}
              </td>
              <!-- Outstanding -->
              <td class="py-3.5 px-5 text-xs">
                <span v-if="b.outstanding_balance_etb > 0" class="text-rose-600 font-bold px-2 py-0.5 rounded-full bg-rose-50 border border-rose-200">
                  ETB {{ b.outstanding_balance_etb?.toLocaleString() }} Unsettled
                </span>
                <span v-else class="text-emerald-700 font-bold px-2 py-0.5 rounded-full bg-emerald-50 border border-emerald-200">
                  ✓ 0 ETB (Cleared)
                </span>
              </td>
              <!-- Action -->
              <td class="py-3.5 px-5 text-right">
                <div class="flex items-center justify-end gap-2">
                  <button 
                    @click="viewInvoice(b)"
                    class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs rounded-xl transition flex items-center gap-1 cursor-pointer">
                    <span>📄</span> Tax Invoice
                  </button>
                  <button 
                    @click="executeCheckOut(b)"
                    :disabled="checkingOutId === b.id"
                    class="px-3.5 py-2 bg-[#D4A329] hover:bg-[#B8861B] text-slate-900 font-bold text-xs rounded-xl shadow-sm transition disabled:opacity-50 flex items-center gap-1.5 cursor-pointer">
                    <span v-if="checkingOutId === b.id" class="animate-spin text-xs">⏳</span>
                    <span>Check-Out</span>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Final Official Ethiopian Departure Tax Invoice Modal (Screen 8C) -->
    <div v-if="completedInvoice" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-3xl overflow-hidden shadow-2xl max-w-lg w-full p-6 space-y-4 border border-slate-100 animate-fadeIn">
        <!-- Invoice Header -->
        <div class="border-b border-slate-200 pb-3 text-center space-y-1">
          <div class="w-10 h-10 rounded-xl bg-emerald-100 text-emerald-800 text-xl flex items-center justify-center mx-auto">
            👑
          </div>
          <h3 class="text-base font-bold text-slate-900 font-serif">LUXURY ETHIOPIAN RESORT & SUITES</h3>
          <p class="text-[10px] text-slate-500">Boutique Hospitality PMS • Addis Ababa & Rift Valley Resorts</p>
          <div class="flex items-center justify-center gap-3 text-[10px] font-mono text-slate-400">
            <span>TIN: 0049281921</span>
            <span>•</span>
            <span>VAT Reg: 991823901</span>
          </div>
        </div>

        <!-- Invoice Details -->
        <div class="p-4 bg-slate-50 rounded-2xl text-xs space-y-2 border border-slate-200">
          <div class="flex justify-between items-center border-b border-slate-200 pb-2">
            <div>
              <span class="text-[10px] text-slate-400 uppercase font-bold block">Tax Invoice Ref</span>
              <span class="font-bold text-slate-900 font-mono text-sm">{{ completedInvoice.reference_code }}</span>
            </div>
            <span class="px-2.5 py-0.5 rounded-full bg-emerald-100 text-emerald-800 font-bold text-[10px] uppercase">
              PAID IN FULL
            </span>
          </div>

          <!-- Itemized Breakdown -->
          <div class="space-y-1.5 pt-1 text-[11px]">
            <div class="flex justify-between text-slate-600">
              <span>Accommodation & Room Services (Net):</span>
              <span class="font-medium text-slate-800">ETB {{ Math.round(Number(completedInvoice.total_price_etb) / 1.25).toLocaleString() }}</span>
            </div>
            <div class="flex justify-between text-slate-600">
              <span>10% Hotel Hospitality Service Charge:</span>
              <span class="font-medium text-slate-800">ETB {{ Math.round((Number(completedInvoice.total_price_etb) / 1.25) * 0.10).toLocaleString() }}</span>
            </div>
            <div class="flex justify-between text-slate-600">
              <span>15% Ethiopian VAT (Value Added Tax):</span>
              <span class="font-medium text-slate-800">ETB {{ Math.round((Number(completedInvoice.total_price_etb) / 1.25) * 0.15).toLocaleString() }}</span>
            </div>
            <div class="flex justify-between text-slate-900 font-bold text-xs pt-2 border-t border-slate-200">
              <span>Gross Total Settled:</span>
              <span class="text-[#0B4D34] text-sm">ETB {{ Number(completedInvoice.total_price_etb).toLocaleString() }}</span>
            </div>
          </div>

          <div class="flex justify-between text-[10px] text-slate-400 pt-2 border-t border-slate-200">
            <span>Departure Timestamp:</span>
            <span>{{ formatDateTime(completedInvoice.actual_check_out_at) }}</span>
          </div>
        </div>

        <!-- Buttons -->
        <div class="flex gap-2">
          <button 
            @click="printInvoice"
            class="flex-1 py-2.5 bg-[#0B4D34] hover:bg-[#064E3B] text-white text-xs font-bold rounded-xl shadow-xs transition flex items-center justify-center gap-1.5 cursor-pointer">
            <span>🖨️</span>
            <span>Print Tax Invoice</span>
          </button>
          <button 
            @click="completedInvoice = null"
            class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl transition cursor-pointer">
            Close
          </button>
        </div>
      </div>
    </div>

    <!-- Official Ethiopian Tax Invoice Modal -->
    <TaxInvoiceModal 
      :is-open="showInvoiceModal" 
      :invoice-data="activeInvoiceData" 
      @close="showInvoiceModal = false" />
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import TaxInvoiceModal from '../components/TaxInvoiceModal.vue';

const eligibleDepartures = ref([]);
const loading = ref(false);
const searchQuery = ref('');
const checkingOutId = ref(null);
const completedInvoice = ref(null);
const showInvoiceModal = ref(false);
const activeInvoiceData = ref(null);

async function viewInvoice(b) {
  try {
    const res = await fetch(`/api/v1/bookings/${b.id}/invoice`, { headers: getAuthHeaders() });
    const data = await res.json();
    if (data.invoice) {
      activeInvoiceData.value = data.invoice;
      showInvoiceModal.value = true;
    }
  } catch (err) {
    console.error('Failed to load invoice:', err);
  }
}

function getAuthHeaders() {
  const token = localStorage.getItem('hotel_auth_token');
  return token ? { 'Authorization': `Bearer ${token}` } : {};
}

function formatDateTime(dtStr) {
  if (!dtStr) return '';
  const d = new Date(dtStr);
  return d.toLocaleString('en-US', {
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  });
}

async function fetchDepartures() {
  loading.value = true;
  try {
    let url = '/api/v1/admin/bookings/check-out-eligible';
    if (searchQuery.value) {
      url += `?search=${encodeURIComponent(searchQuery.value)}`;
    }

    const res = await fetch(url, { headers: getAuthHeaders() });
    const data = await res.json();
    eligibleDepartures.value = data.eligible_departures || [];
  } catch (err) {
    console.error('Error fetching departures:', err);
  } finally {
    loading.value = false;
  }
}

async function executeCheckOut(b) {
  if (b.outstanding_balance_etb > 0) {
    if (!confirm(`Guest ${b.guest_name} has an outstanding balance of ETB ${b.outstanding_balance_etb.toLocaleString()}. Proceed with check-out settlement?`)) {
      return;
    }
  } else {
    if (!confirm(`Complete check-out for guest ${b.guest_name} (Room ${b.room_number})?`)) return;
  }

  checkingOutId.value = b.id;
  try {
    const res = await fetch(`/api/v1/bookings/${b.id}/check-out`, {
      method: 'POST',
      headers: getAuthHeaders()
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.error || 'Failed to complete check-out');

    completedInvoice.value = data.invoice;
    fetchDepartures();
  } catch (err) {
    alert(err.message);
  } finally {
    checkingOutId.value = null;
  }
}

function printInvoice() {
  window.print();
}

onMounted(() => {
  fetchDepartures();
});
</script>
