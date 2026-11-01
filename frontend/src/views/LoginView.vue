<template>
  <div class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <!-- Card Container -->
    <div class="bg-white rounded-3xl overflow-hidden shadow-2xl max-w-3xl w-full border border-slate-100 grid grid-cols-1 md:grid-cols-2 relative animate-fadeIn">
      <!-- Close Button -->
      <button 
        @click="$emit('close')"
        aria-label="Close"
        class="absolute top-4 right-4 z-10 w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-600 flex items-center justify-center text-sm transition">
        ✕
      </button>

      <!-- Left Column: Luxury Ethiopian Resort Visual -->
      <div class="relative h-48 md:h-full min-h-[340px] overflow-hidden bg-[#064E3B]">
        <img 
          src="https://images.unsplash.com/photo-1566073771259-6a8506099945?w=800&auto=format&fit=crop&q=80" 
          class="w-full h-full object-cover opacity-90" 
          alt="Ethiopian Boutique Resort" />
        <div class="absolute inset-0 bg-gradient-to-t from-[#064E3B] via-[#064E3B]/40 to-transparent flex flex-col justify-end p-6 text-white">
          <div class="w-10 h-10 rounded-xl bg-[#D4A329] text-[#064E3B] font-bold flex items-center justify-center font-serif text-xl mb-3 shadow-md border border-yellow-200">
            👑
          </div>
          <h3 class="text-xl sm:text-2xl font-bold font-serif leading-tight">
            Ethiopian Boutique Stays
          </h3>
          <p class="text-xs text-yellow-200/90 mt-1">
            Sign in to access your hotel reservation & management portal.
          </p>
        </div>
      </div>

      <!-- Right Column: Login Form (ONLY Email & Password) -->
      <div class="p-6 sm:p-8 flex flex-col justify-center space-y-4">
        <div>
          <div class="flex items-center justify-between">
            <h2 class="text-2xl font-bold text-slate-900 tracking-tight">Sign In</h2>
            <span class="text-[11px] px-2.5 py-1 bg-emerald-50 text-[#0B4D34] font-semibold rounded-full border border-emerald-200">
              Verified Portal
            </span>
          </div>
          <p class="text-xs text-slate-500 mt-1">
            Enter your email and password to access your account.
          </p>
        </div>

        <!-- Global Notifications: Success / Error / Unverified -->
        <div v-if="successMessage" class="p-3 bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs rounded-xl flex items-start gap-2">
          <span class="text-emerald-600 font-bold">✓</span>
          <span>{{ successMessage }}</span>
        </div>

        <div v-if="errorMessage" class="p-3 bg-rose-50 border border-rose-200 text-rose-700 text-xs rounded-xl flex items-start gap-2">
          <span class="text-rose-500 font-bold">⚠️</span>
          <span>{{ errorMessage }}</span>
        </div>

        <!-- Unverified Email Alert with Direct 6-Digit Code Entry & Resend Action -->
        <div v-if="unverifiedNotice" class="p-3.5 bg-amber-50 border border-amber-300 text-amber-900 text-xs rounded-2xl space-y-2.5">
          <div class="flex items-start gap-2">
            <span class="text-amber-600 font-bold text-sm">✉️</span>
            <div>
              <p class="font-bold">Email Verification Required</p>
              <p class="text-[11px] text-amber-800 mt-0.5">
                Enter the 6-digit verification code sent to <strong class="underline">{{ unverifiedEmail }}</strong>:
              </p>
            </div>
          </div>

          <!-- Code Entry Box -->
          <div class="space-y-1">
            <input 
              v-model="verificationCode"
              type="text" 
              maxlength="6"
              placeholder="000000" 
              class="w-full text-center tracking-[10px] text-xl font-mono font-bold py-2 bg-white border border-amber-300 rounded-xl focus:outline-none focus:border-[#0B4D34] transition" />
            <div class="flex items-center justify-between text-[10px] text-amber-700">
              <span>Code expires in 15 mins</span>
              <span v-if="verifyAttemptsRemaining !== null && verifyAttemptsRemaining < 5" class="font-bold text-rose-600">
                {{ verifyAttemptsRemaining }} attempts left
              </span>
            </div>
          </div>

          <!-- Buttons -->
          <div class="flex gap-2">
            <button 
              type="button"
              @click="handleVerifyCodeInLogin"
              :disabled="verifyingCode || verificationCode.length !== 6"
              class="flex-1 py-2 px-3 bg-[#0B4D34] hover:bg-[#083c28] text-white font-semibold text-xs rounded-xl transition shadow-xs disabled:opacity-50 flex items-center justify-center gap-1.5 cursor-pointer">
              <span v-if="verifyingCode" class="inline-block w-3 h-3 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
              <span>{{ verifyingCode ? 'Verifying...' : 'Verify & Sign In' }}</span>
            </button>
            <button 
              type="button"
              @click="handleResendVerification"
              :disabled="resending || resendCooldown > 0"
              class="py-2 px-3 bg-amber-200/70 hover:bg-amber-200 text-amber-900 font-medium text-xs rounded-xl transition disabled:opacity-50 cursor-pointer">
              {{ resendCooldown > 0 ? `${resendCooldown}s` : (resending ? '...' : 'Resend Code') }}
            </button>
          </div>
        </div>

        <!-- Forgot Password View Modal / Toggle -->
        <div v-if="showForgotPassword" class="p-4 bg-slate-50 border border-slate-200 rounded-2xl space-y-3">
          <div class="flex items-center justify-between">
            <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Reset Password</h4>
            <button @click="showForgotPassword = false" class="text-xs text-slate-400 hover:text-slate-600">✕</button>
          </div>
          <p class="text-[11px] text-slate-600">
            Enter your registered email address and we will dispatch a secure single-use password reset link.
          </p>
          <input 
            v-model="forgotEmail"
            type="email" 
            placeholder="name@domain.com"
            class="w-full px-3.5 py-2 bg-white border border-slate-300 rounded-xl text-xs focus:outline-none focus:border-[#0B4D34]" />
          <div class="flex gap-2">
            <button 
              type="button" 
              @click="handleForgotPassword"
              :disabled="forgotLoading || !forgotEmail"
              class="flex-1 py-2 bg-[#0B4D34] hover:bg-[#083c28] text-white text-xs font-semibold rounded-xl transition disabled:opacity-50">
              {{ forgotLoading ? 'Sending...' : 'Send Reset Link' }}
            </button>
            <button 
              type="button" 
              @click="showForgotPassword = false"
              class="px-3 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 text-xs rounded-xl transition">
              Cancel
            </button>
          </div>
        </div>

        <!-- MAIN LOGIN FORM (ONLY EMAIL AND PASSWORD) -->
        <form v-else @submit.prevent="handleLogin" class="space-y-3.5">
          <!-- 1. Email Address (Empty on initial load) -->
          <div>
            <label class="block text-xs font-semibold text-slate-700 mb-1">
              Email Address <span class="text-rose-500">*</span>
            </label>
            <div class="relative">
              <span class="absolute left-3.5 top-3 text-xs text-slate-400">✉️</span>
              <input 
                v-model="form.email" 
                type="email" 
                required
                autocomplete="email"
                placeholder="name@domain.com" 
                :class="errors.email ? 'border-rose-400 bg-rose-50/30' : 'border-slate-200 bg-slate-50'"
                class="w-full pl-10 pr-4 py-2.5 border rounded-xl text-xs focus:outline-none focus:border-[#0B4D34] transition" />
            </div>
            <p v-if="errors.email" class="text-[11px] text-rose-500 mt-1">{{ errors.email }}</p>
          </div>

          <!-- 2. Password with visibility toggle (Empty on initial load) -->
          <div>
            <div class="flex items-center justify-between mb-1">
              <label class="block text-xs font-semibold text-slate-700">
                Password <span class="text-rose-500">*</span>
              </label>
              <button 
                type="button" 
                @click="showForgotPassword = true" 
                class="text-[11px] text-[#0B4D34] hover:underline font-medium">
                Forgot password?
              </button>
            </div>
            <div class="relative">
              <span class="absolute left-3.5 top-3 text-xs text-slate-400">🔒</span>
              <input 
                v-model="form.password" 
                :type="showPassword ? 'text' : 'password'" 
                required
                autocomplete="current-password"
                placeholder="Enter your password" 
                :class="errors.password ? 'border-rose-400 bg-rose-50/30' : 'border-slate-200 bg-slate-50'"
                class="w-full pl-10 pr-10 py-2.5 border rounded-xl text-xs focus:outline-none focus:border-[#0B4D34] transition" />
              <button 
                type="button" 
                @click="showPassword = !showPassword"
                class="absolute right-3 top-2.5 text-xs text-slate-400 hover:text-slate-600 p-1">
                {{ showPassword ? '🙈' : '👁️' }}
              </button>
            </div>
            <p v-if="errors.password" class="text-[11px] text-rose-500 mt-1">{{ errors.password }}</p>
          </div>

          <!-- Submit Button (Duplicate submission prevention) -->
          <button 
            type="submit"
            :disabled="loading"
            class="w-full py-3 bg-gradient-to-r from-[#0B4D34] to-[#107550] hover:brightness-110 text-white font-bold text-xs rounded-xl shadow-md transition disabled:opacity-50 flex items-center justify-center gap-2 cursor-pointer">
            <span v-if="loading" class="animate-spin text-sm">⏳</span>
            <span>{{ loading ? 'Authenticating with Database...' : 'Sign In to Hotel Portal' }}</span>
          </button>
        </form>

        <!-- Link to Registration Page -->
        <div class="pt-3 border-t border-slate-100 text-center">
          <p class="text-xs text-slate-600">
            Don't have an account? 
            <button 
              type="button" 
              @click="$emit('switch-to-register')" 
              class="text-[#0B4D34] font-bold hover:underline ml-1">
              Create an account
            </button>
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';

