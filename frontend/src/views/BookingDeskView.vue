<template>
  <div class="space-y-6">
    <!-- Top Operations Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 bg-white p-5 rounded-2xl shadow-sm border border-slate-100">
      <div>
        <h1 class="text-2xl font-bold text-slate-900 flex items-center gap-2">
          <span>📅</span>
          <span>Reservation & Booking Management Desk</span>
        </h1>
        <p class="text-xs text-slate-500 mt-0.5">
          Centralized reservation console, multi-room booking engine, live availability search & lifecycle operations.
        </p>
      </div>

      <div class="flex flex-wrap items-center gap-2.5">
        <button 
          @click="openNewBookingModal(false)"
          class="px-4 py-2 bg-[#0B4D34] hover:bg-[#064E3B] text-white text-xs font-bold rounded-xl shadow-xs transition flex items-center gap-1.5 cursor-pointer">
          <span>➕</span> New Reservation
        </button>

        <button 
          @click="openNewBookingModal(true)"
          class="px-4 py-2 bg-amber-500 hover:bg-amber-600 text-slate-950 text-xs font-bold rounded-xl shadow-xs transition flex items-center gap-1.5 cursor-pointer">
          <span>🚶</span> Walk-In Arrival
        </button>

        <button 
          @click="fetchBookings"
          class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl transition flex items-center gap-1.5 cursor-pointer">
          <span>🔄</span> Refresh
        </button>
      </div>
    </div>

    <!-- Notification Toast / Alert Banner -->
    <div 
      v-if="notification.show" 
      :class="notification.type === 'error' ? 'bg-rose-50 border-rose-200 text-rose-800' : 'bg-emerald-50 border-emerald-200 text-emerald-800'"
      class="p-4 rounded-xl border flex items-center justify-between text-xs font-semibold shadow-xs transition-all">
      <div class="flex items-center gap-2">
        <span>{{ notification.type === 'error' ? '⚠️' : '✓' }}</span>
        <span>{{ notification.message }}</span>
      </div>
      <button @click="notification.show = false" class="text-slate-400 hover:text-slate-600 text-sm">✕</button>
    </div>

    <!-- Dashboard KPI Summary Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-3">
      <div class="bg-white p-3 rounded-xl border border-slate-100 shadow-2xs">
        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Total</span>
        <div class="text-lg font-black text-slate-900 mt-0.5">{{ stats.total || 0 }}</div>
        <span class="text-[9px] text-slate-400">All Stays</span>
      </div>
      <div class="bg-white p-3 rounded-xl border border-blue-100 shadow-2xs">
        <span class="text-[10px] font-bold uppercase tracking-wider text-blue-600">Today In</span>
        <div class="text-lg font-black text-blue-700 mt-0.5">{{ stats.arrivals_today || 0 }}</div>
        <span class="text-[9px] text-blue-600/80">Arrivals</span>
      </div>
      <div class="bg-white p-3 rounded-xl border border-amber-100 shadow-2xs">
        <span class="text-[10px] font-bold uppercase tracking-wider text-amber-600">Today Out</span>
        <div class="text-lg font-black text-amber-700 mt-0.5">{{ stats.departures_today || 0 }}</div>
        <span class="text-[9px] text-amber-600/80">Departures</span>
      </div>
      <div class="bg-white p-3 rounded-xl border border-emerald-100 shadow-2xs">
        <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-600">In-House</span>
        <div class="text-lg font-black text-emerald-700 mt-0.5">{{ stats.in_house || 0 }}</div>
        <span class="text-[9px] text-emerald-600/80">Active</span>
      </div>
      <div class="bg-white p-3 rounded-xl border border-indigo-100 shadow-2xs">
        <span class="text-[10px] font-bold uppercase tracking-wider text-indigo-600">Upcoming</span>
        <div class="text-lg font-black text-indigo-700 mt-0.5">{{ stats.upcoming || 0 }}</div>
        <span class="text-[9px] text-indigo-600/80">Future</span>
      </div>
      <div class="bg-white p-3 rounded-xl border border-yellow-100 shadow-2xs">
        <span class="text-[10px] font-bold uppercase tracking-wider text-yellow-700">Pending</span>
        <div class="text-lg font-black text-yellow-800 mt-0.5">{{ stats.pending || 0 }}</div>
        <span class="text-[9px] text-yellow-700/80">Hold / Review</span>
      </div>
      <div class="bg-white p-3 rounded-xl border border-rose-100 shadow-2xs">
        <span class="text-[10px] font-bold uppercase tracking-wider text-rose-600">Cancelled</span>
        <div class="text-lg font-black text-rose-700 mt-0.5">{{ stats.cancelled || 0 }}</div>
        <span class="text-[9px] text-rose-600/80">Released</span>
      </div>
      <div class="bg-white p-3 rounded-xl border border-slate-200 shadow-2xs">
        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-500">No-Show</span>
        <div class="text-lg font-black text-slate-800 mt-0.5">{{ stats.no_show || 0 }}</div>
        <span class="text-[9px] text-slate-400">Missed</span>
      </div>
    </div>

    <!-- Filter & Search Toolbar -->
    <div class="bg-white p-4 rounded-2xl shadow-xs border border-slate-100 space-y-3">
      <!-- Status Tabs -->
      <div class="flex items-center space-x-1.5 overflow-x-auto pb-1 text-xs">
        <button 
          v-for="tab in statusTabs"
          :key="tab.id"
          @click="currentStatus = tab.id; fetchBookings()"
          :class="currentStatus === tab.id ? 'bg-[#0B4D34] text-white shadow-xs font-bold' : 'bg-slate-100 text-slate-700 hover:bg-slate-200 font-medium'"
          class="px-3.5 py-1.5 rounded-full transition flex-shrink-0 flex items-center gap-1.5 cursor-pointer">
          <span>{{ tab.label }}</span>
          <span 
            :class="currentStatus === tab.id ? 'bg-amber-400 text-slate-950' : 'bg-white text-slate-700'"
            class="text-[10px] px-1.5 py-0.2 rounded-full font-bold">
            {{ getTabCount(tab.id) }}
          </span>
        </button>
      </div>

      <!-- Second Filter Row: Text Search, Date Range Pickers -->
      <div class="flex flex-col sm:flex-row items-center justify-between gap-3 pt-2 border-t border-slate-100 text-xs">
        <!-- Search Input -->
        <div class="relative w-full sm:w-80">
          <input 
            v-model="searchQuery" 
            @input="fetchBookings"
            type="text" 
            placeholder="Search Reference #ETH, Guest Name, Phone, Room #..." 
            class="w-full pl-8 pr-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-[#0B4D34]" />
          <span class="absolute left-2.5 top-2.5 text-xs text-slate-400">🔍</span>
        </div>

        <!-- Date Range Filter -->
        <div class="flex flex-wrap items-center gap-2">
          <span class="text-slate-400 font-semibold">Stay Dates:</span>
          <input 
            v-model="dateFilters.checkIn" 
            @change="fetchBookings"
            type="date" 
            class="px-2 py-1 bg-slate-50 border border-slate-200 rounded-lg text-xs" />
          <span class="text-slate-400">to</span>
          <input 
            v-model="dateFilters.checkOut" 
            @change="fetchBookings"
            type="date" 
            class="px-2 py-1 bg-slate-50 border border-slate-200 rounded-lg text-xs" />
          <button 
            @click="resetFilters" 
            class="text-xs text-slate-400 hover:text-slate-600 font-semibold underline ml-1">
            Reset
          </button>
        </div>
      </div>
    </div>

    <!-- Main Reservations Listing Table -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
      <div class="px-5 py-3.5 border-b border-slate-100 flex items-center justify-between">
        <div class="flex items-center gap-2">
          <h2 class="text-sm font-bold text-slate-900">Reservations Master Ledger</h2>
          <span class="text-xs px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 font-bold">
            {{ reservations.length }} bookings
          </span>
        </div>
        <span class="text-[11px] text-slate-400 font-mono">Live ACID Database</span>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-xs">
          <thead>
            <tr class="bg-slate-50 text-slate-600 text-[11px] font-semibold uppercase tracking-wider border-b border-slate-100">
              <th class="py-3 px-4">Ref Code</th>
              <th class="py-3 px-4">Guest Information</th>
              <th class="py-3 px-4">Rooms Reserved</th>
              <th class="py-3 px-4">Dates & Duration</th>
              <th class="py-3 px-4">Total Amount</th>
              <th class="py-3 px-4">Outstanding</th>
              <th class="py-3 px-4">Payment</th>
              <th class="py-3 px-4">Status</th>
              <th class="py-3 px-4 text-right">Desk Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 text-slate-700">
            <tr v-if="loading" class="text-center">
              <td colspan="9" class="py-12 text-slate-400">
                <div class="text-2xl animate-spin mb-1">⏳</div>
                <p>Loading reservations from database...</p>
              </td>
            </tr>
            <tr v-else-if="reservations.length === 0" class="text-center">
              <td colspan="9" class="py-12 text-slate-400">
                <div class="text-2xl mb-1">🔍</div>
                <p>No reservations found matching the selected filter criteria.</p>
                <button @click="resetFilters" class="mt-2 text-[#0B4D34] font-bold underline">Reset Filters</button>
              </td>
            </tr>
            <tr 
              v-else
              v-for="b in paginatedReservations" 
              :key="b.id"
              class="hover:bg-slate-50/60 transition">
              <!-- Reference Code -->
              <td class="py-3 px-4">
                <div class="font-mono font-bold text-slate-900 text-xs flex items-center gap-1">
                  <span>{{ b.reference_code }}</span>
                  <span v-if="b.is_walk_in" class="px-1.5 py-0.2 rounded text-[9px] bg-amber-100 text-amber-900 font-bold uppercase">
                    Walk-In
                  </span>
                </div>
                <span class="text-[10px] text-slate-400 block">{{ formatShortDate(b.created_at) }}</span>
              </td>

              <!-- Guest Information -->
              <td class="py-3 px-4">
                <div class="font-bold text-slate-900 text-xs">{{ b.guest_name }}</div>
                <div class="text-[11px] text-slate-500 font-mono">{{ b.guest_phone }}</div>
                <div class="text-[10px] text-slate-400 truncate max-w-[150px]">{{ b.guest_email }}</div>
              </td>

              <!-- Reserved Rooms -->
              <td class="py-3 px-4">
                <div class="font-bold text-slate-800 flex items-center gap-1">
                  <span>Room {{ b.room_number }}</span>
                  <span v-if="b.rooms_count > 1" class="text-[10px] px-1.5 py-0.2 rounded-full bg-emerald-100 text-emerald-800 font-bold">
                    {{ b.rooms_count }} Rooms
                  </span>
                </div>
                <span class="text-[10px] text-slate-400 block">{{ b.category_name }}</span>
              </td>

              <!-- Dates & Duration -->
              <td class="py-3 px-4">
                <div class="font-semibold text-slate-800">{{ b.check_in_date }} → {{ b.check_out_date }}</div>
                <span class="text-[10px] px-1.5 py-0.2 rounded bg-slate-100 text-slate-600 font-bold inline-block mt-0.5">
                  {{ calculateNights(b.check_in_date, b.check_out_date) }} Nights • {{ b.adults_count }} Guests
                </span>
              </td>

              <!-- Total Amount -->
              <td class="py-3 px-4">
                <div class="font-bold text-slate-900">{{ b.total_price_etb?.toLocaleString() }} ETB</div>
                <div class="text-[10px] text-slate-400">${{ b.total_price_usd }} USD</div>
              </td>

              <!-- Outstanding Balance -->
              <td class="py-3 px-4">
                <span v-if="b.outstanding_balance_etb > 0" class="text-rose-600 font-bold">
                  {{ b.outstanding_balance_etb?.toLocaleString() }} ETB
                </span>
                <span v-else class="text-emerald-700 font-semibold text-[11px]">
                  ✓ Settled
                </span>
              </td>

              <!-- Payment Status -->
              <td class="py-3 px-4">
                <span 
                  :class="getPaymentBadgeClass(b.payment_status)"
                  class="text-[10px] font-bold px-2 py-0.5 rounded-full capitalize">
                  {{ b.payment_status }}
                </span>
              </td>

              <!-- Booking Status -->
              <td class="py-3 px-4">
                <span 
                  :class="getBookingBadgeClass(b.booking_status)"
                  class="text-[10px] font-bold px-2.5 py-1 rounded-full uppercase tracking-wider inline-flex items-center gap-1">
                  <span class="w-1.5 h-1.5 rounded-full" :class="getBookingDotClass(b.booking_status)"></span>
                  {{ b.booking_status?.replace('_', ' ') }}
                </span>
              </td>

              <!-- Desk Actions -->
              <td class="py-3 px-4 text-right whitespace-nowrap">
                <div class="inline-flex items-center gap-1">
                  <!-- Details -->
                  <button 
                    @click="openDetails(b)"
                    class="p-1.5 text-slate-600 hover:text-slate-900 hover:bg-slate-100 rounded-lg transition" 
                    title="View Full Reservation Details">
                    👁️
                  </button>

                  <!-- Confirmation Voucher -->
                  <button 
                    @click="openConfirmation(b)"
                    class="p-1.5 text-emerald-700 hover:bg-emerald-50 rounded-lg transition" 
                    title="Print Confirmation Slip / Voucher">
                    🖨️
                  </button>

                  <!-- Edit / Modify -->
                  <button 
                    v-if="!['checked_out', 'cancelled', 'no_show'].includes(b.booking_status)"
                    @click="openModify(b)"
                    class="p-1.5 text-blue-600 hover:bg-blue-50 rounded-lg transition" 
                    title="Modify Reservation Dates & Rooms">
                    ✏️
                  </button>

                  <!-- Quick Check-In (if confirmed) -->
                  <button 
                    v-if="b.booking_status === 'confirmed'"
                    @click="checkInGuest(b)"
                    class="px-2 py-1 bg-emerald-600 hover:bg-emerald-700 text-white text-[10px] font-bold rounded-lg transition shadow-2xs">
                    🛎️ In
                  </button>

                  <!-- Quick Check-Out (if checked_in) -->
                  <button 
                    v-if="b.booking_status === 'checked_in'"
                    @click="checkOutGuest(b)"
                    class="px-2 py-1 bg-amber-500 hover:bg-amber-600 text-slate-950 text-[10px] font-bold rounded-lg transition shadow-2xs">
                    🚪 Out
                  </button>

                  <!-- Mark No-Show -->
                  <button 
                    v-if="b.booking_status === 'confirmed' && isPastArrival(b.check_in_date)"
                    @click="markNoShow(b)"
                    class="p-1.5 text-slate-500 hover:bg-slate-100 rounded-lg transition" 
                    title="Mark No-Show">
                    ❌
                  </button>

                  <!-- Cancel Reservation -->
                  <button 
                    v-if="!['checked_out', 'cancelled', 'no_show'].includes(b.booking_status)"
                    @click="openCancel(b)"
                    class="p-1.5 text-rose-500 hover:bg-rose-50 rounded-lg transition" 
                    title="Cancel Reservation">
                    🚫
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination Controls -->
      <div class="px-5 py-3 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-500">
        <div class="flex items-center gap-2">
          <span>Show:</span>
          <select 
            v-model.number="pageSize" 
            @change="currentPage = 1"
            class="px-2 py-1 bg-slate-50 border border-slate-200 rounded-md text-xs">
            <option :value="10">10 entries</option>
            <option :value="25">25 entries</option>
            <option :value="50">50 entries</option>
            <option :value="1000">All</option>
          </select>
          <span>Showing {{ paginationStart }} - {{ paginationEnd }} of {{ reservations.length }}</span>
        </div>

        <div class="flex items-center gap-1.5" v-if="totalPages > 1">
          <button 
            @click="currentPage--" 
            :disabled="currentPage <= 1"
            class="px-2.5 py-1 rounded-md border border-slate-200 hover:bg-slate-50 disabled:opacity-40">
            Previous
          </button>
          <span class="px-2 font-bold text-slate-700">{{ currentPage }} / {{ totalPages }}</span>
          <button 
            @click="currentPage++" 
            :disabled="currentPage >= totalPages"
            class="px-2.5 py-1 rounded-md border border-slate-200 hover:bg-slate-50 disabled:opacity-40">
            Next
          </button>
        </div>
      </div>
    </div>

    <!-- MODAL 1: NEW RESERVATION & WALK-IN CREATION WIZARD -->
    <div 
      v-if="newBookingModal.show" 
      class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4">
      <div class="bg-white rounded-3xl shadow-2xl max-w-4xl w-full max-h-[92vh] overflow-y-auto p-6 space-y-5">
        <!-- Header -->
        <div class="flex items-start justify-between border-b border-slate-100 pb-3">
          <div>
            <div class="flex items-center gap-2">
              <h3 class="text-xl font-bold text-slate-900">
                {{ newBookingModal.isWalkIn ? '🚶 Create Front-Desk Walk-In Booking' : '➕ Create New Hotel Reservation' }}
              </h3>
              <span 
                :class="newBookingModal.isWalkIn ? 'bg-amber-100 text-amber-900' : 'bg-emerald-100 text-emerald-900'"
                class="text-[10px] font-bold px-2.5 py-0.5 rounded-full uppercase">
                {{ newBookingModal.isWalkIn ? 'Same-Day Walk-In' : 'Advance Booking' }}
              </span>
            </div>
            <p class="text-xs text-slate-400 mt-0.5">
              Live multi-room reservation, automatic rate calculation & double-booking prevention.
            </p>
          </div>
          <button @click="newBookingModal.show = false" class="text-slate-400 hover:text-slate-600 text-lg">✕</button>
        </div>

        <form @submit.prevent="submitNewBooking" class="space-y-4">
          <!-- Step 1: Stay Dates & Guest Counts -->
          <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200/80 space-y-3">
            <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-1.5">
              <span>📅</span> Step 1: Stay Dates & Occupancy
            </h4>
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-3 text-xs">
              <div>
                <label class="font-semibold text-slate-700 block mb-1">Check-In Date *</label>
                <input 
                  v-model="bookingForm.check_in_date" 
                  @change="onSearchAvailability"
                  type="date" 
                  :min="todayStr"
                  required
                  class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl font-semibold" />
              </div>
              <div>
                <label class="font-semibold text-slate-700 block mb-1">Check-Out Date *</label>
                <input 
                  v-model="bookingForm.check_out_date" 
                  @change="onSearchAvailability"
                  type="date" 
                  :min="bookingForm.check_in_date"
                  required
                  class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl font-semibold" />
              </div>
              <div>
                <label class="font-semibold text-slate-700 block mb-1">Adults</label>
                <input 
                  v-model.number="bookingForm.adults" 
                  type="number" 
                  min="1" 
                  max="30"
                  class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl" />
              </div>
              <div>
                <label class="font-semibold text-slate-700 block mb-1">Children</label>
                <input 
                  v-model.number="bookingForm.children" 
                  type="number" 
                  min="0" 
                  max="15"
                  class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl" />
              </div>
            </div>
            <div class="text-[11px] text-slate-500 font-medium">
              Stay Duration: <strong>{{ computedNights }} Nights</strong>
            </div>
          </div>

          <!-- Step 2: Multiple Room Selection with Live Availability -->
          <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200/80 space-y-3">
            <div class="flex items-center justify-between">
              <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-1.5">
                <span>🛏️</span> Step 2: Select Room(s) ({{ availableRoomsList.length }} Available)
              </h4>
              <button 
                type="button"
                @click="onSearchAvailability" 
                class="text-xs text-[#0B4D34] font-bold hover:underline">
                🔄 Recheck Availability
              </button>
            </div>

            <div v-if="searchingRooms" class="py-8 text-center text-xs text-slate-400">
              <div class="animate-spin text-xl mb-1">⏳</div>
              Checking real-time room availability and collision locks...
            </div>

            <div v-else-if="availableRoomsList.length === 0" class="p-6 text-center text-xs text-rose-600 bg-rose-50 rounded-xl border border-rose-200 font-semibold">
              ⚠️ No rooms available for the selected dates. Please adjust your check-in / check-out dates.
            </div>

            <!-- Rooms Selection Grid -->
            <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2.5 max-h-56 overflow-y-auto p-1">
              <label 
                v-for="rm in availableRoomsList" 
                :key="rm.id"
                :class="isRoomSelected(rm.id) ? 'border-[#0B4D34] bg-emerald-50/50 shadow-xs' : 'border-slate-200 bg-white'"
                class="p-3 rounded-xl border-2 flex items-start gap-2.5 cursor-pointer transition select-none">
                <input 
                  type="checkbox" 
                  :value="rm.id" 
                  :checked="isRoomSelected(rm.id)"
                  @change="toggleRoomSelection(rm)"
                  class="mt-1 rounded text-[#0B4D34] focus:ring-0" />
                <div class="flex-1 min-w-0">
                  <div class="flex items-center justify-between">
                    <span class="font-extrabold text-slate-900 text-xs">Room {{ rm.room_number }}</span>
                    <span class="text-[9px] font-bold px-1.5 py-0.2 rounded bg-slate-100 text-slate-700">
                      Floor {{ rm.floor }}
                    </span>
                  </div>
                  <div class="text-[11px] text-slate-600 truncate">{{ rm.title }}</div>
                  <div class="flex items-center justify-between mt-1 text-[10px]">
                    <span class="text-slate-500">{{ rm.category_name }} ({{ rm.capacity }} pax)</span>
                    <span class="font-bold text-[#0B4D34]">{{ rm.total_stay_price_etb?.toLocaleString() }} ETB</span>
                  </div>
                </div>
              </label>
            </div>

            <div class="text-xs font-semibold text-slate-700 pt-1">
              Selected Rooms: <strong class="text-[#0B4D34]">{{ selectedRooms.length }} Room(s)</strong>
              <span v-if="selectedRooms.length > 0" class="text-slate-400 ml-1">
                ({{ selectedRooms.map(r => 'Room ' + r.room_number).join(', ') }})
              </span>
            </div>
          </div>

          <!-- Step 3: Guest Information / Existing Directory Lookup -->
          <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200/80 space-y-3">
            <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-1.5">
              <span>👤</span> Step 3: Guest Profile & Contact
            </h4>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
              <div>
                <label class="font-semibold text-slate-700 block mb-1">Guest Full Name *</label>
                <input 
                  v-model="bookingForm.guest_name" 
                  type="text" 
                  placeholder="e.g. Almaz Tesfaye" 
                  required
                  class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl" />
              </div>
              <div>
                <label class="font-semibold text-slate-700 block mb-1">Phone Number *</label>
                <input 
                  v-model="bookingForm.guest_phone" 
                  type="text" 
                  placeholder="+251 911 234567" 
                  required
                  class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl" />
              </div>
              <div>
                <label class="font-semibold text-slate-700 block mb-1">Email Address</label>
                <input 
                  v-model="bookingForm.guest_email" 
                  type="email" 
                  placeholder="guest@example.com" 
                  class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl" />
              </div>
            </div>
          </div>

          <!-- Step 4: Meal Plan, Addons & Special Requests -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
            <div class="bg-slate-50 p-3.5 rounded-2xl border border-slate-200/80 space-y-2">
              <label class="font-bold text-slate-800 block">Meal Plan</label>
              <select 
                v-model="bookingForm.meal_plan" 
                class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl">
                <option value="room_only">Room Only (+0 ETB)</option>
                <option value="bed_breakfast">Bed & Breakfast (+650 ETB/person/night)</option>
                <option value="half_board">Half Board (+1,450 ETB/person/night)</option>
                <option value="full_board">Full Board (+2,200 ETB/person/night)</option>
              </select>

              <label class="font-bold text-slate-800 block pt-1">Payment Method</label>
              <select 
                v-model="bookingForm.payment_method" 
                class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl">
                <option value="telebirr">Telebirr SuperApp</option>
                <option value="cbe_birr">CBE Birr</option>
                <option value="chapa">Chapa Payment Gateway</option>
                <option value="cash">Cash at Front Desk</option>
                <option value="card">Credit / Debit Card</option>
              </select>
            </div>

            <div class="bg-slate-50 p-3.5 rounded-2xl border border-slate-200/80 space-y-2">
              <label class="font-bold text-slate-800 block">Special Requests & Notes</label>
              <textarea 
                v-model="bookingForm.special_requests" 
                rows="3" 
                placeholder="High floor, quiet wing, airport shuttle pickup time, etc."
                class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl"></textarea>
            </div>
          </div>

          <!-- Total Calculation & Confirmation Bar -->
          <div class="p-4 rounded-2xl bg-[#0B4D34] text-white flex flex-col sm:flex-row items-center justify-between gap-3 shadow-md">
            <div>
              <div class="text-[11px] text-emerald-200 uppercase font-semibold">Total Stay Price (All Rooms Included)</div>
              <div class="text-2xl font-black">{{ computedEstimatedTotal.toLocaleString() }} ETB</div>
              <div class="text-xs text-emerald-200">Approx. ${{ Math.round(computedEstimatedTotal / 64) }} USD</div>
            </div>

            <div class="flex items-center gap-2">
              <button 
                type="button" 
                @click="newBookingModal.show = false"
                class="px-4 py-2.5 bg-white/10 hover:bg-white/20 text-white rounded-xl text-xs font-bold transition">
                Cancel
              </button>
              <button 
                type="submit" 
                :disabled="savingBooking || selectedRooms.length === 0"
                class="px-5 py-2.5 bg-amber-400 hover:bg-amber-300 text-slate-950 rounded-xl text-xs font-black shadow-md transition disabled:opacity-50 cursor-pointer">
                {{ savingBooking ? 'Saving...' : (newBookingModal.isWalkIn ? 'Confirm Walk-In Stay' : 'Confirm Reservation') }}
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>

    <!-- MODAL 2: RESERVATION DETAILS & AUDIT TRAIL MODAL -->
    <div 
      v-if="detailsModal.show && detailsModal.booking" 
      class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4">
      <div class="bg-white rounded-3xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto p-6 space-y-4">
        <!-- Header -->
        <div class="flex items-start justify-between border-b border-slate-100 pb-3">
          <div>
            <div class="flex items-center gap-2">
              <h3 class="text-xl font-bold text-slate-900">Reservation {{ detailsModal.booking.reference_code }}</h3>
              <span 
                :class="getBookingBadgeClass(detailsModal.booking.booking_status)"
                class="text-[10px] font-bold px-2.5 py-0.5 rounded-full uppercase">
                {{ detailsModal.booking.booking_status }}
              </span>
            </div>
            <p class="text-xs text-slate-400">Created {{ formatDateTime(detailsModal.booking.created_at) }}</p>
          </div>
          <button @click="detailsModal.show = false" class="text-slate-400 hover:text-slate-600 text-lg">✕</button>
        </div>

        <!-- Guest Profile & Contact Info -->
        <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100 grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
          <div>
            <span class="text-slate-400 text-[10px] font-semibold uppercase block">Guest Name</span>
            <span class="font-bold text-slate-900 text-sm">{{ detailsModal.booking.guest?.full_name }}</span>
          </div>
          <div>
            <span class="text-slate-400 text-[10px] font-semibold uppercase block">Phone</span>
            <span class="font-bold text-slate-800 font-mono">{{ detailsModal.booking.guest?.phone }}</span>
          </div>
          <div>
            <span class="text-slate-400 text-[10px] font-semibold uppercase block">Email</span>
            <span class="text-slate-700 truncate block">{{ detailsModal.booking.guest?.email }}</span>
          </div>
        </div>

        <!-- Multi-Room Breakdown Table -->
        <div class="space-y-1.5">
          <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider">
            Reserved Rooms ({{ detailsModal.booking.rooms?.length || 1 }})
          </h4>
          <div class="overflow-x-auto border border-slate-100 rounded-xl">
            <table class="w-full text-left text-xs border-collapse">
              <thead>
                <tr class="bg-slate-50 text-slate-500 font-semibold border-b border-slate-100 text-[11px]">
                  <th class="py-2 px-3">Room #</th>
                  <th class="py-2 px-3">Type</th>
                  <th class="py-2 px-3">Rate/Night</th>
                  <th class="py-2 px-3 text-right">Subtotal</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-for="rm in detailsModal.booking.rooms" :key="rm.id">
                  <td class="py-2.5 px-3 font-bold text-slate-900">Room {{ rm.room_number }}</td>
                  <td class="py-2.5 px-3 text-slate-600">{{ rm.category_name }}</td>
                  <td class="py-2.5 px-3 font-mono">{{ rm.nightly_rate_etb?.toLocaleString() }} ETB</td>
                  <td class="py-2.5 px-3 text-right font-bold text-slate-900">{{ rm.total_price_etb?.toLocaleString() }} ETB</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Stay Details & Financials -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
          <div class="bg-slate-50 p-3 rounded-xl border border-slate-100">
            <span class="text-[10px] text-slate-400 uppercase font-semibold block">Stay Dates</span>
            <span class="font-bold text-slate-800">{{ detailsModal.booking.check_in_date }} → {{ detailsModal.booking.check_out_date }}</span>
            <span class="text-[10px] text-slate-500 block">({{ detailsModal.booking.nights }} Nights)</span>
          </div>
          <div class="bg-slate-50 p-3 rounded-xl border border-slate-100">
            <span class="text-[10px] text-slate-400 uppercase font-semibold block">Total Amount</span>
            <span class="font-bold text-[#0B4D34] text-sm">{{ detailsModal.booking.total_price_etb?.toLocaleString() }} ETB</span>
            <span class="text-[10px] text-slate-500 block">(${{ detailsModal.booking.total_price_usd }} USD)</span>
          </div>
          <div class="bg-slate-50 p-3 rounded-xl border border-slate-100">
            <span class="text-[10px] text-slate-400 uppercase font-semibold block">Paid Amount</span>
            <span class="font-bold text-emerald-700 text-sm">{{ detailsModal.booking.verified_paid_etb?.toLocaleString() }} ETB</span>
          </div>
          <div class="bg-slate-50 p-3 rounded-xl border border-slate-100">
            <span class="text-[10px] text-slate-400 uppercase font-semibold block">Balance Due</span>
            <span :class="detailsModal.booking.outstanding_balance_etb > 0 ? 'text-rose-600 font-bold' : 'text-slate-700 font-semibold'">
              {{ detailsModal.booking.outstanding_balance_etb?.toLocaleString() }} ETB
            </span>
          </div>
        </div>

        <!-- Special Requests -->
        <div class="bg-slate-50 p-3 rounded-xl border border-slate-100 text-xs space-y-1">
          <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block">Special Requests</span>
          <p class="text-slate-700 italic">{{ detailsModal.booking.special_requests || 'None requested.' }}</p>
        </div>

        <!-- Audit & Modification History -->
        <div class="space-y-1.5 pt-2 border-t border-slate-100" v-if="detailsModal.booking.history && detailsModal.booking.history.length">
          <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Modification & Audit History</h4>
          <div class="max-h-36 overflow-y-auto space-y-1.5">
            <div 
              v-for="h in detailsModal.booking.history" 
              :key="h.id"
              class="p-2.5 rounded-lg bg-slate-50 text-[11px] border border-slate-200/60 flex items-center justify-between">
              <div>
                <span class="font-bold text-slate-800 capitalize">{{ h.action?.replace(/_/g, ' ') }}</span>
                <span class="text-slate-400 mx-1.5">•</span>
                <span class="text-slate-500">by {{ h.user_name }} ({{ h.user_role }})</span>
              </div>
              <span class="text-[10px] text-slate-400 font-mono">{{ formatDateTime(h.created_at) }}</span>
            </div>
          </div>
        </div>

        <!-- Footer Actions -->
        <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
          <button 
            @click="detailsModal.show = false; openConfirmation(detailsModal.booking)"
            class="px-4 py-2 bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold rounded-xl transition">
            🖨️ Print Confirmation Slip
          </button>
          <button 
            @click="detailsModal.show = false"
            class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition">
            Close
          </button>
        </div>
      </div>
    </div>

    <!-- MODAL 3: PRINTABLE BOOKING CONFIRMATION VOUCHER -->
    <div 
      v-if="confirmationModal.show && confirmationModal.voucher" 
      class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4">
      <div class="bg-white rounded-3xl shadow-2xl max-w-xl w-full p-6 space-y-5 print:p-0 print:shadow-none">
        <!-- Voucher Printable Body -->
        <div id="printable-voucher" class="space-y-4 p-4 border border-slate-200 rounded-2xl bg-white text-xs">
          <!-- Letterhead -->
          <div class="flex items-center justify-between border-b-2 border-[#0B4D34] pb-3">
            <div>
              <h2 class="text-base font-black text-[#0B4D34]">{{ confirmationModal.voucher.hotel.name }}</h2>
              <p class="text-[10px] text-slate-500">{{ confirmationModal.voucher.hotel.address }} • TIN: {{ confirmationModal.voucher.hotel.tin }}</p>
            </div>
            <div class="text-right">
              <span class="text-[10px] text-slate-400 block uppercase font-bold">Booking Voucher</span>
              <span class="font-mono font-black text-sm text-slate-900">{{ confirmationModal.voucher.confirmation_number }}</span>
            </div>
          </div>

          <!-- Guest & Stay Block -->
          <div class="grid grid-cols-2 gap-3 p-3 bg-slate-50 rounded-xl">
            <div>
              <span class="text-[10px] text-slate-400 font-bold uppercase block">Guest Details</span>
              <div class="font-bold text-slate-900 text-sm">{{ confirmationModal.voucher.guest.name }}</div>
              <div class="text-slate-600">{{ confirmationModal.voucher.guest.phone }}</div>
              <div class="text-slate-500 text-[10px]">{{ confirmationModal.voucher.guest.email }}</div>
            </div>
            <div>
              <span class="text-[10px] text-slate-400 font-bold uppercase block">Stay Itinerary</span>
              <div>Check-In: <strong>{{ confirmationModal.voucher.stay.check_in_date }} ({{ confirmationModal.voucher.stay.check_in_time }})</strong></div>
              <div>Check-Out: <strong>{{ confirmationModal.voucher.stay.check_out_date }} ({{ confirmationModal.voucher.stay.check_out_time }})</strong></div>
              <div class="text-slate-500 text-[10px]">{{ confirmationModal.voucher.stay.nights }} Nights • {{ confirmationModal.voucher.stay.total_guests }} Guests</div>
            </div>
          </div>

          <!-- Rooms Reserved -->
          <div>
            <span class="text-[10px] text-slate-400 font-bold uppercase block mb-1">Reserved Accommodations</span>
            <div class="space-y-1">
              <div 
                v-for="(r, idx) in confirmationModal.voucher.rooms" 
                :key="idx"
                class="flex justify-between items-center p-2 rounded-lg bg-slate-50 border border-slate-100">
                <div>
                  <span class="font-bold text-slate-900">Room {{ r.room_number }}</span>
                  <span class="text-slate-500 text-[10px] ml-1.5">({{ r.category_name }})</span>
                </div>
                <div class="font-mono font-bold text-slate-800">{{ r.subtotal_etb?.toLocaleString() }} ETB</div>
              </div>
            </div>
          </div>

          <!-- Financial Statement -->
          <div class="p-3 bg-slate-50 rounded-xl space-y-1 border border-slate-100">
            <div class="flex justify-between">
              <span>Total Stay Price:</span>
              <span class="font-bold">{{ confirmationModal.voucher.financial.total_amount_etb?.toLocaleString() }} ETB</span>
            </div>
            <div class="flex justify-between text-emerald-700">
              <span>Amount Paid:</span>
              <span class="font-bold">{{ confirmationModal.voucher.financial.amount_paid_etb?.toLocaleString() }} ETB</span>
            </div>
            <div class="flex justify-between font-bold text-slate-900 pt-1 border-t border-slate-200">
              <span>Balance Due at Check-In:</span>
              <span>{{ confirmationModal.voucher.financial.balance_due_etb?.toLocaleString() }} ETB</span>
            </div>
          </div>

          <!-- Policy Notice -->
          <div class="text-[9px] text-slate-400 text-center italic border-t border-slate-100 pt-2">
            {{ confirmationModal.voucher.cancellation_policy }}
          </div>
        </div>

        <!-- Voucher Actions -->
        <div class="flex justify-end gap-2 pt-2">
          <button 
            @click="printVoucher" 
            class="px-4 py-2 bg-[#0B4D34] hover:bg-[#064E3B] text-white text-xs font-bold rounded-xl transition">
            🖨️ Print Voucher
          </button>
          <button 
            @click="confirmationModal.show = false" 
            class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition">
            Close
          </button>
        </div>
      </div>
    </div>

    <!-- MODAL 4: RESERVATION MODIFICATION MODAL -->
    <div 
      v-if="modifyModal.show && modifyModal.booking" 
      class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4">
      <div class="bg-white rounded-3xl shadow-2xl max-w-lg w-full p-6 space-y-4">
        <div class="flex items-start justify-between border-b border-slate-100 pb-3">
          <div>
            <h3 class="text-lg font-bold text-slate-900">Modify Reservation {{ modifyModal.booking.reference_code }}</h3>
            <p class="text-xs text-slate-400">Update stay dates, guests, or special requests.</p>
          </div>
          <button @click="modifyModal.show = false" class="text-slate-400 hover:text-slate-600">✕</button>
        </div>

        <form @submit.prevent="submitModification" class="space-y-3 text-xs">
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="font-semibold text-slate-700 block mb-1">Check-In Date</label>
              <input 
                v-model="modifyModal.form.check_in_date" 
                type="date" 
                required
                class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl" />
            </div>
            <div>
              <label class="font-semibold text-slate-700 block mb-1">Check-Out Date</label>
              <input 
                v-model="modifyModal.form.check_out_date" 
                type="date" 
                required
                class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl" />
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="font-semibold text-slate-700 block mb-1">Adults</label>
              <input 
                v-model.number="modifyModal.form.adults_count" 
                type="number" 
                min="1" 
                class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl" />
            </div>
            <div>
              <label class="font-semibold text-slate-700 block mb-1">Children</label>
              <input 
                v-model.number="modifyModal.form.children_count" 
                type="number" 
                min="0" 
                class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl" />
            </div>
          </div>

          <div>
            <label class="font-semibold text-slate-700 block mb-1">Special Requests</label>
            <textarea 
              v-model="modifyModal.form.special_requests" 
              rows="2" 
              class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl"></textarea>
          </div>

          <div class="flex justify-end gap-2 pt-2 border-t border-slate-100">
            <button 
              type="button" 
              @click="modifyModal.show = false"
              class="px-4 py-2 bg-slate-100 text-slate-700 text-xs font-bold rounded-xl">
              Cancel
            </button>
            <button 
              type="submit" 
              :disabled="modifyModal.saving"
              class="px-4 py-2 bg-[#0B4D34] text-white text-xs font-bold rounded-xl disabled:opacity-50">
              {{ modifyModal.saving ? 'Recalculating...' : 'Save Changes' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- MODAL 5: CANCELLATION MODAL -->
    <div 
      v-if="cancelModal.show && cancelModal.booking" 
      class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4">
      <div class="bg-white rounded-3xl shadow-2xl max-w-md w-full p-6 space-y-4">
        <div class="flex items-center gap-3 text-rose-600">
          <div class="w-10 h-10 rounded-full bg-rose-50 flex items-center justify-center text-xl font-bold">
            🚫
          </div>
          <div>
            <h3 class="text-base font-bold text-slate-900">Cancel Reservation</h3>
            <p class="text-[11px] text-slate-400 font-mono">{{ cancelModal.booking.reference_code }}</p>
          </div>
        </div>

        <div class="text-xs text-slate-600 space-y-2">
          <p>
            Are you sure you want to cancel the reservation for <strong>{{ cancelModal.booking.guest_name }}</strong>?
          </p>
          <div class="p-2.5 rounded-lg bg-amber-50 border border-amber-200 text-amber-900 text-[11px]">
            ⚠️ Cancelling will immediately release all reserved rooms back to the hotel inventory and update the financial ledger.
          </div>
        </div>

        <div>
          <label class="text-xs font-semibold text-slate-700 block mb-1">
            Reason for Cancellation <span class="text-rose-500">*</span>
          </label>
          <textarea 
            v-model="cancelModal.reason" 
            rows="2" 
            placeholder="e.g. Flight delay, emergency, guest request..." 
            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs"></textarea>
        </div>

        <div class="flex justify-end gap-2 pt-2">
          <button 
            type="button" 
            @click="cancelModal.show = false"
            class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition">
            Keep Reservation
          </button>
          <button 
            type="button" 
            @click="executeCancel"
            :disabled="cancelModal.cancelling || !cancelModal.reason"
            class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold rounded-xl shadow-xs transition disabled:opacity-50">
            {{ cancelModal.cancelling ? 'Releasing Rooms...' : 'Confirm Cancellation' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';

const currentStatus = ref('all');
const searchQuery = ref('');
const reservations = ref([]);
const loading = ref(false);
const pageSize = ref(10);
const currentPage = ref(1);

const stats = ref({
  total: 0,
  arrivals_today: 0,
  departures_today: 0,
  in_house: 0,
  upcoming: 0,
  pending: 0,
  confirmed: 0,
  cancelled: 0,
  no_show: 0
});

const dateFilters = ref({
  checkIn: '',
  checkOut: '',
});

const statusTabs = [
  { id: 'all', label: 'All Stays' },
  { id: 'arrivals_today', label: 'Today In' },
  { id: 'departures_today', label: 'Today Out' },
  { id: 'in_house', label: 'In-House' },
  { id: 'upcoming', label: 'Upcoming' },
  { id: 'pending', label: 'Pending' },
  { id: 'confirmed', label: 'Confirmed' },
  { id: 'cancelled', label: 'Cancelled' },
  { id: 'no_show', label: 'No-Show' }
];

const todayStr = new Date().toISOString().split('T')[0];
const tomorrowStr = new Date(Date.now() + 86400000).toISOString().split('T')[0];
const dayAfterStr = new Date(Date.now() + 86400000 * 3).toISOString().split('T')[0];

const notification = ref({
  show: false,
  message: '',
  type: 'success'
});

function showToast(message, type = 'success') {
  notification.value = { show: true, message, type };
  setTimeout(() => { notification.value.show = false; }, 4500);
}

// Modal States
const newBookingModal = ref({ show: false, isWalkIn: false });
const detailsModal = ref({ show: false, booking: null });
const confirmationModal = ref({ show: false, voucher: null });
const modifyModal = ref({ show: false, booking: null, saving: false, form: {} });
const cancelModal = ref({ show: false, booking: null, reason: '', cancelling: false });

// New Booking Form State
const bookingForm = ref({
  check_in_date: tomorrowStr,
  check_out_date: dayAfterStr,
  adults: 2,
  children: 0,
  guest_name: '',
  guest_phone: '',
  guest_email: '',
  meal_plan: 'room_only',
  payment_method: 'telebirr',
  special_requests: '',
});

const selectedRooms = ref([]);
const availableRoomsList = ref([]);
const searchingRooms = ref(false);
const savingBooking = ref(false);

function getAuthHeaders() {
  const token = localStorage.getItem('hotel_auth_token');
  return token ? { 'Authorization': `Bearer ${token}` } : {};
}

function getTabCount(tabId) {
  switch (tabId) {
    case 'all': return stats.value.total || 0;
    case 'arrivals_today': return stats.value.arrivals_today || 0;
    case 'departures_today': return stats.value.departures_today || 0;
    case 'in_house': return stats.value.in_house || 0;
    case 'upcoming': return stats.value.upcoming || 0;
    case 'pending': return stats.value.pending || 0;
    case 'confirmed': return stats.value.confirmed || 0;
    case 'cancelled': return stats.value.cancelled || 0;
    case 'no_show': return stats.value.no_show || 0;
    default: return 0;
  }
}

function getPaymentBadgeClass(status) {
  if (status === 'verified' || status === 'paid') return 'bg-emerald-100 text-emerald-800';
  if (status === 'pending_verification' || status === 'pending') return 'bg-amber-100 text-amber-800';
  if (status === 'refunded') return 'bg-blue-100 text-blue-800';
  return 'bg-rose-100 text-rose-800';
}

function getBookingBadgeClass(status) {
  switch (status) {
    case 'confirmed': return 'bg-emerald-100 text-emerald-800';
    case 'checked_in': return 'bg-amber-100 text-amber-800';
    case 'checked_out': return 'bg-slate-100 text-slate-700';
    case 'pending':
    case 'pending_payment':
    case 'pending_verification': return 'bg-yellow-100 text-yellow-800';
    case 'cancelled': return 'bg-rose-100 text-rose-800';
    case 'no_show': return 'bg-purple-100 text-purple-800';
    default: return 'bg-slate-100 text-slate-700';
  }
}

function getBookingDotClass(status) {
  switch (status) {
    case 'confirmed': return 'bg-emerald-500';
    case 'checked_in': return 'bg-amber-500 animate-pulse';
    case 'checked_out': return 'bg-slate-400';
    case 'pending':
    case 'pending_payment': return 'bg-yellow-500';
    case 'cancelled': return 'bg-rose-500';
    case 'no_show': return 'bg-purple-500';
    default: return 'bg-slate-400';
  }
}

function calculateNights(inDate, outDate) {
  if (!inDate || !outDate) return 1;
  const d1 = new Date(inDate);
  const d2 = new Date(outDate);
  return Math.max(1, Math.round((d2 - d1) / (1000 * 60 * 60 * 24)));
}

function formatShortDate(d) {
  if (!d) return '';
  return new Date(d).toLocaleDateString();
}

function formatDateTime(d) {
  if (!d) return '';
  return new Date(d).toLocaleString();
}

function isPastArrival(d) {
  if (!d) return false;
  return new Date(d) <= new Date();
}

const totalPages = computed(() => {
  if (pageSize.value >= 1000) return 1;
  return Math.max(1, Math.ceil(reservations.value.length / pageSize.value));
});

const paginationStart = computed(() => {
  if (!reservations.value.length) return 0;
  return (currentPage.value - 1) * pageSize.value + 1;
});

const paginationEnd = computed(() => {
  return Math.min(currentPage.value * pageSize.value, reservations.value.length);
});

const paginatedReservations = computed(() => {
  if (pageSize.value >= 1000) return reservations.value;
  const start = (currentPage.value - 1) * pageSize.value;
  return reservations.value.slice(start, start + pageSize.value);
});

const computedNights = computed(() => {
  return calculateNights(bookingForm.value.check_in_date, bookingForm.value.check_out_date);
});

const computedEstimatedTotal = computed(() => {
  const roomsSum = selectedRooms.value.reduce((acc, r) => acc + (r.total_stay_price_etb || 0), 0);
  let mealPerGuest = 0;
  if (bookingForm.value.meal_plan === 'bed_breakfast') mealPerGuest = 650;
  if (bookingForm.value.meal_plan === 'half_board') mealPerGuest = 1450;
  if (bookingForm.value.meal_plan === 'full_board') mealPerGuest = 2200;

  const totalGuests = Math.max(1, (bookingForm.value.adults || 1) + (bookingForm.value.children || 0));
  const mealsSum = mealPerGuest * totalGuests * computedNights.value;
  return roomsSum + mealsSum;
});

async function fetchBookings() {
  loading.value = true;
  try {
    const params = new URLSearchParams();
    if (currentStatus.value !== 'all') params.append('status', currentStatus.value);
    if (searchQuery.value) params.append('search', searchQuery.value);
    if (dateFilters.value.checkIn) params.append('check_in_date', dateFilters.value.checkIn);
    if (dateFilters.value.checkOut) params.append('check_out_date', dateFilters.value.checkOut);

    const res = await fetch(`/api/v1/bookings?${params.toString()}`, {
      headers: getAuthHeaders()
    });
    const data = await res.json();
    reservations.value = data.reservations || [];
    if (data.stats) stats.value = data.stats;
    currentPage.value = 1;
  } catch (err) {
    console.error('Failed to load reservations:', err);
    showToast('Failed to load reservations master ledger.', 'error');
  } finally {
    loading.value = false;
  }
}

function resetFilters() {
  currentStatus.value = 'all';
  searchQuery.value = '';
  dateFilters.value = { checkIn: '', checkOut: '' };
  fetchBookings();
}

// Room Selection Logic
function isRoomSelected(roomId) {
  return selectedRooms.value.some(r => r.id === roomId);
}

function toggleRoomSelection(room) {
  const idx = selectedRooms.value.findIndex(r => r.id === room.id);
  if (idx >= 0) {
    selectedRooms.value.splice(idx, 1);
  } else {
    selectedRooms.value.push(room);
  }
}

async function onSearchAvailability() {
  searchingRooms.value = true;
  try {
    const res = await fetch(`/api/v1/rooms/search-availability?check_in=${bookingForm.value.check_in_date}&check_out=${bookingForm.value.check_out_date}`);
    const data = await res.json();
    availableRoomsList.value = data.available_rooms || [];
    // Deselect rooms no longer available
    selectedRooms.value = selectedRooms.value.filter(sr => availableRoomsList.value.some(ar => ar.id === sr.id));
  } catch (err) {
    console.error('Error searching rooms:', err);
  } finally {
    searchingRooms.value = false;
  }
}

function openNewBookingModal(isWalkIn = false) {
  newBookingModal.value = { show: true, isWalkIn };
  selectedRooms.value = [];
  bookingForm.value = {
    check_in_date: isWalkIn ? todayStr : tomorrowStr,
    check_out_date: isWalkIn ? tomorrowStr : dayAfterStr,
    adults: 2,
    children: 0,
    guest_name: '',
    guest_phone: '',
    guest_email: '',
    meal_plan: 'room_only',
    payment_method: isWalkIn ? 'cash' : 'telebirr',
    special_requests: '',
  };
  onSearchAvailability();
}

async function submitNewBooking() {
  if (selectedRooms.value.length === 0) {
    showToast('Please select at least one room.', 'error');
    return;
  }

  savingBooking.value = true;
  try {
    const payload = {
      check_in_date: bookingForm.value.check_in_date,
      check_out_date: bookingForm.value.check_out_date,
      rooms: selectedRooms.value.map(r => ({
        room_id: r.id,
        adults: Math.max(1, Math.floor(bookingForm.value.adults / selectedRooms.value.length)),
        children: 0,
      })),
      adults: bookingForm.value.adults,
      children: bookingForm.value.children,
      guest_name: bookingForm.value.guest_name,
      guest_phone: bookingForm.value.guest_phone,
      guest_email: bookingForm.value.guest_email,
      meal_plan: bookingForm.value.meal_plan,
      payment_method: bookingForm.value.payment_method,
      is_walk_in: newBookingModal.value.isWalkIn,
      special_requests: bookingForm.value.special_requests,
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
    if (!res.ok) throw new Error(data.error || 'Failed to create reservation');

    showToast(`✓ Reservation ${data.booking.reference_code} confirmed!`, 'success');
    newBookingModal.value.show = false;
    fetchBookings();
  } catch (err) {
    showToast(err.message, 'error');
  } finally {
    savingBooking.value = false;
  }
}

async function openDetails(b) {
  try {
    const res = await fetch(`/api/v1/bookings/${b.id}`, { headers: getAuthHeaders() });
    const data = await res.json();
    detailsModal.value = { show: true, booking: data.booking || b };
  } catch (err) {
    detailsModal.value = { show: true, booking: b };
  }
}

async function openConfirmation(b) {
  try {
    const res = await fetch(`/api/v1/bookings/${b.id}/confirmation`, { headers: getAuthHeaders() });
    const data = await res.json();
    confirmationModal.value = { show: true, voucher: data.confirmation };
  } catch (err) {
    showToast('Failed to load voucher.', 'error');
  }
}

function printVoucher() {
  window.print();
}

function openModify(b) {
  modifyModal.value = {
    show: true,
    booking: b,
    saving: false,
    form: {
      check_in_date: b.check_in_date,
      check_out_date: b.check_out_date,
      adults_count: b.adults_count || 1,
      children_count: b.children_count || 0,
      special_requests: b.notes_data?.special_requests || '',
    }
  };
}

async function submitModification() {
  modifyModal.value.saving = true;
  try {
    const res = await fetch(`/api/v1/bookings/${modifyModal.value.booking.id}`, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
        ...getAuthHeaders()
      },
      body: JSON.stringify(modifyModal.value.form)
    });

    const data = await res.json();
    if (!res.ok) throw new Error(data.error || 'Failed to modify reservation');

    showToast(data.message, 'success');
    modifyModal.value.show = false;
    fetchBookings();
  } catch (err) {
    showToast(err.message, 'error');
  } finally {
    modifyModal.value.saving = false;
  }
}

function openCancel(b) {
  cancelModal.value = {
    show: true,
    booking: b,
    reason: '',
    cancelling: false
  };
}

async function executeCancel() {
  cancelModal.value.cancelling = true;
  try {
    const res = await fetch(`/api/v1/bookings/${cancelModal.value.booking.id}/cancel`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        ...getAuthHeaders()
      },
      body: JSON.stringify({ cancellation_reason: cancelModal.value.reason })
    });

    const data = await res.json();
    if (!res.ok) throw new Error(data.error || 'Failed to cancel reservation');

    showToast(data.message, 'success');
    cancelModal.value.show = false;
    fetchBookings();
  } catch (err) {
    showToast(err.message, 'error');
  } finally {
    cancelModal.value.cancelling = false;
  }
}

async function markNoShow(b) {
  if (!confirm(`Mark reservation ${b.reference_code} as No-Show and release rooms?`)) return;

  try {
    const res = await fetch(`/api/v1/bookings/${b.id}/no-show`, {
      method: 'POST',
      headers: getAuthHeaders()
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.error || 'Failed to mark No-Show');

    showToast(data.message, 'success');
    fetchBookings();
  } catch (err) {
    showToast(err.message, 'error');
  }
}

async function checkInGuest(b) {
  try {
    const res = await fetch(`/api/v1/bookings/${b.id}/check-in`, {
      method: 'POST',
      headers: getAuthHeaders()
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.error || 'Check-in failed');

    showToast(data.message, 'success');
    fetchBookings();
  } catch (err) {
    showToast(err.message, 'error');
  }
}

async function checkOutGuest(b) {
  try {
    const res = await fetch(`/api/v1/bookings/${b.id}/check-out`, {
      method: 'POST',
      headers: getAuthHeaders()
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.error || 'Check-out failed');

    showToast(data.message, 'success');
    fetchBookings();
  } catch (err) {
    showToast(err.message, 'error');
  }
}

onMounted(() => {
  fetchBookings();
});
</script>
