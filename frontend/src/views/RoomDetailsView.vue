<template>
  <div class="space-y-8 max-w-6xl mx-auto">
    <!-- Back to catalog button -->
    <button 
      @click="handleBack"
      class="text-xs font-semibold text-[#0B4D34] hover:underline flex items-center gap-1.5 cursor-pointer">
      ← Back to Room Collection
    </button>

    <!-- Main Showcase Layout (Screen 5) -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
      <!-- Left 2 Cols: Media Gallery & Room Info -->
      <div class="lg:col-span-2 space-y-6">
        <!-- Hero Gallery -->
        <div class="space-y-3">
          <div class="w-full h-80 sm:h-96 rounded-3xl overflow-hidden shadow-md border border-slate-100 bg-slate-100">
            <img 
              :src="activeHeroImage || room.image_url" 
              class="w-full h-full object-cover transition-all duration-300" 
              alt="Room Hero Photo" />
          </div>

          <!-- Thumbnail Gallery -->
          <div class="grid grid-cols-4 gap-3">
            <button
              v-for="(img, idx) in galleryImages"
              :key="idx"
              @click="activeHeroImage = img"
              :class="activeHeroImage === img ? 'ring-3 ring-[#0B4D34]' : 'opacity-80 hover:opacity-100'"
              class="h-20 sm:h-24 rounded-2xl overflow-hidden transition-all shadow-sm">
              <img :src="img" class="w-full h-full object-cover" alt="Thumbnail" />
            </button>
          </div>
        </div>

        <!-- Room Title & Amenities -->
        <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100 space-y-4">
          <div class="flex items-center justify-between">
            <div>
              <h2 class="text-2xl sm:text-3xl font-bold text-slate-900 font-serif">
                {{ room.title }}
              </h2>
              <div class="flex items-center text-amber-500 text-sm mt-1">
                <span>★★★★★</span>
                <span class="text-slate-500 text-xs ml-2 font-medium">({{ room.avg_rating || '5.0' }} • Verified Luxury Suite)</span>
              </div>
            </div>
            <span class="px-3.5 py-1.5 rounded-full text-xs font-bold uppercase tracking-wider bg-emerald-50 text-[#0B4D34] border border-emerald-100">
              {{ room.category_name }} • Room {{ room.room_number }}
            </span>
          </div>

          <!-- Amenity Badges (Screen 5) -->
          <div class="flex flex-wrap gap-2.5 pt-2">
            <span 
              v-for="amen in room.amenities" 
              :key="amen.id"
              class="flex items-center gap-1.5 px-3 py-1.5 rounded-full border border-emerald-200 text-xs font-semibold text-[#0B4D34] bg-emerald-50/50">
              <span>✓</span> {{ amen.name }}
            </span>
            <span v-if="!room.amenities || room.amenities.length === 0" class="text-xs text-slate-400">
              Standard hotel luxury amenities included.
            </span>
          </div>

          <p class="text-sm text-slate-600 leading-relaxed pt-2">
            Experience traditional Ethiopian luxury with bespoke timber craft, hand-woven cotton textiles, and breathtaking courtyard garden panoramas. Each suite is equipped with luxury king-size bedding, espresso bar, and 24-hour dedicated butler service.
          </p>
        </div>
      </div>

      <!-- Right 1 Col: Floating Sticky Booking Widget (Screen 5) -->
      <div class="bg-white rounded-3xl shadow-lg border border-slate-100 p-6 space-y-5 h-fit sticky top-6">
        <div>
          <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">Price per Night</span>
          <div class="text-3xl font-extrabold text-[#0B4D34] mt-1 font-serif">
            {{ room.price_etb?.toLocaleString() }} <span class="text-sm font-sans font-semibold text-slate-500">ETB / night</span>
          </div>
          <span class="text-xs text-slate-400">Equivalent to approx. ${{ room.price_usd }} USD</span>
        </div>

        <div class="space-y-3 pt-2">
          <!-- Check-in & Check-out -->
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="text-[11px] font-bold text-slate-600 block mb-1">Check-In</label>
              <input 
                v-model="checkInDate" 
                type="date" 
                class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-[#0B4D34]" />
            </div>
            <div>
              <label class="text-[11px] font-bold text-slate-600 block mb-1">Check-Out</label>
              <input 
                v-model="checkOutDate" 
                type="date" 
                class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-[#0B4D34]" />
            </div>
          </div>

          <!-- Total calculation -->
          <div class="p-3 bg-slate-50 rounded-xl text-xs space-y-1">
            <div class="flex justify-between text-slate-600">
              <span>Duration:</span>
              <span class="font-bold text-slate-800">{{ nights }} Night{{ nights > 1 ? 's' : '' }}</span>
            </div>
            <div class="flex justify-between text-slate-900 font-bold pt-1 border-t border-slate-200">
              <span>Total Stay:</span>
              <span class="text-[#0B4D34]">ETB {{ (room.price_etb * nights)?.toLocaleString() }}</span>
            </div>
          </div>

          <!-- Guest Selectors -->
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="text-[11px] font-bold text-slate-600 block mb-1">Guests (Adults)</label>
              <select 
                v-model="adults" 
                class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-[#0B4D34]">
                <option value="1">1 Adult</option>
                <option value="2">2 Adults</option>
                <option value="3">3 Adults</option>
                <option value="4">4 Adults</option>
              </select>
            </div>
            <div>
              <label class="text-[11px] font-bold text-slate-600 block mb-1">Children</label>
              <select 
                v-model="children" 
                class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-[#0B4D34]">
                <option value="0">None</option>
                <option value="1">1 Child</option>
                <option value="2">2 Children</option>
              </select>
            </div>
          </div>
        </div>

        <!-- Book Now Button (Screen 5) -->
        <button 
          @click="handleBookNow"
          :disabled="bookingSubmitting"
          class="w-full py-4 bg-gradient-to-r from-[#0B4D34] to-[#107550] hover:brightness-110 text-white font-bold text-sm rounded-2xl shadow-lg transition transform active:scale-98 disabled:opacity-50 cursor-pointer">
          {{ bookingSubmitting ? 'Checking Availability & Locking...' : 'Book Now' }}
        </button>

        <p class="text-[11px] text-center text-slate-400">
          Instant confirmation with zero booking fees • Free cancellation up to 48 hours prior
        </p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';

const route = useRoute();
const router = useRouter();

const props = defineProps({
  roomId: {
    type: String,
    default: ''
  }
});

const emit = defineEmits(['back', 'booking-created']);

const effectiveRoomId = computed(() => props.roomId || route?.params?.id || 'rm-101');

function handleBack() {
  emit('back');
  if (router) {
    router.push('/rooms');
  }
}

const room = ref({
  id: 'rm-101',
  room_number: '101',
  title: 'Executive Ethiopian Suite',
  category_name: 'Suite',
  price_etb: 15400,
  price_usd: 240,
  amenities: []
});

const today = new Date();
const tomorrow = new Date();
tomorrow.setDate(tomorrow.getDate() + 1);
const dayAfter = new Date();
dayAfter.setDate(dayAfter.getDate() + 3);

const checkInDate = ref(tomorrow.toISOString().split('T')[0]);
const checkOutDate = ref(dayAfter.toISOString().split('T')[0]);
const adults = ref('2');
const children = ref('0');
const bookingSubmitting = ref(false);

const nights = computed(() => {
  const d1 = new Date(checkInDate.value);
  const d2 = new Date(checkOutDate.value);
  const diff = Math.round((d2 - d1) / (1000 * 60 * 60 * 24));
  return Math.max(1, diff);
});

const galleryImages = ref([
  'https://images.unsplash.com/photo-1591088398332-8a7791972843?w=800&fit=crop',
  'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?w=800&fit=crop',
  'https://images.unsplash.com/photo-1590490360182-c33d57733427?w=800&fit=crop',
  'https://images.unsplash.com/photo-1618773928121-c32242e63f39?w=800&fit=crop'
]);

const activeHeroImage = ref(galleryImages.value[0]);

function getAuthHeaders() {
  const token = localStorage.getItem('hotel_auth_token');
  return token ? { 'Authorization': `Bearer ${token}` } : {};
}

async function fetchRoom() {
  try {
    const res = await fetch(`/api/v1/rooms/${effectiveRoomId.value}`);
    const data = await res.json();
    if (data.room) {
      room.value = {
        ...data.room,
        price_etb: parseFloat(data.room.price_etb),
        price_usd: parseFloat(data.room.price_usd)
      };
      if (data.room.images && data.room.images.length > 0) {
        galleryImages.value = data.room.images;
        activeHeroImage.value = data.room.images[0];
      } else if (data.room.image_url) {
        activeHeroImage.value = data.room.image_url;
      }
    }
  } catch (err) {
    console.error('Error fetching room details:', err);
  }
}

async function handleBookNow() {
  bookingSubmitting.value = true;
  try {
    const savedUser = localStorage.getItem('hotel_user');
    let u = { full_name: 'Valued Guest', email: 'guest@ethiopianhotel.com', phone: '+251 911 000000' };
    if (savedUser) {
      try { u = JSON.parse(savedUser); } catch (e) {}
    }

    const payload = {
      room_id: room.value.id,
      check_in_date: checkInDate.value,
      check_out_date: checkOutDate.value,
      adults: parseInt(adults.value),
      children: parseInt(children.value),
      payment_method: 'telebirr',
      payment_mode: 'manual_receipt',
      guest_name: u.full_name,
      guest_email: u.email,
      guest_phone: u.phone
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
    if (!res.ok) throw new Error(data.error || 'Failed to book');

    alert(`🎉 Reservation created! Reference code: ${data.booking.reference_code}`);
    emit('booking-created', data.booking);
  } catch (err) {
    alert(err.message);
  } finally {
    bookingSubmitting.value = false;
  }
}

onMounted(() => {
  fetchRoom();
});
</script>