const props = defineProps({
  initialSuccess: {
    type: String,
    default: ''
  }
});

const emit = defineEmits(['close', 'login-success', 'switch-to-register']);

const showPassword = ref(false);
const loading = ref(false);
const resending = ref(false);
const errorMessage = ref('');
const successMessage = ref(props.initialSuccess || '');
const unverifiedNotice = ref(false);
const unverifiedEmail = ref('');

// 6-digit verification code states
const verificationCode = ref('');
const verifyingCode = ref(false);
const verifyAttemptsRemaining = ref(null);
const resendCooldown = ref(0);
let cooldownTimer = null;

function startResendCooldown(seconds = 60) {
  resendCooldown.value = seconds;
  if (cooldownTimer) clearInterval(cooldownTimer);
  cooldownTimer = setInterval(() => {
    if (resendCooldown.value > 0) {
      resendCooldown.value--;
    } else {
      clearInterval(cooldownTimer);
      cooldownTimer = null;
    }
  }, 1000);
}

// Forgot password state
const showForgotPassword = ref(false);
const forgotEmail = ref('');
const forgotLoading = ref(false);

// Mandatory Rule: Inputs must start completely EMPTY on initial load
const form = reactive({
  email: '',
  password: ''
});

const errors = reactive({
  email: '',
  password: ''
});

