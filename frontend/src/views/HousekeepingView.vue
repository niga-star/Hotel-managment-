<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
      <div>
        <h2 class="text-xl sm:text-2xl font-bold text-slate-900 flex items-center gap-2">
          <span>🧹</span>
          <span>Housekeeping & Room Turnover Management</span>
        </h2>
        <p class="text-xs text-slate-500 mt-0.5">
          Real-time room cleanliness states and front-desk check-in readiness interlock
        </p>
      </div>

      <button 
        @click="fetchHousekeeping" 
        :disabled="loading"
        class="px-4 py-2 bg-[#0B4D34] hover:bg-[#064E3B] text-white text-xs font-semibold rounded-xl shadow-xs transition flex items-center gap-1.5 cursor-pointer disabled:opacity-50">
        <span>🔄</span>
        <span>Refresh Board</span>
      </button>
    </div>

    <!-- Summary KPI Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
      <div class="bg-white p-4 rounded-2xl border border-slate-100 shadow-xs flex items-center justify-between">
        <div>
          <span class="text-[11px] font-bold uppercase tracking-wider text-emerald-600">Inspected / Ready</span>
          <div class="text-2xl font-bold text-slate-900 mt-0.5">{{ countByStatus('inspected') }}</div>
          <span class="text-[10px] text-slate-400">100% Guest Ready</span>
        </div>
        <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center text-lg font-bold">
          ✓
        </div>
      </div>

      <div class="bg-white p-4 rounded-2xl border border-slate-100 shadow-xs flex items-center justify-between">
        <div>
          <span class="text-[11px] font-bold uppercase tracking-wider text-blue-600">Clean (Uninspected)</span>
          <div class="text-2xl font-bold text-slate-900 mt-0.5">{{ countByStatus('clean') }}</div>
          <span class="text-[10px] text-slate-400">Awaiting Supervisor</span>
        </div>
        <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-700 flex items-center justify-center text-lg font-bold">
          🧼
        </div>
      </div>

      <div class="bg-white p-4 rounded-2xl border border-slate-100 shadow-xs flex items-center justify-between">
        <div>
          <span class="text-[11px] font-bold uppercase tracking-wider text-rose-600">Dirty / Turnover</span>
          <div class="text-2xl font-bold text-slate-900 mt-0.5">{{ countByStatus('dirty') }}</div>
          <span class="text-[10px] text-slate-400">Needs Cleaning</span>
        </div>
        <div class="w-10 h-10 rounded-xl bg-rose-50 text-rose-700 flex items-center justify-center text-lg font-bold">
          🧹
        </div>
      </div>

      <div class="bg-white p-4 rounded-2xl border border-slate-100 shadow-xs flex items-center justify-between">
        <div>
          <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Out of Order</span>
          <div class="text-2xl font-bold text-slate-900 mt-0.5">{{ countByStatus('maintenance') }}</div>
          <span class="text-[10px] text-slate-400">Maintenance</span>
        </div>
        <div class="w-10 h-10 rounded-xl bg-slate-100 text-slate-600 flex items-center justify-center text-lg font-bold">
          🔧
        </div>
      </div>
    </div>

    <!-- Filter Buttons & Search -->
    <div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-100 flex flex-wrap items-center justify-between gap-3 text-xs">
      <div class="flex items-center space-x-2">
        <button 
          v-for="st in ['all', 'dirty', 'clean', 'inspected', 'maintenance']"
          :key="st"
          @click="filterStatus = st"
          :class="filterStatus === st ? 'bg-[#0B4D34] text-white font-bold' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
          class="px-3.5 py-1.5 rounded-full capitalize transition cursor-pointer">
          {{ st === 'all' ? 'All Rooms (' + rooms.length + ')' : st }}
        </button>
      </div>

      <div class="text-slate-400 text-xs">
        Showing <strong>{{ filteredRooms.length }}</strong> rooms
      </div>
    </div>

    <!-- Housekeeping Inventory Cards / Table -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
      <div v-if="loading" class="py-16 text-center text-slate-400">
        <div class="text-2xl animate-spin mb-2">🧹</div>
        <p class="text-xs">Loading housekeeping room inventory...</p>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-xs">
          <thead>
            <tr class="bg-slate-50 text-slate-600 uppercase font-semibold border-b border-slate-100 text-[11px]">
              <th class="py-3 px-5">Room #</th>
              <th class="py-3 px-5">Category & Title</th>
              <th class="py-3 px-5">Floor</th>
              <th class="py-3 px-5">Occupancy Status</th>
              <th class="py-3 px-5">Cleanliness State</th>
              <th class="py-3 px-5 text-right">Update Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr 
              v-for="room in filteredRooms" 
              :key="room.id"
              class="hover:bg-slate-50/60 transition">
              <td class="py-3.5 px-5 font-bold text-slate-900 font-mono text-sm">
                Room {{ room.room_number }}
              </td>

              <td class="py-3.5 px-5">
                <span class="font-bold text-slate-900 block">{{ room.title }}</span>
                <span class="text-[10px] text-slate-400 uppercase font-semibold">{{ room.category_name }}</span>
              </td>

              <td class="py-3.5 px-5 text-slate-600 font-medium">
                Floor {{ room.floor }}
              </td>

              <td class="py-3.5 px-5">
                <span 
                  :class="[
                    room.status === 'available' ? 'bg-emerald-100 text-emerald-800' : (room.status === 'occupied' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-700'),
                    'px-2.5 py-0.5 rounded-full font-bold text-[10px] uppercase'
                  ]">
                  {{ room.status }}
                </span>
              </td>

              <td class="py-3.5 px-5">
                <span 
                  :class="[
                    room.housekeeping_status === 'inspected' ? 'bg-emerald-100 text-emerald-800 border-emerald-200' : '',
                    room.housekeeping_status === 'clean' ? 'bg-blue-100 text-blue-800 border-blue-200' : '',
                    room.housekeeping_status === 'dirty' ? 'bg-rose-100 text-rose-800 border-rose-200' : '',
                    room.housekeeping_status === 'maintenance' ? 'bg-slate-100 text-slate-700 border-slate-200' : '',
                    'px-2.5 py-1 rounded-full font-bold text-[11px] border inline-flex items-center gap-1.5'
                  ]">
                  <span>{{ getHousekeepingIcon(room.housekeeping_status) }}</span>
                  <span class="capitalize">{{ room.housekeeping_status }}</span>
                </span>
              </td>

              <td class="py-3.5 px-5 text-right">
                <div class="inline-flex items-center gap-1.5">
                  <button 
                    v-if="room.housekeeping_status !== 'inspected'"
                    @click="updateStatus(room, 'inspected')"
                    title="Mark Inspected & 100% Guest Ready"
                    class="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-bold text-[11px] transition cursor-pointer">
                    ✓ Ready
                  </button>
                  <button 
                    v-if="room.housekeeping_status !== 'clean'"
                    @click="updateStatus(room, 'clean')"
                    title="Mark Cleaned by Staff"
                    class="px-2.5 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-bold text-[11px] transition cursor-pointer">
                    🧼 Clean
                  </button>
                  <button 
                    v-if="room.housekeeping_status !== 'dirty'"
                    @click="updateStatus(room, 'dirty')"
                    title="Mark Needs Turnover Cleaning"
                    class="px-2.5 py-1 bg-rose-600 hover:bg-rose-700 text-white rounded-lg font-bold text-[11px] transition cursor-pointer">
                    🧹 Dirty
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';

