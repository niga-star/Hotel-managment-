<template>
  <div class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <!-- Card Container matching Screen 7 -->
    <div class="bg-white rounded-3xl overflow-hidden shadow-2xl max-w-3xl w-full border border-slate-100 grid grid-cols-1 md:grid-cols-2 relative">
      <!-- Close Button -->
      <button 
        @click="$emit('close')"
        class="absolute top-4 right-4 z-10 w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-600 flex items-center justify-center text-sm transition">
        ✕
      </button>

      <!-- Left Column: Luxury Resort Image & Welcome Tagline -->
      <div class="relative h-48 md:h-full min-h-[300px] overflow-hidden">
        <img 
          src="https://images.unsplash.com/photo-1566073771259-6a8506099945?w=800&auto=format&fit=crop&q=80" 
          class="w-full h-full object-cover" 
          alt="Ethiopian Boutique Resort" />
        <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent flex items-end p-6">
          <h3 class="text-white text-xl sm:text-2xl font-bold font-serif leading-tight">
            Welcome to Ethiopian Boutique Stays
          </h3>
        </div>
      </div>

      <!-- Right Column: Sign In / Create Account Form -->
      <div class="p-6 sm:p-8 flex flex-col justify-center space-y-4">
        <div>
          <h2 class="text-2xl font-bold text-slate-900">Sign In / Create Account</h2>
          <p class="text-xs text-slate-500 mt-1">Access your account or register to get started.</p>
        </div>

        <form @submit.prevent="handleAuth" class="space-y-3.5">
          <!-- Full Name -->
          <div>
            <div class="relative">
              <span class="absolute left-3.5 top-3 text-sm text-slate-400">👤</span>
              <input 
                v-model="form.full_name" 
                type="text" 
                placeholder="Full Name" 
                class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-[#0B4D34]" />
            </div>
          </div>

          <!-- Phone Number with Ethiopian flag & +251 prefix -->
          <div>
            <div class="relative flex items-center">
              <div class="absolute left-3 flex items-center gap-1 text-xs text-slate-700 font-semibold border-r border-slate-300 pr-2">
                <span>🇪🇹</span>
                <span>+251</span>
              </div>
              <input 
                v-model="form.phone" 
                type="tel" 
                placeholder="911 234567" 
                class="w-full pl-20 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-[#0B4D34]" />
            </div>
          </div>

          <!-- Email Address -->
          <div>
            <div class="relative">
              <span class="absolute left-3.5 top-3 text-sm text-slate-400">✉️</span>
              <input 
                v-model="form.email" 
                type="email" 
                required
                placeholder="Email Address" 
                class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-[#0B4D34]" />
            </div>
          </div>

          <!-- Password with toggle -->
          <div>
            <div class="relative">
              <span class="absolute left-3.5 top-3 text-sm text-slate-400">🔒</span>
              <input 
                v-model="form.password" 
                :type="showPassword ? 'text' : 'password'" 
                required
                placeholder="Password" 
                class="w-full pl-10 pr-10 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-[#0B4D34]" />
              <button 
                type="button" 
                @click="showPassword = !showPassword"
                class="absolute right-3 top-2.5 text-xs text-slate-400 hover:text-slate-600">
                {{ showPassword ? '🙈' : '👁️' }}
              </button>
            </div>
          </div>

          <!-- Login / Register Button -->
          <button 
            type="submit"
            :disabled="loading"
            class="w-full py-3 bg-gradient-to-r from-[#0B4D34] to-[#107550] hover:brightness-110 text-white font-bold text-xs rounded-xl shadow-md transition disabled:opacity-50">
            {{ loading ? 'Authenticating with Database...' : 'Login / Register' }}
          </button>
        </form>

        <!-- Quick Demo Switcher -->
        <div class="pt-2 border-t border-slate-100 text-center">
          <span class="text-[10px] text-slate-400 block mb-1">Quick Demo Accounts:</span>
          <div class="flex items-center justify-center gap-2 text-[11px]">
            <button @click="fillDemo('admin')" class="text-[#0B4D34] font-semibold hover:underline">Admin</button>
            <span class="text-slate-300">•</span>
            <button @click="fillDemo('reception')" class="text-[#0B4D34] font-semibold hover:underline">Receptionist</button>
            <span class="text-slate-300">•</span>
            <button @click="fillDemo('guest')" class="text-[#0B4D34] font-semibold hover:underline">Guest</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';

const emit = defineEmits(['close', 'auth-success']);

const showPassword = ref(false);
const loading = ref(false);

const form = ref({
  full_name: 'Analisa Ethiopian',
  phone: '916 789012',
  email: 'aya@ethin.com',
  password: 'hotel1234'
});

function fillDemo(type) {
  if (type === 'admin') {
    form.value = { full_name: 'Solomon Hailemariam', phone: '911 000111', email: 'admin@ethiopianhotel.com', password: 'hotel1234' };
  } else if (type === 'reception') {
    form.value = { full_name: 'Makeda Assefa', phone: '911 222333', email: 'reception@ethiopianhotel.com', password: 'hotel1234' };
  } else {
    form.value = { full_name: 'Analisa Ethiopian', phone: '916 789012', email: 'aya@ethin.com', password: 'hotel1234' };
  }
}

async function handleAuth() {
  loading.value = true;
  try {
    // Attempt login first
    let res = await fetch('/api/v1/auth/login', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email: form.value.email, password: form.value.password })
    });

    let data = await res.json();

    // If account doesn't exist, register
    if (!res.ok) {
      res = await fetch('/api/v1/auth/register', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(form.value)
      });
      data = await res.json();
    }

    if (!res.ok) throw new Error(data.error || 'Authentication failed');

    localStorage.setItem('auth_token', data.token);
    localStorage.setItem('auth_user', JSON.stringify(data.user));

    emit('auth-success', data.user);
    emit('close');
  } catch (err) {
    alert(err.message);
  } finally {
    loading.value = false;
  }
}
</script>
