<template>
  <div class="space-y-6">
    <!-- Top Filter Bar & Real Date Selection (Screen 4) -->
    <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 flex flex-col xl:flex-row xl:items-center xl:justify-between gap-4">
      <!-- Category Pills -->
      <div class="flex items-center space-x-2 overflow-x-auto pb-1 xl:pb-0">
        <button 
          v-for="cat in ['all', 'suite', 'deluxe', 'standard']"
          :key="cat"
          @click="selectedCategory = cat; fetchRooms()"
          :class="[
            selectedCategory === cat
              ? 'bg-[#0B4D34] text-white shadow-sm font-bold'
              : 'bg-slate-100 text-slate-700 hover:bg-slate-200 font-medium'
          ]"
          class="px-5 py-2.5 rounded-full text-xs capitalize transition flex-shrink-0">
          {{ cat === 'all' ? '🏨 All Rooms' : cat }}
        </button>
      </div>

      <!-- Real Date-Based Availability Picker -->
      <div class="flex flex-wrap items-center gap-3 bg-slate-50 p-2 rounded-2xl border border-slate-200/80">
        <div class="flex items-center gap-2 text-xs text-slate-600 font-semibold pl-2">
          <span>📅</span>
          <span>Dates:</span>
        </div>
        <div class="flex items-center gap-2">
          <input 
            v-model="checkInDate" 
            @change="onDateChange"
            :min="todayDateStr"
            type="date" 
            class="px-3 py-1.5 bg-white border border-slate-200 rounded-xl text-xs font-semibold text-slate-800 focus:outline-none focus:border-[#0B4D34]" />
          <span class="text-xs text-slate-400">→</span>
          <input 
            v-model="checkOutDate" 
            @change="onDateChange"
            :min="minCheckOutDateStr"
            type="date" 
            class="px-3 py-1.5 bg-white border border-slate-200 rounded-xl text-xs font-semibold text-slate-800 focus:outline-none focus:border-[#0B4D34]" />
        </div>
        <span class="text-[11px] font-bold px-2 py-0.5 bg-emerald-100 text-[#0B4D34] rounded-md">
          {{ nights }} {{ nights === 1 ? 'Night' : 'Nights' }}
        </span>
      </div>

      <!-- Search Input & Currency Switcher -->
      <div class="flex items-center space-x-3 w-full xl:w-auto">
        <div class="relative flex-1 xl:w-60">
          <input 
            v-model="searchQuery"
            @input="fetchRooms"
            type="text" 
            placeholder="Search suites, deluxe, room #..." 
            class="w-full pl-9 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-full text-xs focus:outline-none focus:border-[#0B4D34] transition" />
          <span class="absolute left-3 top-2.5 text-xs text-slate-400">🔍</span>
        </div>

        <button 
          @click="toggleCurrency"
          class="px-3 py-2 border border-slate-200 rounded-full text-xs font-semibold hover:bg-slate-50 transition text-slate-700 whitespace-nowrap">
          {{ currency === 'ETB' ? '🇪🇹 ETB' : '💵 USD' }}
        </button>
      </div>
    </div>

    <!-- Layout: Rooms Grid (Left 2 Cols) + Booking Drawer (Right 1 Col) -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Left 2 Cols: Room Cards -->
      <div class="lg:col-span-2">
        <div v-if="loading" class="py-20 text-center text-slate-400 space-y-2">
          <div class="text-3xl animate-bounce">🛎️</div>
          <p class="text-xs">Checking real-time availability in MySQL database...</p>
        </div>

        <div v-else-if="rooms.length === 0" class="py-20 text-center text-slate-400 bg-white rounded-2xl border border-slate-100 p-8 space-y-3">
          <div class="text-4xl">🛏️</div>
          <h4 class="text-base font-bold text-slate-700">No Rooms Found</h4>
          <p class="text-xs text-slate-400">Try adjusting your category filter, dates, or search term.</p>
        </div>

        <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-5">
          <div 
            v-for="room in rooms" 
            :key="room.id"
            @click="selectRoom(room)"
            :class="[
              selectedRoom?.id === room.id 
                ? 'ring-4 ring-[#0B4D34] shadow-xl border-[#0B4D34] scale-[1.01]' 
                : 'hover:shadow-md border-slate-100',
              !room.is_available ? 'opacity-85' : ''
            ]"
            class="bg-white rounded-2xl overflow-hidden border transition-all cursor-pointer group flex flex-col justify-between relative">
            
            <!-- Room Image & Actions -->
            <div class="relative h-48 overflow-hidden bg-slate-100">
              <img 
                :src="room.image_url || 'https://images.unsplash.com/photo-1591088398332-8a7791972843?w=800&fit=crop'" 
                class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" 
                alt="Room Photo" />
              
              <!-- Real Date Availability Badge -->
              <div class="absolute top-3 left-3">
                <span 
                  v-if="room.is_available" 
                  class="px-2.5 py-1 bg-emerald-600/90 backdrop-blur-sm text-white font-bold text-[10px] rounded-full shadow flex items-center gap-1">
                  <span>✓</span> Available for Dates
                </span>
                <span 
                  v-else 
                  class="px-2.5 py-1 bg-rose-600/95 backdrop-blur-sm text-white font-bold text-[10px] rounded-full shadow flex items-center gap-1">
                  <span>✕</span> Booked for Selected Dates
                </span>
              </div>

              <!-- Functional Like / Favorite Heart Button (Item 2) -->
              <button 
                type="button"
                @click.stop="toggleFavorite(room)"
                :title="favorites.has(room.id) ? 'Remove from favorites' : 'Add to favorites'"
                class="absolute top-3 right-3 w-8 h-8 rounded-full bg-white/90 backdrop-blur-sm flex items-center justify-center text-sm shadow hover:scale-110 active:scale-95 transition">
                <span :class="favorites.has(room.id) ? 'text-rose-500 scale-110' : 'text-slate-300'">
                  {{ favorites.has(room.id) ? '❤️' : '🤍' }}
                </span>
              </button>

              <!-- Price Tag Pill -->
              <div class="absolute bottom-3 right-3 px-3 py-1 bg-[#0B4D34]/95 backdrop-blur-sm text-white font-bold text-xs rounded-full shadow border border-yellow-200/20">
                {{ formatPrice(room.price_etb, room.price_usd) }} / night
              </div>
            </div>

            <!-- Content -->
            <div class="p-4 space-y-2">
              <div class="flex items-center justify-between text-xs">
                <div class="flex items-center text-amber-500">
                  <span>★★★★★</span>
                  <span class="text-slate-400 text-[11px] ml-1.5">({{ room.avg_rating || '5.0' }})</span>
                </div>
                <span class="text-[10px] font-bold px-2 py-0.5 rounded-md bg-slate-100 text-slate-700 capitalize">
                  {{ room.category_name }}
                </span>
              </div>

              <h3 class="font-bold text-slate-900 text-sm group-hover:text-[#0B4D34] transition truncate">
                {{ room.title }} (Room {{ room.room_number }})
              </h3>
              <p class="text-xs text-slate-500 line-clamp-1">
                Floor {{ room.floor }} • Up to {{ room.capacity }} Guests • Luxury Boutique Interior
              </p>

              <!-- Amenities snippet -->
              <div class="flex flex-wrap gap-1.5 pt-1">
                <span 
                  v-for="amen in room.amenities?.slice(0, 3)" 
                  :key="amen.id"
                  class="text-[10px] bg-emerald-50 text-[#0B4D34] font-medium px-2 py-0.5 rounded-md border border-emerald-100">
                  {{ amen.name }}
                </span>
              </div>
            </div>

            <!-- Card Bottom Selection Actions -->
            <div class="px-4 pb-4 pt-2 flex items-center justify-between border-t border-slate-50">
              <button 
                type="button"
                @click.stop="openDetails(room.id)"
                class="text-xs font-semibold text-[#0B4D34] hover:underline flex items-center gap-1 cursor-pointer">
                Showcase Details →
              </button>
              
              <button 
                type="button"
                @click.stop="selectRoom(room)"
                :class="selectedRoom?.id === room.id 
                  ? 'bg-[#0B4D34] text-white shadow-sm' 
                  : (room.is_available ? 'bg-emerald-50 text-[#0B4D34] hover:bg-[#0B4D34] hover:text-white' : 'bg-slate-100 text-slate-400')"
                class="px-4 py-1.5 rounded-xl text-xs font-bold transition">
                {{ selectedRoom?.id === room.id ? '✓ Selected' : (room.is_available ? 'Select Room' : 'Unavailable') }}
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Right 1 Col: Dynamic Reservation & Payment Drawer (Screen 4) -->
      <div class="bg-white rounded-3xl shadow-sm border border-slate-100 p-6 space-y-5 sticky top-6 h-fit">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
          <div>
            <h3 class="font-bold text-slate-900 text-base">Booking Summary</h3>
            <p class="text-[11px] text-slate-400">Lock your boutique suite reservation</p>
          </div>
          <span 
            :class="selectedRoom?.is_available ? 'bg-emerald-50 text-[#0B4D34] border-emerald-200' : 'bg-rose-50 text-rose-700 border-rose-200'"
            class="text-xs font-bold px-3 py-1 rounded-full border">
            {{ selectedRoom ? (selectedRoom.is_available ? 'Ready to Reserve' : 'Dates Conflict') : 'Choose a Room' }}
          </span>
        </div>

        <!-- Selected Room Preview Card -->
        <div v-if="selectedRoom" class="p-3.5 bg-slate-50 rounded-2xl border border-slate-200/80 space-y-2">
          <div class="flex items-center gap-3">
            <img 
              :src="selectedRoom.image_url" 
              class="w-14 h-14 rounded-xl object-cover border border-slate-200" 
              alt="Selected Room" />
            <div class="overflow-hidden">
              <h4 class="font-bold text-slate-900 text-xs truncate">{{ selectedRoom.title }}</h4>
              <p class="text-[11px] text-slate-500 font-mono">Room {{ selectedRoom.room_number }} ({{ selectedRoom.category_name }})</p>
              <p class="text-[11px] font-bold text-[#0B4D34] mt-0.5">{{ formatPrice(selectedRoom.price_etb, selectedRoom.price_usd) }} / night</p>
            </div>
          </div>
        </div>
        <div v-else class="p-4 bg-amber-50 border border-amber-200 rounded-2xl text-amber-800 text-xs text-center">
          👈 Please click on any available room card to select it for booking.
        </div>

        <!-- Booking Dates Summary -->
        <div class="space-y-2 text-xs">
          <div class="flex justify-between text-slate-600">
            <span>Check-In Date:</span>
            <span class="font-bold text-slate-900">{{ checkInDate }}</span>
          </div>
          <div class="flex justify-between text-slate-600">
            <span>Check-Out Date:</span>
            <span class="font-bold text-slate-900">{{ checkOutDate }}</span>
          </div>
          <div class="flex justify-between text-slate-600">
            <span>Duration:</span>
            <span class="font-bold text-slate-900">{{ nights }} Night{{ nights > 1 ? 's' : '' }}</span>
          </div>
          <div class="flex justify-between text-slate-900 font-bold pt-2 border-t border-slate-100 text-sm">
            <span>Total Payable:</span>
            <span class="text-[#0B4D34]">{{ selectedRoom ? formatPrice(selectedRoom.price_etb * nights, selectedRoom.price_usd * nights) : '--' }}</span>
          </div>
        </div>

        <!-- Guest Contact Details Form -->
        <div class="space-y-2.5 pt-2 border-t border-slate-100">
          <label class="text-xs font-bold text-slate-700 block">Guest Information</label>
          <input 
            v-model="guestForm.name"
            type="text" 
            placeholder="Full Name (e.g. Almaz Kebede)"
            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-[#0B4D34]" />
          <input 
            v-model="guestForm.email"
            type="email" 
            placeholder="Email Address (for confirmation)"
            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-[#0B4D34]" />
          <input 
            v-model="guestForm.phone"
            type="tel" 
            placeholder="Phone Number (+251 911...)"
            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-[#0B4D34]" />
        </div>

        <!-- Payment Method Selection -->
        <div class="space-y-2 pt-2 border-t border-slate-100">
          <label class="text-xs font-bold text-slate-700 block">Select Payment Workflow</label>
          
          <div class="grid grid-cols-2 gap-2 text-xs">
            <!-- Telebirr (Manual screenshot or direct) -->
            <button 
              type="button"
              @click="paymentMethod = 'telebirr'"
              :class="paymentMethod === 'telebirr' ? 'border-[#0B4D34] bg-emerald-50/50 text-[#0B4D34] font-bold' : 'border-slate-200 text-slate-700'"
              class="p-2.5 rounded-xl border text-left transition flex items-center gap-2">
              <span>📱</span>
              <span>Telebirr</span>
            </button>
            
            <!-- CBE Birr -->
            <button 
              type="button"
              @click="paymentMethod = 'cbe_birr'"
              :class="paymentMethod === 'cbe_birr' ? 'border-[#0B4D34] bg-emerald-50/50 text-[#0B4D34] font-bold' : 'border-slate-200 text-slate-700'"
              class="p-2.5 rounded-xl border text-left transition flex items-center gap-2">
              <span>🏛️</span>
              <span>CBE Birr</span>
            </button>

            <!-- Chapa (Direct Gateway) -->
            <button 
              type="button"
              @click="paymentMethod = 'chapa'"
              :class="paymentMethod === 'chapa' ? 'border-[#0B4D34] bg-emerald-50/50 text-[#0B4D34] font-bold' : 'border-slate-200 text-slate-700'"
              class="p-2.5 rounded-xl border text-left transition flex items-center gap-2 col-span-2">
              <span>💳</span>
              <span>Chapa (Cards / Visa / Mastercard)</span>
            </button>
          </div>

          <!-- Payment Mode Selection -->
          <div class="p-3 bg-slate-50 rounded-xl border border-slate-200/80 space-y-1.5 text-xs">
            <label class="font-semibold text-slate-700 block">Payment Mode:</label>
            <div class="flex items-center gap-4">
              <label class="flex items-center gap-1.5 cursor-pointer">
                <input type="radio" v-model="paymentMode" value="manual_receipt" class="accent-[#0B4D34]" />
                <span>Upload Screenshot/Receipt</span>
              </label>
              <label class="flex items-center gap-1.5 cursor-pointer">
                <input type="radio" v-model="paymentMode" value="gateway" class="accent-[#0B4D34]" />
                <span>Instant Gateway</span>
              </label>
            </div>
          </div>
        </div>

        <!-- Reservation CTA -->
        <button 
          @click="submitBooking"
          :disabled="bookingSubmitting || !selectedRoom || !selectedRoom.is_available"
          class="w-full py-3.5 bg-gradient-to-r from-[#0B4D34] to-[#107550] hover:brightness-110 text-white font-bold text-xs rounded-xl shadow-md transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2 cursor-pointer">
          <span v-if="bookingSubmitting" class="animate-spin text-sm">⏳</span>
          <span>{{ bookingSubmitting ? 'Atomic Lock in Database...' : (selectedRoom?.is_available ? 'Confirm & Reserve Suite' : 'Selected Room Unavailable') }}</span>
        </button>
      </div>
    </div>

    <!-- Payment Screenshot / Receipt Upload Modal (Item 6 Option A) -->
    <div v-if="showReceiptModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-3xl overflow-hidden shadow-2xl max-w-lg w-full p-6 space-y-4 border border-slate-100">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
          <div>
            <h3 class="text-lg font-bold text-slate-900">Upload Payment Proof</h3>
            <p class="text-xs text-slate-500">Booking Reference: <strong class="text-[#0B4D34] font-mono">{{ activeBooking?.reference_code }}</strong></p>
          </div>
          <button @click="showReceiptModal = false" class="text-slate-400 hover:text-slate-600 text-sm">✕</button>
        </div>

        <!-- Hotel Verified Account Transfer Instructions -->
        <div class="p-3 bg-emerald-50 rounded-xl border border-emerald-200 text-xs space-y-1 text-emerald-900">
          <p class="font-bold">🏨 Hotel Account Transfer Details:</p>
          <p v-if="paymentMethod === 'telebirr'">• Telebirr Merchant: <strong>100223</strong> (Ethiopian Boutique Stays)</p>
          <p v-else>• CBE Account: <strong>100023456789</strong> (Commercial Bank of Ethiopia)</p>
          <p>• Amount Due: <strong>ETB {{ activeBooking?.total_price_etb?.toLocaleString() }}</strong></p>
        </div>

        <!-- Drag & Drop Receipt Upload Box -->
        <div 
          @click="$refs.receiptInput.click()"
          class="border-2 border-dashed border-[#0B4D34]/40 hover:border-[#0B4D34] rounded-2xl p-6 text-center cursor-pointer bg-slate-50 transition">
          <input 
            ref="receiptInput" 
            type="file" 
            accept="image/png,image/jpeg,image/webp" 
            @change="onReceiptFileSelected" 
            class="hidden" />
          
          <div v-if="receiptPreview" class="space-y-2">
            <img :src="receiptPreview" class="max-h-48 mx-auto rounded-xl shadow-sm border" alt="Receipt preview" />
            <p class="text-xs text-emerald-600 font-semibold">✓ Image selected: {{ receiptFile?.name }}</p>
          </div>
          <div v-else class="space-y-2">
            <div class="text-3xl">🧾</div>
            <p class="text-xs font-semibold text-slate-700">Click or drag & drop payment screenshot here</p>
            <p class="text-[11px] text-slate-400">PNG, JPG or WebP up to 10MB</p>
          </div>
        </div>

        <div class="flex gap-2 pt-2">
          <button 
            type="button" 
            @click="uploadReceiptProof"
            :disabled="!receiptFile || uploadingReceipt"
            class="flex-1 py-3 bg-[#0B4D34] hover:bg-[#064E3B] text-white text-xs font-bold rounded-xl shadow transition disabled:opacity-50">
            {{ uploadingReceipt ? 'Uploading & Notifying Admin...' : 'Submit Receipt for Verification' }}
          </button>
          <button 
            type="button" 
            @click="skipReceiptForNow"
            class="px-4 py-3 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl transition">
            Upload Later
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';

const router = useRouter();
const emit = defineEmits(['view-details', 'booking-created', 'open-auth']);

function openDetails(roomId) {
  emit('view-details', roomId);
  if (router) {
    router.push(`/room/${roomId}`);
  }
}

const selectedCategory = ref('all');
const searchQuery = ref('');
const currency = ref('ETB');
const rooms = ref([]);
const loading = ref(true);
const favorites = ref(new Set());

// Date selection logic (defaults to today and day-after-tomorrow)
const today = new Date();
const todayDateStr = computed(() => today.toISOString().split('T')[0]);

const tomorrow = new Date();
tomorrow.setDate(tomorrow.getDate() + 1);

const dayAfter = new Date();
dayAfter.setDate(dayAfter.getDate() + 3);

const checkInDate = ref(tomorrow.toISOString().split('T')[0]);
const checkOutDate = ref(dayAfter.toISOString().split('T')[0]);

const minCheckOutDateStr = computed(() => {
  const d = new Date(checkInDate.value);
  d.setDate(d.getDate() + 1);
  return d.toISOString().split('T')[0];
});

const nights = computed(() => {
  const d1 = new Date(checkInDate.value);
  const d2 = new Date(checkOutDate.value);
  const diff = Math.round((d2 - d1) / (1000 * 60 * 60 * 24));
  return Math.max(1, diff);
});

const selectedRoom = ref(null);
const paymentMethod = ref('telebirr');
const paymentMode = ref('manual_receipt');
const bookingSubmitting = ref(false);

// Guest Form
const guestForm = ref({
  name: '',
  email: '',
  phone: ''
});

// Receipt Upload Modal
const showReceiptModal = ref(false);
const activeBooking = ref(null);
const receiptFile = ref(null);
const receiptPreview = ref(null);
const uploadingReceipt = ref(false);

function getAuthHeaders() {
  const token = localStorage.getItem('hotel_auth_token');
  return token ? { 'Authorization': `Bearer ${token}` } : {};
}

function toggleCurrency() {
  currency.value = currency.value === 'ETB' ? 'USD' : 'ETB';
}

function formatPrice(etb, usd) {
  if (currency.value === 'ETB') {
    return `${etb?.toLocaleString()} ETB`;
  }
  return `$${usd}`;
}

function onDateChange() {
  if (new Date(checkOutDate.value) <= new Date(checkInDate.value)) {
    const next = new Date(checkInDate.value);
    next.setDate(next.getDate() + 1);
    checkOutDate.value = next.toISOString().split('T')[0];
  }
  fetchRooms();
}

function selectRoom(room) {
  selectedRoom.value = room;
}

// Functional Favorites (Item 2)
async function fetchFavorites() {
  const token = localStorage.getItem('hotel_auth_token');
  if (!token) return;

  try {
    const res = await fetch('/api/v1/favorites', {
      headers: getAuthHeaders()
    });
    if (res.ok) {
      const data = await res.json();
      favorites.value = new Set(data.favorites || []);
    }
  } catch (err) {
    console.error('Failed to load favorites:', err);
  }
}

async function toggleFavorite(room) {
  const token = localStorage.getItem('hotel_auth_token');
  if (!token) {
    alert('Please sign in to save your favorite rooms.');
    emit('open-auth');
    return;
  }

  const isFav = favorites.value.has(room.id);
  // Optimistic UI update
  if (isFav) {
    favorites.value.delete(room.id);
  } else {
    favorites.value.add(room.id);
  }

  try {
    const res = await fetch(`/api/v1/favorites/${room.id}`, {
      method: isFav ? 'DELETE' : 'POST',
      headers: getAuthHeaders()
    });
    if (!res.ok) {
      // Revert if error
      if (isFav) favorites.value.add(room.id);
      else favorites.value.delete(room.id);
    }
  } catch (err) {
    // Revert
    if (isFav) favorites.value.add(room.id);
    else favorites.value.delete(room.id);
  }
}

async function fetchRooms() {
  loading.value = true;
  try {
    let url = `/api/v1/rooms?category=${selectedCategory.value}&check_in=${checkInDate.value}&check_out=${checkOutDate.value}`;
    if (searchQuery.value) {
      url += `&search=${encodeURIComponent(searchQuery.value)}`;
    }

    const res = await fetch(url);
    const data = await res.json();
    rooms.value = data.rooms || [];

    // Retain selected room if still valid, otherwise pick first available
    if (selectedRoom.value) {
      const updated = rooms.value.find(r => r.id === selectedRoom.value.id);
      selectedRoom.value = updated || rooms.value[0] || null;
    } else if (rooms.value.length > 0) {
      const firstAvailable = rooms.value.find(r => r.is_available) || rooms.value[0];
      selectedRoom.value = firstAvailable;
    }
  } catch (error) {
    console.error('Failed to load rooms:', error);
  } finally {
    loading.value = false;
  }
}

async function submitBooking() {
  if (!selectedRoom.value) {
    alert('Please choose an available room from the catalog.');
    return;
  }

  if (!selectedRoom.value.is_available) {
    alert('Sorry, this room is no longer available for your selected dates. Please choose another room.');
    return;
  }

  bookingSubmitting.value = true;
  try {
    const payload = {
      room_id: selectedRoom.value.id,
      check_in_date: checkInDate.value,
      check_out_date: checkOutDate.value,
      adults: 2,
      children: 0,
      payment_method: paymentMethod.value,
      payment_mode: paymentMode.value,
      guest_name: guestForm.value.name.trim() || 'Valued Guest',
      guest_email: guestForm.value.email.trim() || 'guest@ethiopianhotel.com',
      guest_phone: guestForm.value.phone.trim() || '+251 911 000000'
    };

    const res = await fetch('/api/v1/bookings', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        ...getAuthHeaders()
      },
      body: JSON.stringify(payload)
    });

    const data = await res.json();
    if (!res.ok) {
      throw new Error(data.error || 'Failed to complete booking');
    }

    activeBooking.value = data.booking;

    if (paymentMode.value === 'manual_receipt') {
      showReceiptModal.value = true;
    } else {
      alert(`🎉 Reservation created! Reference: ${data.booking.reference_code}. Opening your digital receipt...`);
      emit('booking-created', data.booking);
    }

    // Refresh rooms to reflect new hold
    fetchRooms();
  } catch (err) {
    alert(err.message);
  } finally {
    bookingSubmitting.value = false;
  }
}

function onReceiptFileSelected(e) {
  const file = e.target.files?.[0];
  if (!file) return;
  receiptFile.value = file;
  receiptPreview.value = URL.createObjectURL(file);
}

async function uploadReceiptProof() {
  if (!receiptFile.value || !activeBooking.value) return;

  uploadingReceipt.value = true;
  const formData = new FormData();
  formData.append('receipt', receiptFile.value);
  formData.append('booking_id', activeBooking.value.id);
  formData.append('payment_id', activeBooking.value.payment_id);

  try {
    const res = await fetch('/api/v1/payments/upload-receipt', {
      method: 'POST',
      headers: getAuthHeaders(),
      body: formData
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.error || 'Upload failed');

    alert('✓ Receipt uploaded successfully! Staff will verify your payment within our 20-minute review target.');
    showReceiptModal.value = false;
    emit('booking-created', activeBooking.value);
  } catch (err) {
    alert('Receipt upload error: ' + err.message);
  } finally {
    uploadingReceipt.value = false;
  }
}

function skipReceiptForNow() {
  showReceiptModal.value = false;
  if (activeBooking.value) {
    emit('booking-created', activeBooking.value);
  }
}

onMounted(() => {
  // Autofill user info if authenticated
  const savedUser = localStorage.getItem('hotel_user');
  if (savedUser) {
    try {
      const u = JSON.parse(savedUser);
      guestForm.value.name = u.full_name || '';
      guestForm.value.email = u.email || '';
      guestForm.value.phone = u.phone || '';
    } catch (e) {}
  }

  fetchFavorites();
  fetchRooms();
});
</script>
