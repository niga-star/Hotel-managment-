<template>
  <div class="space-y-16 pb-12">
    <!-- Hero Section with Luxury Backdrop -->
    <section class="relative rounded-3xl overflow-hidden shadow-2xl min-h-[540px] flex items-center bg-slate-900 text-white">
      <!-- Background Image with Dark Emerald Overlay -->
      <img 
        src="https://images.unsplash.com/photo-1566073771259-6a8506099945?w=1600&auto=format&fit=crop&q=80" 
        alt="Ethiopian Luxury Resort" 
        class="absolute inset-0 w-full h-full object-cover opacity-35 scale-105 transition-transform duration-1000" />
      
      <div class="absolute inset-0 bg-gradient-to-r from-[#064E3B]/95 via-[#064E3B]/80 to-transparent"></div>

      <!-- Hero Content -->
      <div class="relative z-10 max-w-3xl px-6 sm:px-12 py-16 space-y-6">
        <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-white/10 backdrop-blur-md border border-[#D4A329]/40 text-[#D4A329] text-xs font-semibold">
          <span>✨</span>
          <span>Authentic Ethiopian Luxury Hospitality</span>
        </div>

        <h1 class="text-3xl sm:text-5xl lg:text-6xl font-serif font-bold tracking-tight text-white leading-tight">
          Where Ancient Royalty Meets Five-Star Indulgence
        </h1>

        <p class="text-sm sm:text-base text-emerald-100/90 leading-relaxed font-light">
          Nestled among breathtaking Ethiopian scenery, our luxury boutique resort offers curated suites, bespoke spa rituals, cultural culinary excellence, and touchless digital reservations.
        </p>

        <!-- Availability Quick Bar -->
        <div class="pt-2">
          <div class="bg-white/95 backdrop-blur-md p-4 rounded-2xl shadow-xl text-slate-800 flex flex-col md:flex-row items-stretch md:items-center gap-4 border border-white/40">
            <!-- Check In -->
            <div class="flex-1 space-y-1">
              <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500">Check-in</label>
              <input 
                v-model="heroCheckIn" 
                type="date" 
                :min="todayStr"
                class="w-full text-xs font-semibold bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-2 text-slate-800 focus:outline-none focus:border-[#0B4D34]" />
            </div>

            <!-- Check Out -->
            <div class="flex-1 space-y-1">
              <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500">Check-out</label>
              <input 
                v-model="heroCheckOut" 
                type="date" 
                :min="heroCheckIn || todayStr"
                class="w-full text-xs font-semibold bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-2 text-slate-800 focus:outline-none focus:border-[#0B4D34]" />
            </div>

            <!-- Room Category -->
            <div class="flex-1 space-y-1">
              <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500">Suite Tier</label>
              <select 
                v-model="heroCategory"
                class="w-full text-xs font-semibold bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-2 text-slate-800 focus:outline-none focus:border-[#0B4D34]">
                <option value="all">All Available Suites</option>
                <option value="suite">Presidential & Royal Suite</option>
                <option value="deluxe">Deluxe Balcony Suite</option>
                <option value="standard">Standard Heritage Room</option>
              </select>
            </div>

            <!-- Search CTA -->
            <div class="flex items-end pt-1 md:pt-0">
              <button 
                @click="onSearchHero"
                class="w-full md:w-auto px-6 py-2.5 bg-[#0B4D34] hover:bg-[#064E3B] text-[#D4A329] font-bold text-xs rounded-xl shadow-lg transition flex items-center justify-center gap-2 cursor-pointer border border-[#D4A329]/30">
                <span>🔍 Check Rooms</span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Key Distinction Pillars (Why Choose Us) -->
    <section class="space-y-8">
      <div class="text-center max-w-2xl mx-auto space-y-2">
        <span class="text-xs font-bold uppercase tracking-widest text-[#D4A329]">Excellence Defined</span>
        <h2 class="text-2xl sm:text-3xl font-serif font-bold text-slate-900">An Unrivaled Ethiopian Experience</h2>
        <p class="text-xs sm:text-sm text-slate-500">Every touchpoint is designed to deliver comfort, serenity, and memorable cultural authenticity.</p>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        <div 
          v-for="feature in features" 
          :key="feature.title"
          class="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm hover:shadow-md transition-all space-y-3 group">
          <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-[#0B4D34] flex items-center justify-center text-2xl group-hover:scale-110 transition-transform">
            {{ feature.icon }}
          </div>
          <h3 class="font-bold text-slate-900 text-sm">{{ feature.title }}</h3>
          <p class="text-xs text-slate-500 leading-relaxed">{{ feature.description }}</p>
        </div>
      </div>
    </section>

    <!-- Curated Suites Preview Grid -->
    <section class="space-y-8">
      <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
        <div>
          <span class="text-xs font-bold uppercase tracking-widest text-[#0B4D34]">Handcrafted Accommodations</span>
          <h2 class="text-2xl sm:text-3xl font-serif font-bold text-slate-900 mt-1">Featured Boutique Suites</h2>
        </div>
        <router-link 
          to="/rooms" 
          class="inline-flex items-center gap-1.5 text-xs font-bold text-[#0B4D34] hover:text-[#064E3B] transition">
          <span>View All 15+ Rooms</span>
          <span>→</span>
        </router-link>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div 
          v-for="room in previewRooms" 
          :key="room.id"
          class="bg-white rounded-3xl overflow-hidden border border-slate-100 shadow-sm hover:shadow-lg transition-all group flex flex-col justify-between">
          <div class="relative h-56 overflow-hidden">
            <img 
              :src="room.image" 
              class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" 
              :alt="room.title" />
            <div class="absolute top-3 left-3 px-3 py-1 bg-black/60 backdrop-blur-md text-white text-[10px] font-bold uppercase tracking-wider rounded-full">
              {{ room.category }}
            </div>
            <div class="absolute bottom-3 right-3 px-3 py-1 bg-[#0B4D34] text-[#D4A329] text-xs font-bold rounded-full shadow">
              {{ room.price }}
            </div>
          </div>

          <div class="p-6 space-y-4">
            <div>
              <div class="flex items-center text-amber-500 text-xs mb-1">
                <span>★★★★★</span>
                <span class="text-slate-400 text-[11px] ml-1.5">(4.9/5 • 80+ Reviews)</span>
              </div>
              <h3 class="font-serif font-bold text-slate-900 text-lg">{{ room.title }}</h3>
              <p class="text-xs text-slate-500 mt-1 line-clamp-2">{{ room.description }}</p>
            </div>

            <div class="flex items-center justify-between pt-3 border-t border-slate-100 text-xs">
              <span class="text-slate-500 font-medium">{{ room.capacity }}</span>
              <router-link 
                :to="'/room/' + room.id"
                class="font-bold text-[#0B4D34] hover:underline flex items-center gap-1">
                Explore Suite →
              </router-link>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Cultural Dining & Resort Wellness Banner -->
    <section class="bg-gradient-to-r from-[#064E3B] to-[#0B4D34] rounded-3xl p-8 sm:p-12 text-white shadow-xl grid grid-cols-1 lg:grid-cols-2 gap-8 items-center">
      <div class="space-y-4">
        <span class="text-xs font-bold uppercase tracking-widest text-[#D4A329]">Cultural Immersion</span>
        <h2 class="text-2xl sm:text-4xl font-serif font-bold leading-tight">
          Traditional Ethiopian Coffee Ritual & Gourmet Cuisine
        </h2>
        <p class="text-xs sm:text-sm text-emerald-100/90 leading-relaxed">
          Savor organic Ethiopian Yirgacheffe and Sidama single-origin coffee roasted fresh in front of you with frankincense aromatherapy. Our master chefs prepare exquisite traditional Tibs, Doro Wat, and gourmet international dishes.
        </p>
        <div class="flex flex-wrap gap-4 pt-2">
          <router-link 
            to="/rooms"
            class="px-5 py-2.5 bg-[#D4A329] hover:bg-yellow-500 text-slate-900 font-bold text-xs rounded-xl shadow transition">
            Reserve Your Stay
          </router-link>
          <div class="flex items-center gap-2 text-xs text-emerald-200">
            <span>✓</span>
            <span>Complimentary breakfast included in all suites</span>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-2 gap-4">
        <img 
          src="https://images.unsplash.com/photo-1544816155-12df9643f363?w=600&auto=format&fit=crop&q=80" 
          alt="Coffee Ceremony" 
          class="rounded-2xl h-44 sm:h-52 w-full object-cover shadow-md" />
        <img 
          src="https://images.unsplash.com/photo-1571896349842-33c89424de2d?w=600&auto=format&fit=crop&q=80" 
          alt="Luxury Pool" 
          class="rounded-2xl h-44 sm:h-52 w-full object-cover shadow-md mt-6" />
      </div>
    </section>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useRouter } from 'vue-router';

const router = useRouter();

const today = new Date();
const todayStr = computed(() => today.toISOString().split('T')[0]);

const tomorrow = new Date();
tomorrow.setDate(tomorrow.getDate() + 1);
const dayAfter = new Date();
dayAfter.setDate(dayAfter.getDate() + 3);

const heroCheckIn = ref(tomorrow.toISOString().split('T')[0]);
const heroCheckOut = ref(dayAfter.toISOString().split('T')[0]);
const heroCategory = ref('all');

function onSearchHero() {
  router.push({
    path: '/rooms',
    query: {
      check_in: heroCheckIn.value,
      check_out: heroCheckOut.value,
      category: heroCategory.value
    }
  });
}

const features = [
  {
    icon: '👑',
    title: 'Heritage Royal Luxury',
    description: 'Custom carved wooden finishes and authentic handwoven Ethiopian Tibeb textile accents.'
  },
  {
    icon: '🌿',
    title: 'Natural Herbal Spa',
    description: 'Traditional steam bath, eucalyptus body scrubs, and revitalizing holistic massages.'
  },
  {
    icon: '☕',
    title: 'Daily Coffee Ceremony',
    description: 'Authentic 3-round Buna ceremony with freshly roasted highland beans and freshly baked Ambasha.'
  },
  {
    icon: '⚡',
    title: 'Touchless QR Check-in',
    description: 'Check in in seconds with scannable digital receipts, Telebirr, CBE Birr & Chapa payments.'
  }
];

const previewRooms = [
  {
    id: 'rm-101',
    title: 'Executive Ethiopian Suite 101',
    category: 'Suite',
    price: 'ETB 15,400 / night',
    description: 'Spacious master suite featuring king canopy bed, panoramic lake views, and private jacuzzi.',
    capacity: '2 Adults • 1 Child',
    image: 'https://images.unsplash.com/photo-1591088398332-8a7791972843?w=800&fit=crop'
  },
  {
    id: 'rm-102',
    title: 'Deluxe Balcony Suite 102',
    category: 'Deluxe',
    price: 'ETB 9,800 / night',
    description: 'Sunlit deluxe room with private sunset balcony, rainfall shower, and plush Ethiopian cotton linens.',
    capacity: '2 Guests',
    image: 'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?w=800&fit=crop'
  },
  {
    id: 'rm-201',
    title: 'Presidential Royal Villa 201',
    category: 'Suite',
    price: 'ETB 24,000 / night',
    description: 'Two-floor luxury residence with dedicated private butler, private lounge, and private infinity pool.',
    capacity: 'Up to 4 Guests',
    image: 'https://images.unsplash.com/photo-1618773928121-c32242e63f39?w=800&fit=crop'
  }
];
</script>
