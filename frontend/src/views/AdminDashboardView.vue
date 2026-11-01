<template>
  <div class="space-y-6">
    <!-- Top 4 Primary KPI Metrics Row (Screen 2) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
      <!-- Card 1: Room Occupancy Rate -->
      <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 flex items-center justify-between">
        <div>
          <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Room Occupancy</span>
          <div class="text-3xl font-bold text-slate-900 mt-1">{{ kpis.occupancy_rate }}%</div>
          <span class="text-xs text-emerald-600 font-medium">
            {{ kpis.occupied_rooms }} of {{ kpis.total_rooms }} Rooms In Use
          </span>
        </div>
        <!-- Radial Gauge -->
        <div class="relative w-14 h-14 flex items-center justify-center">
          <svg class="w-full h-full transform -rotate-90" viewBox="0 0 36 36">
            <path class="text-slate-100" stroke-width="3.5" stroke="currentColor" fill="none" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
            <path stroke="#0B4D34" stroke-width="3.8" :stroke-dasharray="`${kpis.occupancy_rate}, 100`" stroke-linecap="round" fill="none" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
          </svg>
          <span class="absolute text-[11px] font-bold text-[#0B4D34]">{{ kpis.occupancy_rate }}%</span>
        </div>
      </div>

      <!-- Card 2: Active Stays & Queue -->
      <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 flex items-center justify-between">
        <div>
          <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Active Bookings</span>
          <div class="text-3xl font-bold text-slate-900 mt-1">{{ kpis.active_bookings }}</div>
          <span class="text-xs text-emerald-600 font-medium">
            {{ kpis.confirmed_bookings }} Confirmed • {{ kpis.checked_in_guests }} Checked-In
          </span>
        </div>
        <div class="w-12 h-12 rounded-xl bg-emerald-50 text-[#0B4D34] flex items-center justify-center text-xl font-bold">
          📅
        </div>
      </div>

      <!-- Card 3: Pending Reviews & Check-Ins -->
      <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 flex items-center justify-between">
        <div>
          <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Pending Reviews</span>
          <div class="text-3xl font-bold text-slate-900 mt-1">{{ kpis.pending_payment_verifications }}</div>
          <span class="text-xs text-amber-600 font-medium">
            {{ kpis.pending_bookings }} pending verification
          </span>
        </div>
        <div class="w-12 h-12 rounded-xl bg-amber-50 text-[#D4A329] flex items-center justify-center text-xl">
          ⏳
        </div>
      </div>

      <!-- Card 4: Total Verified Revenue -->
      <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 flex items-center justify-between">
        <div>
          <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Verified Revenue</span>
          <div class="text-2xl font-bold text-slate-900 mt-1">ETB {{ kpis.monthly_revenue_etb?.toLocaleString() }}</div>
          <span class="text-xs text-emerald-600 font-medium">
            {{ kpis.verified_payments_count }} cleared payments
          </span>
        </div>
        <div class="w-12 h-12 rounded-xl bg-emerald-50 text-[#0B4D34] flex items-center justify-center text-xl font-bold">
          💰
        </div>
      </div>
    </div>

    <!-- Secondary Operations Breakdown Strip (Item 4) -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
      <div class="bg-white p-3.5 rounded-xl border border-slate-100 text-center">
        <span class="text-[10px] uppercase font-bold text-slate-400 block">Total Rooms</span>
        <span class="text-lg font-bold text-slate-900">{{ kpis.total_rooms }}</span>
      </div>
      <div class="bg-white p-3.5 rounded-xl border border-slate-100 text-center">
        <span class="text-[10px] uppercase font-bold text-emerald-600 block">Available</span>
        <span class="text-lg font-bold text-emerald-700">{{ kpis.available_rooms }}</span>
      </div>
      <div class="bg-white p-3.5 rounded-xl border border-slate-100 text-center">
        <span class="text-[10px] uppercase font-bold text-amber-600 block">Occupied</span>
        <span class="text-lg font-bold text-amber-700">{{ kpis.occupied_rooms }}</span>
      </div>
      <div class="bg-white p-3.5 rounded-xl border border-slate-100 text-center">
        <span class="text-[10px] uppercase font-bold text-slate-500 block">Maintenance</span>
        <span class="text-lg font-bold text-slate-600">{{ kpis.maintenance_rooms }}</span>
      </div>
      <div class="bg-white p-3.5 rounded-xl border border-slate-100 text-center">
        <span class="text-[10px] uppercase font-bold text-blue-600 block">Customers</span>
        <span class="text-lg font-bold text-blue-700">{{ kpis.total_customers }}</span>
      </div>
      <div class="bg-white p-3.5 rounded-xl border border-slate-100 text-center">
        <span class="text-[10px] uppercase font-bold text-rose-500 block">Unsettled Bal.</span>
        <span class="text-sm font-bold text-rose-600 truncate block">ETB {{ kpis.outstanding_balances_etb?.toLocaleString() }}</span>
      </div>
    </div>

    <!-- Room Status Matrix Grid (Screen 2) -->
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
      <div class="flex items-center justify-between mb-5">
        <div>
          <h3 class="text-lg font-bold text-slate-900">Room Status Floor Matrix</h3>
          <p class="text-xs text-slate-400">Click any room pill to toggle operational state (Available ↔ Occupied ↔ Maintenance)</p>
        </div>
        <button 
          @click="$emit('go-manage-rooms')"
          class="px-4 py-2 bg-[#0B4D34] hover:bg-[#064E3B] text-white text-xs font-semibold rounded-full shadow-sm flex items-center gap-1.5 transition">
          <span>+</span> Add / Manage Rooms
        </button>
      </div>

      <!-- Interactive Room Status Pills -->
      <div class="grid grid-cols-4 sm:grid-cols-6 md:grid-cols-8 gap-3 mb-4">
        <button
          v-for="room in roomsMatrix"
          :key="room.id"
          @click="toggleRoomStatus(room)"
          :class="getRoomStatusClass(room.status)"
          class="py-3 px-2 rounded-xl text-center font-bold text-sm shadow-sm transition-transform hover:scale-105 relative group cursor-pointer">
          <span class="block">Room {{ room.room_number }}</span>
          <span class="text-[10px] uppercase font-normal tracking-wider opacity-85 block truncate">
            {{ room.status }}
          </span>
        </button>
      </div>

      <!-- Legend -->
      <div class="flex items-center justify-end space-x-6 text-xs font-medium text-slate-600 pt-3 border-t border-slate-100">
        <div class="flex items-center gap-1.5">
          <span class="w-3 h-3 rounded-md bg-[#0B4D34]"></span>
          <span>Available</span>
        </div>
        <div class="flex items-center gap-1.5">
          <span class="w-3 h-3 rounded-md bg-[#D4A329]"></span>
          <span>Occupied</span>
        </div>
        <div class="flex items-center gap-1.5">
          <span class="w-3 h-3 rounded-md bg-[#64748B]"></span>
          <span>Maintenance</span>
        </div>
      </div>
    </div>

    <!-- Recent Reservations Live Table (Screen 2) -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
      <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
        <div>
          <h3 class="text-base font-bold text-slate-900">Recent Guest Reservations</h3>
          <p class="text-xs text-slate-400">Live property management activity stream</p>
        </div>
        <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-800 border border-emerald-200">
          Live Database Sync
        </span>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-sm">
          <thead>
            <tr class="bg-slate-50 text-slate-600 text-xs font-semibold uppercase tracking-wider border-b border-slate-100">
              <th class="py-3 px-5">Ref Code</th>
              <th class="py-3 px-5">Guest Name</th>
              <th class="py-3 px-5">Room</th>
              <th class="py-3 px-5">Dates</th>
              <th class="py-3 px-5">Total</th>
              <th class="py-3 px-5">Status</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 text-slate-700">
            <tr v-if="reservations.length === 0" class="text-center py-6">
              <td colspan="6" class="py-6 text-slate-400 text-xs">No reservations registered yet.</td>
            </tr>
            <tr 
              v-for="res in reservations" 
              :key="res.id"
              class="hover:bg-slate-50/50 transition">
              <td class="py-3 px-5 font-mono font-bold text-slate-900 text-xs">{{ res.reference_code }}</td>
              <td class="py-3 px-5 font-semibold text-slate-900 text-xs">{{ res.guest_name }}</td>
              <td class="py-3 px-5 text-xs text-slate-800">Room {{ res.room_number }}</td>
              <td class="py-3 px-5 text-xs text-slate-600">{{ res.check_in_date }} → {{ res.check_out_date }}</td>
              <td class="py-3 px-5 font-bold text-slate-900 text-xs">ETB {{ res.total_price_etb?.toLocaleString() }}</td>
              <td class="py-3 px-5">
                <span :class="getStatusBadgeClass(res.booking_status)" class="text-[11px] font-bold px-2.5 py-0.5 rounded-full capitalize">
                  {{ res.booking_status?.replace('_', ' ') }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';

defineEmits(['go-manage-rooms']);

const kpis = ref({
  total_rooms: 0,
  available_rooms: 0,
  occupied_rooms: 0,
  maintenance_rooms: 0,
  occupancy_rate: 0,
  total_bookings: 0,
  active_bookings: 0,
  pending_bookings: 0,
  confirmed_bookings: 0,
  checked_in_guests: 0,
  checked_out_bookings: 0,
  cancelled_bookings: 0,
  check_ins_today: 0,
  total_customers: 0,
  monthly_revenue_etb: 0,
  verified_payments_count: 0,
  pending_payment_verifications: 0,
  rejected_payments_count: 0,
  outstanding_balances_etb: 0
});

const roomsMatrix = ref([]);
const reservations = ref([]);

function getAuthHeaders() {
  const token = localStorage.getItem('hotel_auth_token');
  return token ? { 'Authorization': `Bearer ${token}` } : {};
}

function getRoomStatusClass(status) {
  if (status === 'available') return 'bg-[#0B4D34] text-white hover:bg-[#064E3B]';
  if (status === 'occupied') return 'bg-[#D4A329] text-slate-900 hover:bg-[#B8861B]';
  return 'bg-[#64748B] text-white hover:bg-[#475569]';
}

function getStatusBadgeClass(status) {
  if (status === 'confirmed') return 'bg-emerald-100 text-emerald-800';
  if (status === 'checked_in') return 'bg-amber-100 text-amber-800';
  if (status === 'checked_out') return 'bg-slate-100 text-slate-700';
  if (status === 'pending_verification') return 'bg-blue-100 text-blue-800';
  return 'bg-rose-100 text-rose-800';
}

async function fetchDashboard() {
  try {
    const res = await fetch('/api/v1/admin/dashboard-stats', {
      headers: getAuthHeaders()
    });
    if (res.ok) {
      const data = await res.json();
      kpis.value = data.kpis || kpis.value;
      roomsMatrix.value = data.rooms_matrix || [];
      reservations.value = data.recent_reservations || [];
    }
  } catch (error) {
    console.error('Failed to load dashboard:', error);
  }
}

async function toggleRoomStatus(room) {
  const nextStatus = room.status === 'available' ? 'occupied' : (room.status === 'occupied' ? 'maintenance' : 'available');
  try {
    const res = await fetch(`/api/v1/rooms/${room.id}/status`, {
      method: 'PATCH',
      headers: {
        'Content-Type': 'application/json',
        ...getAuthHeaders()
      },
      body: JSON.stringify({ status: nextStatus })
    });
    if (res.ok) {
      room.status = nextStatus;
      fetchDashboard();
    }
  } catch (err) {
    console.error('Failed to toggle status:', err);
  }
}

onMounted(() => {
  fetchDashboard();
});
</script>
