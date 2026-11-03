<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-5 rounded-2xl shadow-sm border border-slate-100">
      <div>
        <h2 class="text-2xl font-bold text-[#0B4D34]">Interactive Hotel Floor Map</h2>
        <p class="text-xs text-slate-500">Live architectural room matrix, guest occupancy, and physical turnover state.</p>
      </div>

      <div class="flex items-center gap-3">
        <!-- Status Legend -->
        <div class="hidden lg:flex items-center gap-3 text-xs bg-slate-50 px-3 py-1.5 rounded-full border border-slate-200">
          <span class="flex items-center gap-1.5">
            <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span> Available
          </span>
          <span class="flex items-center gap-1.5">
            <span class="w-2.5 h-2.5 rounded-full bg-rose-500"></span> Occupied
          </span>
          <span class="flex items-center gap-1.5">
            <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span> Maintenance
          </span>
          <span class="flex items-center gap-1.5">
            <span class="w-2.5 h-2.5 rounded-full bg-purple-500"></span> Turnover / Dirty
          </span>
        </div>

        <button 
          @click="fetchFloorData" 
          class="px-4 py-2 bg-[#0B4D34] hover:bg-[#073826] text-white text-xs font-semibold rounded-full shadow-sm transition flex items-center gap-1.5">
          <span>🔄</span> Refresh Floor Map
        </button>
      </div>
    </div>

    <!-- Summary KPI Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4" v-if="summary">
      <div class="bg-white p-4 rounded-xl border border-slate-100 shadow-sm flex items-center justify-between">
        <div>
          <div class="text-[11px] text-slate-400 font-semibold uppercase">Total Rooms</div>
          <div class="text-2xl font-bold text-slate-800">{{ summary.total_rooms }}</div>
        </div>
        <div class="text-2xl">🏨</div>
      </div>
      <div class="bg-white p-4 rounded-xl border border-emerald-100 shadow-sm flex items-center justify-between">
        <div>
          <div class="text-[11px] text-emerald-600 font-semibold uppercase">Available</div>
          <div class="text-2xl font-bold text-emerald-600">{{ summary.available }}</div>
        </div>
        <div class="text-2xl">🟢</div>
      </div>
      <div class="bg-white p-4 rounded-xl border border-rose-100 shadow-sm flex items-center justify-between">
        <div>
          <div class="text-[11px] text-rose-600 font-semibold uppercase">Occupied</div>
          <div class="text-2xl font-bold text-rose-600">{{ summary.occupied }}</div>
        </div>
        <div class="text-2xl">🔴</div>
      </div>
      <div class="bg-white p-4 rounded-xl border border-amber-100 shadow-sm flex items-center justify-between">
        <div>
          <div class="text-[11px] text-amber-600 font-semibold uppercase">Maintenance</div>
          <div class="text-2xl font-bold text-amber-600">{{ summary.maintenance }}</div>
        </div>
        <div class="text-2xl">🛠️</div>
      </div>
    </div>

    <!-- Floor Selector Tabs -->
    <div class="flex items-center space-x-2 overflow-x-auto pb-1" v-if="floors.length">
      <button
        v-for="fl in floors"
        :key="fl.floor"
        @click="selectedFloor = fl.floor"
        :class="selectedFloor === fl.floor ? 'bg-[#0B4D34] text-white shadow font-bold' : 'bg-white text-slate-600 hover:bg-slate-50 border border-slate-200'"
        class="px-5 py-2.5 rounded-full text-xs transition flex items-center gap-2 flex-shrink-0">
        <span>🏢 {{ fl.floor_name }}</span>
        <span 
          :class="selectedFloor === fl.floor ? 'bg-amber-400 text-slate-900' : 'bg-slate-100 text-slate-600'" 
          class="text-[10px] px-2 py-0.5 rounded-full font-bold">
          {{ fl.total_rooms }} Rooms
        </span>
      </button>
    </div>

    <!-- Active Floor Rooms Grid -->
    <div v-if="currentFloorData" class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
      <div class="flex items-center justify-between mb-6 pb-3 border-b border-slate-100">
        <div>
          <h3 class="text-lg font-bold text-slate-800">{{ currentFloorData.floor_name }}</h3>
          <p class="text-xs text-slate-400">
            {{ currentFloorData.available_count }} Available · {{ currentFloorData.occupied_count }} Occupied · {{ currentFloorData.maintenance_count }} Maintenance
          </p>
        </div>
        <div class="text-xs text-slate-400 italic">Click any room node to inspect details or update status</div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
        <div
          v-for="room in currentFloorData.rooms"
          :key="room.id"
          @click="openRoomModal(room)"
          :class="getRoomBorderClass(room)"
          class="p-4 rounded-xl border-2 cursor-pointer transition transform hover:-translate-y-1 hover:shadow-md flex flex-col justify-between min-h-[160px] relative overflow-hidden bg-white">
          
          <!-- Top Row: Room # & Status Pill -->
          <div>
            <div class="flex items-start justify-between">
              <div>
                <span class="text-xl font-black text-slate-800">#{{ room.room_number }}</span>
                <span class="block text-[10px] text-slate-400 font-medium">{{ room.category_name }}</span>
              </div>
              <span 
                :class="getStatusBadgeClass(room.status)"
                class="text-[10px] uppercase font-bold px-2 py-0.5 rounded-full">
                {{ room.status }}
              </span>
            </div>

            <!-- Price & Capacity -->
            <div class="mt-2 text-xs font-semibold text-slate-700">
              {{ room.price_etb.toLocaleString() }} <span class="text-[10px] text-slate-400 font-normal">ETB / night</span>
            </div>
          </div>

          <!-- Middle / Occupant Info -->
          <div class="my-2" v-if="room.occupant">
            <div class="p-2 rounded bg-rose-50 border border-rose-100 text-[11px] text-rose-800">
              <div class="font-bold truncate">👤 {{ room.occupant.guest_name }}</div>
              <div class="text-[10px] font-mono text-rose-600">{{ room.occupant.reference_code }}</div>
              <div class="text-[9px] text-slate-500 mt-0.5">Depart: {{ room.occupant.check_out_date }}</div>
            </div>
          </div>

          <!-- Bottom Row: Housekeeping state -->
          <div class="flex items-center justify-between pt-2 border-t border-slate-100 text-[10px]">
            <span class="flex items-center gap-1" :class="getHousekeepingTextClass(room.housekeeping_status)">
              <span class="w-2 h-2 rounded-full" :class="getHousekeepingDotClass(room.housekeeping_status)"></span>
              <span class="capitalize">{{ room.housekeeping_status }}</span>
            </span>
            <span class="text-slate-400 text-[10px]">👥 {{ room.capacity }} max</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-else-if="loading" class="bg-white p-12 rounded-2xl text-center text-slate-400 text-xs">
      Loading architectural floor map and live room nodes...
    </div>

    <!-- Room Detail & Status Control Modal -->
    <div v-if="activeModalRoom" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
      <div class="bg-white w-full max-w-lg rounded-2xl shadow-2xl overflow-hidden border border-slate-100 animate-fadeIn">
        <!-- Modal Room Image Banner -->
        <div class="relative h-44 bg-slate-800">
          <img 
            :src="activeModalRoom.image_url" 
            :alt="activeModalRoom.title"
            class="w-full h-full object-cover opacity-90" />
          <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent"></div>
          
          <button 
            @click="activeModalRoom = null" 
            class="absolute top-3 right-3 w-8 h-8 rounded-full bg-black/50 text-white flex items-center justify-center hover:bg-black/70">
            ✕
          </button>

          <div class="absolute bottom-3 left-4 text-white">
            <span class="text-2xl font-black">Room {{ activeModalRoom.room_number }}</span>
            <span class="ml-2 text-xs bg-amber-400 text-slate-900 px-2 py-0.5 rounded font-bold uppercase">
              {{ activeModalRoom.category_name }}
            </span>
            <div class="text-xs text-slate-200 mt-0.5">{{ activeModalRoom.title }}</div>
          </div>
        </div>

        <!-- Modal Body -->
        <div class="p-6 space-y-4">
          <!-- Room Specs Grid -->
          <div class="grid grid-cols-3 gap-2 p-3 bg-slate-50 rounded-xl text-center text-xs">
            <div>
              <div class="text-slate-400 text-[10px]">Floor</div>
              <div class="font-bold text-slate-800">Level {{ activeModalRoom.floor }}</div>
            </div>
            <div>
              <div class="text-slate-400 text-[10px]">Rate</div>
              <div class="font-bold text-[#0B4D34]">{{ activeModalRoom.price_etb.toLocaleString() }} ETB</div>
            </div>
            <div>
              <div class="text-slate-400 text-[10px]">Capacity</div>
              <div class="font-bold text-slate-800">{{ activeModalRoom.capacity }} Guests</div>
            </div>
          </div>

          <!-- Current Occupant Details (if occupied) -->
          <div v-if="activeModalRoom.occupant" class="p-3.5 rounded-xl bg-rose-50 border border-rose-200 text-xs">
            <div class="font-bold text-rose-900 flex items-center justify-between">
              <span>Current In-House Guest</span>
              <span class="font-mono text-[11px] bg-rose-200 text-rose-900 px-1.5 py-0.5 rounded">
                {{ activeModalRoom.occupant.reference_code }}
              </span>
            </div>
            <div class="mt-2 text-slate-700">
              <div><strong>Name:</strong> {{ activeModalRoom.occupant.guest_name }}</div>
              <div><strong>Check-In:</strong> {{ activeModalRoom.occupant.check_in_date }}</div>
              <div><strong>Check-Out:</strong> {{ activeModalRoom.occupant.check_out_date }}</div>
            </div>
          </div>

          <!-- Room Status Toggle Control -->
          <div>
            <label class="block text-xs font-bold text-slate-700 mb-1.5">Operational Status</label>
            <div class="grid grid-cols-2 sm:grid-cols-5 gap-1.5">
              <button 
                @click="updateRoomStatus('available')"
                :class="activeModalRoom.status === 'available' ? 'bg-emerald-600 text-white font-bold shadow-xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                class="py-2 px-2 rounded-lg text-xs transition text-center">
                🟢 Available
              </button>
              <button 
                @click="updateRoomStatus('reserved')"
                :class="activeModalRoom.status === 'reserved' ? 'bg-blue-600 text-white font-bold shadow-xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                class="py-2 px-2 rounded-lg text-xs transition text-center">
                🔵 Reserved
              </button>
              <button 
                @click="updateRoomStatus('occupied')"
                :class="activeModalRoom.status === 'occupied' ? 'bg-rose-600 text-white font-bold shadow-xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                class="py-2 px-2 rounded-lg text-xs transition text-center">
                🔴 Occupied
              </button>
              <button 
                @click="updateRoomStatus('cleaning')"
                :class="activeModalRoom.status === 'cleaning' ? 'bg-purple-600 text-white font-bold shadow-xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                class="py-2 px-2 rounded-lg text-xs transition text-center">
                🟣 Cleaning
              </button>
              <button 
                @click="updateRoomStatus('maintenance')"
                :class="activeModalRoom.status === 'maintenance' ? 'bg-amber-600 text-white font-bold shadow-xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                class="py-2 px-2 rounded-lg text-xs transition text-center">
                🟡 Maintenance
              </button>
            </div>
          </div>

          <!-- Housekeeping Cleanliness Toggle -->
          <div>
            <label class="block text-xs font-bold text-slate-700 mb-1.5">Cleanliness Workflow</label>
            <div class="grid grid-cols-4 gap-2 text-xs">
              <button 
                v-for="hs in ['inspected', 'clean', 'dirty', 'maintenance']"
                :key="hs"
                @click="updateHousekeepingStatus(hs)"
                :class="activeModalRoom.housekeeping_status === hs ? 'bg-[#0B4D34] text-white font-bold' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                class="py-2 px-1 rounded-lg text-center capitalize transition text-[11px]">
                {{ hs }}
              </button>
            </div>
          </div>
        </div>

        <!-- Modal Footer -->
        <div class="p-4 bg-slate-50 border-t border-slate-100 flex items-center justify-end">
          <button 
            @click="activeModalRoom = null" 
            class="px-5 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 font-semibold text-xs rounded-full transition">
            Close
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';

