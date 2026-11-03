<template>
  <div class="space-y-6">
    <!-- Header with Title & Quick Actions -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-5 rounded-2xl shadow-sm border border-slate-100">
      <div>
        <div class="flex items-center gap-2">
          <span class="text-2xl">🛎️</span>
          <h2 class="text-2xl font-bold text-[#0B4D34]">Front Desk Check-In Console</h2>
        </div>
        <p class="text-xs text-slate-500 mt-1">Arrivals processing, instant walk-ins, room readiness verification & keycard issuing.</p>
      </div>

      <div class="flex flex-wrap items-center gap-2">
        <button 
          @click="openWalkInModal"
          class="px-4 py-2 bg-[#0B4D34] hover:bg-[#064E3B] text-white text-xs font-bold rounded-xl shadow-sm transition flex items-center gap-1.5 cursor-pointer">
          <span>+</span>
          <span>Walk-In Check-In</span>
        </button>

        <button 
          @click="openAuditLedger"
          class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl transition flex items-center gap-1 cursor-pointer">
          <span>📋</span>
          <span>Operations History</span>
        </button>

        <button 
          @click="fetchArrivalsAndDashboard"
          class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl transition cursor-pointer">
          🔄 Refresh
        </button>
      </div>
    </div>

    <!-- Live Front Desk KPI Dashboard Cards -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
      <div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-100">
        <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Arrivals Today</span>
        <div class="text-2xl font-black text-[#0B4D34] mt-1">{{ dashboardMetrics.expected_arrivals_today }}</div>
        <span class="text-[10px] text-slate-500">Expected arrivals</span>
      </div>

      <div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-100">
        <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Pending In</span>
        <div class="text-2xl font-black text-amber-600 mt-1">{{ dashboardMetrics.pending_check_ins }}</div>
        <span class="text-[10px] text-amber-700 font-medium">Awaiting arrival</span>
      </div>

      <div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-100">
        <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">In-House Guests</span>
        <div class="text-2xl font-black text-emerald-700 mt-1">{{ dashboardMetrics.in_house_guests }}</div>
        <span class="text-[10px] text-slate-500">Currently staying</span>
      </div>

      <div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-100">
        <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Walk-Ins Today</span>
        <div class="text-2xl font-black text-blue-700 mt-1">{{ dashboardMetrics.walk_ins_today }}</div>
        <span class="text-[10px] text-blue-600 font-medium">Direct arrivals</span>
      </div>

      <div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-100">
        <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Rooms Ready</span>
        <div class="text-2xl font-black text-emerald-600 mt-1">{{ dashboardMetrics.rooms_ready }}</div>
        <span class="text-[10px] text-emerald-700 font-medium">Clean & inspected</span>
      </div>

      <div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-100">
        <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Prep / Dirty</span>
        <div class="text-2xl font-black text-rose-600 mt-1">{{ dashboardMetrics.rooms_turnover_prep }}</div>
        <span class="text-[10px] text-rose-600 font-medium">Housekeeping turnover</span>
      </div>
    </div>

    <!-- Search Bar & Filters -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div class="relative w-full sm:w-96">
        <input 
          v-model="searchQuery" 
          @input="fetchArrivalsAndDashboard"
          type="text" 
          placeholder="Search by Reference (#ETH-XXXXX), Guest, or Room..." 
          class="w-full pl-9 pr-4 py-2.5 bg-white border border-slate-200 rounded-full text-xs focus:outline-none focus:border-[#0B4D34] shadow-sm" />
        <span class="absolute left-3.5 top-3 text-xs text-slate-400">🔍</span>
      </div>

      <div class="text-xs text-slate-500 font-medium">
        Showing {{ eligibleArrivals.length }} arrival{{ eligibleArrivals.length === 1 ? '' : 's' }} eligible for check-in
      </div>
    </div>

    <!-- Layout: Arrivals Table + Quick QR Scanner Frame -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Left 2 Cols: Eligible Arrivals Table -->
      <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
          <h3 class="text-base font-bold text-slate-900">Eligible Confirmed Arrivals</h3>
          <span class="text-xs text-slate-400">Filter: booking_status = 'confirmed'</span>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse text-sm">
            <thead>
              <tr class="bg-slate-50 text-slate-600 text-xs font-semibold uppercase tracking-wider border-b border-slate-100">
                <th class="py-3 px-4">Ref Code</th>
                <th class="py-3 px-4">Guest Details</th>
                <th class="py-3 px-4">Assigned Room</th>
                <th class="py-3 px-4">Scheduled Stay</th>
                <th class="py-3 px-4">Room Readiness</th>
                <th class="py-3 px-4">Payment</th>
                <th class="py-3 px-4 text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-slate-700">
              <tr v-if="loading" class="text-center">
                <td colspan="7" class="py-8 text-slate-400 text-xs">Loading arrivals from database...</td>
              </tr>
              <tr v-else-if="eligibleArrivals.length === 0" class="text-center">
                <td colspan="7" class="py-8 text-slate-400 text-xs">No confirmed arrivals pending check-in.</td>
              </tr>
              <tr 
                v-else
                v-for="b in eligibleArrivals" 
                :key="b.id" 
                class="hover:bg-slate-50/50 transition">
                <!-- Ref -->
                <td class="py-3 px-4 font-mono font-bold text-slate-900 text-xs">
                  {{ b.reference_code }}
                </td>
                <!-- Guest -->
                <td class="py-3 px-4">
                  <div class="font-bold text-slate-900 text-xs">{{ b.guest_name }}</div>
                  <div class="text-[11px] text-slate-400 font-mono">{{ b.guest_phone || 'No phone' }}</div>
                </td>
                <!-- Room & Reassign Action -->
                <td class="py-3 px-4">
                  <div class="font-bold text-slate-800 text-xs">Room {{ b.room_number }}</div>
                  <div class="text-[10px] text-slate-400">{{ b.category_name }}</div>
                  <button 
                    @click="openReassignModal(b)"
                    class="mt-1 text-[10px] text-emerald-800 font-semibold hover:underline flex items-center gap-0.5 cursor-pointer">
                    <span>🔁</span> Reassign
                  </button>
                </td>
                <!-- Dates -->
                <td class="py-3 px-4 text-xs text-slate-600">
                  <div>{{ b.check_in_date }}</div>
                  <div class="text-[10px] text-slate-400">to {{ b.check_out_date }}</div>
                </td>
                <!-- Room Readiness -->
                <td class="py-3 px-4">
                  <span 
                    v-if="b.is_room_ready" 
                    class="text-[11px] font-bold px-2.5 py-0.5 rounded-full bg-emerald-100 text-emerald-800 flex items-center gap-1 w-fit">
                    <span>✓</span> Ready / Clean
                  </span>
                  <span 
                    v-else 
                    class="text-[11px] font-bold px-2.5 py-0.5 rounded-full bg-amber-100 text-amber-800 flex items-center gap-1 w-fit">
                    <span>⏳</span> In Prep / Turnover
                  </span>
                </td>
                <!-- Payment -->
                <td class="py-3 px-4">
                  <span 
                    :class="b.payment_status === 'paid' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'"
                    class="text-[11px] font-bold px-2 py-0.5 rounded-full capitalize">
                    {{ b.payment_status === 'paid' ? '✓ Paid' : 'Pending' }}
                  </span>
                </td>
                <!-- Action -->
                <td class="py-3 px-4 text-right">
                  <div class="inline-flex items-center gap-1.5">
                    <button 
                      @click="openRegCard(b)"
                      class="px-2.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl transition cursor-pointer"
                      title="Digital Registration Card & ID">
                      <span>📋</span>
                    </button>
                    <button 
                      @click="openCheckInModal(b)"
                      class="px-3.5 py-1.5 bg-[#0B4D34] hover:bg-[#064E3B] text-white text-xs font-bold rounded-xl shadow-xs transition flex items-center gap-1 cursor-pointer">
                      <span>🛎️ Check-In</span>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Right 1 Col: Quick QR Scanner Simulation -->
      <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 flex flex-col justify-between text-center space-y-4 h-fit sticky top-6">
        <div>
          <div class="w-14 h-14 rounded-2xl bg-emerald-50 text-[#0B4D34] flex items-center justify-center text-2xl mx-auto mb-2 border border-emerald-100">
            📷
          </div>
          <h3 class="text-base font-bold text-slate-900">Touchless QR Check-In</h3>
          <p class="text-xs text-slate-400 mt-1">Scan guest mobile receipt for instant verification</p>
        </div>

        <!-- Simulated Active QR Scanner Frame -->
        <div class="relative bg-slate-50 border-2 border-dashed border-[#0B4D34]/40 rounded-2xl p-4">
          <img 
            :src="simulatedQrUrl" 
            class="w-40 h-40 mx-auto bg-white p-2 rounded-xl shadow-sm border border-slate-200" 
            alt="Scanner View" />
          <div class="mt-3 bg-white p-2 rounded-xl shadow-sm border border-emerald-100 text-xs">
            <span class="text-slate-500 font-medium block">Active Camera Feed:</span>
            <span class="font-bold text-[#0B4D34] text-xs">Ready for Guest Optical Token</span>
          </div>
        </div>

        <button 
          @click="scanFirstArrival"
          :disabled="eligibleArrivals.length === 0"
          class="w-full py-3 bg-[#0B4D34] hover:bg-[#064E3B] text-white font-bold text-xs rounded-xl shadow-md transition disabled:opacity-50 cursor-pointer">
          Simulate Optical QR Check-In
        </button>
      </div>
    </div>

    <!-- Complete Front Desk Check-In Modal (Keycard, Arriving Guests, Notes, Early Arrival) -->
    <div v-if="activeCheckInBooking" class="fixed inset-0 bg-black/60 backdrop-blur-xs z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-3xl overflow-hidden shadow-2xl max-w-lg w-full p-6 space-y-4 border border-slate-100">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
          <div>
            <h3 class="text-base font-bold text-slate-900">Execute Guest Check-In</h3>
            <p class="text-xs text-slate-400">Ref: {{ activeCheckInBooking.reference_code }}</p>
          </div>
          <button @click="activeCheckInBooking = null" class="text-slate-400 hover:text-slate-600 text-sm cursor-pointer">✕</button>
        </div>

        <!-- Room & Guest summary -->
        <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-200 text-xs space-y-2">
          <div class="flex justify-between items-center">
            <span class="text-slate-500">Guest Name:</span>
            <span class="font-bold text-slate-900 text-sm">{{ activeCheckInBooking.guest_name }}</span>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-slate-500">Assigned Room:</span>
            <span class="font-bold text-[#0B4D34]">Room {{ activeCheckInBooking.room_number }} ({{ activeCheckInBooking.category_name }})</span>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-slate-500">Room Readiness:</span>
            <span v-if="activeCheckInBooking.is_room_ready" class="text-emerald-700 font-bold">✓ Ready & Clean</span>
            <span v-else class="text-amber-700 font-bold">⚠️ Room in preparation / dirty</span>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-slate-500">Scheduled Dates:</span>
            <span class="font-semibold text-slate-800">{{ activeCheckInBooking.check_in_date }} → {{ activeCheckInBooking.check_out_date }}</span>
          </div>
        </div>

        <!-- Form fields: Keycard & Guest Count -->
        <div class="grid grid-cols-2 gap-3 text-xs">
          <div>
            <label class="font-bold text-slate-700 block mb-1">🔑 Keycard Number</label>
            <input 
              v-model="checkInForm.key_card_number" 
              type="text" 
              placeholder="e.g. KC-302" 
              class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono outline-none focus:border-[#0B4D34]" />
          </div>
          <div>
            <label class="font-bold text-slate-700 block mb-1">👥 Arriving Guests</label>
            <input 
              v-model.number="checkInForm.num_guests" 
              type="number" 
              min="1" 
              class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs outline-none focus:border-[#0B4D34]" />
          </div>
        </div>

        <!-- Early Check-In Option -->
        <div class="p-3 bg-blue-50/70 rounded-xl border border-blue-200 text-xs space-y-2">
          <label class="flex items-center gap-2 font-bold text-blue-900 cursor-pointer">
            <input type="checkbox" v-model="checkInForm.early_check_in_approved" class="rounded accent-[#0B4D34]" />
            <span>Approve Early Check-In Surcharge</span>
          </label>
          <div v-if="checkInForm.early_check_in_approved" class="grid grid-cols-2 gap-2 pt-1">
            <div>
              <span class="text-[11px] text-blue-800 block">Early Fee (ETB):</span>
              <input 
                v-model.number="checkInForm.early_check_in_fee_etb" 
                type="number" 
                placeholder="ETB Surcharge" 
                class="w-full px-2.5 py-1.5 bg-white border border-blue-300 rounded-lg text-xs" />
            </div>
            <div>
              <span class="text-[11px] text-blue-800 block">Reason:</span>
              <input 
                v-model="checkInForm.early_reason" 
                type="text" 
                placeholder="e.g. Flight arrived 07:00 AM" 
                class="w-full px-2.5 py-1.5 bg-white border border-blue-300 rounded-lg text-xs" />
            </div>
          </div>
        </div>

        <!-- Check-in Front Desk Notes -->
        <div class="text-xs space-y-1">
          <label class="font-bold text-slate-700 block">📝 Front Desk Check-In Notes</label>
          <textarea 
            v-model="checkInForm.check_in_notes" 
            rows="2" 
            placeholder="Special guest requests, luggage handling or VIP greetings..." 
            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs outline-none focus:border-[#0B4D34]"></textarea>
        </div>

        <!-- Action Buttons -->
        <div class="flex gap-2 pt-2">
          <button 
            @click="submitCheckIn"
            :disabled="checkingInId === activeCheckInBooking.id"
            class="flex-1 py-3 bg-[#0B4D34] hover:bg-[#064E3B] text-white text-xs font-bold rounded-xl shadow-xs transition disabled:opacity-50 cursor-pointer">
            {{ checkingInId === activeCheckInBooking.id ? 'Processing...' : 'Confirm Check-In & Activate Room' }}
          </button>
          <button 
            @click="activeCheckInBooking = null"
            class="px-4 py-3 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl cursor-pointer">
            Cancel
          </button>
        </div>
      </div>
    </div>

    <!-- Reassign Room Modal -->
    <div v-if="reassignBooking" class="fixed inset-0 bg-black/60 backdrop-blur-xs z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-3xl overflow-hidden shadow-2xl max-w-md w-full p-6 space-y-4 border border-slate-100">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
          <div>
            <h3 class="text-base font-bold text-slate-900">Reassign Guest Room</h3>
            <p class="text-xs text-slate-400">Ref: {{ reassignBooking.reference_code }} • Guest: {{ reassignBooking.guest_name }}</p>
          </div>
          <button @click="reassignBooking = null" class="text-slate-400 hover:text-slate-600 text-sm cursor-pointer">✕</button>
        </div>

        <div class="text-xs bg-slate-50 p-3 rounded-xl border border-slate-200">
          <span class="text-slate-500 block">Currently Assigned:</span>
          <span class="font-bold text-slate-900">Room {{ reassignBooking.room_number }} ({{ reassignBooking.category_name }})</span>
        </div>

        <div class="text-xs space-y-1">
          <label class="font-bold text-slate-700 block">Select New Available Room</label>
          <select 
            v-model="reassignForm.new_room_id" 
            class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs outline-none focus:border-[#0B4D34]">
            <option value="" disabled>-- Select Available Room --</option>
            <option v-for="r in availableCleanRooms" :key="r.id" :value="r.id">
              Room {{ r.room_number }} ({{ r.category?.name || 'Standard' }}) - ETB {{ r.price_etb?.toLocaleString() }} [Max {{ r.capacity }} Pax]
            </option>
          </select>
        </div>

        <div class="text-xs space-y-1">
          <label class="font-bold text-slate-700 block">Reason for Reassignment</label>
          <input 
            v-model="reassignForm.reason" 
            type="text" 
            placeholder="e.g. Guest requested upper floor / AC maintenance" 
            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs outline-none focus:border-[#0B4D34]" />
        </div>

        <div class="flex gap-2 pt-2">
          <button 
            @click="submitReassignRoom"
            :disabled="!reassignForm.new_room_id || isSubmittingReassign"
            class="flex-1 py-3 bg-[#0B4D34] hover:bg-[#064E3B] text-white text-xs font-bold rounded-xl shadow-xs transition disabled:opacity-50 cursor-pointer">
            {{ isSubmittingReassign ? 'Reassigning...' : 'Confirm Room Change' }}
          </button>
          <button 
            @click="reassignBooking = null"
            class="px-4 py-3 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl cursor-pointer">
            Cancel
          </button>
        </div>
      </div>
    </div>

    <!-- Walk-In Quick Check-In Modal -->
    <div v-if="showWalkInModal" class="fixed inset-0 bg-black/60 backdrop-blur-xs z-50 flex items-center justify-center p-4 overflow-y-auto">
      <div class="bg-white rounded-3xl overflow-hidden shadow-2xl max-w-xl w-full p-6 space-y-4 border border-slate-100 my-8">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
          <div>
            <h3 class="text-base font-bold text-slate-900">Direct Walk-In Check-In</h3>
            <p class="text-xs text-slate-400">Instant registration, room allocation & key issue</p>
          </div>
          <button @click="showWalkInModal = false" class="text-slate-400 hover:text-slate-600 text-sm cursor-pointer">✕</button>
        </div>

        <!-- Guest Details -->
        <div class="space-y-2">
          <span class="text-xs font-bold text-[#0B4D34] uppercase tracking-wider block">👤 Guest Information</span>
          <div class="grid grid-cols-2 gap-2 text-xs">
            <div>
              <label class="text-[11px] text-slate-500 block mb-0.5">First Name *</label>
              <input v-model="walkInForm.first_name" type="text" placeholder="e.g. Dawit" class="w-full px-2.5 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs" />
            </div>
            <div>
              <label class="text-[11px] text-slate-500 block mb-0.5">Last Name *</label>
              <input v-model="walkInForm.last_name" type="text" placeholder="e.g. Haile" class="w-full px-2.5 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs" />
            </div>
            <div>
              <label class="text-[11px] text-slate-500 block mb-0.5">Phone Number *</label>
              <input v-model="walkInForm.phone" type="text" placeholder="+251 911 000000" class="w-full px-2.5 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs font-mono" />
            </div>
            <div>
              <label class="text-[11px] text-slate-500 block mb-0.5">Email Address</label>
              <input v-model="walkInForm.email" type="email" placeholder="dawit@example.com" class="w-full px-2.5 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs" />
            </div>
            <div>
              <label class="text-[11px] text-slate-500 block mb-0.5">ID Type</label>
              <select v-model="walkInForm.id_type" class="w-full px-2.5 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs">
                <option value="national_id">National ID</option>
                <option value="passport">Passport</option>
                <option value="kebele_id">Kebele ID</option>
                <option value="driving_license">Driver's License</option>
              </select>
            </div>
            <div>
              <label class="text-[11px] text-slate-500 block mb-0.5">ID Number</label>
              <input v-model="walkInForm.id_number" type="text" placeholder="ET-ID-99120" class="w-full px-2.5 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs font-mono" />
            </div>
          </div>
        </div>

        <!-- Room & Stay Details -->
        <div class="space-y-2 pt-2 border-t border-slate-100">
          <span class="text-xs font-bold text-[#0B4D34] uppercase tracking-wider block">🏨 Room & Stay</span>
          <div class="grid grid-cols-2 gap-2 text-xs">
            <div class="col-span-2">
              <label class="text-[11px] text-slate-500 block mb-0.5">Select Ready Room *</label>
              <select v-model="walkInForm.room_id" class="w-full px-2.5 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs">
                <option value="" disabled>-- Available Clean Rooms --</option>
                <option v-for="r in availableCleanRooms" :key="r.id" :value="r.id">
                  Room {{ r.room_number }} ({{ r.category?.name }}) - ETB {{ r.price_etb?.toLocaleString() }}/nt (Max {{ r.capacity }} Pax)
                </option>
              </select>
            </div>
            <div>
              <label class="text-[11px] text-slate-500 block mb-0.5">Check-In Date</label>
              <input v-model="walkInForm.check_in_date" type="date" class="w-full px-2.5 py-1.5 bg-slate-100 border border-slate-200 rounded-lg text-xs" readonly />
            </div>
            <div>
              <label class="text-[11px] text-slate-500 block mb-0.5">Check-Out Date *</label>
              <input v-model="walkInForm.check_out_date" type="date" class="w-full px-2.5 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs" />
            </div>
            <div>
              <label class="text-[11px] text-slate-500 block mb-0.5">Number of Guests</label>
              <input v-model.number="walkInForm.num_guests" type="number" min="1" class="w-full px-2.5 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs" />
            </div>
            <div>
              <label class="text-[11px] text-slate-500 block mb-0.5">Keycard Number</label>
              <input v-model="walkInForm.key_card_number" type="text" placeholder="e.g. KC-108" class="w-full px-2.5 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs font-mono" />
            </div>
          </div>
        </div>

        <!-- Initial Payment Capture -->
        <div class="space-y-2 pt-2 border-t border-slate-100">
          <span class="text-xs font-bold text-[#0B4D34] uppercase tracking-wider block">💳 Payment Collection</span>
          <div class="grid grid-cols-2 gap-2 text-xs">
            <div>
              <label class="text-[11px] text-slate-500 block mb-0.5">Payment Method</label>
              <select v-model="walkInForm.payment_method" class="w-full px-2.5 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs">
                <option value="cash">Cash (Front Desk)</option>
                <option value="telebirr">Telebirr</option>
                <option value="cbe_birr">CBE Birr</option>
                <option value="credit_card">Credit / Debit Card</option>
                <option value="bank_transfer">Bank Transfer</option>
              </select>
            </div>
            <div>
              <label class="text-[11px] text-slate-500 block mb-0.5">Paid Amount (ETB)</label>
              <input v-model.number="walkInForm.initial_payment_etb" type="number" placeholder="0 or full stay amount" class="w-full px-2.5 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs font-mono" />
            </div>
          </div>
        </div>

        <!-- Submit Button -->
        <div class="flex gap-2 pt-2">
          <button 
            @click="submitWalkInCheckIn"
            :disabled="!walkInForm.room_id || !walkInForm.first_name || !walkInForm.phone || isSubmittingWalkIn"
            class="flex-1 py-3 bg-[#0B4D34] hover:bg-[#064E3B] text-white text-xs font-bold rounded-xl shadow-xs transition disabled:opacity-50 cursor-pointer">
            {{ isSubmittingWalkIn ? 'Processing Walk-In...' : 'Complete Walk-In & Issue Key' }}
          </button>
          <button 
            @click="showWalkInModal = false"
            class="px-4 py-3 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl cursor-pointer">
            Cancel
          </button>
        </div>
      </div>
    </div>

    <!-- Operations History Audit Ledger Modal -->
    <div v-if="showAuditLedger" class="fixed inset-0 bg-black/60 backdrop-blur-xs z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-3xl overflow-hidden shadow-2xl max-w-2xl w-full p-6 space-y-4 border border-slate-100">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
          <div>
            <h3 class="text-base font-bold text-slate-900">Front Desk Operations Audit Ledger</h3>
            <p class="text-xs text-slate-400">Verifiable trace of check-ins, check-outs, reassignments & surcharges</p>
          </div>
          <button @click="showAuditLedger = false" class="text-slate-400 hover:text-slate-600 text-sm cursor-pointer">✕</button>
        </div>

        <div class="max-h-96 overflow-y-auto divide-y divide-slate-100 text-xs">
          <div v-if="auditLogs.length === 0" class="py-8 text-center text-slate-400">
            No front desk operations logged yet today.
          </div>
          <div v-else v-for="log in auditLogs" :key="log.id" class="py-2.5 px-2 hover:bg-slate-50 transition flex items-center justify-between gap-3">
            <div>
              <div class="flex items-center gap-2">
                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider"
                  :class="log.action.includes('check_in') ? 'bg-emerald-100 text-emerald-800' : (log.action.includes('check_out') ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-800')">
                  {{ log.action }}
                </span>
                <span class="font-bold text-slate-900 font-mono">{{ log.booking?.reference_code || ('Booking #' + log.booking_id) }}</span>
              </div>
              <p class="text-slate-600 text-[11px] mt-1">{{ log.notes || 'Front desk transaction processed.' }}</p>
            </div>
            <div class="text-right text-[10px] text-slate-400 whitespace-nowrap">
              <div>{{ log.user?.name || 'Staff' }}</div>
              <div>{{ formatDateTime(log.created_at) }}</div>
            </div>
          </div>
        </div>

        <div class="text-right pt-2 border-t border-slate-100">
          <button @click="showAuditLedger = false" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl">
            Close
          </button>
        </div>
      </div>
    </div>

    <!-- Digital Guest Registration Card & ID Verification Modal -->
    <div v-if="selectedRegBooking" class="fixed inset-0 bg-black/60 backdrop-blur-xs z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-3xl overflow-hidden shadow-2xl max-w-lg w-full p-6 space-y-4 border border-slate-100">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
          <div>
            <h3 class="text-base font-bold text-slate-900">Official Guest Registration Card</h3>
            <p class="text-xs text-slate-400">Ethiopian Hospitality Regulatory Compliance</p>
          </div>
          <button @click="selectedRegBooking = null" class="text-slate-400 hover:text-slate-600 text-sm cursor-pointer">✕</button>
        </div>

        <!-- Booking details -->
        <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 text-xs space-y-1.5">
          <div class="flex justify-between">
            <span class="text-slate-500">Guest Name:</span>
            <span class="font-bold text-slate-900">{{ selectedRegBooking.guest_name }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-slate-500">Phone & Email:</span>
            <span class="font-mono text-slate-800">{{ selectedRegBooking.guest_phone }} • {{ selectedRegBooking.guest_email }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-slate-500">Assigned Room:</span>
            <span class="font-bold text-[#0B4D34]">Room {{ selectedRegBooking.room_number }} ({{ selectedRegBooking.category_name }})</span>
          </div>
          <div class="flex justify-between">
            <span class="text-slate-500">Stay Dates:</span>
            <span class="font-semibold text-slate-800">{{ selectedRegBooking.check_in_date }} → {{ selectedRegBooking.check_out_date }}</span>
          </div>
        </div>

        <!-- Structured Identification Verification -->
        <div class="space-y-2">
          <label class="text-xs font-bold text-slate-700 block">🪪 Identity Verification (Regulatory Requirement)</label>
          <div class="grid grid-cols-2 gap-2 text-xs">
            <div>
              <label class="text-[11px] text-slate-500 block mb-0.5">ID Type</label>
              <select v-model="selectedRegBooking.guest_id_type" class="w-full px-2.5 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs outline-none">
                <option value="national_id">National ID</option>
                <option value="passport">Passport</option>
                <option value="kebele_id">Kebele ID</option>
                <option value="driving_license">Driver's License</option>
                <option value="resident_id">Resident ID</option>
                <option value="other">Other</option>
              </select>
            </div>
            <div>
              <label class="text-[11px] text-slate-500 block mb-0.5">ID Number</label>
              <input v-model="selectedRegBooking.guest_id_number" type="text" placeholder="e.g. ET-PASS-10294" class="w-full px-2.5 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs font-mono outline-none" />
            </div>
          </div>
        </div>

        <!-- Policy Consent Checkbox -->
        <div class="p-3 bg-amber-50 rounded-xl border border-amber-200/80 text-[11px] text-amber-900 space-y-1.5">
          <p class="font-bold">📜 Hotel House Rules & Acceptance:</p>
          <p>• Check-out time is strictly 12:00 PM noon.</p>
          <p>• All suites are 100% non-smoking.</p>
          <label class="flex items-center gap-2 pt-1 font-bold cursor-pointer">
            <input type="checkbox" v-model="policyAccepted" class="rounded accent-[#0B4D34]" />
            <span>Guest has confirmed identity and accepts house rules</span>
          </label>
        </div>

        <!-- Action -->
        <div class="flex gap-2 pt-2">
          <button 
            @click="completeRegCheckIn"
            :disabled="!policyAccepted || checkingInId === selectedRegBooking.id"
            class="flex-1 py-3 bg-[#0B4D34] hover:bg-[#064E3B] text-white text-xs font-bold rounded-xl shadow-xs transition disabled:opacity-50 cursor-pointer">
            {{ checkingInId === selectedRegBooking.id ? 'Processing...' : 'Complete Check-In & Issue Key' }}
          </button>
          <button 
            @click="selectedRegBooking = null"
            class="px-4 py-3 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl cursor-pointer">
            Cancel
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';

const eligibleArrivals = ref([]);
const dashboardMetrics = ref({
  expected_arrivals_today: 0,
  pending_check_ins: 0,
  in_house_guests: 0,
  walk_ins_today: 0,
  rooms_ready: 0,
  rooms_turnover_prep: 0
});
const availableCleanRooms = ref([]);
const loading = ref(false);
const searchQuery = ref('');
const checkingInId = ref(null);
const simulatedQrUrl = ref('https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=%23ETH-CHECKIN-READY');

// Check-in modal
const activeCheckInBooking = ref(null);
const checkInForm = ref({
  key_card_number: '',
  num_guests: 1,
  early_check_in_approved: false,
  early_check_in_fee_etb: 0,
  early_reason: '',
  check_in_notes: ''
});

// Reassign Room Modal
const reassignBooking = ref(null);
const reassignForm = ref({
  new_room_id: '',
  reason: ''
});
const isSubmittingReassign = ref(false);

// Walk-In Modal
const showWalkInModal = ref(false);
const isSubmittingWalkIn = ref(false);
const walkInForm = ref({
  first_name: '',
  last_name: '',
  phone: '',
  email: '',
  id_type: 'national_id',
  id_number: '',
  room_id: '',
  check_in_date: new Date().toISOString().split('T')[0],
  check_out_date: new Date(Date.now() + 86400000).toISOString().split('T')[0],
  num_guests: 1,
  key_card_number: '',
  payment_method: 'cash',
  initial_payment_etb: 0,
  check_in_notes: 'Front Desk Walk-In Guest'
});

// Operations Audit Ledger
const showAuditLedger = ref(false);
const auditLogs = ref([]);

// Digital Reg Card
const selectedRegBooking = ref(null);
const policyAccepted = ref(true);

function getAuthHeaders() {
  const token = localStorage.getItem('hotel_auth_token');
  return token ? { 'Authorization': `Bearer ${token}` } : {};
}

function formatDateTime(dtStr) {
  if (!dtStr) return '';
  const d = new Date(dtStr);
  return d.toLocaleString('en-US', {
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  });
}

async function fetchArrivalsAndDashboard() {
  loading.value = true;
  try {
    let url = '/api/v1/admin/bookings/check-in-eligible';
    if (searchQuery.value) {
      url += `?search=${encodeURIComponent(searchQuery.value)}`;
    }

    const [arrRes, dashRes] = await Promise.all([
      fetch(url, { headers: getAuthHeaders() }),
      fetch('/api/v1/desk/checkin-dashboard', { headers: getAuthHeaders() })
    ]);

    const arrData = await arrRes.json();
    eligibleArrivals.value = arrData.eligible_arrivals || [];

    if (dashRes.ok) {
      const dashData = await dashRes.json();
      dashboardMetrics.value = {
        expected_arrivals_today: dashData.metrics?.expected_arrivals_today || 0,
        pending_check_ins: dashData.metrics?.pending_check_ins || 0,
        in_house_guests: dashData.metrics?.in_house_guests || 0,
        walk_ins_today: dashData.metrics?.walk_ins_today || 0,
        rooms_ready: dashData.metrics?.rooms_ready || 0,
        rooms_turnover_prep: dashData.metrics?.rooms_turnover_prep || 0
      };
    }
  } catch (err) {
    console.error('Error fetching arrivals:', err);
  } finally {
    loading.value = false;
  }
}

async function fetchAvailableRooms() {
  try {
    const res = await fetch('/api/v1/rooms?status=available', { headers: getAuthHeaders() });
    const data = await res.json();
    availableCleanRooms.value = (data.rooms || data.data || []).filter(r => r.status === 'available' || r.cleaning_status === 'inspected' || r.cleaning_status === 'clean');
  } catch (err) {
    console.error('Error loading rooms:', err);
  }
}

function openCheckInModal(b) {
  activeCheckInBooking.value = b;
  checkInForm.value = {
    key_card_number: b.key_card_number || `KC-${b.room_number}`,
    num_guests: b.num_guests || 1,
    early_check_in_approved: false,
    early_check_in_fee_etb: 0,
    early_reason: '',
    check_in_notes: ''
  };
}

async function submitCheckIn() {
  if (!activeCheckInBooking.value) return;
  const b = activeCheckInBooking.value;
  checkingInId.value = b.id;

  try {
    const payload = {
      key_card_number: checkInForm.value.key_card_number,
      num_guests: checkInForm.value.num_guests,
      check_in_notes: checkInForm.value.check_in_notes,
      early_check_in_approved: checkInForm.value.early_check_in_approved,
      early_check_in_fee_etb: checkInForm.value.early_check_in_fee_etb
    };

    const res = await fetch(`/api/v1/bookings/${b.id}/check-in`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        ...getAuthHeaders()
      },
      body: JSON.stringify(payload)
    });

    const data = await res.json();
    if (!res.ok) throw new Error(data.error || 'Failed to complete check-in');

    alert(`✓ Check-In Confirmed! ${data.message || ''}`);
    activeCheckInBooking.value = null;
    fetchArrivalsAndDashboard();
  } catch (err) {
    alert(err.message);
  } finally {
    checkingInId.value = null;
  }
}

function openReassignModal(b) {
  reassignBooking.value = b;
  reassignForm.value = {
    new_room_id: '',
    reason: ''
  };
  fetchAvailableRooms();
}

async function submitReassignRoom() {
  if (!reassignBooking.value || !reassignForm.value.new_room_id) return;
  isSubmittingReassign.value = true;
  try {
    const res = await fetch(`/api/v1/bookings/${reassignBooking.value.id}/reassign-room`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        ...getAuthHeaders()
      },
      body: JSON.stringify({
        new_room_id: reassignForm.value.new_room_id,
        reason: reassignForm.value.reason
      })
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.error || 'Failed to reassign room');

    alert(`✓ ${data.message}`);
    reassignBooking.value = null;
    fetchArrivalsAndDashboard();
  } catch (err) {
    alert(err.message);
  } finally {
    isSubmittingReassign.value = false;
  }
}

function openWalkInModal() {
  showWalkInModal.value = true;
  fetchAvailableRooms();
}

async function submitWalkInCheckIn() {
  isSubmittingWalkIn.value = true;
  try {
    const res = await fetch('/api/v1/desk/walk-in-checkin', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        ...getAuthHeaders()
      },
      body: JSON.stringify(walkInForm.value)
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.error || 'Failed to execute walk-in check-in');

    alert(`✓ Walk-in guest ${data.booking?.guest_name} successfully checked into Room ${data.booking?.room?.room_number}! Ref: ${data.booking?.reference_code}`);
    showWalkInModal.value = false;
    fetchArrivalsAndDashboard();
  } catch (err) {
    alert(err.message);
  } finally {
    isSubmittingWalkIn.value = false;
  }
}

async function openAuditLedger() {
  showAuditLedger.value = true;
  try {
    const res = await fetch('/api/v1/desk/operations-history?limit=25', { headers: getAuthHeaders() });
    const data = await res.json();
    auditLogs.value = data.logs || [];
  } catch (err) {
    console.error('Error fetching audit ledger:', err);
  }
}

function openRegCard(booking) {
  selectedRegBooking.value = booking;
  policyAccepted.value = true;
}

async function completeRegCheckIn() {
  if (!selectedRegBooking.value) return;
  const b = selectedRegBooking.value;
  checkingInId.value = b.id;
  try {
    const res = await fetch(`/api/v1/bookings/${b.id}/check-in`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        ...getAuthHeaders()
      },
      body: JSON.stringify({
        id_type: b.guest_id_type,
        id_number: b.guest_id_number
      })
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.error || 'Failed to complete check-in');

    alert(`✓ Check-In Confirmed! ${data.message}`);
    selectedRegBooking.value = null;
    fetchArrivalsAndDashboard();
  } catch (err) {
    alert(err.message);
  } finally {
    checkingInId.value = null;
  }
}

function scanFirstArrival() {
  if (eligibleArrivals.value.length > 0) {
    const target = eligibleArrivals.value[0];
    simulatedQrUrl.value = `https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=${target.reference_code}`;
    openCheckInModal(target);
  } else {
    alert('No eligible arrivals available to scan.');
  }
}

onMounted(() => {
  fetchArrivalsAndDashboard();
  fetchAvailableRooms();
});
</script>
