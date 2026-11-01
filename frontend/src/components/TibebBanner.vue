<template>
  <header class="relative w-full bg-[#0B4D34] text-white shadow-md z-30 select-none">
    <!-- Traditional Ethiopian Tibeb Ornamental Golden Embroidery Pattern Strip -->
    <div class="h-10 w-full overflow-hidden border-b border-[#D4A329]/40 bg-[#064E3B] flex items-center relative">
      <svg class="w-full h-8 opacity-85" xmlns="http://www.w3.org/2000/svg">
        <defs>
          <pattern id="tibebPattern" width="60" height="32" patternUnits="userSpaceOnUse">
            <!-- Center Diamond Knot -->
            <polygon points="30,2 45,16 30,30 15,16" fill="none" stroke="#D4A329" stroke-width="1.8" />
            <polygon points="30,8 38,16 30,24 22,16" fill="#D4A329" fill-opacity="0.3" stroke="#D4A329" stroke-width="1.2" />
            <circle cx="30" cy="16" r="2.5" fill="#D4A329" />
            
            <!-- Side Chevrons & Traditional Ethiopian Cross Weave -->
            <polyline points="0,16 10,6 20,16 10,26 0,16" fill="none" stroke="#D4A329" stroke-width="1.4" opacity="0.75" />
            <polyline points="40,16 50,6 60,16 50,26 40,16" fill="none" stroke="#D4A329" stroke-width="1.4" opacity="0.75" />
            
            <!-- Top & Bottom Edge Geometrics -->
            <line x1="0" y1="2" x2="60" y2="2" stroke="#D4A329" stroke-width="1" stroke-dasharray="2 3" opacity="0.6" />
            <line x1="0" y1="30" x2="60" y2="30" stroke="#D4A329" stroke-width="1" stroke-dasharray="2 3" opacity="0.6" />
          </pattern>
        </defs>
        <rect width="100%" height="100%" fill="url(#tibebPattern)" />
      </svg>
    </div>

    <!-- Main Navigation Bar -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3 flex items-center justify-between">
      <div class="flex items-center space-x-3">
        <div class="w-9 h-9 rounded-lg bg-[#D4A329] text-[#064E3B] font-bold flex items-center justify-center font-serif text-lg shadow-sm border border-yellow-200">
          👑
        </div>
        <div>
          <h1 class="text-lg sm:text-xl font-bold tracking-tight text-white flex items-center gap-2">
            <span>{{ title }}</span>
          </h1>
          <p class="text-xs text-yellow-200/80 font-medium">Boutique Stays & Heritage Resort PMS</p>
        </div>
      </div>

      <!-- Quick Switcher Bar between Guest Portal & Admin PMS -->
      <div class="flex items-center space-x-2 sm:space-x-3 text-xs sm:text-sm">
        <div class="bg-black/25 backdrop-blur-sm p-1 rounded-full flex border border-white/10">
          <button 
            @click="$emit('switch-section', 'guest')"
            :class="currentSection === 'guest' ? 'bg-[#D4A329] text-slate-900 font-semibold shadow-sm' : 'text-slate-200 hover:text-white'"
            class="px-3 py-1 rounded-full transition-all">
            🏨 Guest Portal
          </button>
          <button 
            @click="$emit('switch-section', 'admin')"
            :class="currentSection === 'admin' ? 'bg-[#D4A329] text-slate-900 font-semibold shadow-sm' : 'text-slate-200 hover:text-white'"
            class="px-3 py-1 rounded-full transition-all">
            ⚙️ Admin / Staff PMS
          </button>
        </div>

        <!-- Auth Status Pill & Logout -->
        <div class="flex items-center space-x-1">
          <button 
            @click="$emit('open-auth')"
            class="flex items-center space-x-1.5 px-3 py-1.5 rounded-full bg-white/10 hover:bg-white/20 border border-white/15 transition text-xs font-medium text-emerald-100">
            <span>👤</span>
            <span class="hidden sm:inline">{{ currentUser?.full_name || 'Sign In' }}</span>
            <span v-if="currentUser?.role" class="ml-1 text-[10px] px-1.5 py-0.2 bg-[#D4A329] text-slate-900 rounded-md font-bold uppercase">
              {{ currentUser.role === 'super_admin' ? 'Admin' : currentUser.role }}
            </span>
          </button>
          <button
            v-if="currentUser"
            @click="$emit('logout')"
            title="Sign Out"
            class="p-1.5 rounded-full bg-white/10 hover:bg-rose-600/80 text-white text-xs transition">
            🚪
          </button>
        </div>
      </div>
    </div>
  </header>
</template>

<script setup>
defineProps({
  title: {
    type: String,
    default: 'Hotel Admin Management Dashboard'
  },
  currentSection: {
    type: String,
    default: 'admin'
  },
  currentUser: {
    type: Object,
    default: null
  }
});

defineEmits(['switch-section', 'open-auth', 'logout']);
</script>
