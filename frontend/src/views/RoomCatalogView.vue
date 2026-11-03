<template>
  <div class="space-y-6">
    <!-- Top Filter Bar & Real Date Selection (Screen 4) -->
    <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 flex flex-col xl:flex-row xl:items-center xl:justify-between gap-4">
      <!-- Category Pills -->
      <div class="flex items-center space-x-2 overflow-x-auto pb-1 xl:pb-0">
        <button 
          @click="selectedCategory = 'all'; fetchRooms()"
          :class="[
            selectedCategory === 'all'
              ? 'bg-[#0B4D34] text-white shadow-sm font-bold'
              : 'bg-slate-100 text-slate-700 hover:bg-slate-200 font-medium'
          ]"
          class="px-5 py-2.5 rounded-full text-xs capitalize transition flex-shrink-0 cursor-pointer">
          🏨 All Rooms
        </button>
        <button 
          v-for="cat in catalogCategories"
          :key="cat.id"
          @click="selectedCategory = cat.slug || cat.name; fetchRooms()"
          :class="[
            selectedCategory === (cat.slug || cat.name)
              ? 'bg-[#0B4D34] text-white shadow-sm font-bold'
              : 'bg-slate-100 text-slate-700 hover:bg-slate-200 font-medium'
          ]"
          class="px-5 py-2.5 rounded-full text-xs capitalize transition flex-shrink-0 cursor-pointer">
          {{ cat.name }}
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

        <!-- Guests Counter Selector -->
        <div class="flex items-center gap-1.5 pl-2 border-l border-slate-200">
          <span>👥</span>
          <select 
            v-model="guestCount.adults" 
            class="bg-white border border-slate-200 rounded-lg px-2 py-1 text-xs font-semibold text-slate-700 focus:outline-none focus:border-[#0B4D34]">
            <option :value="1">1 Adult</option>
            <option :value="2">2 Adults</option>
            <option :value="3">3 Adults</option>
            <option :value="4">4 Adults</option>
          </select>
          <select 
            v-model="guestCount.children" 
            class="bg-white border border-slate-200 rounded-lg px-2 py-1 text-xs font-semibold text-slate-700 focus:outline-none focus:border-[#0B4D34]">
            <option :value="0">0 Child</option>
            <option :value="1">1 Child</option>
            <option :value="2">2 Children</option>
            <option :value="3">3 Children</option>
          </select>
        </div>
      </div>

      <!-- Search Input, Currency Switcher & Dedicated Booking Summary Button -->
      <div class="flex items-center flex-wrap gap-2.5 w-full xl:w-auto">
        <div class="relative flex-1 xl:w-56">
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
          class="px-3.5 py-2 border border-slate-200 rounded-full text-xs font-semibold hover:bg-slate-50 transition text-slate-700 whitespace-nowrap cursor-pointer">
          {{ currency === 'ETB' ? '🇪🇹 ETB' : '💵 USD' }}
        </button>

        <!-- Dedicated Booking Summary Trigger Button -->
        <button 
          @click="isSummaryOpen = true"
          :class="selectedRoom 
            ? 'bg-[#0B4D34] hover:bg-[#073826] text-white shadow-md border-emerald-700' 
            : 'bg-white hover:bg-slate-50 text-slate-700 border-slate-200'"
          class="px-4 py-2 border rounded-full text-xs font-bold transition flex items-center gap-2 cursor-pointer whitespace-nowrap flex-shrink-0">
          <span>📋</span>
          <span v-if="selectedRoom" class="flex items-center gap-1.5">
            <span>Rm {{ selectedRoom.room_number }}</span>
            <span class="text-yellow-300 font-mono">• {{ formatPrice(finalPriceEtb, finalPriceUsd) }}</span>
          </span>
          <span v-else>Booking Summary</span>
          <span 
            v-if="selectedRoom" 
            class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
        </button>
      </div>
    </div>

    <!-- Full-Width Responsive Rooms Catalog (3 Columns Grid) -->
    <div>
      <div v-if="loading" class="py-20 text-center text-slate-400 space-y-2">
        <div class="text-3xl animate-bounce">🛎️</div>
        <p class="text-xs">Checking real-time availability in MySQL database...</p>
      </div>

      <div v-else-if="rooms.length === 0" class="py-20 text-center text-slate-400 bg-white rounded-2xl border border-slate-100 p-8 space-y-3">
        <div class="text-4xl">🛏️</div>
        <h4 class="text-base font-bold text-slate-700">No Rooms Found</h4>
        <p class="text-xs text-slate-400">Try adjusting your category filter, dates, or search term.</p>
      </div>

      <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
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
          <div class="relative h-52 overflow-hidden bg-slate-100">
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

            <!-- Functional Like / Favorite Heart Button -->
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
              :disabled="!room.is_available"
              :class="selectedRoom?.id === room.id 
                ? 'bg-[#0B4D34] text-white shadow-sm' 
                : (room.is_available ? 'bg-gradient-to-r from-[#0B4D34] to-[#107550] text-white hover:brightness-110 shadow-sm' : 'bg-slate-100 text-slate-400 cursor-not-allowed')"
              class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 cursor-pointer">
              <span>{{ selectedRoom?.id === room.id ? '✓ View Summary' : (room.is_available ? 'Book Room' : 'Unavailable') }}</span>
              <span v-if="room.is_available">→</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Slide-over Drawer for Booking Summary (Accessible via Button) -->
    <div 
      v-if="isSummaryOpen" 
      class="fixed inset-0 z-50 overflow-hidden">
      <!-- Backdrop overlay -->
      <div 
        @click="isSummaryOpen = false" 
        class="fixed inset-0 bg-black/60 backdrop-blur-xs transition-opacity animate-fadeIn"></div>

      <!-- Slide-over Panel -->
      <div class="fixed inset-y-0 right-0 max-w-full flex pl-6 sm:pl-10">
        <div class="w-screen max-w-md bg-white shadow-2xl flex flex-col relative z-10 animate-slideIn">
          <!-- Drawer Header -->
          <div class="p-5 border-b border-slate-100 flex items-center justify-between bg-slate-50/70">
            <div>
              <h3 class="font-bold text-slate-900 text-base flex items-center gap-2">
                <span>📋</span>
                <span>Booking Summary</span>
              </h3>
              <p class="text-[11px] text-slate-500">Lock your boutique suite reservation</p>
            </div>
            <div class="flex items-center gap-2">
              <span 
                :class="selectedRoom?.is_available ? 'bg-emerald-50 text-[#0B4D34] border-emerald-200' : 'bg-rose-50 text-rose-700 border-rose-200'"
                class="text-xs font-bold px-3 py-1 rounded-full border">
                {{ selectedRoom ? (selectedRoom.is_available ? 'Ready to Reserve' : 'Dates Conflict') : 'Choose a Room' }}
              </span>
              <button 
                @click="isSummaryOpen = false"
                aria-label="Close Summary"
                class="w-8 h-8 rounded-full bg-slate-200 hover:bg-slate-300 text-slate-700 flex items-center justify-center text-sm transition cursor-pointer">
                ✕
              </button>
            </div>
          </div>

          <!-- Drawer Body (Scrollable) -->
          <div class="flex-1 overflow-y-auto p-5 space-y-4">
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
              👈 Please choose an available room from the catalog to proceed.
            </div>

            <!-- Booking Dates Summary -->
            <div class="p-3.5 bg-slate-50 rounded-2xl border border-slate-200/80 space-y-2 text-xs">
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
              <div class="flex justify-between text-slate-600">
                <span>Guests:</span>
                <span class="font-bold text-slate-900">{{ guestCount.adults }} Adult{{ guestCount.adults > 1 ? 's' : '' }}{{ guestCount.children > 0 ? `, ${guestCount.children} Child` : '' }}</span>
              </div>
            </div>

            <!-- Meal Plan Selection -->
            <div class="space-y-2 pt-2 border-t border-slate-100">
              <label class="text-xs font-bold text-slate-700 block">🍽️ Dining & Meal Plan</label>
              <div class="grid grid-cols-2 gap-2 text-xs">
                <button 
                  type="button" 
                  @click="selectedMealPlan = 'room_only'"
                  :class="selectedMealPlan === 'room_only' ? 'border-[#0B4D34] bg-emerald-50/60 text-[#0B4D34] font-bold' : 'border-slate-200 text-slate-600'"
                  class="p-2 rounded-xl border text-left transition cursor-pointer">
                  <span class="block font-bold">Room Only</span>
                  <span class="text-[10px] text-slate-400">+ETB 0</span>
                </button>
                <button 
                  type="button" 
                  @click="selectedMealPlan = 'bed_breakfast'"
                  :class="selectedMealPlan === 'bed_breakfast' ? 'border-[#0B4D34] bg-emerald-50/60 text-[#0B4D34] font-bold' : 'border-slate-200 text-slate-600'"
                  class="p-2 rounded-xl border text-left transition cursor-pointer">
                  <span class="block font-bold">Bed & Breakfast</span>
                  <span class="text-[10px] text-slate-400">+ETB 650/guest/nt</span>
                </button>
                <button 
                  type="button" 
                  @click="selectedMealPlan = 'half_board'"
                  :class="selectedMealPlan === 'half_board' ? 'border-[#0B4D34] bg-emerald-50/60 text-[#0B4D34] font-bold' : 'border-slate-200 text-slate-600'"
                  class="p-2 rounded-xl border text-left transition cursor-pointer">
                  <span class="block font-bold">Half Board</span>
                  <span class="text-[10px] text-slate-400">+ETB 1,450/guest/nt</span>
                </button>
                <button 
                  type="button" 
                  @click="selectedMealPlan = 'full_board'"
                  :class="selectedMealPlan === 'full_board' ? 'border-[#0B4D34] bg-emerald-50/60 text-[#0B4D34] font-bold' : 'border-slate-200 text-slate-600'"
                  class="p-2 rounded-xl border text-left transition cursor-pointer">
                  <span class="block font-bold">Full Board</span>
                  <span class="text-[10px] text-slate-400">+ETB 2,200/guest/nt</span>
                </button>
              </div>
            </div>

            <!-- Concierge Add-On Packages -->
            <div class="space-y-2 pt-2 border-t border-slate-100">
              <label class="text-xs font-bold text-slate-700 block">✨ Concierge & Experience Add-ons</label>
              <div class="space-y-1.5 text-xs">
                <label class="flex items-center justify-between p-2 rounded-xl border border-slate-200 hover:bg-slate-50 transition cursor-pointer">
                  <div class="flex items-center gap-2">
                    <input type="checkbox" v-model="selectedAddons" value="airport_shuttle" class="rounded accent-[#0B4D34]" />
                    <span>🚗 VIP Airport Shuttle</span>
                  </div>
                  <span class="font-bold text-[#0B4D34] text-[11px]">+ETB 1,200</span>
                </label>
                <label class="flex items-center justify-between p-2 rounded-xl border border-slate-200 hover:bg-slate-50 transition cursor-pointer">
                  <div class="flex items-center gap-2">
                    <input type="checkbox" v-model="selectedAddons" value="coffee_ceremony" class="rounded accent-[#0B4D34]" />
                    <span>☕ Cultural Coffee Ceremony</span>
                  </div>
                  <span class="font-bold text-[#0B4D34] text-[11px]">+ETB 850</span>
                </label>
                <label class="flex items-center justify-between p-2 rounded-xl border border-slate-200 hover:bg-slate-50 transition cursor-pointer">
                  <div class="flex items-center gap-2">
                    <input type="checkbox" v-model="selectedAddons" value="spa_massage" class="rounded accent-[#0B4D34]" />
                    <span>🌿 Couple's Steam & Massage</span>
                  </div>
                  <span class="font-bold text-[#0B4D34] text-[11px]">+ETB 2,800</span>
                </label>
              </div>
            </div>

            <!-- Promo Code / Voucher Box -->
            <div class="p-3 bg-slate-50 rounded-2xl border border-slate-200/80 space-y-2">
              <div class="flex items-center justify-between">
                <label class="text-[11px] font-bold uppercase tracking-wider text-slate-600">Promo / Discount Code</label>
                <span class="text-[10px] text-slate-400">e.g. LUXURYETHIOPIA</span>
              </div>
              <div class="flex gap-2">
                <input 
                  v-model="promoInput" 
                  type="text" 
                  placeholder="Enter Promo Code" 
                  class="flex-1 px-3 py-1.5 bg-white border border-slate-200 rounded-xl text-xs font-mono uppercase focus:outline-none focus:border-[#0B4D34]" />
                <button 
                  type="button" 
                  @click="applyPromoCode" 
                  :disabled="promoChecking || !promoInput.trim()"
                  class="px-3.5 py-1.5 bg-[#0B4D34] hover:bg-[#064E3B] text-white text-xs font-bold rounded-xl transition disabled:opacity-50 cursor-pointer">
                  {{ promoChecking ? '...' : 'Apply' }}
                </button>
              </div>
              <div v-if="promoMessage" :class="appliedPromo ? 'text-emerald-700 font-semibold' : 'text-rose-600'" class="text-[11px] flex items-center justify-between">
                <span>{{ promoMessage }}</span>
                <button v-if="appliedPromo" @click="removePromo" class="text-slate-400 hover:text-slate-600 text-xs cursor-pointer">✕ Remove</button>
              </div>
            </div>

            <!-- Pricing Summary -->
            <div class="space-y-1.5 text-xs pt-1 border-t border-slate-100">
              <div class="flex justify-between text-slate-600">
                <span>Base Room Rate:</span>
                <span class="font-medium text-slate-800">{{ selectedRoom ? formatPrice(subtotalEtb, subtotalUsd) : '--' }}</span>
              </div>
              <div v-if="isWeekendStay" class="flex justify-between text-amber-700 bg-amber-50 px-2 py-0.5 rounded-md">
                <span>✨ Weekend Luxury Rate:</span>
                <span class="font-bold">Active Best Rate</span>
              </div>
              <div v-if="appliedPromo" class="flex justify-between text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-md font-semibold">
                <span>🎁 Discount ({{ appliedPromo.discount_percent }}% Off):</span>
                <span>- {{ formatPrice(discountAmountEtb, discountAmountUsd) }}</span>
              </div>
              <div class="flex justify-between text-slate-900 font-bold pt-2 border-t border-slate-100 text-sm">
                <span>Total Payable:</span>
                <span class="text-[#0B4D34] text-base">{{ selectedRoom ? formatPrice(finalPriceEtb, finalPriceUsd) : '--' }}</span>
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
          </div>

          <!-- Drawer Footer with Proceed to Payment Action Button -->
          <div class="p-5 border-t border-slate-100 bg-white space-y-2.5">
            <button 
              @click="submitBooking"
              :disabled="bookingSubmitting || !selectedRoom || !selectedRoom.is_available"
              class="w-full py-4 bg-gradient-to-r from-[#0B4D34] to-[#107550] hover:brightness-110 text-white font-bold text-sm rounded-2xl shadow-lg transition transform active:scale-98 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2 cursor-pointer">
              <span v-if="bookingSubmitting" class="flex items-center gap-2">
                <span class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                Reserving Room & Preparing Payment...
              </span>
              <span v-else-if="!selectedRoom">Select a Room to Continue</span>
              <span v-else-if="!selectedRoom?.is_available">Selected Room Unavailable</span>
              <span v-else class="flex items-center gap-2">
                <span>Proceed to Payment</span>
                <span class="text-base font-bold">→</span>
              </span>
            </button>

            <!-- Security Trust Badges -->
            <div class="flex items-center justify-center gap-3 text-[11px] text-slate-400">
              <span class="flex items-center gap-1 font-mono">🔒 256-Bit SSL</span>
              <span>•</span>
              <span>Telebirr / CBE / Chapa</span>
              <span>•</span>
              <span>Instant Hold</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Floating Quick Access Button for Booking Summary -->
    <div 
      v-if="selectedRoom && !isSummaryOpen" 
      class="fixed bottom-6 right-6 z-40">
      <button 
        @click="isSummaryOpen = true"
        class="px-5 py-3.5 bg-gradient-to-r from-[#0B4D34] to-[#107550] hover:brightness-110 text-white font-bold text-xs rounded-full shadow-2xl border-2 border-yellow-300/40 flex items-center gap-3 transition transform active:scale-95 cursor-pointer">
        <span class="text-base">📋</span>
        <div class="text-left">
          <div class="text-[10px] text-yellow-200/90 uppercase font-semibold">Selected: Room {{ selectedRoom.room_number }}</div>
          <div class="font-extrabold text-white text-xs">{{ formatPrice(finalPriceEtb, finalPriceUsd) }} • View Summary →</div>
        </div>
      </button>
    </div>

    <!-- Sticky Mobile Floating Checkout Pill -->
    <div 
      v-if="selectedRoom && selectedRoom.is_available && !isSummaryOpen"
      class="lg:hidden fixed bottom-4 left-4 right-4 z-40 bg-[#064E3B] text-white p-3.5 rounded-2xl shadow-2xl border border-yellow-200/30 flex items-center justify-between backdrop-blur-md">
      <div>
        <div class="text-[10px] text-yellow-200/90 uppercase font-bold tracking-wider">Room {{ selectedRoom.room_number }} • {{ nights }} Night{{ nights > 1 ? 's' : '' }}</div>
        <div class="text-sm font-black text-white">{{ formatPrice(finalPriceEtb, finalPriceUsd) }}</div>
      </div>
      <button 
        @click="isSummaryOpen = true"
        class="px-5 py-2.5 bg-[#D4A329] hover:bg-yellow-400 text-slate-950 font-bold text-xs rounded-xl shadow transition cursor-pointer flex items-center gap-1.5">
        <span>Booking Summary →</span>
      </button>
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