const rooms = ref([]);
const loading = ref(true);
const filterStatus = ref('all');

function getAuthHeaders() {
  const token = localStorage.getItem('hotel_auth_token');
  return token ? { 'Authorization': `Bearer ${token}` } : {};
}

function countByStatus(status) {
  return rooms.value.filter(r => r.housekeeping_status === status).length;
}

const filteredRooms = computed(() => {
  if (filterStatus.value === 'all') return rooms.value;
  return rooms.value.filter(r => r.housekeeping_status === filterStatus.value);
});

function getHousekeepingIcon(status) {
  if (status === 'inspected') return '✓';
  if (status === 'clean') return '🧼';
  if (status === 'dirty') return '🧹';
  return '🔧';
}

async function fetchHousekeeping() {
  loading.value = true;
  try {
    const res = await fetch('/api/v1/housekeeping', {
      headers: getAuthHeaders()
    });
    if (res.ok) {
      const data = await res.json();
      rooms.value = data.rooms || [];
    }
  } catch (err) {
    console.error('Failed to load housekeeping rooms:', err);
  } finally {
    loading.value = false;
  }
}

async function updateStatus(room, newStatus) {
  const previous = room.housekeeping_status;
  room.housekeeping_status = newStatus; // optimistic UI update
  try {
    const res = await fetch(`/api/v1/housekeeping/${room.id}`, {
      method: 'PATCH',
      headers: {
        'Content-Type': 'application/json',
        ...getAuthHeaders()
      },
      body: JSON.stringify({ housekeeping_status: newStatus })
    });
    if (!res.ok) {
      room.housekeeping_status = previous;
    }
  } catch (e) {
    room.housekeeping_status = previous;
  }
}

onMounted(() => {
  fetchHousekeeping();
});
</script>
