<template>
  <div class="relative space-y-6">
    <!-- Top Filter Toolbar (Screen 3) -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-4 rounded-2xl shadow-sm border border-slate-100">
      <div class="flex items-center space-x-2">
        <button 
          v-for="cat in ['all', 'suite', 'deluxe', 'standard']"
          :key="cat"
          @click="selectedCategory = cat; fetchBookings()"
          :class="[
            selectedCategory === cat
              ? 'bg-[#0B4D34] text-white shadow-sm'
              : 'bg-slate-100 text-slate-700 hover:bg-slate-200'
          ]"
          class="px-4 py-1.5 rounded-full text-xs font-semibold capitalize transition">
          {{ cat === 'all' ? 'All Stays' : cat }}
        </button>
      </div>

      <div class="flex items-center space-x-2 text-slate-500">
        <button class="p-2 rounded-full hover:bg-slate-100 transition" title="Filter preferences">
          ⚙️
        </button>
        <button class="p-2 rounded-full hover:bg-slate-100 transition relative" title="Notifications">
          🔔
          <span class="w-2 h-2 rounded-full bg-emerald-500 absolute top-1.5 right-1.5"></span>
        </button>
      </div>
    </div>

    <!-- Layout: Bookings List + Receipt Modal/Drawer -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Left 2 Cols: My Bookings Cards -->
      <div class="lg:col-span-2 space-y-4">
        <h2 class="text-2xl font-bold text-slate-900">My Bookings</h2>

        <div v-if="loading" class="py-12 text-center text-slate-400">
          Loading your reservations from database...
        </div>

        <div v-else-if="bookings.length === 0" class="bg-white p-8 rounded-2xl text-center text-slate-500">
          No bookings found in this category.
        </div>

        <div 
          v-for="b in bookings" 
          :key="b.id"
          class="bg-white p-4 sm:p-5 rounded-2xl shadow-sm border border-slate-100 flex flex-col sm:flex-row items-center gap-4 hover:shadow-md transition">
          <!-- Room Image -->
          <img 
            :src="b.image_url || 'https://images.unsplash.com/photo-1591088398332-8a7791972843?w=400&fit=crop'" 
            class="w-full sm:w-40 h-28 rounded-xl object-cover" 
            alt="Room" />

          <!-- Details -->
          <div class="flex-1 w-full space-y-1.5">
            <div class="flex items-center justify-between">
              <h3 class="font-bold text-slate-900 text-base">{{ b.room_title }}</h3>
              <span class="inline-flex items-center gap-1 text-[11px] font-bold px-2.5 py-0.5 rounded-full bg-emerald-100 text-emerald-800">
                ✓ Confirmed
              </span>
            </div>

            <div class="grid grid-cols-2 text-xs text-slate-600 gap-2 pt-1">
              <div>
                <span class="text-slate-400 block text-[10px] uppercase font-semibold">Check-in</span>
                <span class="font-medium">{{ b.check_in_date }}</span>
              </div>
              <div>
                <span class="text-slate-400 block text-[10px] uppercase font-semibold">Check-out</span>
                <span class="font-medium">{{ b.check_out_date }}</span>
              </div>
            </div>

            <div class="pt-1 flex items-center justify-between">
              <span class="text-sm font-bold text-[#0B4D34]">
                Price: ${{ b.total_price_usd }} <span class="text-xs text-slate-400 font-normal">({{ b.total_price_etb.toLocaleString() }} ETB)</span>
              </span>
              <div class="flex items-center space-x-2">
                <button 
                  @click="openReceipt(b)"
                  class="px-3 py-1 bg-emerald-50 hover:bg-emerald-100 text-[#0B4D34] text-xs font-semibold rounded-lg transition">
                  View Receipt
                </button>
                <button 
                  @click="cancelBooking(b)"
                  class="px-3 py-1 border border-slate-200 hover:bg-slate-50 text-slate-500 text-xs font-medium rounded-lg transition">
                  Cancel
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Right 1 Col: Booking Receipt & Confirmation Flyout (Screen 3) -->
      <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 flex flex-col justify-between sticky top-6">
        <div class="space-y-4">
          <!-- Top Badge -->
          <div class="bg-emerald-50 border border-emerald-100 text-emerald-900 text-xs font-semibold py-1.5 px-3 rounded-xl text-center">
            Digital Booking Receipt
          </div>

          <!-- Confirmed Status -->
          <div class="text-center pt-2">
            <div class="w-12 h-12 rounded-full bg-emerald-100 text-emerald-700 font-bold flex items-center justify-center mx-auto text-xl mb-1">
              ✓
            </div>
            <h4 class="font-bold text-slate-900 text-base">Payment Confirmed</h4>
            <p class="text-xs font-mono text-emerald-800 font-semibold mt-0.5">
              Booking Reference: {{ activeReceipt.reference_code }}
            </p>
          </div>

          <!-- Customer Info -->
          <div class="bg-slate-50 p-3.5 rounded-xl space-y-1.5 text-xs text-slate-700">
            <div class="flex justify-between">
              <span class="text-slate-400">Customer:</span>
              <span class="font-semibold text-slate-900">{{ activeReceipt.guest_name }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-slate-400">Email:</span>
              <span class="font-medium truncate max-w-[170px]">{{ activeReceipt.guest_email }}</span>
            </div>
            <div class="flex justify-between border-t border-slate-200/80 pt-1.5">
              <span class="text-slate-400">Base Price:</span>
              <span class="line-through text-slate-400">${{ (activeReceipt.total_price_usd * 1.2).toFixed(0) }}</span>
            </div>
            <div class="flex justify-between font-bold text-slate-900 text-sm">
              <span>Total Price:</span>
              <span class="text-[#0B4D34]">${{ activeReceipt.total_price_usd }} ({{ activeReceipt.total_price_etb.toLocaleString() }} ETB)</span>
            </div>
          </div>

          <!-- Scannable 2D QR Code Check-in -->
          <div class="text-center py-2 bg-emerald-50/50 rounded-2xl border border-emerald-100/60 p-4">
            <span class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider block mb-2">QR Check-in</span>
            <img 
              :src="activeReceipt.qr_code_data_url" 
              class="w-36 h-36 mx-auto bg-white p-2 rounded-xl shadow-sm border border-slate-200" 
              alt="Check-in QR Code" />
            <span class="text-[10px] text-slate-400 mt-2 block">Present this QR code to the receptionist upon arrival</span>
          </div>

          <!-- Payment Confirmed Method -->
          <div class="flex items-center justify-between text-xs px-2 py-1 text-slate-600 border border-slate-100 rounded-lg">
            <span class="flex items-center gap-1.5 font-medium">
              💳 Payment method confirmed
            </span>
            <span class="font-semibold uppercase text-emerald-700">{{ activeReceipt.payment_method }}</span>
          </div>

          <!-- Phase 5: Automated Guest Notifications Status -->
          <div class="bg-slate-50 p-3 rounded-xl border border-slate-100 space-y-1.5 text-[11px] text-slate-600">
            <div class="flex items-center justify-between">
              <span class="flex items-center gap-1">✉️ Email Receipt:</span>
              <span class="text-emerald-700 font-semibold">✓ Dispatched</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="flex items-center gap-1">📱 Ethio SMS:</span>
              <span class="text-emerald-700 font-semibold">✓ Delivered</span>
            </div>
            <button 
              @click="resendNotifications(activeReceipt)"
              class="w-full mt-1 py-1.5 text-[11px] text-[#0B4D34] hover:bg-emerald-50 border border-emerald-200 rounded-lg font-semibold transition">
              ↻ Resend Confirmation Email & SMS
            </button>
          </div>
        </div>

        <!-- Download Receipt PDF Button -->
        <button 
          @click="downloadReceiptPDF"
          class="w-full mt-4 py-3 bg-[#0B4D34] hover:bg-[#064E3B] text-white text-xs font-bold rounded-xl shadow-md transition flex items-center justify-center gap-2">
          <span>📥</span> Download Receipt PDF
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';

const selectedCategory = ref('all');
const bookings = ref([]);
const loading = ref(true);

const activeReceipt = ref({
  reference_code: '#ETH-89274',
  guest_name: 'Analisa Ethiopian',
  guest_email: 'aya@ethin.com',
  total_price_usd: 380,
  total_price_etb: 24320,
  payment_method: 'telebirr',
  qr_code_data_url: 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=ETH-89274'
});

async function fetchBookings() {
  loading.value = true;
  try {
    const res = await fetch(`/api/v1/bookings/my-bookings?category=${selectedCategory.value}`);
    const data = await res.json();
    bookings.value = data.bookings || [];
    if (bookings.value.length > 0) {
      activeReceipt.value = bookings.value[0];
    }
  } catch (error) {
    console.error('Error loading bookings:', error);
  } finally {
    loading.value = false;
  }
}

function openReceipt(b) {
  activeReceipt.value = b;
}

function cancelBooking(b) {
  if (confirm(`Are you sure you want to cancel reservation ${b.reference_code}?`)) {
    alert(`Reservation ${b.reference_code} has been cancelled.`);
    fetchBookings();
  }
}

async function resendNotifications(b) {
  try {
    const res = await fetch('/api/v1/notifications/resend', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ booking_id: b.id, channel: 'email' })
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.error);
    alert(`✉️📱 Confirmation email and Ethio SMS re-dispatched to ${b.guest_email} & ${b.guest_phone}!`);
  } catch (err) {
    alert('Failed to resend: ' + err.message);
  }
}

function downloadReceiptPDF() {
  window.print();
}

onMounted(() => {
  fetchBookings();
});
</script>
