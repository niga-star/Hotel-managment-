<template>
  <div class="max-w-md mx-auto space-y-4 pb-12">
    <!-- Mobile App Top Bar -->
    <div class="bg-[#0B4D34] text-white p-4 rounded-2xl shadow-md flex items-center justify-between sticky top-2 z-30">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-full bg-amber-400 text-slate-900 font-bold flex items-center justify-center text-sm shadow">
          🧹
        </div>
        <div>
          <h2 class="text-sm font-bold tracking-tight">Housekeeping Ops Portal</h2>
          <p class="text-[10px] text-emerald-200">Staff Assigned: Almaz T. (Wing A & B)</p>
        </div>
      </div>

      <button 
        @click="fetchRooms" 
        class="p-2 rounded-full bg-emerald-800 hover:bg-emerald-700 text-white text-xs">
        🔄
      </button>
    </div>

    <!-- Daily Cleanliness Progress Bar -->
    <div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-100 space-y-2">
      <div class="flex justify-between items-center text-xs">
        <span class="font-bold text-slate-700">Turnover Shift Progress</span>
        <span class="font-mono font-bold text-[#0B4D34]">{{ completedCount }} / {{ rooms.length }} Rooms ({{ progressPercent }}%)</span>
      </div>
      <div class="w-full h-3 bg-slate-100 rounded-full overflow-hidden">
        <div 
          class="h-full bg-gradient-to-r from-emerald-500 to-[#0B4D34] rounded-full transition-all duration-500"
          :style="{ width: progressPercent + '%' }"></div>
      </div>
      <div class="flex justify-between text-[10px] text-slate-400 pt-1">
        <span>🔴 {{ dirtyRooms.length }} Dirty Turnover</span>
        <span>🔵 {{ cleanRooms.length }} Cleaned</span>
        <span>🟢 {{ inspectedRooms.length }} Inspected</span>
      </div>
    </div>

    <!-- Quick Status Filter Pills -->
    <div class="flex items-center gap-2 overflow-x-auto pb-1 text-xs">
      <button 
        v-for="tab in ['all', 'dirty', 'clean', 'inspected', 'maintenance']" 
        :key="tab"
        @click="activeFilter = tab"
        :class="activeFilter === tab ? 'bg-[#0B4D34] text-white font-bold' : 'bg-white text-slate-600 border border-slate-200'"
        class="px-3.5 py-1.5 rounded-full capitalize flex-shrink-0 transition">
        {{ tab }} ({{ getTabCount(tab) }})
      </button>
    </div>

    <!-- Room Cards List -->
    <div class="space-y-3">
      <div 
        v-if="filteredRooms.length === 0" 
        class="bg-white p-8 rounded-2xl text-center text-slate-400 text-xs border border-slate-100">
        No rooms found under the '{{ activeFilter }}' filter.
      </div>

      <div 
        v-for="room in filteredRooms" 
        :key="room.id"
        class="bg-white p-4 rounded-2xl shadow-sm border border-slate-100 space-y-3 relative overflow-hidden transition">
        
        <!-- Left Status Indicator Bar -->
        <div 
          class="absolute left-0 top-0 bottom-0 w-1.5"
          :class="getStatusColor(room.housekeeping_status)"></div>

        <!-- Room Header & Floor -->
        <div class="flex items-start justify-between pl-2">
          <div>
            <div class="flex items-center gap-2">
              <span class="text-xl font-black text-slate-800">Room {{ room.room_number }}</span>
              <span class="text-[10px] px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 font-semibold uppercase">
                Floor {{ room.floor }}
              </span>
            </div>
            <p class="text-xs text-slate-400 mt-0.5">{{ room.title }}</p>
          </div>

          <span 
            :class="getStatusBadgeClass(room.housekeeping_status)"
            class="text-[10px] font-bold px-2.5 py-1 rounded-full uppercase">
            {{ room.housekeeping_status }}
          </span>
        </div>

        <!-- Room Turnover Urgency Banner -->
        <div 
          v-if="room.housekeeping_status === 'dirty'"
          class="p-2.5 rounded-xl bg-purple-50 border border-purple-100 text-purple-900 text-xs flex items-center gap-2 pl-2">
          <span class="text-base">🚨</span>
          <div>
            <div class="font-bold">Priority Turnover Required</div>
            <div class="text-[10px] text-purple-700">Guest departed · Clean & sanitize bedding before next arrival</div>
          </div>
        </div>

        <!-- Action Buttons (1-Tap Updates) -->
        <div class="grid grid-cols-3 gap-2 pt-1 border-t border-slate-100">
          <button 
            @click="setStatus(room, 'clean')"
            :disabled="room.housekeeping_status === 'clean'"
            class="py-2 px-2 bg-blue-50 hover:bg-blue-100 text-blue-700 font-bold text-xs rounded-xl transition flex items-center justify-center gap-1 disabled:opacity-40">
            <span>✨</span> Mark Clean
          </button>

          <button 
            @click="setStatus(room, 'inspected')"
            :disabled="room.housekeeping_status === 'inspected'"
            class="py-2 px-2 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 font-bold text-xs rounded-xl transition flex items-center justify-center gap-1 disabled:opacity-40">
            <span>✓</span> Inspected
          </button>

          <button 
            @click="setStatus(room, 'maintenance')"
            :disabled="room.housekeeping_status === 'maintenance'"
            class="py-2 px-2 bg-amber-50 hover:bg-amber-100 text-amber-800 font-bold text-xs rounded-xl transition flex items-center justify-center gap-1 disabled:opacity-40">
            <span>🛠️</span> Defect
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';

