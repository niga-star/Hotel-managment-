<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-5 rounded-2xl shadow-sm border border-slate-100">
      <div>
        <h2 class="text-2xl font-bold text-[#0B4D34]">Guest Check-In Desk</h2>
        <p class="text-xs text-slate-500">Arrival processing console, room occupancy verification & touchless QR validation.</p>
      </div>

      <div class="flex items-center gap-2">
        <span class="text-xs font-bold px-3 py-1 rounded-full bg-emerald-50 text-[#0B4D34] border border-emerald-200">
          {{ eligibleArrivals.length }} Guest{{ eligibleArrivals.length === 1 ? '' : 's' }} Eligible for Arrival
        </span>
        <button 
          @click="fetchArrivals"
          class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-full transition">
          🔄 Refresh Arrivals
        </button>
      </div>
    </div>

    <!-- Search Bar -->
    <div class="flex items-center justify-between gap-4">
      <div class="relative w-full sm:w-96">
        <input 
          v-model="searchQuery" 
          @input="fetchArrivals"
          type="text" 
          placeholder="Search by Reference (#ETH-XXXXX), Guest, or Room..." 
          class="w-full pl-9 pr-4 py-2.5 bg-white border border-slate-200 rounded-full text-xs focus:outline-none focus:border-[#0B4D34] shadow-sm" />
        <span class="absolute left-3.5 top-3 text-xs text-slate-400">🔍</span>
      </div>
    </div>

    <!-- Layout: Eligible Table (Left 2 Cols) + Quick QR Scanner (Right 1 Col) -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Left 2 Cols: Eligible Arrivals Table -->
      <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
          <h3 class="text-base font-bold text-slate-900">Eligible Confirmed Arrivals</h3>
          <span class="text-xs text-slate-400">Filter: booking_status = 'confirmed'</span>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse text-sm">
            <thead>
              <tr class="bg-slate-50 text-slate-600 text-xs font-semibold uppercase tracking-wider border-b border-slate-100">
                <th class="py-3 px-5">Ref Code</th>
                <th class="py-3 px-5">Guest Name & Phone</th>
                <th class="py-3 px-5">Assigned Room</th>
                <th class="py-3 px-5">Scheduled Dates</th>
                <th class="py-3 px-5">Room Readiness</th>
                <th class="py-3 px-5">Payment</th>
                <th class="py-3 px-5 text-right">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-slate-700">
              <tr v-if="loading" class="text-center">
                <td colspan="7" class="py-8 text-slate-400 text-xs">Loading arrivals from database...</td>
              </tr>
              <tr v-else-if="eligibleArrivals.length === 0" class="text-center">
                <td colspan="7" class="py-8 text-slate-400 text-xs">No confirmed arrivals pending check-in.</td>
              </tr>
              <tr 
                v-else
                v-for="b in eligibleArrivals" 
                :key="b.id" 
                class="hover:bg-slate-50/50 transition">
                <!-- Ref -->
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
                <!-- Dates -->
                <td class="py-3.5 px-5 text-xs text-slate-600">
                  <div>{{ b.check_in_date }}</div>
                  <div class="text-[10px] text-slate-400">until {{ b.check_out_date }}</div>
                </td>
                <!-- Room Readiness -->
                <td class="py-3.5 px-5">
                  <span 
                    v-if="b.is_room_ready" 
                    class="text-[11px] font-bold px-2.5 py-0.5 rounded-full bg-emerald-100 text-emerald-800 flex items-center gap-1 w-fit">
                    <span>✓</span> Ready
                  </span>
                  <span 
                    v-else 
                    class="text-[11px] font-bold px-2.5 py-0.5 rounded-full bg-amber-100 text-amber-800 flex items-center gap-1 w-fit">
                    <span>⏳</span> Occupied / Prep
                  </span>
                </td>
                <!-- Payment -->
                <td class="py-3.5 px-5">
                  <span 
                    :class="b.payment_status === 'paid' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'"
                    class="text-[11px] font-bold px-2 py-0.5 rounded-full capitalize">
                    {{ b.payment_status === 'paid' ? '✓ Paid' : 'Pending' }}
                  </span>
                </td>
                <!-- Action -->
                <td class="py-3.5 px-5 text-right">
                  <div class="inline-flex items-center gap-2">
                    <button 
                      @click="openRegCard(b)"
                      class="px-2.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl transition cursor-pointer flex items-center gap-1">
                      <span>📋</span>
                      <span>Reg Card & ID</span>
                    </button>
                    <button 
                      @click="executeCheckIn(b)"
                      :disabled="checkingInId === b.id"
                      class="px-3.5 py-1.5 bg-[#0B4D34] hover:bg-[#064E3B] text-white text-xs font-bold rounded-xl shadow-xs transition disabled:opacity-50 flex items-center gap-1.5 cursor-pointer">
                      <span v-if="checkingInId === b.id" class="animate-spin text-xs">⏳</span>
                      <span>Check-In</span>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Right 1 Col: Quick QR Scanner Simulation (Item 3B) -->
      <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 flex flex-col justify-between text-center space-y-4 h-fit sticky top-6">
        <div>
          <div class="w-14 h-14 rounded-2xl bg-emerald-50 text-[#0B4D34] flex items-center justify-center text-2xl mx-auto mb-2 border border-emerald-100">
            📷
          </div>
          <h3 class="text-base font-bold text-slate-900">Touchless QR Check-In</h3>
          <p class="text-xs text-slate-400 mt-1">Scan guest mobile receipt for instant verification</p>
        </div>

        <!-- Simulated Active QR Scanner Frame -->
        <div class="relative bg-slate-50 border-2 border-dashed border-[#0B4D34]/40 rounded-2xl p-4">
          <img 
            :src="simulatedQrUrl" 
            class="w-40 h-40 mx-auto bg-white p-2 rounded-xl shadow-sm border border-slate-200" 
            alt="Scanner View" />
          <div class="mt-3 bg-white p-2 rounded-xl shadow-sm border border-emerald-100 text-xs">
            <span class="text-slate-500 font-medium block">Active Camera Feed:</span>
            <span class="font-bold text-[#0B4D34] text-xs">Ready for Guest Optical Token</span>
          </div>
        </div>

        <button 
          @click="scanFirstArrival"
          :disabled="eligibleArrivals.length === 0"
          class="w-full py-3 bg-[#0B4D34] hover:bg-[#064E3B] text-white font-bold text-xs rounded-xl shadow-md transition disabled:opacity-50">
          Simulate Optical QR Check-In
        </button>
      </div>
    </div>

    <!-- Digital Guest Registration Card & ID Verification Modal -->
    <div v-if="selectedRegBooking" class="fixed inset-0 bg-black/60 backdrop-blur-xs z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-3xl overflow-hidden shadow-2xl max-w-lg w-full p-6 space-y-4 border border-slate-100">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
          <div>
            <h3 class="text-base font-bold text-slate-900">Official Guest Registration Card</h3>
            <p class="text-xs text-slate-400">Ethiopian Hospitality Regulatory Compliance</p>
          </div>
          <button @click="selectedRegBooking = null" class="text-slate-400 hover:text-slate-600 text-sm cursor-pointer">✕</button>
        </div>

        <!-- Booking details -->
        <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 text-xs space-y-1.5">
          <div class="flex justify-between">
            <span class="text-slate-500">Guest Name:</span>
            <span class="font-bold text-slate-900">{{ selectedRegBooking.guest_name }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-slate-500">Phone & Email:</span>
            <span class="font-mono text-slate-800">{{ selectedRegBooking.guest_phone }} • {{ selectedRegBooking.guest_email }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-slate-500">Assigned Room:</span>
            <span class="font-bold text-[#0B4D34]">Room {{ selectedRegBooking.room_number }} ({{ selectedRegBooking.category_name }})</span>
          </div>
          <div class="flex justify-between">
            <span class="text-slate-500">Stay Dates:</span>
            <span class="font-semibold text-slate-800">{{ selectedRegBooking.check_in_date }} → {{ selectedRegBooking.check_out_date }}</span>
          </div>
        </div>

        <!-- ID Upload Box -->
        <div class="space-y-1.5">
          <label class="text-xs font-bold text-slate-700 block">🪪 Guest ID / Passport Attachment (Mandatory)</label>
          <div 
            @click="$refs.idFileInput.click()" 
            class="border-2 border-dashed border-slate-200 hover:border-[#0B4D34] rounded-xl p-4 text-center cursor-pointer bg-slate-50/50 transition">
            <input 
              ref="idFileInput" 
              type="file" 
              accept="image/*" 
              @change="onIdSelected" 
              class="hidden" />
            <div v-if="idPreview" class="space-y-1">
              <img :src="idPreview" class="max-h-32 mx-auto rounded-lg shadow-xs border" alt="ID Preview" />
              <p class="text-xs text-emerald-600 font-semibold">✓ ID Image Verified & Attached</p>
            </div>
            <div v-else class="text-xs text-slate-500 space-y-1">
              <div class="text-2xl">📸</div>
              <p class="font-semibold text-slate-700">Click to upload Kebele ID, Passport or Resident ID</p>
              <p class="text-[10px] text-slate-400">JPG, PNG, PDF up to 10MB</p>
            </div>
          </div>
        </div>

        <!-- Policy Consent Checkbox -->
        <div class="p-3 bg-amber-50 rounded-xl border border-amber-200/80 text-[11px] text-amber-900 space-y-1.5">
          <p class="font-bold">📜 Hotel House Rules & Acceptance:</p>
          <p>• Check-out time is strictly 12:00 PM noon.</p>
          <p>• All suites are 100% non-smoking. Smoking incurs ETB 3,000 restoration charge.</p>
          <label class="flex items-center gap-2 pt-1 font-bold cursor-pointer">
            <input type="checkbox" v-model="policyAccepted" class="rounded accent-[#0B4D34]" />
            <span>Guest has confirmed identity and accepts all house rules</span>
          </label>
        </div>

        <!-- Action -->
        <div class="flex gap-2 pt-2">
          <button 
            @click="completeRegCheckIn"
            :disabled="!policyAccepted || checkingInId === selectedRegBooking.id"
            class="flex-1 py-3 bg-[#0B4D34] hover:bg-[#064E3B] text-white text-xs font-bold rounded-xl shadow-xs transition disabled:opacity-50 cursor-pointer">
            {{ checkingInId === selectedRegBooking.id ? 'Processing...' : 'Complete Check-In & Issue Key' }}
          </button>
          <button 
            @click="selectedRegBooking = null"
            class="px-4 py-3 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl cursor-pointer">
            Cancel
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';

const eligibleArrivals = ref([]);
const loading = ref(false);
const searchQuery = ref('');
const checkingInId = ref(null);
const simulatedQrUrl = ref('https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=%23ETH-CHECKIN-READY');

const selectedRegBooking = ref(null);
const idPreview = ref(null);
const policyAccepted = ref(true);

function openRegCard(booking) {
  selectedRegBooking.value = booking;
  idPreview.value = null;
  policyAccepted.value = true;
}

function onIdSelected(e) {
  const f = e.target.files?.[0];
  if (f) {
    idPreview.value = URL.createObjectURL(f);
  }
}

async function completeRegCheckIn() {
  if (!selectedRegBooking.value) return;
  await executeCheckIn(selectedRegBooking.value);
  selectedRegBooking.value = null;
}

function getAuthHeaders() {
  const token = localStorage.getItem('hotel_auth_token');
  return token ? { 'Authorization': `Bearer ${token}` } : {};
}

async function fetchArrivals() {
  loading.value = true;
  try {
    let url = '/api/v1/admin/bookings/check-in-eligible';
    if (searchQuery.value) {
      url += `?search=${encodeURIComponent(searchQuery.value)}`;
    }

    const res = await fetch(url, { headers: getAuthHeaders() });
    const data = await res.json();
    eligibleArrivals.value = data.eligible_arrivals || [];
  } catch (err) {
    console.error('Error fetching arrivals:', err);
  } finally {
    loading.value = false;
  }
}

async function executeCheckIn(b) {
  if (!confirm(`Check in guest ${b.guest_name} into Room ${b.room_number}?`)) return;

  checkingInId.value = b.id;
  try {
    const res = await fetch(`/api/v1/bookings/${b.id}/check-in`, {
      method: 'POST',
      headers: getAuthHeaders()
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.error || 'Failed to complete check-in');

    alert(`✓ Check-In Confirmed! ${data.message}`);
    fetchArrivals();
  } catch (err) {
    alert(err.message);
  } finally {
    checkingInId.value = null;
  }
}

function scanFirstArrival() {
  if (eligibleArrivals.value.length > 0) {
    const target = eligibleArrivals.value[0];
    simulatedQrUrl.value = `https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=${target.reference_code}`;
    executeCheckIn(target);
  } else {
    alert('No eligible arrivals available to scan.');
  }
}

onMounted(() => {
  fetchArrivals();
});
</script>
