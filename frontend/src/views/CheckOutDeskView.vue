<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-5 rounded-2xl shadow-sm border border-slate-100">
      <div>
        <div class="flex items-center gap-2">
          <span class="text-2xl">🚪</span>
          <h2 class="text-2xl font-bold text-[#0B4D34]">Front Desk Check-Out & Settlement Desk</h2>
        </div>
        <p class="text-xs text-slate-500 mt-1">Departure clearance console, incidental billing, balance settlement, invoice issuance & room turnover release.</p>
      </div>

      <div class="flex items-center gap-2">
        <button 
          @click="fetchDepartures"
          class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-full transition cursor-pointer">
          🔄 Refresh Departures
        </button>
      </div>
    </div>

    <!-- Departure KPI Metrics -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-100">
        <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">In-House Guests</span>
        <div class="text-2xl font-black text-[#0B4D34] mt-1">{{ eligibleDepartures.length }}</div>
        <span class="text-[10px] text-slate-500">Active checked-in rooms</span>
      </div>

      <div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-100">
        <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Expected Today</span>
        <div class="text-2xl font-black text-amber-600 mt-1">{{ departuresTodayCount }}</div>
        <span class="text-[10px] text-amber-700 font-medium">Departing by 12:00 PM</span>
      </div>

      <div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-100">
        <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Unsettled Balances</span>
        <div class="text-2xl font-black text-rose-600 mt-1">{{ unsettledCount }}</div>
        <span class="text-[10px] text-rose-600 font-medium">Pending folio payment</span>
      </div>

      <div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-100">
        <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Cleared Folios</span>
        <div class="text-2xl font-black text-emerald-600 mt-1">{{ clearedCount }}</div>
        <span class="text-[10px] text-emerald-700 font-medium">Ready for immediate checkout</span>
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
              <th class="py-3 px-4">Ref Code</th>
              <th class="py-3 px-4">Guest Details</th>
              <th class="py-3 px-4">Occupied Room</th>
              <th class="py-3 px-4">Stay Schedule</th>
              <th class="py-3 px-4">Total Bill</th>
              <th class="py-3 px-4">Folio Balance</th>
              <th class="py-3 px-4 text-right">Departure Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 text-slate-700">
            <tr v-if="loading" class="text-center">
              <td colspan="7" class="py-8 text-slate-400 text-xs">Loading active occupants from database...</td>
            </tr>
            <tr v-else-if="eligibleDepartures.length === 0" class="text-center">
              <td colspan="7" class="py-8 text-slate-400 text-xs">No active checked-in guests in hotel rooms.</td>
            </tr>
            <tr 
              v-else
              v-for="b in eligibleDepartures" 
              :key="b.id" 
              class="hover:bg-slate-50/50 transition">
              <!-- Ref Code -->
              <td class="py-3.5 px-4 font-mono font-bold text-slate-900 text-xs">
                {{ b.reference_code }}
                <div v-if="b.key_card_number" class="text-[10px] text-slate-400 font-mono font-normal">
                  Key: {{ b.key_card_number }}
                </div>
              </td>
              <!-- Guest -->
              <td class="py-3.5 px-4">
                <div class="font-bold text-slate-900 text-xs">{{ b.guest_name }}</div>
                <div class="text-[11px] text-slate-400 font-mono">{{ b.guest_phone }}</div>
              </td>
              <!-- Room -->
              <td class="py-3.5 px-4">
                <div class="font-bold text-slate-800 text-xs">Room {{ b.room_number }}</div>
                <div class="text-[10px] text-slate-400">{{ b.category_name }}</div>
              </td>
              <!-- Stay Schedule -->
              <td class="py-3.5 px-4 text-xs text-slate-600">
                <div class="font-mono text-[11px]">{{ b.actual_check_in_at ? formatDateTime(b.actual_check_in_at) : b.check_in_date }}</div>
                <div class="text-[10px] text-slate-400">until {{ b.check_out_date }}</div>
              </td>
              <!-- Total Bill -->
              <td class="py-3.5 px-4 font-bold text-slate-900 text-xs">
                ETB {{ (b.gross_total_etb || b.total_price_etb)?.toLocaleString() }}
              </td>
              <!-- Outstanding Balance -->
              <td class="py-3.5 px-4 text-xs">
                <span v-if="(b.outstanding_balance_etb || 0) > 0" class="text-rose-600 font-bold px-2 py-0.5 rounded-full bg-rose-50 border border-rose-200">
                  ETB {{ b.outstanding_balance_etb?.toLocaleString() }} Unsettled
                </span>
                <span v-else class="text-emerald-700 font-bold px-2 py-0.5 rounded-full bg-emerald-50 border border-emerald-200">
                  ✓ 0 ETB (Cleared)
                </span>
              </td>
              <!-- Actions -->
              <td class="py-3.5 px-4 text-right">
                <div class="flex items-center justify-end gap-1.5 flex-wrap">
                  <!-- Incidental Charges -->
                  <button 
                    @click="openServiceChargeModal(b)"
                    class="px-2.5 py-1.5 bg-amber-50 hover:bg-amber-100 text-amber-800 font-semibold text-xs rounded-xl border border-amber-200 transition flex items-center gap-1 cursor-pointer"
                    title="Post Mini-bar, Restaurant, Laundry Charges">
                    <span>🍷</span> Incidentals
                  </button>

                  <!-- Settle Payment (if balance > 0) -->
                  <button 
                    v-if="(b.outstanding_balance_etb || 0) > 0"
                    @click="openSettlementModal(b)"
                    class="px-2.5 py-1.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 font-semibold text-xs rounded-xl border border-emerald-200 transition flex items-center gap-1 cursor-pointer">
                    <span>💳</span> Settle
                  </button>

                  <!-- Tax Invoice -->
                  <button 
                    @click="viewInvoice(b)"
                    class="px-2.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs rounded-xl transition flex items-center gap-1 cursor-pointer"
                    title="Official Ethiopian Tax Invoice">
                    <span>📄</span> Invoice
                  </button>

                  <!-- Check-Out Execution -->
                  <button 
                    @click="initiateCheckOut(b)"
                    :disabled="checkingOutId === b.id"
                    class="px-3.5 py-1.5 bg-[#D4A329] hover:bg-[#B8861B] text-slate-900 font-bold text-xs rounded-xl shadow-xs transition disabled:opacity-50 flex items-center gap-1 cursor-pointer">
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

    <!-- Post Incidental Service Charge Modal -->
    <div v-if="activeServiceBooking" class="fixed inset-0 bg-black/60 backdrop-blur-xs z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-3xl overflow-hidden shadow-2xl max-w-md w-full p-6 space-y-4 border border-slate-100">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
          <div>
            <h3 class="text-base font-bold text-slate-900">Post Incidental Service Charge</h3>
            <p class="text-xs text-slate-400">Ref: {{ activeServiceBooking.reference_code }} • Room {{ activeServiceBooking.room_number }}</p>
          </div>
          <button @click="activeServiceBooking = null" class="text-slate-400 hover:text-slate-600 text-sm cursor-pointer">✕</button>
        </div>

        <div class="text-xs space-y-3">
          <div>
            <label class="font-bold text-slate-700 block mb-1">Service Type</label>
            <select v-model="serviceForm.service_type" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs outline-none">
              <option value="minibar">Mini-Bar Consumption</option>
              <option value="restaurant">Hotel Restaurant / Bar</option>
              <option value="room_service">In-Room Dining / Room Service</option>
              <option value="laundry">Dry Cleaning & Laundry</option>
              <option value="spa">Spa & Wellness Center</option>
              <option value="late_checkout_fee">Late Check-Out Fee</option>
              <option value="early_checkin_fee">Early Check-In Fee</option>
              <option value="damage_fee">Room Restoration / Damage</option>
              <option value="other">Other Incidentals</option>
            </select>
          </div>

          <div>
            <label class="font-bold text-slate-700 block mb-1">Description / Items</label>
            <input 
              v-model="serviceForm.description" 
              type="text" 
              placeholder="e.g. 2x Ethiopian Wine, 1x Sparkling Water" 
              class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs outline-none focus:border-[#0B4D34]" />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="font-bold text-slate-700 block mb-1">Quantity</label>
              <input 
                v-model.number="serviceForm.quantity" 
                type="number" 
                min="1" 
                class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs outline-none" />
            </div>
            <div>
              <label class="font-bold text-slate-700 block mb-1">Total Charge (ETB)</label>
              <input 
                v-model.number="serviceForm.amount_etb" 
                type="number" 
                placeholder="ETB Amount" 
                class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono font-bold outline-none focus:border-[#0B4D34]" />
            </div>
          </div>
        </div>

        <div class="flex gap-2 pt-2">
          <button 
            @click="submitServiceCharge"
            :disabled="!serviceForm.amount_etb || isSubmittingService"
            class="flex-1 py-3 bg-[#0B4D34] hover:bg-[#064E3B] text-white text-xs font-bold rounded-xl shadow-xs transition disabled:opacity-50 cursor-pointer">
            {{ isSubmittingService ? 'Posting Charge...' : 'Add Charge to Folio' }}
          </button>
          <button 
            @click="activeServiceBooking = null"
            class="px-4 py-3 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl cursor-pointer">
            Cancel
          </button>
        </div>
      </div>
    </div>

    <!-- Balance Settlement Modal -->
    <div v-if="settleBooking" class="fixed inset-0 bg-black/60 backdrop-blur-xs z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-3xl overflow-hidden shadow-2xl max-w-md w-full p-6 space-y-4 border border-slate-100">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
          <div>
            <h3 class="text-base font-bold text-slate-900">Settle Outstanding Folio Balance</h3>
            <p class="text-xs text-slate-400">Guest: {{ settleBooking.guest_name }} • Room {{ settleBooking.room_number }}</p>
          </div>
          <button @click="settleBooking = null" class="text-slate-400 hover:text-slate-600 text-sm cursor-pointer">✕</button>
        </div>

        <div class="p-3 bg-rose-50 rounded-xl border border-rose-200 text-xs flex items-center justify-between">
          <span class="text-rose-900 font-medium">Pending Unsettled Amount:</span>
          <span class="text-rose-700 font-bold text-sm font-mono">ETB {{ settleBooking.outstanding_balance_etb?.toLocaleString() }}</span>
        </div>

        <div class="space-y-3 text-xs">
          <div>
            <label class="font-bold text-slate-700 block mb-1">Settlement Payment Method</label>
            <select v-model="settleForm.payment_method" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs outline-none">
              <option value="cash">Cash (Front Desk)</option>
              <option value="telebirr">Telebirr Instant Transfer</option>
              <option value="cbe_birr">CBE Birr</option>
              <option value="credit_card">Credit / Debit Card Terminal</option>
              <option value="bank_transfer">Direct Bank Transfer</option>
            </select>
          </div>

          <div>
            <label class="font-bold text-slate-700 block mb-1">Amount to Settle (ETB)</label>
            <input 
              v-model.number="settleForm.amount_etb" 
              type="number" 
              class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono font-bold outline-none" />
          </div>

          <div>
            <label class="font-bold text-slate-700 block mb-1">Receipt / Reference Note</label>
            <input 
              v-model="settleForm.reference_note" 
              type="text" 
              placeholder="e.g. Telebirr Txn #TB-881290 or Cash Receipt #CR-402" 
              class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs outline-none" />
          </div>
        </div>

        <div class="flex gap-2 pt-2">
          <button 
            @click="submitSettlement"
            :disabled="!settleForm.amount_etb || isSubmittingSettle"
            class="flex-1 py-3 bg-[#0B4D34] hover:bg-[#064E3B] text-white text-xs font-bold rounded-xl shadow-xs transition disabled:opacity-50 cursor-pointer">
            {{ isSubmittingSettle ? 'Recording Settlement...' : 'Record Payment & Clear Folio' }}
          </button>
          <button 
            @click="settleBooking = null"
            class="px-4 py-3 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl cursor-pointer">
            Cancel
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
import { ref, computed, onMounted } from 'vue';
import TaxInvoiceModal from '../components/TaxInvoiceModal.vue';

const eligibleDepartures = ref([]);
const loading = ref(false);
const searchQuery = ref('');
const checkingOutId = ref(null);
const showInvoiceModal = ref(false);
const activeInvoiceData = ref(null);

// Incidental service modal
const activeServiceBooking = ref(null);
const isSubmittingService = ref(false);
const serviceForm = ref({
  service_type: 'restaurant',
  description: '',
  quantity: 1,
  amount_etb: 0
});

// Settlement modal
const settleBooking = ref(null);
const isSubmittingSettle = ref(false);
const settleForm = ref({
  amount_etb: 0,
  payment_method: 'telebirr',
  reference_note: ''
});

const todayDateStr = new Date().toISOString().split('T')[0];

const departuresTodayCount = computed(() => {
  return eligibleDepartures.value.filter(b => b.check_out_date <= todayDateStr).length;
});

const unsettledCount = computed(() => {
  return eligibleDepartures.value.filter(b => (b.outstanding_balance_etb || 0) > 0).length;
});

const clearedCount = computed(() => {
  return eligibleDepartures.value.filter(b => (b.outstanding_balance_etb || 0) <= 0).length;
});

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

function openServiceChargeModal(b) {
  activeServiceBooking.value = b;
  serviceForm.value = {
    service_type: 'minibar',
    description: '',
    quantity: 1,
    amount_etb: 0
  };
}

async function submitServiceCharge() {
  if (!activeServiceBooking.value || !serviceForm.value.amount_etb) return;
  isSubmittingService.value = true;
  try {
    const res = await fetch(`/api/v1/bookings/${activeServiceBooking.value.id}/service-charges`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        ...getAuthHeaders()
      },
      body: JSON.stringify(serviceForm.value)
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.error || 'Failed to post service charge');

    alert(`✓ Incidental charge posted! Folio updated to ETB ${data.booking?.gross_total_etb?.toLocaleString()}`);
    activeServiceBooking.value = null;
    fetchDepartures();
  } catch (err) {
    alert(err.message);
  } finally {
    isSubmittingService.value = false;
  }
}

function openSettlementModal(b) {
  settleBooking.value = b;
  settleForm.value = {
    amount_etb: b.outstanding_balance_etb || 0,
    payment_method: 'telebirr',
    reference_note: `Departure Settlement - Ref ${b.reference_code}`
  };
}

async function submitSettlement() {
  if (!settleBooking.value || !settleForm.value.amount_etb) return;
  isSubmittingSettle.value = true;
  try {
    const res = await fetch(`/api/v1/bookings/${settleBooking.value.id}/settle-payment`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        ...getAuthHeaders()
      },
      body: JSON.stringify(settleForm.value)
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.error || 'Failed to record settlement');

    alert(`✓ Payment of ETB ${settleForm.value.amount_etb} recorded! Folio balance: ETB ${data.booking?.outstanding_balance_etb || 0}`);
    settleBooking.value = null;
    fetchDepartures();
  } catch (err) {
    alert(err.message);
  } finally {
    isSubmittingSettle.value = false;
  }
}

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