const isSummaryOpen = ref(false);
const selectedCategory = ref('all');
const catalogCategories = ref([]);
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
const guestCount = ref({ adults: 2, children: 0 });
const selectedMealPlan = ref('room_only');
const selectedAddons = ref([]);

const mealPlanRates = {
  'room_only': 0,
  'bed_breakfast': 650,
  'half_board': 1450,
  'full_board': 2200
};

const addonRates = {
  'airport_shuttle': 1200,
  'coffee_ceremony': 850,
  'spa_massage': 2800,
  'honeymoon_setup': 1600
};

const mealTotalEtb = computed(() => {
  const perPersonPerNight = mealPlanRates[selectedMealPlan.value] || 0;
  const guestTotal = (guestCount.value.adults || 1) + (guestCount.value.children || 0);
  return perPersonPerNight * guestTotal * nights.value;
});

const addonsTotalEtb = computed(() => {
  let sum = 0;
  for (const add of selectedAddons.value) {
    if (addonRates[add]) sum += addonRates[add];
  }
  return sum;
});

const promoInput = ref('');
const promoChecking = ref(false);
const promoMessage = ref('');
const appliedPromo = ref(null);

const isWeekendStay = computed(() => {
  const d1 = new Date(checkInDate.value);
  const d2 = new Date(checkOutDate.value);
  for (let d = new Date(d1); d < d2; d.setDate(d.getDate() + 1)) {
    if (d.getDay() === 5 || d.getDay() === 6) return true;
  }
  return false;
});

