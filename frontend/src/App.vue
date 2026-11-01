<template>
  <div class="min-h-screen flex flex-col bg-[#F4F6F8]">
    <!-- Traditional Ethiopian Heritage Tibeb Header Banner -->
    <TibebBanner 
      :title="headerTitle"
      :currentSection="currentSection"
      :currentUser="currentUser"
      @switch-section="switchSection"
      @open-auth="authModal = 'login'"
      @logout="handleLogout"
    />

    <!-- Secondary Guest Sub-Nav when in Guest Portal -->
    <div v-if="currentSection === 'guest'" class="bg-white border-b border-slate-200/80 shadow-xs">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between py-2 text-xs">
        <div class="flex items-center space-x-6">
          <button 
            @click="guestView = 'catalog'"
            :class="guestView === 'catalog' ? 'text-[#0B4D34] font-bold border-b-2 border-[#0B4D34]' : 'text-slate-600 hover:text-slate-900'"
            class="py-2 transition">
            🛎️ Room Discovery & Booking
          </button>
          <button 
            @click="guestView = 'my-bookings'"
            :class="guestView === 'my-bookings' ? 'text-[#0B4D34] font-bold border-b-2 border-[#0B4D34]' : 'text-slate-600 hover:text-slate-900'"
            class="py-2 transition">
            🎫 My Bookings & Receipts
          </button>
        </div>
        <div class="hidden sm:flex items-center space-x-2 text-emerald-800 font-medium">
          <span>✨</span>
          <span>Boutique Ethiopian Suites & Luxury Resort</span>
        </div>
      </div>
    </div>

    <!-- Main Container -->
    <div class="flex-1 flex">
      <!-- Admin Sidebar (When in Admin Section) -->
      <AdminSidebar 
        v-if="currentSection === 'admin'"
        :currentView="adminView"
        @select-view="view => adminView = view"
      />

      <!-- Content Area -->
      <main class="flex-1 p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto w-full">
        <!-- Guest Section -->
        <template v-if="currentSection === 'guest'">
          <!-- Screen 4: Room Catalog & Calendar Drawer -->
          <RoomCatalogView 
            v-if="guestView === 'catalog'"
            @view-details="openRoomDetails"
            @booking-created="onBookingCreated"
          />

          <!-- Screen 5: Room Details & Reviews -->
          <RoomDetailsView 
            v-else-if="guestView === 'details'"
            :roomId="selectedRoomId"
            @back="guestView = 'catalog'"
            @booking-created="onBookingCreated"
          />

          <!-- Screen 3: My Bookings & Digital Receipt -->
          <MyBookingsView 
            v-else-if="guestView === 'my-bookings'"
          />
        </template>

        <!-- Admin PMS Section -->
        <template v-else>
          <!-- Screen 2: Dashboard Overview & Room Status Grid -->
          <AdminDashboardView 
            v-if="adminView === 'dashboard'"
            @go-manage-rooms="adminView = 'manage-rooms'"
          />

          <!-- Screen 6: Manage Hotel Rooms -->
          <ManageRoomsView 
            v-else-if="adminView === 'manage-rooms'"
          />

          <!-- Screen 8A: Booking Desk -->
          <BookingDeskView 
            v-else-if="adminView === 'bookings'"
          />

          <!-- Screen 8B: Check-In Desk -->
          <CheckInDeskView 
            v-else-if="adminView === 'check-in' || adminView === 'checkin-out'"
          />

          <!-- Screen 8C: Check-Out Desk -->
          <CheckOutDeskView 
            v-else-if="adminView === 'check-out'"
          />

          <!-- Screen 9: Payments & Transactions -->
          <PaymentsView 
            v-else-if="adminView === 'payments'"
          />

          <!-- Screen 1: Financial & Occupancy Reports -->
          <ReportsView 
            v-else-if="adminView === 'reports'"
          />
        </template>
      </main>
    </div>

    <!-- Screen 7: Separate Auth Modals (Login & Register) -->
    <!-- 7A: Login Modal (ONLY Email & Password, empty on load) -->
    <LoginView 
      v-if="authModal === 'login'"
      :initialSuccess="authSuccessMessage"
      @close="authModal = null; authSuccessMessage = ''"
      @login-success="onLoginSuccess"
      @switch-to-register="authModal = 'register'; authSuccessMessage = ''"
    />

    <!-- 7B: Registration Modal (EXACTLY 4 Fields, empty on load) -->
    <RegisterView 
      v-else-if="authModal === 'register'"
      @close="authModal = null"
      @switch-to-login="authModal = 'login'"
      @register-success="onRegisterSuccess"
    />

    <!-- Global Footer with Ethiopian Heritage Notice -->
    <footer class="bg-[#064E3B] text-emerald-200/80 border-t border-[#D4A329]/30 py-6 text-center text-xs">
      <div class="max-w-7xl mx-auto px-4 space-y-2">
        <p class="font-medium text-white flex items-center justify-center gap-2">
          <span>👑</span>
          <span>Luxury Ethiopian Hotel Management & Guest Booking System</span>
          <span class="text-[#D4A329]">•</span>
          <span>Database-Driven Property Management Engine</span>
        </p>
        <p class="text-[11px] text-emerald-300/70">
          Crafted according to ISO/IEC/IEEE 29148 standards with real MySQL transactions, Telebirr, CBE Birr & Chapa integrations.
        </p>
      </div>
    </footer>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';

