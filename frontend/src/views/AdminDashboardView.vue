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

    <!-- Room Status Matrix Grid & Interactive Tape Chart Timeline (Screen 2) -->
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 space-y-4">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-100 pb-4">
        <div>
          <h3 class="text-lg font-bold text-slate-900">Room Operations & Availability</h3>
          <p class="text-xs text-slate-400">Manage floor inventory status or view the 14-day booking timeline schedule</p>
        </div>

        <div class="flex items-center gap-3">
          <!-- View Mode Toggle -->
          <div class="bg-slate-100 p-1 rounded-xl flex border border-slate-200 text-xs font-semibold">
            <button 
              @click="matrixViewMode = 'matrix'"
              :class="matrixViewMode === 'matrix' ? 'bg-white text-[#0B4D34] shadow-sm font-bold' : 'text-slate-600 hover:text-slate-900'"
              class="px-3 py-1.5 rounded-lg transition cursor-pointer">
              🟩 Floor Matrix
            </button>
            <button 
              @click="matrixViewMode = 'timeline'; loadDeskBookings()"
              :class="matrixViewMode === 'timeline' ? 'bg-[#0B4D34] text-white shadow-sm font-bold' : 'text-slate-600 hover:text-slate-900'"
              class="px-3 py-1.5 rounded-lg transition cursor-pointer">
              📅 14-Day Tape Chart
            </button>
          </div>

          <button 
            @click="$emit('go-manage-rooms')"
            class="px-3.5 py-1.5 bg-[#0B4D34] hover:bg-[#064E3B] text-white text-xs font-semibold rounded-xl shadow-sm flex items-center gap-1.5 transition cursor-pointer">
            <span>+</span> Manage Rooms
          </button>
        </div>
      </div>

      <!-- Mode A: Floor Matrix Grid -->
      <div v-if="matrixViewMode === 'matrix'">
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

      <!-- Mode B: Interactive 14-Day Tape Chart Timeline -->
      <div v-else class="space-y-3">
        <!-- Tape Chart Navigation Controls -->
        <div class="flex flex-wrap items-center justify-between gap-3 text-xs bg-slate-50 p-2.5 rounded-xl border border-slate-200">
          <div class="flex items-center gap-2">
            <button 
              @click="timelineOffsetDays -= 7" 
              class="px-2.5 py-1 bg-white border border-slate-200 rounded-lg hover:bg-slate-100 font-semibold cursor-pointer">
              ← Previous 7 Days
            </button>
            <button 
              @click="timelineOffsetDays = 0" 
              class="px-2.5 py-1 bg-[#0B4D34] text-white rounded-lg hover:bg-[#064E3B] font-bold cursor-pointer">
              Today
            </button>
            <button 
              @click="timelineOffsetDays += 7" 
              class="px-2.5 py-1 bg-white border border-slate-200 rounded-lg hover:bg-slate-100 font-semibold cursor-pointer">
              Next 7 Days →
            </button>
          </div>

          <div class="flex items-center gap-4 text-[11px] font-medium text-slate-600">
            <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded bg-emerald-600"></span> Confirmed</span>
            <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded bg-amber-500"></span> Checked-In</span>
            <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded bg-slate-200"></span> Vacant / Clean</span>
          </div>
        </div>

        <!-- Gantt Timeline Grid Table -->
        <div class="overflow-x-auto border border-slate-200 rounded-2xl shadow-xs">
          <table class="w-full text-center border-collapse min-w-[850px]">
            <thead>
              <tr class="bg-slate-100/90 text-slate-700 text-xs font-semibold border-b border-slate-200">
                <th class="py-2.5 px-3 text-left sticky left-0 bg-slate-100 z-10 w-28 border-r border-slate-200">Room</th>
                <th 
                  v-for="d in timelineDates" 
                  :key="d.dateStr"
                  :class="[
                    d.isToday ? 'bg-emerald-100/80 text-[#0B4D34] font-bold' : (d.isWeekend ? 'bg-slate-50 text-slate-800' : 'text-slate-600'),
                    'py-2 px-1 text-[11px] min-w-16 border-r border-slate-200/70'
                  ]">
                  <span class="block text-[10px] uppercase font-mono">{{ d.dayName }}</span>
                  <span class="text-xs font-bold">{{ d.dayNumber }} {{ d.monthName }}</span>
                </th>
              </tr>
            </thead>
            <tbody>
              <tr 
                v-for="room in roomsMatrix" 
                :key="room.id"
                class="border-b border-slate-100 hover:bg-slate-50/50 transition">
                <!-- Room info column -->
                <td class="py-2.5 px-3 text-left sticky left-0 bg-white font-bold text-xs text-slate-900 border-r border-slate-200 shadow-xs z-10">
                  <div class="flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full" :class="room.status === 'available' ? 'bg-emerald-500' : (room.status === 'occupied' ? 'bg-amber-500' : 'bg-slate-400')"></span>
                    <span>Room {{ room.room_number }}</span>
                  </div>
                  <span class="text-[10px] font-normal text-slate-400 block">{{ room.category_name || 'Suite' }}</span>
                </td>

                <!-- 14-Day Cells -->
                <td 
                  v-for="d in timelineDates" 
                  :key="d.dateStr"
                  class="p-1 border-r border-slate-100 text-[10px] h-12 relative align-middle">
                  <!-- Active Reservation Block -->
                  <div 
                    v-if="getBookingForRoomAndDate(room.id, d.dateStr)"
                    @click="selectedTapeBooking = getBookingForRoomAndDate(room.id, d.dateStr)"
                    :class="[
                      getBookingForRoomAndDate(room.id, d.dateStr).booking_status === 'checked_in' 
                        ? 'bg-amber-500/90 text-white shadow-xs' 
                        : 'bg-[#0B4D34] text-white shadow-xs'
                    ]"
                    class="w-full h-full rounded-md p-1 flex flex-col justify-center text-left cursor-pointer hover:brightness-110 transition">
                    <span class="font-bold truncate text-[10px] leading-tight">
                      {{ getBookingForRoomAndDate(room.id, d.dateStr).guest_name?.split(' ')[0] }}
                    </span>
                    <span class="text-[8px] opacity-80 font-mono truncate leading-tight">
                      {{ getBookingForRoomAndDate(room.id, d.dateStr).reference_code }}
                    </span>
                  </div>
                  <!-- Vacant Day Slot -->
                  <div v-else class="w-full h-full flex items-center justify-center text-slate-200 hover:text-emerald-600 hover:bg-emerald-50/50 rounded transition text-xs">
                    ·
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Quick Tape Chart Reservation Popover Modal -->
    <div v-if="selectedTapeBooking" class="fixed inset-0 bg-black/50 backdrop-blur-xs z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-2xl max-w-sm w-full p-5 space-y-3 shadow-2xl border border-slate-100">
        <div class="flex items-center justify-between border-b border-slate-100 pb-2">
          <div>
            <h4 class="font-bold text-slate-900 text-sm">{{ selectedTapeBooking.room_title }} (Room {{ selectedTapeBooking.room_number }})</h4>
            <p class="text-[11px] text-[#0B4D34] font-mono font-bold">{{ selectedTapeBooking.reference_code }}</p>
          </div>
          <button @click="selectedTapeBooking = null" class="text-slate-400 hover:text-slate-600 text-sm">✕</button>
        </div>

        <div class="space-y-2 text-xs">
          <div class="flex justify-between text-slate-600">
            <span>Guest Name:</span>
            <span class="font-bold text-slate-900">{{ selectedTapeBooking.guest_name }}</span>
          </div>
          <div class="flex justify-between text-slate-600">
            <span>Phone:</span>
            <span class="font-bold text-slate-900">{{ selectedTapeBooking.guest_phone || 'N/A' }}</span>
          </div>
          <div class="flex justify-between text-slate-600">
            <span>Dates:</span>
            <span class="font-bold text-slate-900">{{ selectedTapeBooking.check_in_date }} → {{ selectedTapeBooking.check_out_date }}</span>
          </div>
          <div class="flex justify-between text-slate-600">
            <span>Status:</span>
            <span :class="getStatusBadgeClass(selectedTapeBooking.booking_status)" class="px-2 py-0.5 rounded-full font-bold text-[10px] capitalize">
              {{ selectedTapeBooking.booking_status }}
            </span>
          </div>
          <div class="flex justify-between text-slate-600">
            <span>Total Price:</span>
            <span class="font-bold text-[#0B4D34]">ETB {{ Number(selectedTapeBooking.total_price_etb).toLocaleString() }}</span>
          </div>
        </div>

        <button 
          @click="selectedTapeBooking = null" 
          class="w-full py-2 bg-[#0B4D34] hover:bg-[#064E3B] text-white font-bold text-xs rounded-xl shadow-xs transition cursor-pointer">
          Close Window
        </button>
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

