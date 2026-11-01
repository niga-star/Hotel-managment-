<template>
  <div class="space-y-6">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Left 2 Cols: Manage Hotel Rooms Table (Screen 6) -->
      <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
          <div>
            <h2 class="text-xl font-bold text-slate-900">Manage Hotel Rooms</h2>
            <p class="text-xs text-slate-400">Total Rooms in Database: {{ rooms.length }}</p>
          </div>
          <button 
            @click="resetForm"
            class="px-4 py-2 bg-[#0B4D34] hover:bg-[#064E3B] text-white text-xs font-semibold rounded-full shadow-sm flex items-center gap-1.5 transition cursor-pointer">
            <span>+</span> Add New Room
          </button>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse text-sm">
            <thead>
              <tr class="bg-slate-50 text-slate-600 text-xs font-semibold uppercase tracking-wider border-b border-slate-100">
                <th class="py-3 px-5">Photo</th>
                <th class="py-3 px-5">Room #</th>
                <th class="py-3 px-5">Category</th>
                <th class="py-3 px-5">Price (ETB)</th>
                <th class="py-3 px-5">Status</th>
                <th class="py-3 px-5 text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-slate-700">
              <tr v-if="loading" class="text-center">
                <td colspan="6" class="py-8 text-slate-400 text-xs">Loading rooms from database...</td>
              </tr>
              <tr v-else-if="rooms.length === 0" class="text-center">
                <td colspan="6" class="py-8 text-slate-400 text-xs">No rooms in inventory. Click "Add New Room" to create one.</td>
              </tr>
              <tr 
                v-else
                v-for="room in rooms" 
                :key="room.id"
                class="hover:bg-slate-50/50 transition">
                <!-- Photo -->
                <td class="py-3 px-5">
                  <img 
                    :src="room.image_url || 'https://images.unsplash.com/photo-1591088398332-8a7791972843?w=800&fit=crop'" 
                    class="w-14 h-10 rounded-lg object-cover border border-slate-200" 
                    alt="Room" />
                </td>
                <!-- Room -->
                <td class="py-3 px-5 font-bold text-slate-900 text-xs">
                  Room {{ room.room_number }}
                  <span class="block text-[10px] text-slate-400 font-normal truncate max-w-[140px]">{{ room.title }}</span>
                </td>
                <!-- Category Pill -->
                <td class="py-3 px-5">
                  <span 
                    :class="getCategoryBadgeClass(room.category_slug)"
                    class="text-[11px] font-bold px-3 py-1 rounded-full">
                    {{ room.category_name }}
                  </span>
                </td>
                <!-- Price -->
                <td class="py-3 px-5 font-semibold text-slate-800 text-xs">
                  {{ room.price_etb?.toLocaleString() }} ETB
                </td>
                <!-- Status Badge -->
                <td class="py-3 px-5">
                  <span 
                    :class="getStatusBadgeClass(room.status)"
                    class="text-[11px] font-bold px-3 py-1 rounded-full capitalize">
                    {{ room.status }}
                  </span>
                </td>
                <!-- Actions -->
                <td class="py-3 px-5 text-right space-x-2">
                  <button 
                    @click="editRoom(room)"
                    class="p-1.5 text-emerald-700 hover:bg-emerald-50 rounded-lg transition" 
                    title="Edit Room">
                    ✏️
                  </button>
                  <button 
                    @click="deleteRoom(room)"
                    class="p-1.5 text-rose-500 hover:bg-rose-50 rounded-lg transition" 
                    title="Delete Room">
                    🗑️
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Right 1 Col: Add / Edit Room Form Panel (Screen 6) -->
      <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 space-y-4 h-fit sticky top-6">
        <h3 class="text-lg font-bold text-slate-900 border-b border-slate-100 pb-3">
          {{ isEditing ? 'Edit Room #' + form.room_number : 'Add New Room' }}
        </h3>

        <form @submit.prevent="saveRoom" class="space-y-3.5">
          <!-- Room Number -->
          <div>
            <label class="text-xs font-semibold text-slate-700 block mb-1">Room Number <span class="text-rose-500">*</span></label>
            <input 
              v-model="form.room_number" 
              type="text" 
              placeholder="e.g., 305" 
              required
              :disabled="isEditing"
              class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-[#0B4D34]" />
          </div>

          <!-- Room Title -->
          <div>
            <label class="text-xs font-semibold text-slate-700 block mb-1">Room Title <span class="text-rose-500">*</span></label>
            <input 
              v-model="form.title" 
              type="text" 
              placeholder="e.g., Royal Simien Suite" 
              required
              class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-[#0B4D34]" />
          </div>

          <!-- Category Dropdown -->
          <div>
            <label class="text-xs font-semibold text-slate-700 block mb-1">Category <span class="text-rose-500">*</span></label>
            <select 
              v-model="form.category_id" 
              required
              class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-[#0B4D34]">
              <option value="" disabled>Select Category</option>
              <option value="cat-suite">Suite</option>
              <option value="cat-deluxe">Deluxe</option>
              <option value="cat-standard">Standard</option>
            </select>
          </div>

          <!-- Price (ETB/Night) -->
          <div>
            <label class="text-xs font-semibold text-slate-700 block mb-1">Price (ETB/Night) <span class="text-rose-500">*</span></label>
            <input 
              v-model.number="form.price_etb" 
              type="number" 
              min="500"
              placeholder="2500" 
              required
              class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-[#0B4D34]" />
          </div>

          <!-- Capacity & Floor -->
          <div class="grid grid-cols-2 gap-2">
            <div>
              <label class="text-xs font-semibold text-slate-700 block mb-1">Capacity</label>
              <input 
                v-model.number="form.capacity" 
                type="number" 
                min="1"
                max="8"
                required
                class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-[#0B4D34]" />
            </div>
            <div>
              <label class="text-xs font-semibold text-slate-700 block mb-1">Floor</label>
              <input 
                v-model.number="form.floor" 
                type="number" 
                min="1"
                max="20"
                required
                class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-[#0B4D34]" />
            </div>
          </div>

          <!-- Status Dropdown (when editing) -->
          <div v-if="isEditing">
            <label class="text-xs font-semibold text-slate-700 block mb-1">Operational Status</label>
            <select 
              v-model="form.status" 
              class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-[#0B4D34]">
              <option value="available">Available</option>
              <option value="occupied">Occupied</option>
              <option value="maintenance">Maintenance</option>
            </select>
          </div>

          <!-- Image Upload Box -->
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
                <img :src="form.image_url" class="h-20 mx-auto rounded-lg object-cover" alt="Preview" />
                <span class="text-[10px] text-emerald-600 font-bold block">✓ Photo attached</span>
              </div>
              <div v-else class="text-xs text-slate-500">
                <span>📷 Click to upload room photo</span>
              </div>
            </div>
          </div>

          <!-- Submit & Cancel Buttons -->
          <div class="flex gap-2 pt-2">
            <button 
              type="submit"
              :disabled="savingRoom || isUploading"
              class="flex-1 py-3 bg-[#0B4D34] hover:bg-[#064E3B] text-white text-xs font-bold rounded-xl shadow-md transition disabled:opacity-50 cursor-pointer">
              {{ savingRoom ? 'Saving to Database...' : (isEditing ? 'Update Room' : 'Create Room') }}
            </button>
            <button 
              v-if="isEditing"
              type="button"
              @click="resetForm"
              class="px-4 py-3 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl transition">
              Cancel
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';

const rooms = ref([]);
const loading = ref(false);
const isEditing = ref(false);
const savingRoom = ref(false);
const isUploading = ref(false);
const editingRoomId = ref(null);

const form = ref({
  room_number: '',
  title: '',
  category_id: 'cat-suite',
  price_etb: 3500,
  capacity: 2,
  floor: 1,
  status: 'available',
  amenities: ['amen-wifi', 'amen-breakfast'],
  image_url: 'https://images.unsplash.com/photo-1591088398332-8a7791972843?w=800&fit=crop'
});

function getAuthHeaders() {
  const token = localStorage.getItem('hotel_auth_token');
  return token ? { 'Authorization': `Bearer ${token}` } : {};
}

function getCategoryBadgeClass(slug) {
  if (slug === 'suite') return 'bg-amber-100 text-amber-800';
  if (slug === 'deluxe') return 'bg-emerald-100 text-emerald-800';
  return 'bg-blue-100 text-blue-800';
}

function getStatusBadgeClass(status) {
  if (status === 'available') return 'bg-emerald-100 text-emerald-800';
  if (status === 'occupied') return 'bg-amber-100 text-amber-800';
  return 'bg-slate-100 text-slate-700';
}

async function fetchRooms() {
  loading.value = true;
  try {
    const res = await fetch('/api/v1/rooms');
    const data = await res.json();
    rooms.value = data.rooms || [];
  } catch (err) {
    console.error('Failed to load rooms:', err);
  } finally {
    loading.value = false;
  }
}

function resetForm() {
  isEditing.value = false;
  editingRoomId.value = null;
  form.value = {
    room_number: '',
    title: '',
    category_id: 'cat-suite',
    price_etb: 3500,
    capacity: 2,
    floor: 1,
    status: 'available',
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
    capacity: room.capacity,
    floor: room.floor,
    status: room.status,
    amenities: room.amenities?.map(a => a.id) || ['amen-wifi'],
    image_url: room.image_url
  };
}

async function deleteRoom(room) {
  if (!confirm(`Are you sure you want to delete Room ${room.room_number}?`)) return;

  try {
    const res = await fetch(`/api/v1/rooms/${room.id}`, {
      method: 'DELETE',
      headers: getAuthHeaders()
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.error || 'Failed to delete room');

    alert(`✓ Room ${room.room_number} deleted successfully.`);
    fetchRooms();
  } catch (err) {
    alert(err.message);
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
  } catch (err) {
    alert('Upload error: ' + err.message);
  } finally {
    isUploading.value = false;
  }
}

async function saveRoom() {
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
    if (!res.ok) throw new Error(data.error || 'Failed to save room to database');

    alert(`✓ Room ${form.value.room_number} saved successfully in MySQL!`);
    resetForm();
    fetchRooms();
  } catch (err) {
    alert(err.message);
  } finally {
    savingRoom.value = false;
  }
}

onMounted(() => {
  fetchRooms();
});
</script>