onMounted(() => {
  // Check if user came with ?verified=true in the URL
  const urlParams = new URLSearchParams(window.location.search);
  if (urlParams.get('verified') === 'true') {
    successMessage.value = 'Email verified successfully! You may now log in with your credentials.';
  }
});

function validateForm() {
  errors.email = '';
  errors.password = '';
  let valid = true;

  if (!form.email || !form.email.trim()) {
    errors.email = 'Email address is required.';
    valid = false;
  } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email.trim())) {
    errors.email = 'Please enter a valid email format (e.g. name@domain.com).';
    valid = false;
  }

  if (!form.password) {
    errors.password = 'Password is required.';
    valid = false;
  }

  return valid;
}

async function handleLogin() {
  if (loading.value) return; // Prevent duplicate submission
  errorMessage.value = '';
  unverifiedNotice.value = false;

  if (!validateForm()) return;

  loading.value = true;
  try {
    const res = await fetch('/api/v1/auth/login', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        email: form.email.trim().toLowerCase(),
        password: form.password
      })
    });

    const data = await res.json();

    if (!res.ok) {
      if (res.status === 403 && data.unverified) {
        unverifiedNotice.value = true;
        unverifiedEmail.value = data.email || form.email.trim();
        errorMessage.value = data.error || 'Email verification is required before logging in.';
      } else {
        errorMessage.value = data.error || 'Authentication failed. Please verify your credentials.';
      }
      return;
    }

    // Success: store token and user session
    if (data.token) {
      localStorage.setItem('hotel_auth_token', data.token);
      localStorage.setItem('hotel_user', JSON.stringify(data.user));
    }

    emit('login-success', data.user);
  } catch (err) {
    errorMessage.value = 'Network error: Unable to reach authentication server.';
  } finally {
    loading.value = false;
  }
}

