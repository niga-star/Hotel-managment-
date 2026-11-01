<template>
  <div class="space-y-6">
    <!-- Header with controls matching Screen 8 -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-5 rounded-2xl shadow-sm border border-slate-100">
      <div>
        <h2 class="text-2xl font-bold text-[#0B4D34]">Bookings & Check-In / Check-Out Desk</h2>
        <p class="text-xs text-slate-500">Front desk operations console and QR guest processing.</p>
      </div>

      <button 
        @click="showWalkInModal = true"
        class="px-5 py-2.5 bg-[#0B4D34] hover:bg-[#064E3B] text-white text-xs font-bold rounded-full shadow-md flex items-center gap-1.5 transition">
        <span>+</span> Walk-In Booking
      </button>
    </div>

    <!-- Filter Pills & Search Bar (Screen 8) -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div class="flex items-center space-x-2">
        <button 
          @click="activeFilter = 'arriving'; fetchDeskBookings()"
          :class="activeFilter === 'arriving' ? 'bg-[#0B4D34] text-white shadow-sm font-bold' : 'bg-white text-slate-700 border border-slate-200'"
          class="px-4 py-2 rounded-full text-xs transition">
          Arriving Today ({{ stats.arriving }})
        </button>
        <button 
          @click="activeFilter = 'departing'; fetchDeskBookings()"
          :class="activeFilter === 'departing' ? 'bg-[#0B4D34] text-white shadow-sm font-bold' : 'bg-white text-slate-700 border border-slate-200'"
          class="px-4 py-2 rounded-full text-xs transition">
          Departing Today ({{ stats.departing }})
        </button>
        <button 
          @click="activeFilter = 'active'; fetchDeskBookings()"
          :class="activeFilter === 'active' ? 'bg-[#0B4D34] text-white shadow-sm font-bold' : 'bg-white text-slate-700 border border-slate-200'"
          class="px-4 py-2 rounded-full text-xs transition">
          Active Guests ({{ stats.active }})
        </button>
      </div>

      <!-- Search Input -->
      <div class="relative w-full sm:w-80">
        <input 
          v-model="searchQuery" 
          @input="fetchDeskBookings"
          type="text" 
          placeholder="Search Booking Reference (e.g. #ETH-89274)" 
          class="w-full pl-9 pr-4 py-2.5 bg-white border border-slate-200 rounded-full text-xs focus:outline-none focus:border-[#0B4D34] shadow-sm" />
        <span class="absolute left-3.5 top-3 text-xs text-slate-400">🔍</span>
      </div>
    </div>

    <!-- Layout: Table (Left 2 Cols) + Quick QR Scanner (Right 1 Col) -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Left 2 Cols: Guest Check-In/Out Table -->
      <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100">
          <h3 class="text-base font-bold text-slate-900">Guest Check-In/Out</h3>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse text-sm">
            <thead>
              <tr class="bg-slate-50 text-slate-600 text-xs font-semibold uppercase tracking-wider border-b border-slate-100">
                <th class="py-3 px-5">Guest Name</th>
                <th class="py-3 px-5">Phone</th>
                <th class="py-3 px-5">Room Number</th>
                <th class="py-3 px-5">Check-In</th>
                <th class="py-3 px-5">Check-Out</th>
                <th class="py-3 px-5">Payment Status</th>
                <th class="py-3 px-5 text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-slate-700">
              <tr 
                v-for="b in reservations" 
                :key="b.id" 
                class="hover:bg-slate-50/50 transition">
                <!-- Guest Name -->
                <td class="py-3.5 px-5 flex items-center space-x-3">
                  <img 
                    :src="b.avatar_url || 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=100&h=100&fit=crop&crop=face'" 
                    class="w-8 h-8 rounded-full object-cover border border-slate-200" 
                    alt="Guest" />
                  <span class="font-semibold text-slate-900 text-xs">{{ b.guest_name }}</span>
                </td>
                <!-- Phone -->
                <td class="py-3.5 px-5 text-xs text-slate-600 font-mono">{{ b.guest_phone }}</td>
                <!-- Room -->
                <td class="py-3.5 px-5 font-bold text-slate-800 text-xs">Room {{ b.room_number }}</td>
                <!-- Dates -->
                <td class="py-3.5 px-5 text-xs text-slate-600">{{ b.check_in_date }}</td>
                <td class="py-3.5 px-5 text-xs text-slate-600">{{ b.check_out_date }}</td>
                <!-- Payment Status -->
                <td class="py-3.5 px-5">
                  <span class="text-[11px] font-bold px-2.5 py-0.5 rounded-full bg-emerald-100 text-emerald-800">
                    Paid
                  </span>
                </td>
                <!-- Actions Button -->
                <td class="py-3.5 px-5 text-right">
                  <button 
                    v-if="b.booking_status === 'confirmed'"
                    @click="confirmCheckIn(b)"
                    class="px-3.5 py-1.5 bg-[#0B4D34] hover:bg-[#064E3B] text-white text-xs font-semibold rounded-lg shadow-sm transition">
                    Confirm Check-In
                  </button>
                  <button 
                    v-else-if="b.booking_status === 'checked_in'"
                    @click="checkOutAndInvoice(b)"
                    class="px-3.5 py-1.5 bg-[#D4A329] hover:bg-[#B8861B] text-slate-900 text-xs font-semibold rounded-lg shadow-sm transition">
                    Check-Out & Invoice
                  </button>
                  <span v-else class="text-xs text-slate-400">Checked Out</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Right 1 Col: Quick QR Code Check-In Panel (Screen 8) -->
      <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 flex flex-col justify-between text-center space-y-4 h-fit sticky top-6">
        <div>
          <!-- Scanner Camera Icon -->
          <div class="w-14 h-14 rounded-2xl bg-emerald-50 text-[#0B4D34] flex items-center justify-center text-2xl mx-auto mb-2 border border-emerald-100">
            📷
          </div>
          <h3 class="text-base font-bold text-slate-900">Quick QR Code Check-In</h3>
          <p class="text-xs text-slate-400 mt-1">Touchless scan from guest mobile receipt</p>
        </div>

        <!-- Simulated Active QR Scanner Frame -->
        <div class="relative bg-slate-50 border-2 border-dashed border-[#0B4D34]/40 rounded-2xl p-4">
          <img 
            src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=%23ETH-89274%3AAbebeKebede%3ARoom104" 
            class="w-40 h-40 mx-auto bg-white p-2 rounded-xl shadow-sm border border-slate-200" 
            alt="Scanner View" />
          <div class="mt-3 bg-white p-2 rounded-xl shadow-sm border border-emerald-100 text-xs">
            <span class="text-slate-500 font-medium block">Scanning:</span>
            <span class="font-bold text-[#0B4D34] text-xs">Almaz Tesfaye (to show instant verification)</span>
          </div>
        </div>

        <button 
          @click="simulateQRScan"
          class="w-full py-3 bg-[#0B4D34] hover:bg-[#064E3B] text-white font-bold text-xs rounded-xl shadow-md transition">
          Simulate Camera QR Verification
        </button>
      </div>
    </div>

    <!-- Walk-In Booking Modal -->
    <div v-if="showWalkInModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-2xl p-6 max-w-md w-full space-y-4 shadow-2xl border border-slate-100">
        <div class="flex items-center justify-between border-b pb-3">
          <h3 class="font-bold text-slate-900 text-base">New Walk-In Reservation</h3>
          <button @click="showWalkInModal = false" class="text-slate-400 hover:text-slate-600">✕</button>
        </div>
        <form @submit.prevent="submitWalkIn" class="space-y-3 text-xs">
          <div>
            <label class="font-semibold block mb-1">Guest Full Name</label>
            <input v-model="walkInForm.guest_name" required type="text" class="w-full p-2 bg-slate-50 border rounded-lg" placeholder="Abebe Kebede" />
          </div>
          <div>
            <label class="font-semibold block mb-1">Phone (+251)</label>
            <input v-model="walkInForm.guest_phone" required type="text" class="w-full p-2 bg-slate-50 border rounded-lg" placeholder="+251 911 234567" />
          </div>
          <div>
            <label class="font-semibold block mb-1">Select Room</label>
            <select v-model="walkInForm.room_id" required class="w-full p-2 bg-slate-50 border rounded-lg">
              <option value="rm-101">Room 101 - Executive Suite (2,500 ETB)</option>
              <option value="rm-104">Room 104 - Valley Deluxe (1,900 ETB)</option>
              <option value="rm-202">Room 202 - Standard Room (1,200 ETB)</option>
            </select>
          </div>
          <div class="grid grid-cols-2 gap-2">
            <div>
              <label class="font-semibold block mb-1">Check-In</label>
              <input v-model="walkInForm.check_in_date" required type="date" class="w-full p-2 bg-slate-50 border rounded-lg" />
            </div>
            <div>
              <label class="font-semibold block mb-1">Check-Out</label>
              <input v-model="walkInForm.check_out_date" required type="date" class="w-full p-2 bg-slate-50 border rounded-lg" />
            </div>
          </div>
          <button type="submit" class="w-full py-3 bg-[#0B4D34] text-white font-bold rounded-xl mt-2">
            Confirm Walk-In & Check-In
          </button>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';

