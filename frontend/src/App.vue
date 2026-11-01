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
          <router-link 
            to="/"
            :class="route.path === '/' ? 'text-[#0B4D34] font-bold border-b-2 border-[#0B4D34]' : 'text-slate-600 hover:text-slate-900'"
            class="py-2 transition">
            🏡 {{ t('resortOverview') }}
          </router-link>
          <router-link 
            to="/rooms"
            :class="isGuestRoomsActive ? 'text-[#0B4D34] font-bold border-b-2 border-[#0B4D34]' : 'text-slate-600 hover:text-slate-900'"
            class="py-2 transition">
            🛎️ {{ t('roomDiscovery') }}
          </router-link>
          <router-link 
            to="/my-bookings"
            :class="route.path === '/my-bookings' ? 'text-[#0B4D34] font-bold border-b-2 border-[#0B4D34]' : 'text-slate-600 hover:text-slate-900'"
            class="py-2 transition">
            🎫 {{ t('myBookings') }}
          </router-link>
        </div>
        <div class="hidden sm:flex items-center space-x-2 text-emerald-800 font-medium">
          <span>✨</span>
          <span>{{ t('luxuryBannerNotice') }}</span>
        </div>
      </div>
    </div>

    <!-- Main Container -->
    <div class="flex-1 flex">
      <!-- Admin Sidebar (When in Admin Section) -->
      <AdminSidebar 
        v-if="currentSection === 'admin'"
        :currentView="currentAdminView"
        @select-view="onSelectAdminView"
      />

      <!-- Content Area with Router View -->
      <main class="flex-1 p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto w-full">
        <router-view 
          @booking-created="onBookingCreated"
          @open-auth="authModal = 'login'"
        />
      </main>
    </div>

    <!-- Screen 7: Separate Auth Modals (Login & Register) -->
    <!-- 7A: Login Modal -->
    <LoginView 
      v-if="authModal === 'login'"
      :initialSuccess="authSuccessMessage"
      @close="authModal = null; authSuccessMessage = ''"
      @login-success="onLoginSuccess"
      @switch-to-register="authModal = 'register'; authSuccessMessage = ''"
    />

    <!-- 7B: Registration Modal -->
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
import { useRoute, useRouter } from 'vue-router';

import TibebBanner from './components/TibebBanner.vue';
import AdminSidebar from './components/AdminSidebar.vue';

// Auth Modals
import LoginView from './views/LoginView.vue';
import RegisterView from './views/RegisterView.vue';
import { useI18n } from './i18n';

const route = useRoute();
const router = useRouter();
const { t, lang } = useI18n();

const authModal = ref(null); // 'login' | 'register' | null
const authSuccessMessage = ref('');
const currentUser = ref(null);

const currentSection = computed(() => {
  return route.path.startsWith('/admin') ? 'admin' : 'guest';
});

const isGuestRoomsActive = computed(() => {
  return route.path === '/rooms' || route.path.startsWith('/room/');
});

const currentAdminView = computed(() => {
  if (route.path.includes('/rooms')) return 'manage-rooms';
  if (route.path.includes('/bookings')) return 'bookings';
  if (route.path.includes('/check-in')) return 'check-in';
  if (route.path.includes('/check-out')) return 'check-out';
  if (route.path.includes('/housekeeping')) return 'housekeeping';
  if (route.path.includes('/payments')) return 'payments';
  if (route.path.includes('/reports')) return 'reports';
  return 'dashboard';
});

const headerTitle = computed(() => {
  if (currentSection.value === 'admin') {
    return 'Hotel Admin Management Dashboard';
  }
  return 'Ethiopian Boutique Stays & Resorts';
});

onMounted(() => {
  const savedToken = localStorage.getItem('hotel_auth_token');
  const savedUser = localStorage.getItem('hotel_user');
  if (savedToken && savedUser) {
    try {
      const parsed = JSON.parse(savedUser);
      currentUser.value = parsed;
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

function switchSection(section) {
  if (section === 'admin') {
    if (!currentUser.value || !['super_admin', 'admin', 'receptionist'].includes(currentUser.value.role)) {
      authModal.value = 'login';
      authSuccessMessage.value = 'Administrator or Staff sign-in required to access Hotel PMS.';
      return;
    }
    router.push('/admin/dashboard');
  } else {
    router.push('/rooms');
  }
}

function onSelectAdminView(viewId) {
  const map = {
    'dashboard': '/admin/dashboard',
    'manage-rooms': '/admin/rooms',
    'bookings': '/admin/bookings',
    'check-in': '/admin/check-in',
    'check-out': '/admin/check-out',
    'housekeeping': '/admin/housekeeping',
    'payments': '/admin/payments',
    'reports': '/admin/reports'
  };
  if (map[viewId]) {
    router.push(map[viewId]);
  }
}

function handleLogout() {
  localStorage.removeItem('hotel_auth_token');
  localStorage.removeItem('hotel_user');
  currentUser.value = null;
  router.push('/rooms');
}

function onBookingCreated(booking) {
  router.push('/my-bookings');
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
    router.push('/admin/dashboard');
  } else {
    router.push('/rooms');
  }
}
</script>