const subtotalEtb = computed(() => {
  if (!selectedRoom.value) return 0;
  return (selectedRoom.value.price_etb * nights.value) + mealTotalEtb.value + addonsTotalEtb.value;
});

const subtotalUsd = computed(() => {
  if (!selectedRoom.value) return 0;
  return Math.round(subtotalEtb.value / 64);
});

const discountAmountEtb = computed(() => {
  if (!appliedPromo.value) return 0;
  return Math.round(subtotalEtb.value * (appliedPromo.value.discount_percent / 100));
});

const discountAmountUsd = computed(() => {
  if (!appliedPromo.value) return 0;
  return Math.round(subtotalUsd.value * (appliedPromo.value.discount_percent / 100));
});

const finalPriceEtb = computed(() => subtotalEtb.value - discountAmountEtb.value);
const finalPriceUsd = computed(() => subtotalUsd.value - discountAmountUsd.value);

async function applyPromoCode() {
  if (!promoInput.value.trim()) return;
  promoChecking.value = true;
  promoMessage.value = '';
  try {
    const res = await fetch('/api/v1/bookings/validate-promo', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ promo_code: promoInput.value })
    });
    const data = await res.json();
    if (res.ok && data.valid) {
      appliedPromo.value = data;
      promoMessage.value = `✓ ${data.title} applied!`;
    } else {
      appliedPromo.value = null;
      promoMessage.value = data.error || 'Invalid promo code';
    }
  } catch (e) {
    promoMessage.value = 'Failed to validate promo code';
  } finally {
    promoChecking.value = false;
  }
}