import TibebBanner from './components/TibebBanner.vue';
import AdminSidebar from './components/AdminSidebar.vue';

// Screen Views
import ReportsView from './views/ReportsView.vue'; // Screen 1
import AdminDashboardView from './views/AdminDashboardView.vue'; // Screen 2
import MyBookingsView from './views/MyBookingsView.vue'; // Screen 3
import RoomCatalogView from './views/RoomCatalogView.vue'; // Screen 4
import RoomDetailsView from './views/RoomDetailsView.vue'; // Screen 5
import ManageRoomsView from './views/ManageRoomsView.vue'; // Screen 6
import BookingDeskView from './views/BookingDeskView.vue'; // Screen 8A
import CheckInDeskView from './views/CheckInDeskView.vue'; // Screen 8B
import CheckOutDeskView from './views/CheckOutDeskView.vue'; // Screen 8C
import PaymentsView from './views/PaymentsView.vue'; // Screen 9

// Upgraded Separate Auth Views
import LoginView from './views/LoginView.vue';
import RegisterView from './views/RegisterView.vue';

const currentSection = ref('guest'); // Default to guest portal
const adminView = ref('dashboard');
const guestView = ref('catalog');

const selectedRoomId = ref('rm-101');
const authModal = ref(null); // 'login' | 'register' | null
const authSuccessMessage = ref('');

// Dynamic User Session - No hardcoded credentials
const currentUser = ref(null);

onMounted(() => {
  const savedToken = localStorage.getItem('hotel_auth_token');
  const savedUser = localStorage.getItem('hotel_user');
  if (savedToken && savedUser) {
    try {
      const parsed = JSON.parse(savedUser);
      currentUser.value = parsed;
      if (['super_admin', 'admin', 'receptionist'].includes(parsed.role)) {
        currentSection.value = 'admin';
      }
    } catch (e) {
      localStorage.removeItem('hotel_user');
    }
  }

  // Check if URL has ?verified=true or ?reset_token
  const params = new URLSearchParams(window.location.search);
  if (params.get('verified') === 'true') {
    authModal.value = 'login';
    authSuccessMessage.value = 'Email verified successfully! You may now sign in.';
  }
});

const headerTitle = computed(() => {
  if (currentSection.value === 'admin') {
    return 'Hotel Admin Management Dashboard';
  }
  return 'Ethiopian Boutique Stays & Resorts';
});

function switchSection(section) {
  if (section === 'admin') {
    // Check RBAC permission for Admin PMS section
    if (!currentUser.value || !['super_admin', 'admin', 'receptionist'].includes(currentUser.value.role)) {
      authModal.value = 'login';
      authSuccessMessage.value = 'Administrator or Staff sign-in required to access Hotel PMS.';
      return;
    }
  }
  currentSection.value = section;
}

function handleLogout() {
  localStorage.removeItem('hotel_auth_token');
  localStorage.removeItem('hotel_user');
  currentUser.value = null;
  currentSection.value = 'guest';
}

function openRoomDetails(roomId) {
  selectedRoomId.value = roomId;
  guestView.value = 'details';
}

function onBookingCreated(booking) {
  guestView.value = 'my-bookings';
}

function onRegisterSuccess(payload) {
  authSuccessMessage.value = payload.message || 'Registration successful! Please verify your email.';
  authModal.value = 'login';
}

function onLoginSuccess(user) {
  currentUser.value = user;
  authModal.value = null;
  authSuccessMessage.value = '';
  if (['super_admin', 'admin', 'receptionist'].includes(user.role)) {
    currentSection.value = 'admin';
  } else {
    currentSection.value = 'guest';
  }
}
</script>