async function handleVerifyCodeInLogin() {
  if (verifyingCode.value || verificationCode.value.length !== 6) return;
  verifyingCode.value = true;
  errorMessage.value = '';

  try {
    const res = await fetch('/api/v1/auth/verify-code', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        email: unverifiedEmail.value || form.email.trim(),
        code: verificationCode.value.trim()
      })
    });

    const data = await res.json();
    if (!res.ok) {
      errorMessage.value = data.error || 'Invalid verification code.';
      if (typeof data.attemptsRemaining === 'number') {
        verifyAttemptsRemaining.value = data.attemptsRemaining;
      }
      return;
    }

    successMessage.value = 'Email verified successfully! Signing you in...';
    unverifiedNotice.value = false;

    if (data.token) {
      localStorage.setItem('hotel_auth_token', data.token);
      localStorage.setItem('hotel_user', JSON.stringify(data.user));
    }

    setTimeout(() => {
      emit('login-success', data.user);
    }, 800);
  } catch (err) {
    errorMessage.value = 'Network error: Failed to verify code.';
  } finally {
    verifyingCode.value = false;
  }
}

async function handleResendVerification() {
  if (resending.value || resendCooldown.value > 0) return;
  const targetEmail = unverifiedEmail.value || form.email.trim();
  if (!targetEmail) return;

  resending.value = true;
  try {
    const res = await fetch('/api/v1/auth/resend-code', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email: targetEmail })
    });
    const data = await res.json();
    if (!res.ok) {
      errorMessage.value = data.error || 'Failed to resend verification code.';
      return;
    }
    successMessage.value = data.message || 'A fresh 6-digit verification code has been dispatched. Please check your inbox.';
    startResendCooldown(60);
    verificationCode.value = '';
  } catch (e) {
    errorMessage.value = 'Failed to resend verification code.';
  } finally {
    resending.value = false;
  }
}

async function handleForgotPassword() {
  if (!forgotEmail.value || forgotLoading.value) return;
  forgotLoading.value = true;
  errorMessage.value = '';

  try {
    const res = await fetch('/api/v1/auth/forgot-password', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email: forgotEmail.value.trim().toLowerCase() })
    });
    const data = await res.json();
    successMessage.value = data.message || 'If an account exists, a reset link was sent.';
    showForgotPassword.value = false;
    forgotEmail.value = '';
  } catch (err) {
    errorMessage.value = 'Failed to submit password reset request.';
  } finally {
    forgotLoading.value = false;
  }
}
</script>

<style scoped>
@keyframes fadeIn {
  from { opacity: 0; transform: scale(0.97); }
  to { opacity: 1; transform: scale(1); }
}
.animate-fadeIn {
  animation: fadeIn 0.18s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
</style>