async function initiateCheckOut(b) {
  const balance = Number(b.outstanding_balance_etb || 0);
  if (balance > 0) {
    const settleNow = confirm(`⚠️ Guest ${b.guest_name} has an outstanding folio balance of ETB ${balance.toLocaleString()}.\n\nWould you like to settle the balance now and finalize check-out? Click 'OK' to settle and complete check-out.`);
    if (!settleNow) return;

    // Prompt for payment method
    const pMethod = prompt("Enter payment method ('telebirr', 'cbe_birr', 'cash', 'credit_card'):", "telebirr") || "telebirr";
    await executeCheckOutWithSettlement(b, balance, pMethod);
    return;
  }

  if (!confirm(`Complete check-out for guest ${b.guest_name} (Room ${b.room_number})?\n\nThis will release the room for housekeeping turnover.`)) {
    return;
  }

  await executeSimpleCheckOut(b);
}

async function executeCheckOutWithSettlement(b, balance, method) {
  checkingOutId.value = b.id;
  try {
    const res = await fetch(`/api/v1/bookings/${b.id}/check-out`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        ...getAuthHeaders()
      },
      body: JSON.stringify({
        settle_amount_etb: balance,
        payment_method: method
      })
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.error || 'Failed to complete check-out');

    alert(`✓ Check-Out Completed! ${data.message}\nRoom ${b.room_number} is now under housekeeping turnover (cleaning).`);
    if (data.invoice) {
      activeInvoiceData.value = data.invoice;
      showInvoiceModal.value = true;
    }
    fetchDepartures();
  } catch (err) {
    alert(err.message);
  } finally {
    checkingOutId.value = null;
  }
}

async function executeSimpleCheckOut(b) {
  checkingOutId.value = b.id;
  try {
    const res = await fetch(`/api/v1/bookings/${b.id}/check-out`, {
      method: 'POST',
      headers: getAuthHeaders()
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.error || 'Failed to complete check-out');

    alert(`✓ Check-Out Completed! ${data.message}\nRoom ${b.room_number} is released for housekeeping turnover.`);
    if (data.invoice) {
      activeInvoiceData.value = data.invoice;
      showInvoiceModal.value = true;
    }
    fetchDepartures();
  } catch (err) {
    alert(err.message);
  } finally {
    checkingOutId.value = null;
  }
}

onMounted(() => {
  fetchDepartures();
});
</script>
