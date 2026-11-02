import { createRouter, createWebHistory } from 'vue-router';

// Guest Subsystem Views
import LandingPageView from '../views/LandingPageView.vue';
import RoomCatalogView from '../views/RoomCatalogView.vue';
import RoomDetailsView from '../views/RoomDetailsView.vue';
import MyBookingsView from '../views/MyBookingsView.vue';

// Admin PMS Subsystem Views
import AdminDashboardView from '../views/AdminDashboardView.vue';
import ManageRoomsView from '../views/ManageRoomsView.vue';
import BookingDeskView from '../views/BookingDeskView.vue';
import CheckInDeskView from '../views/CheckInDeskView.vue';
import CheckOutDeskView from '../views/CheckOutDeskView.vue';
import PaymentsView from '../views/PaymentsView.vue';
import ReportsView from '../views/ReportsView.vue';
import HousekeepingView from '../views/HousekeepingView.vue';
import HousekeeperAppView from '../views/HousekeeperAppView.vue';
import FloorMapView from '../views/FloorMapView.vue';

const routes = [
  // Guest Portal Routes
  {
    path: '/',
    name: 'Home',
    component: LandingPageView,
    meta: { section: 'guest', title: 'Luxury Ethiopian Resort & Suites' }
  },
  {
    path: '/rooms',
    name: 'RoomCatalog',
    component: RoomCatalogView,
    meta: { section: 'guest', title: 'Room Discovery & Booking' }
  },
  {
    path: '/room/:id',
    name: 'RoomDetails',
    component: RoomDetailsView,
    props: true,
    meta: { section: 'guest', title: 'Room Details & Showcase' }
  },
  {
    path: '/my-bookings',
    name: 'MyBookings',
    component: MyBookingsView,
    meta: { section: 'guest', title: 'My Bookings & Digital Receipt' }
  },

  // Admin & Staff PMS Routes
  {
    path: '/admin',
    redirect: '/admin/dashboard'
  },
  {
    path: '/admin/dashboard',
    name: 'AdminDashboard',
    component: AdminDashboardView,
    meta: { section: 'admin', title: 'Hotel Admin Management Dashboard', adminView: 'dashboard' }
  },
  {
    path: '/admin/floor-map',
    name: 'AdminFloorMap',
    component: FloorMapView,
    meta: { section: 'admin', title: 'Interactive Hotel Floor Map & Matrix', adminView: 'floor-map' }
  },
  {
    path: '/admin/rooms',
    name: 'AdminRooms',
    component: ManageRoomsView,
    meta: { section: 'admin', title: 'Manage Hotel Rooms (Inventory)', adminView: 'manage-rooms' }
  },
  {
    path: '/admin/bookings',
    name: 'AdminBookings',
    component: BookingDeskView,
    meta: { section: 'admin', title: 'Bookings Operations Desk', adminView: 'bookings' }
  },
  {
    path: '/admin/check-in',
    name: 'AdminCheckIn',
    component: CheckInDeskView,
    meta: { section: 'admin', title: 'Front Desk Check-In Desk', adminView: 'check-in' }
  },
  {
    path: '/admin/check-out',
    name: 'AdminCheckOut',
    component: CheckOutDeskView,
    meta: { section: 'admin', title: 'Front Desk Check-Out Desk', adminView: 'check-out' }
  },
  {
    path: '/admin/housekeeping',
    name: 'AdminHousekeeping',
    component: HousekeepingView,
    meta: { section: 'admin', title: 'Housekeeping & Cleanliness Management', adminView: 'housekeeping' }
  },
  {
    path: '/staff/housekeeping',
    name: 'StaffHousekeepingApp',
    component: HousekeeperAppView,
    meta: { section: 'staff', title: 'Mobile Housekeeper Turnover App', adminView: 'housekeeping-app' }
  },
  {
    path: '/admin/payments',
    name: 'AdminPayments',
    component: PaymentsView,
    meta: { section: 'admin', title: 'Payments & Transactions Management', adminView: 'payments' }
  },
  {
    path: '/admin/reports',
    name: 'AdminReports',
    component: ReportsView,
    meta: { section: 'admin', title: 'Financial & Occupancy Analytics', adminView: 'reports' }
  },

  // Fallback Wildcard
  {
    path: '/:pathMatch(.*)*',
    redirect: '/'
  }
];

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior(to, from, savedPosition) {
    if (savedPosition) {
      return savedPosition;
    }
    return { top: 0 };
  }
});

// Navigation Guards: update document title and enforce Role-Based Access Control
router.beforeEach((to, from, next) => {
  if (to.meta && to.meta.title) {
    document.title = `${to.meta.title} | Luxury Ethiopian Hotel PMS`;
  }

  // RBAC Permission Check for Admin section
  if (to.meta && to.meta.section === 'admin') {
    const rawUser = localStorage.getItem('hotel_user');
    let user = null;
    if (rawUser) {
      try { user = JSON.parse(rawUser); } catch (e) {}
    }

    // If user has specific role metadata requirements
    if (to.meta.requiredRole && user && user.role !== to.meta.requiredRole && user.role !== 'admin' && user.role !== 'super_admin') {
      alert(`Access Restricted: This view requires '${to.meta.requiredRole}' role credentials.`);
      return next('/admin/dashboard');
    }
  }

  next();
});

export default router;
