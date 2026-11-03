<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-5 rounded-2xl shadow-sm border border-slate-100">
      <div>
        <div class="flex items-center gap-2">
          <h2 class="text-2xl font-bold text-[#0B4D34]">Guest Directory & Profiles</h2>
          <span class="text-xs font-bold px-2.5 py-0.5 rounded-full bg-emerald-100 text-[#0B4D34]">
            CRM & Hospitality Intelligence
          </span>
        </div>
        <p class="text-xs text-slate-500 mt-1">
          Guest recognition, identification verification, permanent preferences & historical stay records.
        </p>
      </div>

      <div class="flex items-center gap-2">
        <button 
          @click="fetchGuests(true)" 
          class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl transition flex items-center gap-1.5 cursor-pointer">
          <span>🔄</span> Refresh
        </button>
        <button 
          @click="openRegisterModal" 
          class="px-4 py-2 bg-[#0B4D34] hover:bg-[#064E3B] text-white text-xs font-bold rounded-xl shadow-xs transition flex items-center gap-1.5 cursor-pointer">
          <span>➕</span> Register New Guest
        </button>
      </div>
    </div>

    <!-- 6 KPI Stat Cards -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3.5">
      <div class="bg-white p-4 rounded-xl border border-slate-100 shadow-xs">
        <div class="flex items-center justify-between text-slate-400 text-xs mb-1">
          <span>Total Guests</span>
          <span>👥</span>
        </div>
        <div class="text-2xl font-extrabold text-slate-900">{{ stats.total_guests || 0 }}</div>
        <div class="text-[10px] text-emerald-600 font-medium mt-0.5">Registered in PMS</div>
      </div>

      <div class="bg-white p-4 rounded-xl border border-slate-100 shadow-xs">
        <div class="flex items-center justify-between text-slate-400 text-xs mb-1">
          <span>Currently In-House</span>
          <span>🛎️</span>
        </div>
        <div class="text-2xl font-extrabold text-emerald-700">{{ stats.in_house_guests || 0 }}</div>
        <div class="text-[10px] text-slate-400 mt-0.5">Active checked-in stays</div>
      </div>

      <div class="bg-white p-4 rounded-xl border border-slate-100 shadow-xs">
        <div class="flex items-center justify-between text-slate-400 text-xs mb-1">
          <span>Upcoming Arrivals</span>
          <span>🧳</span>
        </div>
        <div class="text-2xl font-extrabold text-blue-700">{{ stats.upcoming_arrivals || 0 }}</div>
        <div class="text-[10px] text-slate-400 mt-0.5">Next 7 days</div>
      </div>

      <div class="bg-white p-4 rounded-xl border border-slate-100 shadow-xs">
        <div class="flex items-center justify-between text-slate-400 text-xs mb-1">
          <span>Returning Guests</span>
          <span>⭐</span>
        </div>
        <div class="text-2xl font-extrabold text-[#D4A329]">{{ stats.returning_guests || 0 }}</div>
        <div class="text-[10px] text-amber-700 font-medium mt-0.5">Repeat visitors (2+ stays)</div>
      </div>

      <div class="bg-white p-4 rounded-xl border border-slate-100 shadow-xs">
        <div class="flex items-center justify-between text-slate-400 text-xs mb-1">
          <span>VIP & VVIP</span>
          <span>👑</span>
        </div>
        <div class="text-2xl font-extrabold text-purple-700">{{ stats.vip_guests || 0 }}</div>
        <div class="text-[10px] text-purple-600 font-medium mt-0.5">Priority recognition</div>
      </div>

      <div class="bg-white p-4 rounded-xl border border-slate-100 shadow-xs">
        <div class="flex items-center justify-between text-slate-400 text-xs mb-1">
          <span>New This Month</span>
          <span>✨</span>
        </div>
        <div class="text-2xl font-extrabold text-slate-700">{{ stats.new_guests_this_month || 0 }}</div>
        <div class="text-[10px] text-slate-400 mt-0.5">First registrations</div>
      </div>
    </div>

    <!-- Filter & Search Toolbar -->
    <div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-100 space-y-3">
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3">
        <!-- Search -->
        <div class="lg:col-span-2 relative">
          <input 
            v-model="searchQuery" 
            @input="onSearchInput"
            type="text" 
            placeholder="Search by Name, Phone, Email, ID Number, or Guest ID..." 
            class="w-full pl-9 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:bg-white focus:outline-none focus:border-[#0B4D34] transition" />
          <span class="absolute left-3 top-3 text-xs text-slate-400">🔍</span>
          <button 
            v-if="searchQuery" 
            @click="clearSearch" 
            class="absolute right-3 top-2.5 text-xs text-slate-400 hover:text-slate-600">✕</button>
        </div>

        <!-- VIP Filter -->
        <div>
          <select 
            v-model="filterVip" 
            @change="fetchGuests(true)"
            class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-700 focus:bg-white focus:outline-none focus:border-[#0B4D34]">
            <option value="all">All VIP Tiers</option>
            <option value="standard">Standard</option>
            <option value="vip">VIP</option>
            <option value="vvip">VVIP</option>
            <option value="blacklist">Blacklist</option>
          </select>
        </div>

        <!-- Returning Guest Filter -->
        <div>
          <select 
            v-model="filterReturning" 
            @change="fetchGuests(true)"
            class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-700 focus:bg-white focus:outline-none focus:border-[#0B4D34]">
            <option value="all">All Visitors</option>
            <option value="returning">Returning Guests (Repeat)</option>
            <option value="first_time">First-Time Visitors</option>
          </select>
        </div>

        <!-- ID Type Filter -->
        <div>
          <select 
            v-model="filterIdType" 
            @change="fetchGuests(true)"
            class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-700 focus:bg-white focus:outline-none focus:border-[#0B4D34]">
            <option value="all">All ID Types</option>
            <option value="passport">Passport</option>
            <option value="national_id">National ID</option>
            <option value="kebele_id">Kebele ID</option>
            <option value="driving_license">Driving License</option>
            <option value="resident_id">Resident ID</option>
            <option value="other">Other ID</option>
          </select>
        </div>

        <!-- Sort By -->
        <div>
          <select 
            v-model="sortBy" 
            @change="fetchGuests(true)"
            class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-700 focus:bg-white focus:outline-none focus:border-[#0B4D34]">
            <option value="created_at">Sort: Newest Registered</option>
            <option value="name">Sort: Guest Name (A-Z)</option>
            <option value="stays_count">Sort: Most Completed Stays</option>
            <option value="bookings_count">Sort: Total Reservations</option>
          </select>
        </div>
      </div>
    </div>

    <!-- Guest Table Listing -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
      <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
        <div class="flex items-center gap-2">
          <h3 class="text-sm font-bold text-slate-900">Registered Guests List</h3>
          <span class="text-xs text-slate-400">({{ pagination.total || 0 }} total registered)</span>
        </div>
        <div class="text-xs text-slate-500">
          Page {{ pagination.current_page }} of {{ pagination.last_page || 1 }}
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-xs">
          <thead>
            <tr class="bg-slate-50 text-slate-600 font-semibold uppercase tracking-wider border-b border-slate-100">
              <th class="py-3.5 px-5">Guest Profile</th>
              <th class="py-3.5 px-5">Contact Details</th>
              <th class="py-3.5 px-5">Identification</th>
              <th class="py-3.5 px-5">Stay History</th>
              <th class="py-3.5 px-5">Preferences</th>
              <th class="py-3.5 px-5">Status</th>
              <th class="py-3.5 px-5 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 text-slate-700">
            <tr v-if="loading" class="text-center">
              <td colspan="7" class="py-12 text-slate-400">
                <span class="inline-block animate-spin mr-2">⏳</span> Loading guest records from MySQL...
              </td>
            </tr>
            <tr v-else-if="guests.length === 0" class="text-center">
              <td colspan="7" class="py-12 text-slate-400">
                No guests found matching the selected search criteria.
              </td>
            </tr>
            <tr 
              v-else 
              v-for="g in guests" 
              :key="g.id"
              class="hover:bg-slate-50/60 transition group">
              <!-- Guest Profile & Name -->
              <td class="py-3.5 px-5">
                <div class="flex items-center gap-3">
                  <div 
                    :class="avatarBg(g.vip_status)"
                    class="w-9 h-9 rounded-full flex items-center justify-center font-bold text-xs text-white shadow-xs">
                    {{ initials(g.full_name) }}
                  </div>
                  <div>
                    <div class="flex items-center gap-1.5">
                      <span class="font-bold text-slate-900 text-xs">{{ g.full_name }}</span>
                      <span 
                        v-if="g.vip_status && g.vip_status !== 'standard'" 
                        :class="vipBadgeClass(g.vip_status)"
                        class="px-1.5 py-0.2 text-[9px] font-extrabold uppercase rounded-full">
                        {{ g.vip_status }}
                      </span>
                    </div>
                    <div class="flex items-center gap-2 mt-0.5">
                      <span 
                        v-if="g.is_returning" 
                        class="text-[10px] font-semibold text-[#D4A329] flex items-center gap-0.5">
                        ⭐ Returning Guest
                      </span>
                      <span v-else class="text-[10px] text-slate-400">
                        First-Time
                      </span>
                      <span class="text-[10px] text-slate-300">•</span>
                      <span class="text-[10px] text-slate-400 font-mono">{{ g.id }}</span>
                    </div>
                  </div>
                </div>
              </td>

              <!-- Contact -->
              <td class="py-3.5 px-5">
                <div class="font-medium text-slate-800">{{ g.phone }}</div>
                <div class="text-[11px] text-slate-400">{{ g.email }}</div>
                <div class="text-[10px] text-slate-500 mt-0.5">{{ g.city || 'Addis Ababa' }}, {{ g.nationality || 'Ethiopian' }}</div>
              </td>

              <!-- Identification -->
              <td class="py-3.5 px-5">
                <div v-if="g.id_number" class="space-y-0.5">
                  <span class="inline-block text-[10px] uppercase font-bold text-slate-600 bg-slate-100 px-1.5 py-0.5 rounded">
                    {{ formatIdType(g.id_type) }}
                  </span>
                  <div class="font-mono text-[11px] font-semibold text-slate-800 tracking-wider">
                    {{ g.id_number }}
                  </div>
                  <div v-if="g.id_expiry_date" class="text-[10px] text-slate-400">
                    Exp: {{ g.id_expiry_date }}
                  </div>
                </div>
                <div v-else class="text-slate-400 italic text-[11px]">
                  ID not yet recorded
                </div>
              </td>

              <!-- Stay History -->
              <td class="py-3.5 px-5">
                <div class="flex items-center gap-1.5">
                  <span class="font-bold text-slate-900">{{ g.total_bookings_count || 0 }}</span>
                  <span class="text-[11px] text-slate-500">stays booked</span>
                </div>
                <div class="text-[10px] text-emerald-600 font-medium">
                  {{ g.completed_stays_count || 0 }} completed
                </div>
                <span 
                  v-if="g.active_stays_count > 0"
                  class="inline-block mt-1 px-2 py-0.5 bg-emerald-100 text-emerald-800 font-bold rounded-full text-[9px] animate-pulse">
                  🛎️ In-House Guest
                </span>
              </td>

              <!-- Preferences -->
              <td class="py-3.5 px-5">
                <div class="flex flex-wrap gap-1 max-w-xs">
                  <span 
                    v-if="g.preferences?.smoking" 
                    class="px-1.5 py-0.5 bg-red-50 text-red-700 text-[10px] rounded font-medium">
                    🚬 Smoking
                  </span>
                  <span 
                    v-else 
                    class="px-1.5 py-0.5 bg-emerald-50 text-emerald-700 text-[10px] rounded font-medium">
                    🚭 Non-Smoking
                  </span>
                  <span 
                    v-if="g.preferences?.bed_type" 
                    class="px-1.5 py-0.5 bg-slate-100 text-slate-700 text-[10px] rounded capitalize">
                    🛏️ {{ g.preferences.bed_type }}
                  </span>
                  <span 
                    v-if="g.preferences?.floor_preference && g.preferences.floor_preference !== 'any'" 
                    class="px-1.5 py-0.5 bg-slate-100 text-slate-700 text-[10px] rounded capitalize">
                    🏢 {{ g.preferences.floor_preference }} floor
                  </span>
                  <span 
                    v-if="g.preferences?.accessibility_needs" 
                    class="px-1.5 py-0.5 bg-blue-50 text-blue-700 text-[10px] rounded">
                    ♿ Accessible
                  </span>
                </div>
              </td>

              <!-- Status -->
              <td class="py-3.5 px-5">
                <span 
                  :class="g.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'"
                  class="px-2 py-0.5 rounded-full font-bold text-[10px]">
                  {{ g.is_active ? 'Active' : 'Inactive' }}
                </span>
              </td>

              <!-- Actions -->
              <td class="py-3.5 px-5 text-right whitespace-nowrap">
                <div class="flex items-center justify-end gap-1.5">
                  <button 
                    @click="viewDossier(g)"
                    title="View Comprehensive Stay Dossier"
                    class="p-1.5 text-slate-600 hover:text-[#0B4D34] hover:bg-emerald-50 rounded-lg transition cursor-pointer">
                    👁️
                  </button>
                  <button 
                    @click="openEditModal(g)"
                    title="Edit Guest Details"
                    class="p-1.5 text-slate-600 hover:text-amber-600 hover:bg-amber-50 rounded-lg transition cursor-pointer">
                    ✏️
                  </button>
                  <button 
                    @click="createBookingForGuest(g)"
                    title="Create Reservation for this Guest"
                    class="px-2 py-1 bg-emerald-50 text-[#0B4D34] hover:bg-[#0B4D34] hover:text-white rounded-lg font-bold text-[10px] transition cursor-pointer">
                    + Book
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="px-6 py-4 border-t border-slate-100 flex items-center justify-between text-xs text-slate-600">
        <div>
          Showing page {{ pagination.current_page }} of {{ pagination.last_page || 1 }}
        </div>
        <div class="flex items-center gap-1.5">
          <button 
            @click="changePage(pagination.current_page - 1)"
            :disabled="pagination.current_page <= 1"
            class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 disabled:opacity-40 rounded-lg transition font-medium cursor-pointer">
            Previous
          </button>
          <button 
            @click="changePage(pagination.current_page + 1)"
            :disabled="pagination.current_page >= pagination.last_page"
            class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 disabled:opacity-40 rounded-lg transition font-medium cursor-pointer">
            Next
          </button>
        </div>
      </div>
    </div>

    <!-- MODAL 1: REGISTER NEW GUEST -->
    <div 
      v-if="showRegisterModal" 
      class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4 overflow-y-auto">
      <div class="bg-white w-full max-w-2xl rounded-2xl shadow-2xl border border-slate-100 overflow-hidden my-8">
        <div class="px-6 py-4 bg-[#0B4D34] text-white flex items-center justify-between">
          <div class="flex items-center gap-2">
            <span class="text-xl">👥</span>
            <div>
              <h3 class="font-bold text-base">Register New Guest Profile</h3>
              <p class="text-[11px] text-emerald-200">Official PMS registration, identity verification & preferences</p>
            </div>
          </div>
          <button @click="showRegisterModal = false" class="text-emerald-200 hover:text-white text-lg">✕</button>
        </div>

        <!-- Form Body -->
        <form @submit.prevent="submitRegister" class="p-6 space-y-4 max-h-[80vh] overflow-y-auto text-xs">
          <!-- Duplicate Warning Banner -->
          <div v-if="duplicateWarning" class="p-3 bg-amber-50 border border-amber-300 rounded-xl text-amber-900 flex items-start gap-2">
            <span class="text-base">⚠️</span>
            <div class="flex-1">
              <p class="font-bold">Returning Guest Match Detected!</p>
              <p class="text-[11px]">{{ duplicateWarning.message }}</p>
              <button 
                type="button" 
                @click="selectExistingGuest(duplicateWarning.guest)" 
                class="mt-1.5 px-3 py-1 bg-amber-600 hover:bg-amber-700 text-white font-bold rounded-lg text-[10px]">
                Use Existing Guest Profile (ID: {{ duplicateWarning.guest.id }})
              </button>
            </div>
          </div>

          <!-- Personal Information -->
          <div>
            <h4 class="text-xs font-bold uppercase text-[#0B4D34] border-b border-emerald-100 pb-1 mb-2.5">
              1. Personal & Contact Information
            </h4>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <label class="block font-semibold text-slate-700 mb-1">First Name *</label>
                <input 
                  v-model="regForm.first_name" 
                  @blur="checkDuplicates"
                  required 
                  type="text" 
                  placeholder="e.g. Almaz" 
                  class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:bg-white focus:border-[#0B4D34] outline-none" />
              </div>
              <div>
                <label class="block font-semibold text-slate-700 mb-1">Last Name *</label>
                <input 
                  v-model="regForm.last_name" 
                  @blur="checkDuplicates"
                  required 
                  type="text" 
                  placeholder="e.g. Ayana" 
                  class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:bg-white focus:border-[#0B4D34] outline-none" />
              </div>
              <div>
                <label class="block font-semibold text-slate-700 mb-1">Phone Number *</label>
                <input 
                  v-model="regForm.phone" 
                  @blur="checkDuplicates"
                  required 
                  type="text" 
                  placeholder="+251 911 223344" 
                  class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:bg-white focus:border-[#0B4D34] outline-none" />
              </div>
              <div>
                <label class="block font-semibold text-slate-700 mb-1">Email Address *</label>
                <input 
                  v-model="regForm.email" 
                  @blur="checkDuplicates"
                  required 
                  type="email" 
                  placeholder="guest@example.et" 
                  class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:bg-white focus:border-[#0B4D34] outline-none" />
              </div>
              <div>
                <label class="block font-semibold text-slate-700 mb-1">Gender</label>
                <select 
                  v-model="regForm.gender" 
                  class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:bg-white focus:border-[#0B4D34] outline-none">
                  <option value="unspecified">Unspecified</option>
                  <option value="female">Female</option>
                  <option value="male">Male</option>
                  <option value="other">Other</option>
                </select>
              </div>
              <div>
                <label class="block font-semibold text-slate-700 mb-1">Date of Birth</label>
                <input 
                  v-model="regForm.date_of_birth" 
                  type="date" 
                  class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:bg-white focus:border-[#0B4D34] outline-none" />
              </div>
              <div>
                <label class="block font-semibold text-slate-700 mb-1">Nationality</label>
                <input 
                  v-model="regForm.nationality" 
                  type="text" 
                  placeholder="e.g. Ethiopian" 
                  class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:bg-white focus:border-[#0B4D34] outline-none" />
              </div>
              <div>
                <label class="block font-semibold text-slate-700 mb-1">VIP Recognition Tier</label>
                <select 
                  v-model="regForm.vip_status" 
                  class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:bg-white focus:border-[#0B4D34] outline-none">
                  <option value="standard">Standard Guest</option>
                  <option value="vip">VIP Guest</option>
                  <option value="vvip">VVIP / State Dignitary</option>
                  <option value="blacklist">Flagged / Blacklist</option>
                </select>
              </div>
            </div>
          </div>

          <!-- Identification Information -->
          <div>
            <h4 class="text-xs font-bold uppercase text-[#0B4D34] border-b border-emerald-100 pb-1 mb-2.5">
              2. Official Identification Details
            </h4>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <label class="block font-semibold text-slate-700 mb-1">Identification Type</label>
                <select 
                  v-model="regForm.id_type" 
                  class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:bg-white focus:border-[#0B4D34] outline-none">
                  <option value="">Select ID Document</option>
                  <option value="national_id">National ID Card</option>
                  <option value="passport">Passport</option>
                  <option value="kebele_id">Kebele / City Resident ID</option>
                  <option value="driving_license">Driver's License</option>
                  <option value="resident_id">Foreign Resident ID</option>
                  <option value="other">Other Official Document</option>
                </select>
              </div>
              <div>
                <label class="block font-semibold text-slate-700 mb-1">ID Number</label>
                <input 
                  v-model="regForm.id_number" 
                  @blur="checkDuplicates"
                  type="text" 
                  placeholder="e.g. ET-PASS-492817" 
                  class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:bg-white focus:border-[#0B4D34] outline-none font-mono" />
              </div>
              <div>
                <label class="block font-semibold text-slate-700 mb-1">Issuing Country</label>
                <input 
                  v-model="regForm.id_issue_country" 
                  type="text" 
                  placeholder="e.g. Ethiopia" 
                  class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:bg-white focus:border-[#0B4D34] outline-none" />
              </div>
              <div>
                <label class="block font-semibold text-slate-700 mb-1">ID Expiration Date</label>
                <input 
                  v-model="regForm.id_expiry_date" 
                  type="date" 
                  class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:bg-white focus:border-[#0B4D34] outline-none" />
              </div>
            </div>
          </div>

          <!-- Address Information -->
          <div>
            <h4 class="text-xs font-bold uppercase text-[#0B4D34] border-b border-emerald-100 pb-1 mb-2.5">
              3. Residential Address & Emergency Contact
            </h4>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
              <div class="sm:col-span-2">
                <label class="block font-semibold text-slate-700 mb-1">Street Address</label>
                <input 
                  v-model="regForm.address" 
                  type="text" 
                  placeholder="Bole Sub-City, Woreda 03" 
                  class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:bg-white focus:border-[#0B4D34] outline-none" />
              </div>
              <div>
                <label class="block font-semibold text-slate-700 mb-1">City</label>
                <input 
                  v-model="regForm.city" 
                  type="text" 
                  placeholder="Addis Ababa" 
                  class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:bg-white focus:border-[#0B4D34] outline-none" />
              </div>
              <div>
                <label class="block font-semibold text-slate-700 mb-1">Emergency Contact Name</label>
                <input 
                  v-model="regForm.emergency_contact_name" 
                  type="text" 
                  placeholder="Next of kin" 
                  class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:bg-white focus:border-[#0B4D34] outline-none" />
              </div>
              <div class="sm:col-span-2">
                <label class="block font-semibold text-slate-700 mb-1">Emergency Contact Phone</label>
                <input 
                  v-model="regForm.emergency_contact_phone" 
                  type="text" 
                  placeholder="+251 911 000000" 
                  class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:bg-white focus:border-[#0B4D34] outline-none" />
              </div>
            </div>
          </div>

          <!-- Permanent Hospitality Preferences -->
          <div>
            <h4 class="text-xs font-bold uppercase text-[#0B4D34] border-b border-emerald-100 pb-1 mb-2.5">
              4. Permanent Guest Preferences (Stored for All Stays)
            </h4>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
              <div>
                <label class="block font-semibold text-slate-700 mb-1">Smoking Preference</label>
                <select 
                  v-model="regForm.preferences.smoking" 
                  class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:bg-white focus:border-[#0B4D34] outline-none">
                  <option :value="false">Strictly Non-Smoking</option>
                  <option :value="true">Smoking Permitted Suite</option>
                </select>
              </div>
              <div>
                <label class="block font-semibold text-slate-700 mb-1">Bed Configuration</label>
                <select 
                  v-model="regForm.preferences.bed_type" 
                  class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:bg-white focus:border-[#0B4D34] outline-none">
                  <option value="king">King Bed</option>
                  <option value="twin">Twin Beds</option>
                  <option value="queen">Queen Bed</option>
                </select>
              </div>
              <div>
                <label class="block font-semibold text-slate-700 mb-1">Floor Level</label>
                <select 
                  v-model="regForm.preferences.floor_preference" 
                  class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:bg-white focus:border-[#0B4D34] outline-none">
                  <option value="any">No Preference</option>
                  <option value="high">High Floor (Panoramic)</option>
                  <option value="low">Low Floor / Easy Access</option>
                </select>
              </div>
              <div>
                <label class="block font-semibold text-slate-700 mb-1">Dietary Requirements</label>
                <input 
                  v-model="regForm.preferences.dietary" 
                  type="text" 
                  placeholder="e.g. Ethiopian Fasting (Vegetarian), Halal" 
                  class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:bg-white focus:border-[#0B4D34] outline-none" />
              </div>
              <div class="sm:col-span-2 flex items-center pt-5">
                <label class="flex items-center gap-2 cursor-pointer font-semibold text-slate-700">
                  <input type="checkbox" v-model="regForm.preferences.accessibility_needs" class="rounded accent-[#0B4D34]" />
                  <span>Requires Accessibility Accessible Room / Elevator Proximity</span>
                </label>
              </div>
            </div>
          </div>

          <!-- Staff Notes -->
          <div>
            <label class="block font-semibold text-slate-700 mb-1">Concierge & Front Desk Internal Notes</label>
            <textarea 
              v-model="regForm.notes" 
              rows="2" 
              placeholder="Private internal operational notes, guest habits, coffee ceremony preferences..." 
              class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:bg-white focus:border-[#0B4D34] outline-none"></textarea>
          </div>

          <!-- Modal Actions -->
          <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-100">
            <button 
              type="button" 
              @click="showRegisterModal = false" 
              class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded-xl">
              Cancel
            </button>
            <button 
              type="submit" 
              :disabled="submittingReg"
              class="px-6 py-2.5 bg-[#0B4D34] hover:bg-[#064E3B] text-white font-bold rounded-xl shadow-xs disabled:opacity-50 flex items-center gap-2">
              <span v-if="submittingReg" class="animate-spin">⏳</span>
              <span>Complete Guest Registration</span>
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- MODAL 2: GUEST PROFILE & STAY DOSSIER -->
    <div 
      v-if="showDossierModal && selectedGuestDossier" 
      class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4 overflow-y-auto">
      <div class="bg-white w-full max-w-4xl rounded-2xl shadow-2xl border border-slate-100 overflow-hidden my-6">
        <!-- Dossier Header -->
        <div class="px-6 py-5 bg-[#0B4D34] text-white flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div class="flex items-center gap-3.5">
            <div 
              :class="avatarBg(selectedGuestDossier.guest.vip_status)"
              class="w-14 h-14 rounded-2xl flex items-center justify-center font-extrabold text-lg text-white shadow-md border-2 border-white/20">
              {{ initials(selectedGuestDossier.guest.full_name) }}
            </div>
            <div>
              <div class="flex items-center gap-2">
                <h3 class="text-xl font-bold">{{ selectedGuestDossier.guest.full_name }}</h3>
                <span 
                  :class="vipBadgeClass(selectedGuestDossier.guest.vip_status)"
                  class="px-2 py-0.5 text-[10px] font-extrabold uppercase rounded-full">
                  {{ selectedGuestDossier.guest.vip_status }}
                </span>
                <span 
                  v-if="selectedGuestDossier.summary.is_returning" 
                  class="px-2 py-0.5 bg-[#D4A329] text-slate-900 text-[10px] font-extrabold rounded-full">
                  ⭐ Returning Visitor
                </span>
              </div>
              <p class="text-xs text-emerald-200 mt-0.5 font-mono">
                Guest ID: {{ selectedGuestDossier.guest.id }} • Registered: {{ selectedGuestDossier.guest.created_at_human }}
              </p>
            </div>
          </div>

          <div class="flex items-center gap-2">
            <button 
              @click="openEditModal(selectedGuestDossier.guest)"
              class="px-3.5 py-2 bg-emerald-700/60 hover:bg-emerald-700 text-white rounded-xl text-xs font-semibold transition cursor-pointer">
              ✏️ Edit Profile
            </button>
            <button 
              @click="createBookingForGuest(selectedGuestDossier.guest)"
              class="px-4 py-2 bg-[#D4A329] hover:bg-amber-400 text-slate-900 rounded-xl text-xs font-bold transition shadow-xs cursor-pointer">
              + New Reservation
            </button>
            <button @click="showDossierModal = false" class="text-emerald-200 hover:text-white text-lg ml-2">✕</button>
          </div>
        </div>

        <!-- 4 Summary Stat Pills -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 p-4 bg-slate-50 border-b border-slate-100 text-xs">
          <div class="bg-white p-3 rounded-xl border border-slate-100 shadow-2xs">
            <span class="text-slate-400 text-[11px] block">Total Reservations</span>
            <span class="text-lg font-bold text-slate-900">{{ selectedGuestDossier.summary.total_reservations }}</span>
          </div>
          <div class="bg-white p-3 rounded-xl border border-slate-100 shadow-2xs">
            <span class="text-slate-400 text-[11px] block">Completed Stays</span>
            <span class="text-lg font-bold text-emerald-700">{{ selectedGuestDossier.summary.completed_stays }}</span>
          </div>
          <div class="bg-white p-3 rounded-xl border border-slate-100 shadow-2xs">
            <span class="text-slate-400 text-[11px] block">Current Stays</span>
            <span class="text-lg font-bold text-blue-700">{{ selectedGuestDossier.summary.current_stays }}</span>
          </div>
          <div class="bg-white p-3 rounded-xl border border-slate-100 shadow-2xs">
            <span class="text-slate-400 text-[11px] block">Lifetime Expenditure</span>
            <span class="text-lg font-bold text-[#D4A329]">{{ formatMoney(selectedGuestDossier.summary.total_spent_etb) }} ETB</span>
          </div>
        </div>

        <!-- Dossier Navigation Tabs -->
        <div class="border-b border-slate-200 px-6 flex gap-4 text-xs font-bold">
          <button 
            v-for="t in dossierTabs" 
            :key="t.id"
            @click="activeDossierTab = t.id"
            :class="activeDossierTab === t.id ? 'border-[#0B4D34] text-[#0B4D34]' : 'border-transparent text-slate-500 hover:text-slate-800'"
            class="py-3 border-b-2 transition cursor-pointer">
            {{ t.label }}
          </button>
        </div>

        <!-- Tab Content -->
        <div class="p-6 max-h-[60vh] overflow-y-auto text-xs space-y-4">
          <!-- TAB 1: OVERVIEW & IDENTIFICATION -->
          <div v-if="activeDossierTab === 'overview'" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <!-- Contact Card -->
              <div class="bg-slate-50 p-4 rounded-xl border border-slate-100 space-y-2">
                <h4 class="font-bold text-slate-900 text-xs flex items-center gap-1.5 text-[#0B4D34]">
                  <span>📞</span> Contact Information
                </h4>
                <div class="space-y-1 text-slate-600">
                  <p><span class="font-semibold text-slate-800">Phone:</span> {{ selectedGuestDossier.guest.phone }}</p>
                  <p><span class="font-semibold text-slate-800">Email:</span> {{ selectedGuestDossier.guest.email }}</p>
                  <p><span class="font-semibold text-slate-800">Address:</span> {{ selectedGuestDossier.guest.address || 'N/A' }}</p>
                  <p><span class="font-semibold text-slate-800">City / Country:</span> {{ selectedGuestDossier.guest.city }}, {{ selectedGuestDossier.guest.country }}</p>
                  <p><span class="font-semibold text-slate-800">Nationality:</span> {{ selectedGuestDossier.guest.nationality }}</p>
                </div>
              </div>

              <!-- Identification Card -->
              <div class="bg-slate-50 p-4 rounded-xl border border-slate-100 space-y-2">
                <h4 class="font-bold text-slate-900 text-xs flex items-center gap-1.5 text-[#0B4D34]">
                  <span>🪪</span> Official Identification Dossier
                </h4>
                <div class="space-y-1 text-slate-600">
                  <p><span class="font-semibold text-slate-800">ID Type:</span> {{ formatIdType(selectedGuestDossier.guest.id_type) }}</p>
                  <p><span class="font-semibold text-slate-800">ID Number:</span> <span class="font-mono font-bold text-slate-900">{{ selectedGuestDossier.guest.id_number || 'Not Recorded' }}</span></p>
                  <p><span class="font-semibold text-slate-800">Issuing Country:</span> {{ selectedGuestDossier.guest.id_issue_country }}</p>
                  <p><span class="font-semibold text-slate-800">Expiration Date:</span> {{ selectedGuestDossier.guest.id_expiry_date || 'N/A' }}</p>
                  <p><span class="font-semibold text-slate-800">Emergency Contact:</span> {{ selectedGuestDossier.guest.emergency_contact_name || 'N/A' }} ({{ selectedGuestDossier.guest.emergency_contact_phone || 'N/A' }})</p>
                </div>
              </div>
            </div>

            <!-- Notes -->
            <div class="bg-amber-50/60 p-4 rounded-xl border border-amber-200/60">
              <h4 class="font-bold text-amber-900 text-xs mb-1">📝 Internal Staff Notes & Guest Habits:</h4>
              <p class="text-amber-800 text-xs italic">{{ selectedGuestDossier.guest.notes || 'No special internal staff notes on file.' }}</p>
            </div>
          </div>

          <!-- TAB 2: PERMANENT PREFERENCES -->
          <div v-if="activeDossierTab === 'preferences'" class="space-y-4">
            <div class="bg-slate-50 p-4 rounded-xl border border-slate-100 space-y-3">
              <h4 class="font-bold text-slate-900 text-xs text-[#0B4D34]">Hospitality Standing Preferences</h4>
              <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                <div class="bg-white p-3 rounded-lg border border-slate-200">
                  <span class="text-slate-400 text-[10px] block">Smoking Status</span>
                  <span class="font-bold text-slate-800">
                    {{ selectedGuestDossier.guest.preferences?.smoking ? '🚬 Smoking Permitted' : '🚭 100% Non-Smoking' }}
                  </span>
                </div>
                <div class="bg-white p-3 rounded-lg border border-slate-200">
                  <span class="text-slate-400 text-[10px] block">Preferred Bed Type</span>
                  <span class="font-bold text-slate-800 capitalize">
                    🛏️ {{ selectedGuestDossier.guest.preferences?.bed_type || 'King Bed' }}
                  </span>
                </div>
                <div class="bg-white p-3 rounded-lg border border-slate-200">
                  <span class="text-slate-400 text-[10px] block">Floor Level Preference</span>
                  <span class="font-bold text-slate-800 capitalize">
                    🏢 {{ selectedGuestDossier.guest.preferences?.floor_preference || 'Any Floor' }}
                  </span>
                </div>
                <div class="bg-white p-3 rounded-lg border border-slate-200 sm:col-span-2">
                  <span class="text-slate-400 text-[10px] block">Dietary Restrictions & Meal Style</span>
                  <span class="font-bold text-slate-800">
                    🍽️ {{ selectedGuestDossier.guest.preferences?.dietary || 'None Stated' }}
                  </span>
                </div>
                <div class="bg-white p-3 rounded-lg border border-slate-200">
                  <span class="text-slate-400 text-[10px] block">Accessibility Accommodations</span>
                  <span class="font-bold text-slate-800">
                    {{ selectedGuestDossier.guest.preferences?.accessibility_needs ? '♿ Accessible Room Required' : 'Standard Room' }}
                  </span>
                </div>
              </div>
            </div>
          </div>

          <!-- TAB 3: RESERVATIONS & STAY HISTORY -->
          <div v-if="activeDossierTab === 'history'" class="space-y-3">
            <div class="flex items-center justify-between">
              <h4 class="font-bold text-slate-900 text-xs">Complete Stays Itinerary</h4>
              <span class="text-slate-400 text-[11px]">{{ selectedGuestDossier.booking_history.all.length }} stays on record</span>
            </div>

            <div v-if="selectedGuestDossier.booking_history.all.length === 0" class="text-slate-400 py-6 text-center">
              No historical reservations found for this guest.
            </div>

            <div v-else class="space-y-2">
              <div 
                v-for="b in selectedGuestDossier.booking_history.all" 
                :key="b.id"
                class="p-3.5 bg-slate-50 hover:bg-slate-100/80 rounded-xl border border-slate-200/80 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 transition">
                <div>
                  <div class="flex items-center gap-2">
                    <span class="font-mono font-bold text-slate-900 text-xs">{{ b.reference_code }}</span>
                    <span 
                      :class="statusBadge(b.booking_status)" 
                      class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase">
                      {{ b.booking_status }}
                    </span>
                    <span v-if="b.is_walk_in" class="text-[10px] bg-purple-100 text-purple-800 px-1.5 py-0.2 rounded font-semibold">
                      Walk-In
                    </span>
                  </div>
                  <div class="text-[11px] text-slate-600 mt-1">
                    Room {{ b.primary_room?.room_number || 'N/A' }} ({{ b.primary_room?.category || 'Deluxe' }}) • 
                    {{ b.check_in_date }} to {{ b.check_out_date }}
                  </div>
                  <div v-if="b.special_requests && b.special_requests !== 'None'" class="text-[10px] text-amber-700 italic mt-0.5">
                    Requests: "{{ b.special_requests }}"
                  </div>
                  <div v-if="b.cancellation_reason" class="text-[10px] text-rose-700 italic mt-0.5">
                    Cancellation reason: "{{ b.cancellation_reason }}"
                  </div>
                </div>

                <div class="text-right">
                  <div class="font-bold text-slate-900 text-xs">{{ formatMoney(b.total_price_etb) }} ETB</div>
                  <div class="text-[10px] text-slate-400 capitalize">Payment: {{ b.payment_status }}</div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Dossier Footer -->
        <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex items-center justify-between text-xs">
          <span class="text-slate-400">Status: {{ selectedGuestDossier.guest.is_active ? 'Active Guest Account' : 'Deactivated Guest Account' }}</span>
          <button 
            @click="showDossierModal = false" 
            class="px-5 py-2 bg-[#0B4D34] hover:bg-[#064E3B] text-white font-bold rounded-xl transition">
            Close Dossier
          </button>
        </div>
      </div>
    </div>

    <!-- MODAL 3: EDIT GUEST PROFILE -->
    <div 
      v-if="showEditModal" 
      class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4 overflow-y-auto">
      <div class="bg-white w-full max-w-2xl rounded-2xl shadow-2xl border border-slate-100 overflow-hidden my-8">
        <div class="px-6 py-4 bg-[#0B4D34] text-white flex items-center justify-between">
          <h3 class="font-bold text-base">Edit Guest Profile: {{ editForm.full_name }}</h3>
          <button @click="showEditModal = false" class="text-emerald-200 hover:text-white text-lg">✕</button>
        </div>

        <form @submit.prevent="submitEdit" class="p-6 space-y-4 max-h-[80vh] overflow-y-auto text-xs">
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="block font-semibold text-slate-700 mb-1">First Name *</label>
              <input v-model="editForm.first_name" required type="text" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg outline-none" />
            </div>
            <div>
              <label class="block font-semibold text-slate-700 mb-1">Last Name *</label>
              <input v-model="editForm.last_name" required type="text" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg outline-none" />
            </div>
            <div>
              <label class="block font-semibold text-slate-700 mb-1">Phone Number *</label>
              <input v-model="editForm.phone" required type="text" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg outline-none" />
            </div>
            <div>
              <label class="block font-semibold text-slate-700 mb-1">Email Address *</label>
              <input v-model="editForm.email" required type="email" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg outline-none" />
            </div>
            <div>
              <label class="block font-semibold text-slate-700 mb-1">VIP Tier</label>
              <select v-model="editForm.vip_status" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg outline-none">
                <option value="standard">Standard</option>
                <option value="vip">VIP</option>
                <option value="vvip">VVIP</option>
                <option value="blacklist">Blacklist</option>
              </select>
            </div>
            <div>
              <label class="block font-semibold text-slate-700 mb-1">Nationality</label>
              <input v-model="editForm.nationality" type="text" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg outline-none" />
            </div>
            <div>
              <label class="block font-semibold text-slate-700 mb-1">ID Document Type</label>
              <select v-model="editForm.id_type" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg outline-none">
                <option value="">None</option>
                <option value="national_id">National ID</option>
                <option value="passport">Passport</option>
                <option value="kebele_id">Kebele ID</option>
                <option value="driving_license">Driver's License</option>
                <option value="resident_id">Resident ID</option>
                <option value="other">Other</option>
              </select>
            </div>
            <div>
              <label class="block font-semibold text-slate-700 mb-1">ID Number</label>
              <input v-model="editForm.id_number" type="text" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg font-mono outline-none" />
            </div>
            <div class="sm:col-span-2">
              <label class="block font-semibold text-slate-700 mb-1">Street Address</label>
              <input v-model="editForm.address" type="text" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg outline-none" />
            </div>
            <div class="sm:col-span-2">
              <label class="block font-semibold text-slate-700 mb-1">Concierge & Front Desk Internal Notes</label>
              <textarea v-model="editForm.notes" rows="2" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg outline-none"></textarea>
            </div>
            <div class="sm:col-span-2">
              <label class="flex items-center gap-2 cursor-pointer font-bold text-slate-800">
                <input type="checkbox" v-model="editForm.is_active" class="rounded accent-[#0B4D34]" />
                <span>Guest Account is Active in PMS</span>
              </label>
            </div>
          </div>

          <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-100">
            <button type="button" @click="showEditModal = false" class="px-4 py-2 bg-slate-100 text-slate-700 font-semibold rounded-xl">
              Cancel
            </button>
            <button type="submit" class="px-6 py-2 bg-[#0B4D34] text-white font-bold rounded-xl shadow-xs">
              Save Profile Updates
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useRouter } from 'vue-router';

const router = useRouter();

// State
const guests = ref([]);
const loading = ref(false);
const stats = ref({});
const searchQuery = ref('');
const filterVip = ref('all');
const filterReturning = ref('all');
const filterIdType = ref('all');
const sortBy = ref('created_at');

const pagination = reactive({
  current_page: 1,
  last_page: 1,
  per_page: 15,
  total: 0
});

// Modal Controls
const showRegisterModal = ref(false);
const showDossierModal = ref(false);
const showEditModal = ref(false);
const selectedGuestDossier = ref(null);
const submittingReg = ref(false);
const duplicateWarning = ref(null);
const activeDossierTab = ref('overview');

const dossierTabs = [
  { id: 'overview', label: 'Overview & Identification' },
  { id: 'preferences', label: 'Standing Preferences' },
  { id: 'history', label: 'Stay History Itinerary' },
];

const regForm = reactive({
  first_name: '',
  last_name: '',
  email: '',
  phone: '',
  gender: 'unspecified',
  date_of_birth: '',
  nationality: 'Ethiopian',
  address: '',
  city: 'Addis Ababa',
  country: 'Ethiopia',
  id_type: '',
  id_number: '',
  id_issue_country: 'Ethiopia',
  id_expiry_date: '',
  vip_status: 'standard',
  emergency_contact_name: '',
  emergency_contact_phone: '',
  notes: '',
  preferences: {
    smoking: false,
    bed_type: 'king',
    floor_preference: 'any',
    dietary: '',
    accessibility_needs: false,
  }
});

const editForm = reactive({
  id: '',
  first_name: '',
  last_name: '',
  full_name: '',
  email: '',
  phone: '',
  nationality: 'Ethiopian',
  vip_status: 'standard',
  id_type: '',
  id_number: '',
  address: '',
  notes: '',
  is_active: true,
});

onMounted(() => {
  fetchDashboardStats();
  fetchGuests();
});

async function fetchDashboardStats() {
  try {
    const res = await fetch('/api/v1/guests/dashboard-stats');
    if (res.ok) {
      const data = await res.json();
      stats.value = data.stats || {};
    }
  } catch (err) {
    console.error('Failed to fetch guest dashboard stats:', err);
  }
}

async function fetchGuests(resetPage = false) {
  if (resetPage) pagination.current_page = 1;
  loading.value = true;

  try {
    const params = new URLSearchParams({
      page: pagination.current_page,
      per_page: pagination.per_page,
      search: searchQuery.value,
      vip_status: filterVip.value,
      returning: filterReturning.value,
      id_type: filterIdType.value,
      sort_by: sortBy.value,
    });

    const res = await fetch(`/api/v1/guests?${params.toString()}`);
    if (res.ok) {
      const data = await res.json();
      guests.value = data.guests || [];
      if (data.pagination) {
        pagination.current_page = data.pagination.current_page;
        pagination.last_page = data.pagination.last_page;
        pagination.total = data.pagination.total;
      }
    }
  } catch (err) {
    console.error('Failed to fetch guests:', err);
  } finally {
    loading.value = false;
  }
}

let searchDebounce = null;
function onSearchInput() {
  clearTimeout(searchDebounce);
  searchDebounce = setTimeout(() => {
    fetchGuests(true);
  }, 300);
}

function clearSearch() {
  searchQuery.value = '';
  fetchGuests(true);
}

function changePage(page) {
  if (page < 1 || page > pagination.last_page) return;
  pagination.current_page = page;
  fetchGuests();
}

function openRegisterModal() {
  duplicateWarning.value = null;
  Object.assign(regForm, {
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    gender: 'unspecified',
    date_of_birth: '',
    nationality: 'Ethiopian',
    address: '',
    city: 'Addis Ababa',
    country: 'Ethiopia',
    id_type: '',
    id_number: '',
    id_issue_country: 'Ethiopia',
    id_expiry_date: '',
    vip_status: 'standard',
    emergency_contact_name: '',
    emergency_contact_phone: '',
    notes: '',
    preferences: {
      smoking: false,
      bed_type: 'king',
      floor_preference: 'any',
      dietary: '',
      accessibility_needs: false,
    }
  });
  showRegisterModal.value = true;
}

async function checkDuplicates() {
  if (!regForm.email && !regForm.phone && !regForm.id_number) return;
  try {
    const params = new URLSearchParams();
    if (regForm.email) params.set('email', regForm.email);
    if (regForm.phone) params.set('phone', regForm.phone);
    if (regForm.id_number) params.set('id_number', regForm.id_number);

    const res = await fetch(`/api/v1/guests/check-duplicate?${params.toString()}`);
    if (res.ok) {
      const data = await res.json();
      if (data.is_duplicate && data.matches.length > 0) {
        const m = data.matches[0];
        duplicateWarning.value = {
          message: `Matching guest found: ${m.full_name} (${m.phone}, ${m.email}). Avoid creating a duplicate profile.`,
          guest: m
        };
      } else {
        duplicateWarning.value = null;
      }
    }
  } catch (e) {
    console.error('Check duplicate failed:', e);
  }
}

function selectExistingGuest(g) {
  showRegisterModal.value = false;
  viewDossier(g);
}

async function submitRegister() {
  submittingReg.value = true;
  try {
    const res = await fetch('/api/v1/guests', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(regForm)
    });
    const data = await res.json();
    if (res.ok) {
      alert(`Success: Guest '${data.guest?.full_name}' registered successfully!`);
      showRegisterModal.value = false;
      fetchGuests(true);
      fetchDashboardStats();
    } else {
      alert(`Error: ${data.error || 'Failed to register guest'}`);
    }
  } catch (err) {
    alert('Network error while registering guest.');
  } finally {
    submittingReg.value = false;
  }
}

async function viewDossier(g) {
  try {
    const res = await fetch(`/api/v1/guests/${g.id}`);
    if (res.ok) {
      const data = await res.json();
      selectedGuestDossier.value = data;
      activeDossierTab.value = 'overview';
      showDossierModal.value = true;
    } else {
      alert('Failed to retrieve guest profile details.');
    }
  } catch (e) {
    alert('Error loading guest dossier.');
  }
}

function openEditModal(g) {
  Object.assign(editForm, {
    id: g.id,
    first_name: g.first_name || '',
    last_name: g.last_name || '',
    full_name: g.full_name || '',
    email: g.email || '',
    phone: g.phone || '',
    nationality: g.nationality || 'Ethiopian',
    vip_status: g.vip_status || 'standard',
    id_type: g.id_type || '',
    id_number: g.id_number || '',
    address: g.address || '',
    notes: g.notes || '',
    is_active: g.is_active !== undefined ? g.is_active : true,
  });
  showEditModal.value = true;
}

async function submitEdit() {
  try {
    const res = await fetch(`/api/v1/guests/${editForm.id}`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(editForm)
    });
    const data = await res.json();
    if (res.ok) {
      alert('Guest profile updated successfully.');
      showEditModal.value = false;
      fetchGuests();
      if (showDossierModal.value && selectedGuestDossier.value) {
        viewDossier({ id: editForm.id });
      }
    } else {
      alert(`Error: ${data.error || 'Failed to update guest profile'}`);
    }
  } catch (e) {
    alert('Error updating guest profile.');
  }
}

function createBookingForGuest(g) {
  // Navigate to Booking Desk with preselected guest params
  router.push({
    path: '/admin/bookings',
    query: { guest_id: g.id, guest_name: g.full_name, guest_phone: g.phone, guest_email: g.email }
  });
}

// Helpers
function initials(name) {
  if (!name) return 'GU';
  return name.split(' ').map(n => n[0]).slice(0, 2).join('').toUpperCase();
}

function formatMoney(amount) {
  return Number(amount || 0).toLocaleString();
}

function formatIdType(t) {
  if (!t) return 'None';
  return t.replace('_', ' ').toUpperCase();
}

function avatarBg(vip) {
  switch (vip) {
    case 'vvip': return 'bg-purple-800';
    case 'vip': return 'bg-[#0B4D34]';
    case 'blacklist': return 'bg-rose-800';
    default: return 'bg-slate-700';
  }
}

function vipBadgeClass(vip) {
  switch (vip) {
    case 'vvip': return 'bg-purple-100 text-purple-900 border border-purple-200';
    case 'vip': return 'bg-amber-100 text-amber-900 border border-amber-300';
    case 'blacklist': return 'bg-rose-100 text-rose-900 border border-rose-300';
    default: return 'bg-slate-100 text-slate-700';
  }
}

function statusBadge(s) {
  switch (s) {
    case 'confirmed': return 'bg-emerald-100 text-emerald-800';
    case 'checked_in': return 'bg-blue-100 text-blue-800';
    case 'checked_out': return 'bg-slate-100 text-slate-800';
    case 'cancelled': return 'bg-rose-100 text-rose-800';
    case 'no_show': return 'bg-amber-100 text-amber-800';
    default: return 'bg-slate-100 text-slate-600';
  }
}
</script>