const matrixViewMode = ref('matrix'); // 'matrix' | 'timeline'
const timelineOffsetDays = ref(0);
const allBookings = ref([]);
const selectedTapeBooking = ref(null);

const timelineDates = computed(() => {
  const dates = [];
  const base = new Date();
  base.setDate(base.getDate() + timelineOffsetDays.value);
  for (let i = 0; i < 14; i++) {
    const d = new Date(base);
    d.setDate(d.getDate() + i);
    dates.push({
      dateStr: d.toISOString().split('T')[0],
      dayName: d.toLocaleDateString('en-US', { weekday: 'short' }),
      dayNumber: d.getDate(),
      monthName: d.toLocaleDateString('en-US', { month: 'short' }),
      isToday: d.toDateString() === (new Date()).toDateString(),
      isWeekend: d.getDay() === 0 || d.getDay() === 6
    });
  }
  return dates;
});

function getBookingForRoomAndDate(roomId, dateStr) {
  return allBookings.value.find(b => {
    if (b.room_id !== roomId) return false;
    if (['cancelled', 'rejected'].includes(b.booking_status)) return false;
    return dateStr >= b.check_in_date && dateStr < b.check_out_date;
  });
}

async function loadDeskBookings() {
  try {
    const res = await fetch('/api/v1/admin/bookings/desk', {
      headers: getAuthHeaders()
    });
    if (res.ok) {
      const data = await res.json();
      allBookings.value = data.bookings || [];
    }
  } catch (err) {
    console.error('Failed to load tape chart bookings:', err);
  }
}

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
  loadDeskBookings();
});
</script>