const rooms = ref([]);
const loading = ref(false);
const activeFilter = ref('all');

async function fetchRooms() {
  loading.value = true;
  try {
    const res = await fetch('/api/v1/housekeeping');
    const data = await res.json();
    rooms.value = data.rooms || [];
  } catch (err) {
    console.error('Failed to load housekeeping rooms:', err);
  } finally {
    loading.value = false;
  }
}

async function setStatus(room, newStatus) {
  try {
    const res = await fetch(`/api/v1/housekeeping/${room.id}`, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ housekeeping_status: newStatus })
    });
    if (res.ok) {
      room.housekeeping_status = newStatus;
    }
  } catch (err) {
    alert('Failed to update room: ' + err.message);
  }
}

const dirtyRooms = computed(() => rooms.value.filter(r => r.housekeeping_status === 'dirty'));
const cleanRooms = computed(() => rooms.value.filter(r => r.housekeeping_status === 'clean'));
const inspectedRooms = computed(() => rooms.value.filter(r => r.housekeeping_status === 'inspected'));

const completedCount = computed(() => inspectedRooms.value.length + cleanRooms.value.length);
const progressPercent = computed(() => {
  if (rooms.value.length === 0) return 0;
  return Math.round((completedCount.value / rooms.value.length) * 100);
});

const filteredRooms = computed(() => {
  if (activeFilter.value === 'all') return rooms.value;
  return rooms.value.filter(r => r.housekeeping_status === activeFilter.value);
});

function getTabCount(tab) {
  if (tab === 'all') return rooms.value.length;
  return rooms.value.filter(r => r.housekeeping_status === tab).length;
}

function getStatusColor(status) {
  if (status === 'inspected') return 'bg-emerald-500';
  if (status === 'clean') return 'bg-blue-500';
  if (status === 'dirty') return 'bg-purple-500';
  return 'bg-amber-500';
}

function getStatusBadgeClass(status) {
  if (status === 'inspected') return 'bg-emerald-100 text-emerald-800';
  if (status === 'clean') return 'bg-blue-100 text-blue-800';
  if (status === 'dirty') return 'bg-purple-100 text-purple-800';
  return 'bg-amber-100 text-amber-800';
}

onMounted(() => {
  fetchRooms();
});
</script>
