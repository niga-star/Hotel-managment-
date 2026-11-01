<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-5 rounded-2xl shadow-sm border border-slate-100">
      <div>
        <h2 class="text-2xl font-bold text-[#0B4D34]">Bookings Management Desk</h2>
        <p class="text-xs text-slate-500">Centralized reservation ledger, payment reconciliation & status controls.</p>
      </div>

      <div class="flex items-center gap-2">
        <button 
          @click="fetchBookings"
          class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-full transition flex items-center gap-1.5">
          <span>🔄</span> Refresh Desk
        </button>
      </div>
    </div>

    <!-- Status Tabs & Filter Pills (Item 3A) -->
    <div class="flex flex-col xl:flex-row xl:items-center xl:justify-between gap-4">
      <div class="flex items-center space-x-1.5 overflow-x-auto pb-1 xl:pb-0">
        <button 
          v-for="tab in statusTabs"
          :key="tab.id"
          @click="currentStatus = tab.id; fetchBookings()"
          :class="currentStatus === tab.id ? 'bg-[#0B4D34] text-white shadow-sm font-bold' : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-50'"
          class="px-3.5 py-2 rounded-full text-xs transition flex-shrink-0 flex items-center gap-1.5">
          <span>{{ tab.label }}</span>
          <span 
            :class="currentStatus === tab.id ? 'bg-yellow-400 text-slate-900' : 'bg-slate-100 text-slate-600'"
            class="text-[10px] px-1.5 py-0.2 rounded-full font-bold">
            {{ stats[tab.id] !== undefined ? stats[tab.id] : 0 }}
          </span>
        </button>
      </div>

      <!-- Search Input -->
      <div class="relative w-full xl:w-80">
        <input 
          v-model="searchQuery" 
          @input="fetchBookings"
          type="text" 
          placeholder="Search Reference, Guest, Room #..." 
          class="w-full pl-9 pr-4 py-2.5 bg-white border border-slate-200 rounded-full text-xs focus:outline-none focus:border-[#0B4D34] shadow-sm" />
        <span class="absolute left-3.5 top-3 text-xs text-slate-400">🔍</span>
      </div>
    </div>

    <!-- Bookings Table -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
      <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
        <h3 class="text-base font-bold text-slate-900">Reservations ({{ reservations.length }})</h3>
        <span class="text-xs text-slate-400">All data loaded from MySQL</span>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-sm">
          <thead>
            <tr class="bg-slate-50 text-slate-600 text-xs font-semibold uppercase tracking-wider border-b border-slate-100">
              <th class="py-3 px-5">Ref Code</th>
              <th class="py-3 px-5">Guest Name & Contact</th>
              <th class="py-3 px-5">Room</th>
              <th class="py-3 px-5">Dates (Check-In → Out)</th>
              <th class="py-3 px-5">Total Amount</th>
              <th class="py-3 px-5">Outstanding</th>
              <th class="py-3 px-5">Payment Status</th>
              <th class="py-3 px-5">Booking Status</th>
              <th class="py-3 px-5 text-right">Desk Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 text-slate-700">
            <tr v-if="loading" class="text-center py-12">
              <td colspan="9" class="py-8 text-slate-400 text-xs">Loading reservations from database...</td>
            </tr>
            <tr v-else-if="reservations.length === 0" class="text-center py-12">
              <td colspan="9" class="py-8 text-slate-400 text-xs">No reservations found for the selected filter.</td>
            </tr>
            <tr 
              v-else
              v-for="b in reservations" 
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
                <div class="text-[10px] text-slate-400">{{ b.guest_email }}</div>
              </td>
              <!-- Room -->
              <td class="py-3.5 px-5">
                <div class="font-bold text-slate-800 text-xs">Room {{ b.room_number }}</div>
                <div class="text-[10px] text-slate-400">{{ b.category_name }}</div>
              </td>
              <!-- Dates -->
              <td class="py-3.5 px-5 text-xs text-slate-600">
                <div>{{ b.check_in_date }}</div>
                <div class="text-[10px] text-slate-400">to {{ b.check_out_date }}</div>
              </td>
              <!-- Total -->
              <td class="py-3.5 px-5 font-bold text-slate-900 text-xs">
                ETB {{ b.total_price_etb?.toLocaleString() }}
              </td>
              <!-- Outstanding -->
              <td class="py-3.5 px-5 text-xs">
                <span v-if="b.outstanding_balance_etb > 0" class="text-rose-600 font-bold">
                  ETB {{ b.outstanding_balance_etb?.toLocaleString() }}
                </span>
                <span v-else class="text-emerald-600 font-semibold">
                  Settled (0 ETB)
                </span>
              </td>
              <!-- Payment Status -->
              <td class="py-3.5 px-5">
                <span 
                  :class="getPaymentBadgeClass(b.payment_status_label || b.payment_status)"
                  class="text-[11px] font-bold px-2.5 py-0.5 rounded-full capitalize">
                  {{ b.payment_status_label || b.payment_status }}
                </span>
              </td>
              <!-- Booking Status -->
              <td class="py-3.5 px-5">
                <span 
                  :class="getBookingBadgeClass(b.booking_status)"
                  class="text-[11px] font-bold px-2.5 py-0.5 rounded-full capitalize">
                  {{ b.booking_status?.replace('_', ' ') }}
                </span>
              </td>
              <!-- Desk Actions -->
              <td class="py-3.5 px-5 text-right space-x-1.5 whitespace-nowrap">
                <!-- Approve Pending -->
                <button 
                  v-if="b.booking_status === 'pending_verification'"
                  @click="updateStatus(b, 'confirmed')"
                  class="px-2.5 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-[11px] font-bold rounded-lg shadow-sm transition">
                  ✓ Approve
                </button>
                <!-- Reject Pending -->
                <button 
                  v-if="b.booking_status === 'pending_verification'"
                  @click="promptReject(b)"
                  class="px-2.5 py-1.5 bg-rose-600 hover:bg-rose-700 text-white text-[11px] font-bold rounded-lg shadow-sm transition">
                  ✕ Reject
                </button>
                <!-- Cancel Active -->
                <button 
                  v-if="['confirmed', 'pending_payment'].includes(b.booking_status)"
                  @click="updateStatus(b, 'cancelled')"
                  class="px-2.5 py-1.5 bg-slate-200 hover:bg-slate-300 text-slate-700 text-[11px] font-semibold rounded-lg transition">
                  Cancel
                </button>
                <!-- View Details -->
                <button 
                  @click="viewBooking(b)"
                  class="px-2.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-[11px] font-semibold rounded-lg transition"
                  title="View digital details">
                  👁️ Details
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Booking Details Modal -->
    <div v-if="activeModalBooking" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-3xl overflow-hidden shadow-2xl max-w-lg w-full p-6 space-y-4 border border-slate-100">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
          <div>
            <h3 class="text-lg font-bold text-slate-900">Booking Details</h3>
            <p class="text-xs text-slate-400 font-mono">{{ activeModalBooking.reference_code }}</p>
          </div>
          <button @click="activeModalBooking = null" class="text-slate-400 hover:text-slate-600">✕</button>
        </div>

        <div class="space-y-3 text-xs">
          <div class="p-3 bg-slate-50 rounded-xl space-y-1.5">
            <div class="flex justify-between">
              <span class="text-slate-500">Guest:</span>
              <span class="font-bold text-slate-800">{{ activeModalBooking.guest_name }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-slate-500">Contact:</span>
              <span class="font-bold text-slate-800">{{ activeModalBooking.guest_phone }} ({{ activeModalBooking.guest_email }})</span>
            </div>
            <div class="flex justify-between">
              <span class="text-slate-500">Assigned Room:</span>
              <span class="font-bold text-slate-800">Room {{ activeModalBooking.room_number }} ({{ activeModalBooking.room_title }})</span>
            </div>
            <div class="flex justify-between">
              <span class="text-slate-500">Stay Dates:</span>
              <span class="font-bold text-slate-800">{{ activeModalBooking.check_in_date }} to {{ activeModalBooking.check_out_date }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-slate-500">Payment Channel:</span>
              <span class="font-bold text-slate-800 capitalize">{{ activeModalBooking.payment_method }}</span>
            </div>
            <div class="flex justify-between text-sm pt-2 border-t border-slate-200">
              <span class="font-bold text-slate-800">Total Price:</span>
              <span class="font-bold text-[#0B4D34]">ETB {{ activeModalBooking.total_price_etb?.toLocaleString() }}</span>
            </div>
          </div>

          <div v-if="activeModalBooking.receipt_url" class="space-y-1 text-center">
            <span class="text-[11px] font-bold text-slate-600 block text-left">Uploaded Payment Evidence:</span>
            <img :src="activeModalBooking.receipt_url" class="max-h-56 mx-auto rounded-xl border shadow-sm" alt="Payment Proof" />
          </div>
        </div>

        <button 
          @click="activeModalBooking = null"
          class="w-full py-2.5 bg-[#0B4D34] text-white text-xs font-bold rounded-xl transition">
          Close Window
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';

const currentStatus = ref('all');
const searchQuery = ref('');
const reservations = ref([]);
const loading = ref(false);
const activeModalBooking = ref(null);

const stats = ref({
  total: 0,
  pending_verification: 0,
  confirmed: 0,
  checked_in: 0,
  checked_out: 0,
  cancelled: 0,
  rejected: 0
});

const statusTabs = [
  { id: 'all', label: 'All Bookings' },
  { id: 'pending_verification', label: 'Pending Review' },
  { id: 'confirmed', label: 'Confirmed' },
  { id: 'checked_in', label: 'Checked-In' },
  { id: 'checked_out', label: 'Checked-Out' },
  { id: 'cancelled', label: 'Cancelled' },
  { id: 'rejected', label: 'Rejected' }
];

function getAuthHeaders() {
  const token = localStorage.getItem('hotel_auth_token');
  return token ? { 'Authorization': `Bearer ${token}` } : {};
}

function getPaymentBadgeClass(status) {
  if (status === 'verified' || status === 'paid') return 'bg-emerald-100 text-emerald-800';
  if (status === 'pending_verification' || status === 'pending') return 'bg-amber-100 text-amber-800';
  return 'bg-rose-100 text-rose-800';
}

function getBookingBadgeClass(status) {
  if (status === 'confirmed') return 'bg-emerald-100 text-emerald-800';
  if (status === 'checked_in') return 'bg-amber-100 text-amber-800';
  if (status === 'checked_out') return 'bg-slate-100 text-slate-700';
  if (status === 'pending_verification') return 'bg-blue-100 text-blue-800';
  return 'bg-rose-100 text-rose-800';
}

async function fetchBookings() {
  loading.value = true;
  try {
    let url = `/api/v1/admin/bookings/desk?status=${currentStatus.value}`;
    if (searchQuery.value) {
      url += `&search=${encodeURIComponent(searchQuery.value)}`;
    }

    const res = await fetch(url, {
      headers: getAuthHeaders()
    });
    const data = await res.json();
    reservations.value = data.reservations || [];
    stats.value = data.stats || stats.value;
  } catch (err) {
    console.error('Error loading desk bookings:', err);
  } finally {
    loading.value = false;
  }
}

async function updateStatus(b, newStatus, reason = '') {
  if (!confirm(`Are you sure you want to change status of ${b.reference_code} to ${newStatus}?`)) return;

  try {
    const res = await fetch(`/api/v1/admin/bookings/${b.id}/status`, {
      method: 'PATCH',
      headers: {
        'Content-Type': 'application/json',
        ...getAuthHeaders()
      },
      body: JSON.stringify({ status: newStatus, rejection_reason: reason })
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.error || 'Failed to update status');

    alert(`✓ Booking ${b.reference_code} updated to ${newStatus}.`);
    fetchBookings();
  } catch (err) {
    alert(err.message);
  }
}

function promptReject(b) {
  const reason = prompt('Please enter the reason for rejecting this booking:');
  if (reason && reason.trim()) {
    updateStatus(b, 'rejected', reason.trim());
  }
}

function viewBooking(b) {
  activeModalBooking.value = b;
}

onMounted(() => {
  fetchBookings();
});
</script>