const floors = ref([]);
const summary = ref(null);
const selectedFloor = ref(1);
const loading = ref(false);
const activeModalRoom = ref(null);

const currentFloorData = computed(() => {
  return floors.value.find(f => f.floor === selectedFloor.value);
});

async function fetchFloorData() {
  loading.value = true;
  try {
    const res = await fetch('/api/v1/rooms/floor-map');
    const data = await res.json();
    floors.value = data.floors || [];
    summary.value = data.summary || null;

    if (floors.value.length && !floors.value.some(f => f.floor === selectedFloor.value)) {
      selectedFloor.value = floors.value[0].floor;
    }
  } catch (err) {
    console.error('Failed to fetch floor map:', err);
  } finally {
    loading.value = false;
  }
}

function openRoomModal(room) {
  activeModalRoom.value = { ...room };
}

async function updateRoomStatus(newStatus) {
  if (!activeModalRoom.value) return;
  try {
    const res = await fetch(`/api/v1/rooms/${activeModalRoom.value.id}/status`, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ status: newStatus })
    });
    if (res.ok) {
      activeModalRoom.value.status = newStatus;
      await fetchFloorData();
    }
  } catch (e) {
    console.error('Status update error:', e);
  }
}

async function updateHousekeepingStatus(newStatus) {
  if (!activeModalRoom.value) return;
  try {
    const res = await fetch(`/api/v1/housekeeping/${activeModalRoom.value.id}`, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ housekeeping_status: newStatus })
    });
    if (res.ok) {
      activeModalRoom.value.housekeeping_status = newStatus;
      await fetchFloorData();
    }
  } catch (e) {
    console.error('Housekeeping update error:', e);
  }
}