const activeFilter = ref('arriving');
const searchQuery = ref('');
const reservations = ref([]);
const stats = ref({ arriving: 15, departing: 8, active: 42 });

const showWalkInModal = ref(false);
const walkInForm = ref({
  guest_name: '',
  guest_phone: '+251 911 000000',
  room_id: 'rm-104',
  check_in_date: '2026-10-21',
  check_out_date: '2026-10-25'
});

async function fetchDeskBookings() {
  try {
    let url = `/api/v1/bookings/desk?filter=${activeFilter.value}`;
    if (searchQuery.value) {
      url += `&search=${encodeURIComponent(searchQuery.value)}`;
    }
    const res = await fetch(url);
    const data = await res.json();
    reservations.value = data.reservations || [];
    if (data.stats) stats.value = data.stats;
  } catch (err) {
    console.error('Failed to load desk bookings:', err);
  }
}

async function confirmCheckIn(b) {
  try {
    await fetch(`/api/v1/bookings/${b.id}/check-in`, { method: 'POST' });
    alert(`✓ Guest ${b.guest_name} confirmed checked into Room ${b.room_number}.`);
    fetchDeskBookings();
  } catch (err) {
    alert('Check in failed.');
  }
}

async function checkOutAndInvoice(b) {
  try {
    const res = await fetch(`/api/v1/bookings/${b.id}/check-out`, { method: 'POST' });
    const data = await res.json();
    alert(`✓ Guest ${b.guest_name} checked out. Official invoice #${data.invoice.reference_code} generated for ETB ${data.invoice.total_price_etb}.`);
    fetchDeskBookings();
  } catch (err) {
    alert('Check out failed.');
  }
}

function simulateQRScan() {
  alert('📷 QR Verification Success!\n\nBooking: #ETH-89274\nGuest: Almaz Tesfaye\nRoom: 104\nStatus: Verified Paid.\n\nFront desk is ready for 1-click check-in.');
}

async function submitWalkIn() {
  try {
    const res = await fetch('/api/v1/bookings', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        ...walkInForm.value,
        is_walk_in: 1,
        payment_method: 'cash'
      })
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.error);

    alert(`✓ Walk-in guest registered! Reference: ${data.booking.reference_code}`);
    showWalkInModal.value = false;
    fetchDeskBookings();
  } catch (err) {
    alert(err.message);
  }
}

onMounted(() => {
  fetchDeskBookings();
});
</script>