function removePromo() {
  appliedPromo.value = null;
  promoMessage.value = '';
  promoInput.value = '';
}

const bookingSubmitting = ref(false);

// Guest Form
const guestForm = ref({
  name: '',
  email: '',
  phone: ''
});

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
  isSummaryOpen.value = true;
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

  if (!guestForm.value.name.trim()) {
    alert('Please enter your full name before proceeding to payment.');
    return;
  }
  if (!guestForm.value.email.trim() || !guestForm.value.email.includes('@')) {
    alert('Please provide a valid email address for your booking confirmation.');
    return;
  }
  if (!guestForm.value.phone.trim()) {
    alert('Please provide a phone number for SMS and reservation updates.');
    return;
  }

  bookingSubmitting.value = true;
  try {
    const payload = {
      room_id: selectedRoom.value.id,
      check_in_date: checkInDate.value,
      check_out_date: checkOutDate.value,
      adults: guestCount.value.adults,
      children: guestCount.value.children,
      promo_code: appliedPromo.value ? appliedPromo.value.code : null,
      meal_plan: selectedMealPlan.value,
      addons: selectedAddons.value,
      payment_method: 'cbe_birr',
      payment_mode: 'checkout',
      guest_name: guestForm.value.name.trim(),
      guest_email: guestForm.value.email.trim().toLowerCase(),
      guest_phone: guestForm.value.phone.trim()
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

    emit('booking-created', data.booking);
    isSummaryOpen.value = false;

    // Direct navigation to dedicated separate payment page
    router.push(`/payment/${data.booking.id}`);

    // Refresh rooms to reflect new hold
    fetchRooms();
  } catch (err) {
    alert(err.message);
  } finally {
    bookingSubmitting.value = false;
  }
}

async function fetchCatalogCategories() {
  try {
    const res = await fetch('/api/v1/categories');
    const data = await res.json();
    catalogCategories.value = data.categories || [];
  } catch (err) {
    console.error('Failed to load categories in catalog:', err);
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

  fetchCatalogCategories();
  fetchFavorites();
  fetchRooms();
});
</script>

<style scoped>
@keyframes slideInRight {
  from { transform: translateX(100%); opacity: 0; }
  to { transform: translateX(0); opacity: 1; }
}
.animate-slideIn {
  animation: slideInRight 0.28s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

@keyframes fadeInBackdrop {
  from { opacity: 0; }
  to { opacity: 1; }
}
.animate-fadeIn {
  animation: fadeInBackdrop 0.2s ease-out forwards;
}
</style>