function getRoomBorderClass(room) {
  if (room.status === 'occupied') return 'border-rose-300 hover:border-rose-500';
  if (room.status === 'reserved') return 'border-blue-300 hover:border-blue-500';
  if (room.status === 'cleaning' || room.housekeeping_status === 'dirty') return 'border-purple-300 hover:border-purple-500';
  if (room.status === 'maintenance' || room.housekeeping_status === 'maintenance') return 'border-amber-300 hover:border-amber-500';
  return 'border-emerald-300 hover:border-emerald-500';
}

function getStatusBadgeClass(status) {
  if (status === 'available') return 'bg-emerald-100 text-emerald-800';
  if (status === 'reserved') return 'bg-blue-100 text-blue-800';
  if (status === 'occupied') return 'bg-rose-100 text-rose-800';
  if (status === 'cleaning') return 'bg-purple-100 text-purple-800';
  if (status === 'maintenance') return 'bg-amber-100 text-amber-800';
  return 'bg-slate-100 text-slate-700';
}

function getHousekeepingTextClass(status) {
  if (status === 'inspected') return 'text-emerald-700';
  if (status === 'clean') return 'text-blue-700';
  if (status === 'dirty') return 'text-purple-700';
  return 'text-amber-700';
}

function getHousekeepingDotClass(status) {
  if (status === 'inspected') return 'bg-emerald-500';
  if (status === 'clean') return 'bg-blue-500';
  if (status === 'dirty') return 'bg-purple-500';
  return 'bg-amber-500';
}

onMounted(() => {
  fetchFloorData();
});
</script>
