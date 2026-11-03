<template>
  <div class="space-y-6">
    <!-- Top Operations Header & Tabs -->
    <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-slate-900 flex items-center gap-2">
          <span>🛏️</span>
          <span>Room Management & Inventory Matrix</span>
        </h1>
        <p class="text-xs text-slate-500 mt-0.5">
          Comprehensive inventory control, room types, pricing, amenities, operational statuses, and availability.
        </p>
      </div>

      <!-- Main Navigation Tabs -->
      <div class="flex items-center gap-2 bg-slate-100 p-1 rounded-2xl text-xs font-semibold">
        <button 
          @click="activeTab = 'rooms'"
          :class="activeTab === 'rooms' ? 'bg-[#0B4D34] text-white shadow-sm font-bold' : 'text-slate-600 hover:text-slate-900'"
          class="px-4 py-2 rounded-xl transition cursor-pointer flex items-center gap-1.5">
          <span>🏨</span>
          <span>Rooms Inventory ({{ rooms.length }})</span>
        </button>
        <button 
          @click="activeTab = 'categories'"
          :class="activeTab === 'categories' ? 'bg-[#0B4D34] text-white shadow-sm font-bold' : 'text-slate-600 hover:text-slate-900'"
          class="px-4 py-2 rounded-xl transition cursor-pointer flex items-center gap-1.5">
          <span>🏷️</span>
          <span>Room Types & Categories ({{ categories.length }})</span>
        </button>
        <button 
          @click="activeTab = 'availability'"
          :class="activeTab === 'availability' ? 'bg-[#0B4D34] text-white shadow-sm font-bold' : 'text-slate-600 hover:text-slate-900'"
          class="px-4 py-2 rounded-xl transition cursor-pointer flex items-center gap-1.5">
          <span>📅</span>
          <span>Date Availability Search</span>
        </button>
      </div>
    </div>

    <!-- Notification Toast / Alert Banner -->
    <div 
      v-if="notification.show" 
      :class="notification.type === 'error' ? 'bg-rose-50 border-rose-200 text-rose-800' : 'bg-emerald-50 border-emerald-200 text-emerald-800'"
      class="p-4 rounded-xl border flex items-center justify-between text-xs font-semibold transition-all shadow-xs">
      <div class="flex items-center gap-2">
        <span>{{ notification.type === 'error' ? '⚠️' : '✓' }}</span>
        <span>{{ notification.message }}</span>
      </div>
      <button @click="notification.show = false" class="text-slate-400 hover:text-slate-600 text-sm">✕</button>
    </div>

    <!-- TAB 1: ROOMS INVENTORY -->
    <div v-show="activeTab === 'rooms'" class="space-y-6">
      <!-- Operational Status KPI Cards -->
      <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
        <div class="bg-white p-3.5 rounded-xl border border-slate-100 shadow-xs">
          <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Total Rooms</span>
          <div class="text-xl font-bold text-slate-900 mt-0.5">{{ summary.total_rooms || rooms.length }}</div>
          <span class="text-[10px] text-slate-400">{{ summary.active_rooms || rooms.length }} Active In System</span>
        </div>
        <div class="bg-white p-3.5 rounded-xl border border-emerald-100 shadow-xs">
          <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-600">Available</span>
          <div class="text-xl font-bold text-emerald-700 mt-0.5">{{ summary.available_count || 0 }}</div>
          <span class="text-[10px] text-emerald-600/80">Ready For Check-In</span>
        </div>
        <div class="bg-white p-3.5 rounded-xl border border-blue-100 shadow-xs">
          <span class="text-[10px] font-bold uppercase tracking-wider text-blue-600">Reserved</span>
          <div class="text-xl font-bold text-blue-700 mt-0.5">{{ summary.reserved_count || 0 }}</div>
          <span class="text-[10px] text-blue-600/80">Booked Arrivals Today</span>
        </div>
        <div class="bg-white p-3.5 rounded-xl border border-rose-100 shadow-xs">
          <span class="text-[10px] font-bold uppercase tracking-wider text-rose-600">Occupied</span>
          <div class="text-xl font-bold text-rose-700 mt-0.5">{{ summary.occupied_count || 0 }}</div>
          <span class="text-[10px] text-rose-600/80">Guests In-House</span>
        </div>
        <div class="bg-white p-3.5 rounded-xl border border-purple-100 shadow-xs">
          <span class="text-[10px] font-bold uppercase tracking-wider text-purple-600">Cleaning</span>
          <div class="text-xl font-bold text-purple-700 mt-0.5">{{ summary.cleaning_count || 0 }}</div>
          <span class="text-[10px] text-purple-600/80">Housekeeping Turnover</span>
        </div>
        <div class="bg-white p-3.5 rounded-xl border border-amber-100 shadow-xs">
          <span class="text-[10px] font-bold uppercase tracking-wider text-amber-600">Maintenance</span>
          <div class="text-xl font-bold text-amber-700 mt-0.5">{{ summary.maintenance_count || 0 }}</div>
          <span class="text-[10px] text-amber-600/80">Out of Service / Repair</span>
        </div>
      </div>

      <!-- Main Layout: Table on Left (2 cols), Add/Edit Form on Right (1 col) -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left 2 Cols: Room Listing Table & Filters -->
        <div class="lg:col-span-2 space-y-4">
          <!-- Filter & Search Toolbar -->
          <div class="bg-white p-4 rounded-2xl shadow-xs border border-slate-100 space-y-3">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
              <!-- Search Input -->
              <div class="relative sm:col-span-1">
                <input 
                  v-model="filters.search" 
                  @input="fetchRooms"
                  type="text" 
                  placeholder="Search room #, title..." 
                  class="w-full pl-8 pr-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-[#0B4D34]" />
                <span class="absolute left-2.5 top-2.5 text-xs text-slate-400">🔍</span>
              </div>

              <!-- Category Dropdown -->
              <div>
                <select 
                  v-model="filters.category" 
                  @change="fetchRooms"
                  class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-[#0B4D34]">
                  <option value="all">🏨 All Room Types</option>
                  <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                    {{ cat.name }} ({{ cat.max_capacity }} pax)
                  </option>
                </select>
              </div>

              <!-- Operational Status Dropdown -->
              <div>
                <select 
                  v-model="filters.status" 
                  @change="fetchRooms"
                  class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-[#0B4D34]">
                  <option value="all">⚡ All Statuses</option>
                  <option value="available">🟢 Available Only</option>
                  <option value="reserved">🔵 Reserved Only</option>
                  <option value="occupied">🔴 Occupied Only</option>
                  <option value="cleaning">🟣 Cleaning / Turnover</option>
                  <option value="maintenance">🟡 Under Maintenance</option>
                  <option value="inactive">⚪ Inactive / Archived</option>
                </select>
              </div>
            </div>

            <!-- Second Filter Row: Price Range & Date Range & Show Inactive -->
            <div class="flex flex-wrap items-center justify-between gap-2.5 pt-2 border-t border-slate-100 text-xs">
              <div class="flex items-center gap-2">
                <span class="text-slate-400 font-semibold">Price (ETB):</span>
                <input 
                  v-model.number="filters.min_price" 
                  @change="fetchRooms"
                  type="number" 
                  placeholder="Min" 
                  class="w-20 px-2 py-1 bg-slate-50 border border-slate-200 rounded-lg text-xs" />
                <span class="text-slate-400">-</span>
                <input 
                  v-model.number="filters.max_price" 
                  @change="fetchRooms"
                  type="number" 
                  placeholder="Max" 
                  class="w-20 px-2 py-1 bg-slate-50 border border-slate-200 rounded-lg text-xs" />
              </div>

              <div class="flex items-center gap-3">
                <label class="flex items-center gap-1.5 cursor-pointer text-slate-600 font-medium select-none">
                  <input 
                    v-model="filters.include_inactive" 
                    @change="fetchRooms"
                    type="checkbox" 
                    class="rounded text-[#0B4D34] focus:ring-0" />
                  <span>Show Archived / Inactive</span>
                </label>

                <button 
                  @click="resetFilters" 
                  class="text-xs text-slate-400 hover:text-slate-600 font-semibold underline">
                  Reset
                </button>
              </div>
            </div>
          </div>

          <!-- Rooms Table Card -->
          <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <div class="px-5 py-3.5 border-b border-slate-100 flex items-center justify-between">
              <div class="flex items-center gap-2">
                <h2 class="text-sm font-bold text-slate-900">Hotel Rooms Directory</h2>
                <span class="text-xs px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 font-bold">
                  {{ filteredRooms.length }} rooms
                </span>
              </div>
              <button 
                @click="resetForm(); isEditing = false;"
                class="px-3.5 py-1.5 bg-[#0B4D34] hover:bg-[#064E3B] text-white text-xs font-semibold rounded-full shadow-xs flex items-center gap-1 transition cursor-pointer">
                <span>+</span> Add Room
              </button>
            </div>

            <div class="overflow-x-auto">
              <table class="w-full text-left border-collapse text-xs">
                <thead>
                  <tr class="bg-slate-50 text-slate-600 uppercase font-semibold border-b border-slate-100 tracking-wider">
                    <th class="py-3 px-4">Photo</th>
                    <th class="py-3 px-4">Room # & Title</th>
                    <th class="py-3 px-4">Type</th>
                    <th class="py-3 px-4">Price / Night</th>
                    <th class="py-3 px-4">Operational Status</th>
                    <th class="py-3 px-4 text-right">Actions</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-700">
                  <tr v-if="loading" class="text-center">
                    <td colspan="6" class="py-12 text-slate-400">
                      <div class="text-2xl animate-spin mb-1">⏳</div>
                      <p>Loading hotel rooms database...</p>
                    </td>
                  </tr>
                  <tr v-else-if="filteredRooms.length === 0" class="text-center">
                    <td colspan="6" class="py-12 text-slate-400">
                      <div class="text-2xl mb-1">🔍</div>
                      <p>No rooms match your filter criteria.</p>
                      <button @click="resetFilters" class="mt-2 text-[#0B4D34] font-bold underline">Clear Filters</button>
                    </td>
                  </tr>
                  <tr 
                    v-else
                    v-for="room in paginatedRooms" 
                    :key="room.id"
                    :class="!room.is_active ? 'opacity-60 bg-slate-50/50' : 'hover:bg-slate-50/60'"
                    class="transition">
                    <!-- Photo -->
                    <td class="py-3 px-4">
                      <img 
                        :src="room.image_url || 'https://images.unsplash.com/photo-1591088398332-8a7791972843?w=800&fit=crop'" 
                        class="w-12 h-10 rounded-lg object-cover border border-slate-200 shadow-2xs" 
                        alt="Room" />
                    </td>

                    <!-- Room # and Title -->
                    <td class="py-3 px-4">
                      <div class="flex items-center gap-1.5">
                        <span class="font-extrabold text-slate-900 text-sm">Room {{ room.room_number }}</span>
                        <span v-if="!room.is_active" class="px-1.5 py-0.5 rounded text-[9px] bg-slate-200 text-slate-700 font-bold uppercase">
                          Archived
                        </span>
                      </div>
                      <p class="text-[11px] text-slate-400 truncate max-w-[170px]">{{ room.title }}</p>
                      <div class="flex items-center gap-2 mt-0.5 text-[10px] text-slate-500">
                        <span>Floor {{ room.floor }}</span>
                        <span>•</span>
                        <span>Max {{ room.capacity }} Guests</span>
                      </div>
                    </td>

                    <!-- Category Type Pill -->
                    <td class="py-3 px-4">
                      <span 
                        :style="{ backgroundColor: (room.badge_color || '#0B4D34') + '18', color: room.badge_color || '#0B4D34' }"
                        class="text-[11px] font-bold px-2.5 py-1 rounded-full whitespace-nowrap border border-current/20">
                        {{ room.category_name }}
                      </span>
                    </td>

                    <!-- Price -->
                    <td class="py-3 px-4">
                      <div class="font-bold text-slate-900">{{ room.price_etb?.toLocaleString() }} ETB</div>
                      <div class="text-[10px] text-slate-400">${{ room.price_usd }} USD</div>
                    </td>

                    <!-- Operational Status -->
                    <td class="py-3 px-4">
                      <div class="space-y-1">
                        <span 
                          :class="getStatusBadgeClass(room.dynamic_status)"
                          class="inline-flex items-center gap-1.5 text-[10px] font-bold px-2.5 py-1 rounded-full uppercase tracking-wider">
                          <span class="w-1.5 h-1.5 rounded-full" :class="getStatusDotClass(room.dynamic_status)"></span>
                          {{ formatStatusLabel(room.dynamic_status) }}
                        </span>
                        <div class="text-[9px] text-slate-400 capitalize">
                          HK: {{ room.housekeeping_status }}
                        </div>
                      </div>
                    </td>

                    <!-- Action Buttons -->
                    <td class="py-3 px-4 text-right">
                      <div class="inline-flex items-center gap-1">
                        <!-- Inspect Details Modal Trigger -->
                        <button 
                          @click="openDetailsModal(room)"
                          class="p-1.5 text-slate-600 hover:text-slate-900 hover:bg-slate-100 rounded-lg transition" 
                          title="View Room Details">
                          👁️
                        </button>

                        <!-- Edit Room Trigger -->
                        <button 
                          @click="editRoom(room)"
                          class="p-1.5 text-emerald-700 hover:bg-emerald-50 rounded-lg transition" 
                          title="Edit Room Information">
                          ✏️
                        </button>

                        <!-- Toggle Active/Inactive -->
                        <button 
                          @click="toggleActive(room)"
                          :class="room.is_active ? 'text-amber-600 hover:bg-amber-50' : 'text-emerald-600 hover:bg-emerald-50'"
                          class="p-1.5 rounded-lg transition" 
                          :title="room.is_active ? 'Deactivate / Archive Room' : 'Reactivate Room'">
                          {{ room.is_active ? '⏸️' : '▶️' }}
                        </button>

                        <!-- Delete Room Trigger -->
                        <button 
                          @click="confirmDeleteRoom(room)"
                          class="p-1.5 text-rose-500 hover:bg-rose-50 rounded-lg transition" 
                          title="Delete or Soft-Archive Room">
                          🗑️
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
                  <option :value="10">10 rooms</option>
                  <option :value="25">25 rooms</option>
                  <option :value="50">50 rooms</option>
                  <option :value="1000">All</option>
                </select>
                <span>Showing {{ paginationStart }} - {{ paginationEnd }} of {{ filteredRooms.length }} rooms</span>
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
        </div>

        <!-- Right 1 Col: Comprehensive Add / Edit Room Form -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 space-y-4 h-fit sticky top-6">
          <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
              <span>{{ isEditing ? '✏️' : '➕' }}</span>
              <span>{{ isEditing ? 'Edit Room #' + form.room_number : 'Add New Hotel Room' }}</span>
            </h3>
            <button 
              v-if="isEditing" 
              @click="resetForm" 
              class="text-xs text-slate-400 hover:text-slate-600 font-medium">
              Cancel
            </button>
          </div>

          <form @submit.prevent="saveRoom" class="space-y-3.5">
            <!-- Room Number & Floor -->
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="text-xs font-semibold text-slate-700 block mb-1">
                  Room Number <span class="text-rose-500">*</span>
                </label>
                <input 
                  v-model="form.room_number" 
                  type="text" 
                  placeholder="e.g. 305" 
                  required
                  class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-800 focus:outline-none focus:border-[#0B4D34]" />
              </div>
              <div>
                <label class="text-xs font-semibold text-slate-700 block mb-1">
                  Floor Level <span class="text-rose-500">*</span>
                </label>
                <input 
                  v-model.number="form.floor" 
                  type="number" 
                  min="1" 
                  max="99" 
                  required
                  class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-[#0B4D34]" />
              </div>
            </div>

            <!-- Room Title -->
            <div>
              <label class="text-xs font-semibold text-slate-700 block mb-1">
                Room Display Title <span class="text-rose-500">*</span>
              </label>
              <input 
                v-model="form.title" 
                type="text" 
                placeholder="e.g. Semien Grand Mountain Suite" 
                required
                class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-[#0B4D34]" />
            </div>

            <!-- Category / Room Type Dropdown -->
            <div>
              <div class="flex items-center justify-between mb-1">
                <label class="text-xs font-semibold text-slate-700">
                  Room Type / Category <span class="text-rose-500">*</span>
                </label>
                <button 
                  type="button"
                  @click="activeTab = 'categories'" 
                  class="text-[10px] text-[#0B4D34] font-bold hover:underline">
                  + Manage Types
                </button>
              </div>
              <select 
                v-model="form.category_id" 
                @change="onCategorySelect"
                required
                class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-[#0B4D34]">
                <option value="" disabled>Select Room Type</option>
                <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                  {{ cat.name }} (Base: {{ cat.base_price_etb }} ETB • Max: {{ cat.max_capacity }} guests)
                </option>
              </select>
            </div>

            <!-- Capacity & Price per Night -->
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="text-xs font-semibold text-slate-700 block mb-1">
                  Max Capacity <span class="text-rose-500">*</span>
                </label>
                <input 
                  v-model.number="form.capacity" 
                  type="number" 
                  min="1" 
                  max="20" 
                  required
                  class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-[#0B4D34]" />
              </div>
              <div>
                <label class="text-xs font-semibold text-slate-700 block mb-1">
                  Price (ETB / Night) <span class="text-rose-500">*</span>
                </label>
                <input 
                  v-model.number="form.price_etb" 
                  @input="autoCalculateUsdPrice"
                  type="number" 
                  min="100" 
                  required
                  class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-900 focus:outline-none focus:border-[#0B4D34]" />
              </div>
            </div>

            <!-- Price USD & Status -->
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="text-xs font-semibold text-slate-700 block mb-1">Price (USD)</label>
                <input 
                  v-model.number="form.price_usd" 
                  type="number" 
                  min="5" 
                  class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-[#0B4D34]" />
              </div>
              <div>
                <label class="text-xs font-semibold text-slate-700 block mb-1">Status</label>
                <select 
                  v-model="form.status" 
                  class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-[#0B4D34]">
                  <option value="available">🟢 Available</option>
                  <option value="reserved">🔵 Reserved</option>
                  <option value="occupied">🔴 Occupied</option>
                  <option value="cleaning">🟣 Cleaning / Dirty</option>
                  <option value="maintenance">🟡 Maintenance</option>
                </select>
              </div>
            </div>

            <!-- Room Description -->
            <div>
              <label class="text-xs font-semibold text-slate-700 block mb-1">Description & Key Features</label>
              <textarea 
                v-model="form.description" 
                rows="2" 
                placeholder="Comfortable suite with balcony, king-size bed, luxury bathroom..." 
                class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-[#0B4D34]"></textarea>
            </div>

            <!-- Amenities Checkboxes Grid -->
            <div>
              <label class="text-xs font-semibold text-slate-700 block mb-1.5">
                Room Facilities & Amenities
              </label>
              <div class="grid grid-cols-2 gap-2 bg-slate-50 p-2.5 rounded-xl border border-slate-200 max-h-36 overflow-y-auto">
                <label 
                  v-for="amen in allAmenities" 
                  :key="amen.id" 
                  class="flex items-center gap-2 text-[11px] text-slate-700 cursor-pointer select-none hover:text-slate-900">
                  <input 
                    type="checkbox" 
                    :value="amen.id" 
                    v-model="form.amenities" 
                    class="rounded text-[#0B4D34] focus:ring-0" />
                  <span>{{ amen.name }}</span>
                </label>
              </div>
            </div>

            <!-- Room Image Upload -->
            <div>
              <label class="text-xs font-semibold text-slate-700 block mb-1">Room Photo</label>
              <div 
                @click="$refs.fileInput.click()"
                class="border-2 border-dashed border-[#0B4D34]/30 hover:border-[#0B4D34] rounded-xl p-3 text-center cursor-pointer bg-slate-50 transition">
                <input 
                  ref="fileInput" 
                  type="file" 
                  accept="image/*" 
                  @change="onFileSelected" 
                  class="hidden" />
                <div v-if="form.image_url" class="space-y-1">
                  <img :src="form.image_url" class="h-16 mx-auto rounded-lg object-cover" alt="Preview" />
                  <span class="text-[10px] text-emerald-600 font-bold block">✓ Photo attached</span>
                </div>
                <div v-else class="text-xs text-slate-500">
                  <span>📷 Click to upload room photo</span>
                </div>
              </div>
            </div>

            <!-- Submit Buttons -->
            <div class="flex gap-2 pt-2">
              <button 
                type="submit"
                :disabled="savingRoom || isUploading"
                class="flex-1 py-2.5 bg-[#0B4D34] hover:bg-[#064E3B] text-white text-xs font-bold rounded-xl shadow-md transition disabled:opacity-50 cursor-pointer">
                {{ savingRoom ? 'Saving to Database...' : (isEditing ? 'Update Room Information' : 'Create Hotel Room') }}
              </button>
              <button 
                v-if="isEditing"
                type="button"
                @click="resetForm"
                class="px-3.5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl transition">
                Cancel
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- TAB 2: ROOM TYPES & CATEGORIES MANAGEMENT -->
    <div v-show="activeTab === 'categories'" class="space-y-6">
      <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-4">
          <div>
            <h2 class="text-lg font-bold text-slate-900">Room Categories & Standard Types</h2>
            <p class="text-xs text-slate-500">
              Configure room classifications (Single, Double, Twin, Triple, Deluxe, Suite, Family) with base pricing and capacity rules.
            </p>
          </div>
          <button 
            @click="openCategoryModal()"
            class="px-4 py-2 bg-[#0B4D34] hover:bg-[#064E3B] text-white text-xs font-bold rounded-xl shadow-xs flex items-center gap-1.5 transition cursor-pointer">
            <span>+</span> Add Room Type
          </button>
        </div>

        <!-- Categories Table -->
        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse text-xs">
            <thead>
              <tr class="bg-slate-50 text-slate-600 uppercase font-semibold border-b border-slate-100 tracking-wider">
                <th class="py-3 px-4">Badge</th>
                <th class="py-3 px-4">Type Name & Slug</th>
                <th class="py-3 px-4">Max Capacity</th>
                <th class="py-3 px-4">Base Rate (ETB)</th>
                <th class="py-3 px-4">Base Rate (USD)</th>
                <th class="py-3 px-4">Assigned Rooms</th>
                <th class="py-3 px-4">Description</th>
                <th class="py-3 px-4 text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-slate-700">
              <tr v-for="cat in categories" :key="cat.id" class="hover:bg-slate-50/60 transition">
                <td class="py-3 px-4">
                  <span 
                    :style="{ backgroundColor: cat.badge_color || '#0B4D34' }"
                    class="w-4 h-4 rounded-full inline-block shadow-2xs"></span>
                </td>
                <td class="py-3 px-4">
                  <span class="font-bold text-slate-900 text-sm block">{{ cat.name }}</span>
                  <span class="text-[10px] text-slate-400 font-mono">{{ cat.slug }}</span>
                </td>
                <td class="py-3 px-4 font-semibold text-slate-800">
                  👥 {{ cat.max_capacity }} Guests
                </td>
                <td class="py-3 px-4 font-bold text-slate-900">
                  {{ cat.base_price_etb?.toLocaleString() }} ETB
                </td>
                <td class="py-3 px-4 text-slate-600">
                  ${{ cat.base_price_usd }} USD
                </td>
                <td class="py-3 px-4">
                  <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-700">
                    {{ cat.active_rooms_count }} active / {{ cat.total_rooms_count }} total
                  </span>
                </td>
                <td class="py-3 px-4 text-slate-500 max-w-xs truncate">
                  {{ cat.description || 'Standard luxury room category.' }}
                </td>
                <td class="py-3 px-4 text-right space-x-1">
                  <button 
                    @click="openCategoryModal(cat)"
                    class="p-1.5 text-emerald-700 hover:bg-emerald-50 rounded-lg transition" 
                    title="Edit Room Type">
                    ✏️
                  </button>
                  <button 
                    @click="deleteCategory(cat)"
                    class="p-1.5 text-rose-500 hover:bg-rose-50 rounded-lg transition" 
                    title="Delete Room Type">
                    🗑️
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- TAB 3: DATE AVAILABILITY INSPECTOR -->
    <div v-show="activeTab === 'availability'" class="space-y-6">
      <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-100 pb-4">
          <div>
            <h2 class="text-lg font-bold text-slate-900">Real-Time Date Collision & Availability Inspector</h2>
            <p class="text-xs text-slate-500">
              Verify room availability for specific dates, detect reservations, and inspect turnover locks.
            </p>
          </div>

          <!-- Date Selector -->
          <div class="flex items-center gap-2 bg-slate-50 p-2 rounded-xl border border-slate-200 text-xs">
            <span class="font-bold text-slate-700">📅 Range:</span>
            <input 
              v-model="availDates.checkIn" 
              @change="fetchDateAvailability"
              type="date" 
              class="px-2 py-1 bg-white border border-slate-200 rounded-lg text-xs font-semibold" />
            <span>→</span>
            <input 
              v-model="availDates.checkOut" 
              @change="fetchDateAvailability"
              type="date" 
              class="px-2 py-1 bg-white border border-slate-200 rounded-lg text-xs font-semibold" />
            <button 
              @click="fetchDateAvailability"
              class="px-3 py-1 bg-[#0B4D34] hover:bg-[#064E3B] text-white rounded-lg font-bold">
              Check
            </button>
          </div>
        </div>

        <!-- Availability Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
          <div 
            v-for="r in dateAvailabilityRooms" 
            :key="r.id"
            :class="r.is_available ? 'border-emerald-200 bg-emerald-50/20' : 'border-rose-200 bg-rose-50/20'"
            class="p-4 rounded-xl border shadow-2xs space-y-2">
            <div class="flex items-center justify-between">
              <span class="text-base font-extrabold text-slate-900">Room {{ r.room_number }}</span>
              <span 
                :class="r.is_available ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'"
                class="text-[10px] font-bold px-2 py-0.5 rounded-full uppercase">
                {{ r.is_available ? 'Free & Ready' : 'Unavailable' }}
              </span>
            </div>
            <p class="text-xs text-slate-500 truncate">{{ r.title }}</p>
            <div class="text-[11px] font-semibold text-slate-700">
              {{ r.category_name }} • {{ r.price_etb?.toLocaleString() }} ETB/night
            </div>
            <p class="text-[10px]" :class="r.is_available ? 'text-emerald-700 font-medium' : 'text-rose-700 font-bold'">
              {{ r.availability_reason }}
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL 1: ROOM DETAILS MODAL -->
    <div 
      v-if="detailsModal.show && detailsModal.room" 
      class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4">
      <div class="bg-white rounded-3xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto overflow-x-hidden p-6 space-y-5">
        <!-- Modal Header -->
        <div class="flex items-start justify-between border-b border-slate-100 pb-3">
          <div>
            <div class="flex items-center gap-2">
              <h3 class="text-2xl font-black text-slate-900">Room {{ detailsModal.room.room_number }}</h3>
              <span 
                :class="getStatusBadgeClass(detailsModal.room.dynamic_status)"
                class="text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wider">
                {{ formatStatusLabel(detailsModal.room.dynamic_status) }}
              </span>
            </div>
            <p class="text-xs text-slate-500 mt-0.5">{{ detailsModal.room.title }}</p>
          </div>
          <button @click="detailsModal.show = false" class="text-slate-400 hover:text-slate-600 text-lg">✕</button>
        </div>

        <!-- Room High-Res Image Showcase -->
        <div class="rounded-2xl overflow-hidden border border-slate-100 shadow-inner max-h-56">
          <img 
            :src="detailsModal.room.image_url" 
            class="w-full h-56 object-cover" 
            alt="Room Showcase" />
        </div>

        <!-- Specifications Grid -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
          <div class="bg-slate-50 p-3 rounded-xl border border-slate-100">
            <span class="text-[10px] text-slate-400 font-semibold uppercase">Category</span>
            <div class="text-sm font-bold text-slate-800">{{ detailsModal.room.category_name }}</div>
          </div>
          <div class="bg-slate-50 p-3 rounded-xl border border-slate-100">
            <span class="text-[10px] text-slate-400 font-semibold uppercase">Floor</span>
            <div class="text-sm font-bold text-slate-800">Floor {{ detailsModal.room.floor }}</div>
          </div>
          <div class="bg-slate-50 p-3 rounded-xl border border-slate-100">
            <span class="text-[10px] text-slate-400 font-semibold uppercase">Max Capacity</span>
            <div class="text-sm font-bold text-slate-800">{{ detailsModal.room.capacity }} Guests</div>
          </div>
          <div class="bg-slate-50 p-3 rounded-xl border border-slate-100">
            <span class="text-[10px] text-slate-400 font-semibold uppercase">Nightly Rate</span>
            <div class="text-sm font-bold text-[#0B4D34]">{{ detailsModal.room.price_etb?.toLocaleString() }} ETB</div>
            <div class="text-[10px] text-slate-400">(${{ detailsModal.room.price_usd }} USD)</div>
          </div>
        </div>

        <!-- Description -->
        <div class="space-y-1">
          <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Room Description</h4>
          <p class="text-xs text-slate-600 leading-relaxed bg-slate-50 p-3.5 rounded-xl border border-slate-100">
            {{ detailsModal.room.description || 'No description provided.' }}
          </p>
        </div>

        <!-- Amenities Included -->
        <div class="space-y-1.5">
          <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Included Amenities & Services</h4>
          <div class="flex flex-wrap gap-2">
            <span 
              v-for="a in detailsModal.room.amenities" 
              :key="a.id"
              class="px-3 py-1 bg-emerald-50 text-emerald-800 rounded-full text-xs font-semibold border border-emerald-200 flex items-center gap-1.5">
              <span>✨</span>
              <span>{{ a.name }}</span>
            </span>
            <span v-if="!detailsModal.room.amenities || detailsModal.room.amenities.length === 0" class="text-xs text-slate-400">
              No amenities assigned to this room.
            </span>
          </div>
        </div>

        <!-- Recent Reservations History -->
        <div class="space-y-2 pt-2 border-t border-slate-100" v-if="detailsModal.room.recent_bookings && detailsModal.room.recent_bookings.length">
          <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Recent Reservations History</h4>
          <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
              <thead>
                <tr class="bg-slate-50 text-slate-500 font-semibold border-b border-slate-100">
                  <th class="py-2 px-3">Reference</th>
                  <th class="py-2 px-3">Guest Name</th>
                  <th class="py-2 px-3">Dates</th>
                  <th class="py-2 px-3">Status</th>
                  <th class="py-2 px-3 text-right">Total Price</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-for="b in detailsModal.room.recent_bookings" :key="b.id">
                  <td class="py-2 px-3 font-mono font-bold text-slate-800">{{ b.reference_code }}</td>
                  <td class="py-2 px-3 text-slate-700">{{ b.guest_name }}</td>
                  <td class="py-2 px-3 text-slate-500">{{ b.check_in_date }} → {{ b.check_out_date }}</td>
                  <td class="py-2 px-3">
                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-slate-100 text-slate-700">
                      {{ b.booking_status }}
                    </span>
                  </td>
                  <td class="py-2 px-3 text-right font-bold text-slate-800">{{ b.total_price_etb?.toLocaleString() }} ETB</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Modal Actions -->
        <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
          <button 
            @click="detailsModal.show = false; editRoom(detailsModal.room)"
            class="px-4 py-2 bg-[#0B4D34] hover:bg-[#064E3B] text-white text-xs font-bold rounded-xl transition">
            ✏️ Edit This Room
          </button>
          <button 
            @click="detailsModal.show = false"
            class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition">
            Close
          </button>
        </div>
      </div>
    </div>

    <!-- MODAL 2: ADD / EDIT ROOM CATEGORY MODAL -->
    <div 
      v-if="categoryModal.show" 
      class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4">
      <div class="bg-white rounded-3xl shadow-2xl max-w-lg w-full p-6 space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
          <h3 class="text-lg font-bold text-slate-900">
            {{ categoryModal.isEditing ? 'Edit Room Type' : 'Create New Room Category' }}
          </h3>
          <button @click="categoryModal.show = false" class="text-slate-400 hover:text-slate-600">✕</button>
        </div>

        <form @submit.prevent="saveCategory" class="space-y-3.5">
          <div>
            <label class="text-xs font-semibold text-slate-700 block mb-1">
              Category Name <span class="text-rose-500">*</span>
            </label>
            <input 
              v-model="categoryModal.form.name" 
              type="text" 
              placeholder="e.g., Twin Room, Family Suite" 
              required
              class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-900 focus:outline-none focus:border-[#0B4D34]" />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="text-xs font-semibold text-slate-700 block mb-1">
                Base Price (ETB) <span class="text-rose-500">*</span>
              </label>
              <input 
                v-model.number="categoryModal.form.base_price_etb" 
                type="number" 
                min="100" 
                required
                class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold focus:outline-none focus:border-[#0B4D34]" />
            </div>
            <div>
              <label class="text-xs font-semibold text-slate-700 block mb-1">Base Price (USD)</label>
              <input 
                v-model.number="categoryModal.form.base_price_usd" 
                type="number" 
                min="5" 
                class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-[#0B4D34]" />
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="text-xs font-semibold text-slate-700 block mb-1">
                Max Guest Capacity <span class="text-rose-500">*</span>
              </label>
              <input 
                v-model.number="categoryModal.form.max_capacity" 
                type="number" 
                min="1" 
                max="20" 
                required
                class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-[#0B4D34]" />
            </div>
            <div>
              <label class="text-xs font-semibold text-slate-700 block mb-1">Badge Color</label>
              <div class="flex items-center gap-2">
                <input 
                  v-model="categoryModal.form.badge_color" 
                  type="color" 
                  class="w-8 h-8 rounded-lg cursor-pointer border border-slate-200 p-0" />
                <input 
                  v-model="categoryModal.form.badge_color" 
                  type="text" 
                  class="flex-1 px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono" />
              </div>
            </div>
          </div>

          <div>
            <label class="text-xs font-semibold text-slate-700 block mb-1">Description</label>
            <textarea 
              v-model="categoryModal.form.description" 
              rows="2" 
              placeholder="Detailed description of room type specifications..." 
              class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-[#0B4D34]"></textarea>
          </div>

          <div class="flex justify-end gap-2 pt-2 border-t border-slate-100">
            <button 
              type="button" 
              @click="categoryModal.show = false"
              class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition">
              Cancel
            </button>
            <button 
              type="submit" 
              :disabled="categoryModal.saving"
              class="px-4 py-2 bg-[#0B4D34] hover:bg-[#064E3B] text-white text-xs font-bold rounded-xl transition disabled:opacity-50">
              {{ categoryModal.saving ? 'Saving...' : (categoryModal.isEditing ? 'Update Room Type' : 'Create Room Type') }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- MODAL 3: INTELLIGENT DELETION / ARCHIVE CONFIRMATION MODAL -->
    <div 
      v-if="deleteModal.show && deleteModal.room" 
      class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4">
      <div class="bg-white rounded-3xl shadow-2xl max-w-md w-full p-6 space-y-4">
        <div class="flex items-center gap-3 text-rose-600">
          <div class="w-10 h-10 rounded-full bg-rose-50 flex items-center justify-center text-xl font-bold">
            ⚠️
          </div>
          <div>
            <h3 class="text-base font-bold text-slate-900">
              Confirm Room Action: Room {{ deleteModal.room.room_number }}
            </h3>
            <p class="text-[11px] text-slate-400">Hotel Management Inventory Integrity Policy</p>
          </div>
        </div>

        <div class="text-xs text-slate-600 space-y-2 bg-slate-50 p-4 rounded-xl border border-slate-200">
          <p>
            You are about to remove <strong>Room {{ deleteModal.room.room_number }} ({{ deleteModal.room.title }})</strong>.
          </p>
          <div class="p-2.5 rounded-lg bg-blue-50 border border-blue-200 text-blue-900 text-[11px] font-medium">
            ℹ️ <strong>System Protocol:</strong>
            <ul class="list-disc pl-4 mt-1 space-y-0.5">
              <li>If this room has historical reservations or payments, it will be <strong>safely deactivated (archived)</strong> to preserve audit logs, invoices, and accounting ledgers.</li>
              <li>If this room has zero historical records, it will be <strong>permanently deleted</strong>.</li>
              <li>Occupied rooms cannot be deleted or deactivated.</li>
            </ul>
          </div>
        </div>

        <div class="flex justify-end gap-2 pt-2">
          <button 
            type="button" 
            @click="deleteModal.show = false"
            class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition">
            Cancel
          </button>
          <button 
            type="button" 
            @click="executeDeleteRoom"
            :disabled="deleteModal.deleting"
            class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold rounded-xl shadow-sm transition disabled:opacity-50">
            {{ deleteModal.deleting ? 'Processing...' : 'Proceed with Action' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';

const activeTab = ref('rooms');
const rooms = ref([]);
const categories = ref([]);
const allAmenities = ref([]);
const summary = ref({});
const loading = ref(false);
const isEditing = ref(false);
const editingRoomId = ref(null);
const savingRoom = ref(false);
const isUploading = ref(false);

const pageSize = ref(10);
const currentPage = ref(1);

const filters = ref({
  search: '',
  category: 'all',
  status: 'all',
  min_price: null,
  max_price: null,
  include_inactive: false,
});

const form = ref({
  room_number: '',
  title: '',
  category_id: '',
  price_etb: 2500,
  price_usd: 39,
  capacity: 2,
  floor: 1,
  status: 'available',
  description: '',
  amenities: [],
  image_url: 'https://images.unsplash.com/photo-1591088398332-8a7791972843?w=800&fit=crop'
});

const notification = ref({
  show: false,
  message: '',
  type: 'success'
});

function showToast(message, type = 'success') {
  notification.value = { show: true, message, type };
  setTimeout(() => {
    notification.value.show = false;
  }, 4500);
}

// Details Modal State
const detailsModal = ref({
  show: false,
  room: null,
});

// Category Modal State
const categoryModal = ref({
  show: false,
  isEditing: false,
  editingId: null,
  saving: false,
  form: {
    name: '',
    base_price_etb: 1500,
    base_price_usd: 210,
    max_capacity: 2,
    badge_color: '#0B4D34',
    description: '',
  }
});

// Delete Confirmation Modal State
const deleteModal = ref({
  show: false,
  room: null,
  deleting: false,
});

// Availability Inspector State
const availDates = ref({
  checkIn: new Date().toISOString().split('T')[0],
  checkOut: new Date(Date.now() + 86400000 * 2).toISOString().split('T')[0],
});
const dateAvailabilityRooms = ref([]);

function getAuthHeaders() {
  const token = localStorage.getItem('hotel_auth_token');
  return token ? { 'Authorization': `Bearer ${token}` } : {};
}

function getStatusBadgeClass(status) {
  switch (status) {
    case 'available': return 'bg-emerald-100 text-emerald-800';
    case 'reserved': return 'bg-blue-100 text-blue-800';
    case 'occupied': return 'bg-rose-100 text-rose-800';
    case 'cleaning': return 'bg-purple-100 text-purple-800';
    case 'maintenance': return 'bg-amber-100 text-amber-800';
    default: return 'bg-slate-100 text-slate-700';
  }
}

function getStatusDotClass(status) {
  switch (status) {
    case 'available': return 'bg-emerald-500 animate-pulse';
    case 'reserved': return 'bg-blue-500';
    case 'occupied': return 'bg-rose-500';
    case 'cleaning': return 'bg-purple-500';
    case 'maintenance': return 'bg-amber-500';
    default: return 'bg-slate-400';
  }
}

function formatStatusLabel(status) {
  switch (status) {
    case 'available': return 'Available';
    case 'reserved': return 'Reserved';
    case 'occupied': return 'Occupied';
    case 'cleaning': return 'Cleaning';
    case 'maintenance': return 'Maintenance';
    default: return status;
  }
}

const filteredRooms = computed(() => {
  return rooms.value;
});

const totalPages = computed(() => {
  if (pageSize.value >= 1000) return 1;
  return Math.max(1, Math.ceil(filteredRooms.value.length / pageSize.value));
});

const paginationStart = computed(() => {
  if (!filteredRooms.value.length) return 0;
  return (currentPage.value - 1) * pageSize.value + 1;
});

const paginationEnd = computed(() => {
  return Math.min(currentPage.value * pageSize.value, filteredRooms.value.length);
});

const paginatedRooms = computed(() => {
  if (pageSize.value >= 1000) return filteredRooms.value;
  const start = (currentPage.value - 1) * pageSize.value;
  return filteredRooms.value.slice(start, start + pageSize.value);
});

async function fetchCategories() {
  try {
    const res = await fetch('/api/v1/categories');
    const data = await res.json();
    categories.value = data.categories || [];
    if (!form.value.category_id && categories.value.length > 0) {
      form.value.category_id = categories.value[0].id;
    }
  } catch (err) {
    console.error('Failed to load categories:', err);
  }
}

async function fetchAmenities() {
  try {
    const res = await fetch('/api/v1/amenities');
    const data = await res.json();
    allAmenities.value = data.amenities || [];
  } catch (err) {
    console.error('Failed to load amenities:', err);
  }
}

async function fetchRooms() {
  loading.value = true;
  try {
    const params = new URLSearchParams();
    if (filters.value.category !== 'all') params.append('category', filters.value.category);
    if (filters.value.search) params.append('search', filters.value.search);
    if (filters.value.status !== 'all') params.append('status', filters.value.status);
    if (filters.value.min_price) params.append('min_price', filters.value.min_price);
    if (filters.value.max_price) params.append('max_price', filters.value.max_price);
    if (filters.value.include_inactive) params.append('include_inactive', 'true');

    const res = await fetch(`/api/v1/rooms?${params.toString()}`);
    const data = await res.json();
    rooms.value = data.rooms || [];
    if (data.summary) {
      summary.value = data.summary;
    }
    currentPage.value = 1;
  } catch (err) {
    console.error('Failed to load rooms:', err);
    showToast('Failed to load hotel rooms inventory.', 'error');
  } finally {
    loading.value = false;
  }
}

function resetFilters() {
  filters.value = {
    search: '',
    category: 'all',
    status: 'all',
    min_price: null,
    max_price: null,
    include_inactive: false,
  };
  fetchRooms();
}

function onCategorySelect() {
  const selected = categories.value.find(c => c.id === form.value.category_id);
  if (selected && !isEditing.value) {
    form.value.price_etb = selected.base_price_etb;
    form.value.price_usd = selected.base_price_usd;
    form.value.capacity = selected.max_capacity;
  }
}

function autoCalculateUsdPrice() {
  if (form.value.price_etb && !form.value.price_usd) {
    form.value.price_usd = Math.round(form.value.price_etb / 64);
  }
}

function resetForm() {
  isEditing.value = false;
  editingRoomId.value = null;
  form.value = {
    room_number: '',
    title: '',
    category_id: categories.value.length ? categories.value[0].id : '',
    price_etb: 2500,
    price_usd: 39,
    capacity: 2,
    floor: 1,
    status: 'available',
    description: '',
    amenities: ['amen-wifi', 'amen-breakfast'],
    image_url: 'https://images.unsplash.com/photo-1591088398332-8a7791972843?w=800&fit=crop'
  };
}

function editRoom(room) {
  isEditing.value = true;
  editingRoomId.value = room.id;
  form.value = {
    room_number: room.room_number,
    title: room.title,
    category_id: room.category_id,
    price_etb: room.price_etb,
    price_usd: room.price_usd,
    capacity: room.capacity,
    floor: room.floor,
    status: room.status,
    description: room.description || '',
    amenities: room.amenities?.map(a => a.id) || ['amen-wifi'],
    image_url: room.image_url
  };
  // Scroll to form on mobile
  if (window.innerWidth < 1024) {
    window.scrollTo({ top: 300, behavior: 'smooth' });
  }
}

async function saveRoom() {
  if (!form.value.room_number) {
    showToast('Room number is required.', 'error');
    return;
  }
  if (form.value.price_etb <= 0) {
    showToast('Room price must be greater than zero.', 'error');
    return;
  }
  if (form.value.capacity <= 0) {
    showToast('Room capacity must be a positive number.', 'error');
    return;
  }

  savingRoom.value = true;
  try {
    const endpoint = isEditing.value ? `/api/v1/rooms/${editingRoomId.value}` : '/api/v1/rooms';
    const method = isEditing.value ? 'PUT' : 'POST';

    const res = await fetch(endpoint, {
      method,
      headers: {
        'Content-Type': 'application/json',
        ...getAuthHeaders()
      },
      body: JSON.stringify(form.value)
    });

    const data = await res.json();
    if (!res.ok) {
      const errMsg = data.error || (data.errors ? Object.values(data.errors).flat().join(', ') : 'Failed to save room.');
      throw new Error(errMsg);
    }

    showToast(data.message || `✓ Room ${form.value.room_number} saved successfully!`, 'success');
    resetForm();
    fetchRooms();
  } catch (err) {
    showToast(err.message, 'error');
  } finally {
    savingRoom.value = false;
  }
}

function confirmDeleteRoom(room) {
  if (room.status === 'occupied') {
    showToast(`Cannot delete Room ${room.room_number}: guest is currently in-house. Check out guest first.`, 'error');
    return;
  }
  deleteModal.value = {
    show: true,
    room,
    deleting: false
  };
}

async function executeDeleteRoom() {
  const room = deleteModal.value.room;
  if (!room) return;

  deleteModal.value.deleting = true;
  try {
    const res = await fetch(`/api/v1/rooms/${room.id}`, {
      method: 'DELETE',
      headers: getAuthHeaders()
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.error || 'Failed to delete room');

    showToast(data.message, 'success');
    deleteModal.value.show = false;
    fetchRooms();
  } catch (err) {
    showToast(err.message, 'error');
  } finally {
    deleteModal.value.deleting = false;
  }
}

async function toggleActive(room) {
  try {
    const res = await fetch(`/api/v1/rooms/${room.id}/toggle-active`, {
      method: 'PATCH',
      headers: getAuthHeaders()
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.error || 'Failed to toggle room status');

    showToast(data.message, 'success');
    fetchRooms();
  } catch (err) {
    showToast(err.message, 'error');
  }
}

async function openDetailsModal(room) {
  try {
    const res = await fetch(`/api/v1/rooms/${room.id}`);
    const data = await res.json();
    detailsModal.value = {
      show: true,
      room: data.room || room
    };
  } catch (err) {
    detailsModal.value = { show: true, room };
  }
}

async function onFileSelected(e) {
  const file = e.target.files?.[0];
  if (!file) return;

  isUploading.value = true;
  const formData = new FormData();
  formData.append('image', file);

  try {
    const res = await fetch('/api/v1/upload/room-image', {
      method: 'POST',
      headers: getAuthHeaders(),
      body: formData
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.error || 'Upload failed');

    form.value.image_url = data.image_url;
    showToast('Photo uploaded successfully.', 'success');
  } catch (err) {
    showToast('Upload error: ' + err.message, 'error');
  } finally {
    isUploading.value = false;
  }
}

// Category / Room Type CRUD functions
function openCategoryModal(cat = null) {
  if (cat) {
    categoryModal.value = {
      show: true,
      isEditing: true,
      editingId: cat.id,
      saving: false,
      form: {
        name: cat.name,
        base_price_etb: cat.base_price_etb,
        base_price_usd: cat.base_price_usd,
        max_capacity: cat.max_capacity,
        badge_color: cat.badge_color || '#0B4D34',
        description: cat.description || ''
      }
    };
  } else {
    categoryModal.value = {
      show: true,
      isEditing: false,
      editingId: null,
      saving: false,
      form: {
        name: '',
        base_price_etb: 1500,
        base_price_usd: 210,
        max_capacity: 2,
        badge_color: '#0B4D34',
        description: ''
      }
    };
  }
}

async function saveCategory() {
  categoryModal.value.saving = true;
  try {
    const endpoint = categoryModal.value.isEditing 
      ? `/api/v1/categories/${categoryModal.value.editingId}` 
      : '/api/v1/categories';
    const method = categoryModal.value.isEditing ? 'PUT' : 'POST';

    const res = await fetch(endpoint, {
      method,
      headers: {
        'Content-Type': 'application/json',
        ...getAuthHeaders()
      },
      body: JSON.stringify(categoryModal.value.form)
    });

    const data = await res.json();
    if (!res.ok) {
      const errMsg = data.error || (data.errors ? Object.values(data.errors).flat().join(', ') : 'Failed to save room type.');
      throw new Error(errMsg);
    }

    showToast(data.message, 'success');
    categoryModal.value.show = false;
    await fetchCategories();
  } catch (err) {
    showToast(err.message, 'error');
  } finally {
    categoryModal.value.saving = false;
  }
}

async function deleteCategory(cat) {
  if (cat.active_rooms_count > 0 || cat.total_rooms_count > 0) {
    showToast(`Cannot delete '${cat.name}': ${cat.total_rooms_count} rooms are assigned to this type. Reassign rooms first.`, 'error');
    return;
  }

  if (!confirm(`Are you sure you want to delete room type '${cat.name}'?`)) return;

  try {
    const res = await fetch(`/api/v1/categories/${cat.id}`, {
      method: 'DELETE',
      headers: getAuthHeaders()
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.error || 'Failed to delete category');

    showToast(data.message, 'success');
    await fetchCategories();
  } catch (err) {
    showToast(err.message, 'error');
  }
}

// Date Availability Inspector
async function fetchDateAvailability() {
  try {
    const res = await fetch(`/api/v1/rooms?check_in=${availDates.value.checkIn}&check_out=${availDates.value.checkOut}`);
    const data = await res.json();
    dateAvailabilityRooms.value = data.rooms || [];
  } catch (err) {
    console.error('Failed to load date availability:', err);
  }
}

onMounted(async () => {
  await Promise.all([
    fetchCategories(),
    fetchAmenities(),
    fetchRooms()
  ]);
  fetchDateAvailability();
});
</script>
