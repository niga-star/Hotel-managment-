<template>
  <div class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-3 sm:p-4 overflow-y-auto">
    <!-- Card Container -->
    <div class="bg-white rounded-3xl overflow-hidden shadow-2xl max-w-4xl w-full border border-slate-100 grid grid-cols-1 md:grid-cols-12 relative animate-fadeIn my-auto">
      <!-- Close Button -->
      <button 
        @click="$emit('close')"
        aria-label="Close"
        class="absolute top-4 right-4 z-10 w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-600 flex items-center justify-center text-sm transition">
        ✕
      </button>

      <!-- Left Column: Ethiopian Heritage Luxury Visual (5 cols) -->
      <div class="relative md:col-span-5 hidden md:block min-h-[500px] overflow-hidden bg-[#064E3B]">
        <img 
          src="https://images.unsplash.com/photo-1542314831-068cd1dbfeeb?w=800&auto=format&fit=crop&q=80" 
          class="w-full h-full object-cover opacity-85" 
          alt="Ethiopian Boutique Luxury Hotel" />
        <div class="absolute inset-0 bg-gradient-to-t from-[#064E3B] via-[#064E3B]/45 to-transparent flex flex-col justify-end p-6 text-white">
          <div class="w-10 h-10 rounded-xl bg-[#D4A329] text-[#064E3B] font-bold flex items-center justify-center font-serif text-xl mb-3 shadow-md border border-yellow-200">
            👑
          </div>
          <h3 class="text-2xl font-bold font-serif leading-tight">
            Join Ethiopian Boutique Stays
          </h3>
          <p class="text-xs text-yellow-200/90 mt-1.5 leading-relaxed">
            Create an account to reserve luxury suites, manage itineraries, and experience authentic Ethiopian hospitality.
          </p>

          <div class="mt-6 pt-4 border-t border-white/15 space-y-2 text-[11px] text-emerald-100">
            <div class="flex items-center gap-2">
              <span class="text-[#D4A329]">✓</span>
              <span>Real-time suite availability & booking</span>
            </div>
            <div class="flex items-center gap-2">
              <span class="text-[#D4A329]">✓</span>
              <span>Telebirr, CBE Birr & Chapa payment support</span>
            </div>
            <div class="flex items-center gap-2">
              <span class="text-[#D4A329]">✓</span>
              <span>Direct check-in & digital receipts</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Right Column: Registration / Verification Flow (7 cols) -->
      <div class="p-6 sm:p-8 md:col-span-7 flex flex-col justify-center space-y-4 max-h-[92vh] overflow-y-auto">
        <!-- STEP 1: REGISTRATION FORM -->
        <template v-if="currentStep === 'form'">
          <div>
            <div class="flex items-center justify-between">
              <h2 class="text-2xl font-bold text-slate-900 tracking-tight">Create Account</h2>
              <span class="text-[11px] px-2.5 py-1 bg-yellow-50 text-[#0B4D34] font-semibold rounded-full border border-yellow-300/60">
                Guest Portal
              </span>
            </div>
            <p class="text-xs text-slate-500 mt-1">
              Complete the 4 steps below to register your hotel account.
            </p>
          </div>

          <!-- Global Error Notification -->
          <div v-if="serverError" class="p-3 bg-rose-50 border border-rose-200 text-rose-700 text-xs rounded-xl flex items-start gap-2">
            <span class="text-rose-500 font-bold">⚠️</span>
            <span>{{ serverError }}</span>
          </div>

          <!-- REGISTRATION FORM (EXACTLY 4 FIELDS) -->
          <form @submit.prevent="handleRegister" class="space-y-3.5">
          <!-- 1. FULL NAME -->
          <div>
            <div class="flex items-center justify-between mb-1">
              <label class="block text-xs font-semibold text-slate-700">
                1. Full Name <span class="text-rose-500">*</span>
              </label>
              <span v-if="nameValidation.valid && form.full_name" class="text-[10px] text-emerald-600 font-medium flex items-center gap-1">
                <span>✓</span> Valid Name
              </span>
            </div>
            <div class="relative">
              <span class="absolute left-3.5 top-3 text-xs text-slate-400">👤</span>
              <input 
                v-model="form.full_name"
                @input="validateNameLive"
                type="text" 
                maxlength="30"
                placeholder="e.g. Amir Hassen" 
                :class="errors.full_name ? 'border-rose-400 bg-rose-50/30' : (form.full_name && nameValidation.valid ? 'border-emerald-500 bg-emerald-50/20' : 'border-slate-200 bg-slate-50')"
                class="w-full pl-10 pr-4 py-2.5 border rounded-xl text-xs focus:outline-none focus:border-[#0B4D34] transition" />
            </div>
            <p v-if="errors.full_name" class="text-[11px] text-rose-500 mt-1">{{ errors.full_name }}</p>
            <p v-else class="text-[10px] text-slate-400 mt-0.5">3–30 characters, alphabetic letters only.</p>
          </div>

          <!-- 2. INTERNATIONAL PHONE NUMBER (Country Selector + Numeric Digits) -->
          <div>
            <div class="flex items-center justify-between mb-1">
              <label class="block text-xs font-semibold text-slate-700">
                2. Phone Number (International) <span class="text-rose-500">*</span>
              </label>
              <span v-if="phoneValidation.valid && form.phone" class="text-[10px] text-emerald-600 font-medium flex items-center gap-1">
                <span>✓</span> {{ phoneValidation.e164 }}
              </span>
            </div>
            <div class="flex items-center gap-1.5">
              <!-- Country Dialing Code Selector -->
              <div class="relative w-36 shrink-0">
                <select 
                  v-model="selectedCountry"
                  @change="onCountryChange"
                  class="w-full pl-2.5 pr-6 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-700 focus:outline-none focus:border-[#0B4D34] appearance-none cursor-pointer">
                  <option v-for="c in countries" :key="c.code" :value="c">
                    {{ c.flag }} {{ c.dial }} ({{ c.code }})
                  </option>
                </select>
                <span class="absolute right-2 top-3 pointer-events-none text-[10px] text-slate-400">▼</span>
              </div>

              <!-- Phone Digits Input (Only numeric digits allowed) -->
              <div class="relative flex-1">
                <input 
                  v-model="form.phone"
                  @input="handlePhoneInput"
                  type="tel" 
                  :placeholder="selectedCountry.placeholder"
                  :class="errors.phone ? 'border-rose-400 bg-rose-50/30' : (form.phone && phoneValidation.valid ? 'border-emerald-500 bg-emerald-50/20' : 'border-slate-200 bg-slate-50')"
                  class="w-full px-3.5 py-2.5 border rounded-xl text-xs focus:outline-none focus:border-[#0B4D34] transition" />
              </div>
            </div>
            <p v-if="errors.phone" class="text-[11px] text-rose-500 mt-1">{{ errors.phone }}</p>
            <p v-else class="text-[10px] text-slate-400 mt-0.5">Stored in normalized E.164 format for booking SMS confirmations.</p>
          </div>

          <!-- 3. EMAIL ADDRESS -->
          <div>
            <div class="flex items-center justify-between mb-1">
              <label class="block text-xs font-semibold text-slate-700">
                3. Email Address <span class="text-rose-500">*</span>
              </label>
              <span v-if="emailValid && form.email" class="text-[10px] text-emerald-600 font-medium">✓ Valid Email</span>
            </div>
            <div class="relative">
              <span class="absolute left-3.5 top-3 text-xs text-slate-400">✉️</span>
              <input 
                v-model="form.email"
                @input="validateEmailLive"
                type="email" 
                required
                autocomplete="email"
                placeholder="e.g. guest@example.com" 
                :class="errors.email ? 'border-rose-400 bg-rose-50/30' : (form.email && emailValid ? 'border-emerald-500 bg-emerald-50/20' : 'border-slate-200 bg-slate-50')"
                class="w-full pl-10 pr-4 py-2.5 border rounded-xl text-xs focus:outline-none focus:border-[#0B4D34] transition" />
            </div>
            <p v-if="errors.email" class="text-[11px] text-rose-500 mt-1">{{ errors.email }}</p>
            <p v-else class="text-[10px] text-slate-400 mt-0.5">Verification link will be dispatched to this address.</p>
          </div>

          <!-- 4. STRONG PASSWORD (Checklist + Strength Meter) -->
          <div>
            <div class="flex items-center justify-between mb-1">
              <label class="block text-xs font-semibold text-slate-700">
                4. Password <span class="text-rose-500">*</span>
              </label>
              <!-- Strength Label -->
              <span 
                v-if="form.password" 
                class="text-[10px] font-bold uppercase tracking-wider"
                :class="strengthColorText">
                {{ strengthLabel }}
              </span>
            </div>
            <div class="relative">
              <span class="absolute left-3.5 top-3 text-xs text-slate-400">🔒</span>
              <input 
                v-model="form.password"
                @input="validatePasswordLive"
                :type="showPassword ? 'text' : 'password'" 
                required
                autocomplete="new-password"
                placeholder="Minimum 8 characters with upper, lower, number, symbol" 
                :class="errors.password ? 'border-rose-400 bg-rose-50/30' : (form.password && passChecklist.allMet ? 'border-emerald-500 bg-emerald-50/20' : 'border-slate-200 bg-slate-50')"
                class="w-full pl-10 pr-10 py-2.5 border rounded-xl text-xs focus:outline-none focus:border-[#0B4D34] transition" />
              <button 
                type="button" 
                @click="showPassword = !showPassword"
                class="absolute right-3 top-2.5 text-xs text-slate-400 hover:text-slate-600 p-1">
                {{ showPassword ? '🙈' : '👁️' }}
              </button>
            </div>

            <!-- Strength Meter Progress Bar -->
            <div v-if="form.password" class="w-full bg-slate-100 rounded-full h-1.5 mt-2 overflow-hidden flex">
              <div 
                class="h-full transition-all duration-300"
                :class="strengthBg"
                :style="{ width: strengthPercent + '%' }"></div>
            </div>

            <!-- Password Requirements Checklist (Live while typing) -->
            <div class="mt-2.5 p-2.5 bg-slate-50 border border-slate-200 rounded-xl space-y-1 text-[11px]">
              <div class="text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">
                Password Security Checklist:
              </div>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-1">
                <div :class="passChecklist.minLength ? 'text-emerald-700 font-medium' : 'text-slate-400'" class="flex items-center gap-1.5">
                  <span>{{ passChecklist.minLength ? '✓' : '○' }}</span>
                  <span>At least 8 characters</span>
                </div>
                <div :class="passChecklist.hasUpper ? 'text-emerald-700 font-medium' : 'text-slate-400'" class="flex items-center gap-1.5">
                  <span>{{ passChecklist.hasUpper ? '✓' : '○' }}</span>
                  <span>1 uppercase letter (A–Z)</span>
                </div>
                <div :class="passChecklist.hasLower ? 'text-emerald-700 font-medium' : 'text-slate-400'" class="flex items-center gap-1.5">
                  <span>{{ passChecklist.hasLower ? '✓' : '○' }}</span>
                  <span>1 lowercase letter (a–z)</span>
                </div>
                <div :class="passChecklist.hasDigit ? 'text-emerald-700 font-medium' : 'text-slate-400'" class="flex items-center gap-1.5">
                  <span>{{ passChecklist.hasDigit ? '✓' : '○' }}</span>
                  <span>1 numeric digit (0–9)</span>
                </div>
                <div :class="passChecklist.hasSpecial ? 'text-emerald-700 font-medium' : 'text-slate-400'" class="flex items-center gap-1.5 sm:col-span-2">
                  <span>{{ passChecklist.hasSpecial ? '✓' : '○' }}</span>
                  <span>1 special character (@, #, $, %, !, &, etc.)</span>
                </div>
              </div>
            </div>

            <p v-if="errors.password" class="text-[11px] text-rose-500 mt-1">{{ errors.password }}</p>
          </div>

          <!-- Submit Button (Disabled while submitting or invalid) -->
          <button 
            type="submit"
            :disabled="loading || !isFormValid"
            class="w-full py-3 bg-gradient-to-r from-[#0B4D34] to-[#107550] hover:brightness-110 text-white font-bold text-xs rounded-xl shadow-md transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2 cursor-pointer mt-2">
            <span v-if="loading" class="animate-spin text-sm">⏳</span>
            <span>{{ loading ? 'Creating Account & Dispatching Verification...' : 'Register Hotel Account' }}</span>
          </button>
        </form>

        <!-- Link back to Login Page -->
        <div class="pt-3 border-t border-slate-100 text-center">
          <p class="text-xs text-slate-600">
            Already have an account? 
            <button 
              type="button" 
              @click="$emit('switch-to-login')" 
              class="text-[#0B4D34] font-bold hover:underline ml-1">
              Sign In
            </button>
          </p>
        </div>
      </template>

      <!-- STEP 2: 6-DIGIT EMAIL CODE VERIFICATION -->
      <template v-else-if="currentStep === 'verify'">
        <div class="space-y-4">
          <div class="text-center">
            <div class="w-14 h-14 mx-auto rounded-2xl bg-emerald-50 text-[#0B4D34] flex items-center justify-center text-2xl mb-2 border border-emerald-200 shadow-xs">
              ✉️
            </div>
            <h2 class="text-xl font-bold text-slate-900 tracking-tight">Verify Your Email</h2>
            <p class="text-xs text-slate-500 mt-1">
              We've dispatched a real 6-digit verification code to:
            </p>
            <p class="text-xs font-bold text-[#0B4D34] mt-0.5">{{ registeredEmail }}</p>
          </div>

          <!-- Error Notice -->
          <div v-if="verifyError" class="p-3 bg-rose-50 border border-rose-200 text-rose-700 text-xs rounded-xl flex items-start gap-2">
            <span class="text-rose-500 font-bold">⚠️</span>
            <span>{{ verifyError }}</span>
          </div>

          <!-- Success Notice -->
          <div v-if="verifySuccess" class="p-3 bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs rounded-xl flex items-start gap-2">
            <span class="text-emerald-600 font-bold">✓</span>
            <span>{{ verifySuccess }}</span>
          </div>

          <!-- 6-Digit Code Input Box -->
          <div>
            <label class="block text-xs font-semibold text-slate-700 mb-1.5 text-center">
              Enter 6-Digit Verification Code
            </label>
            <div class="relative max-w-xs mx-auto">
              <input 
                v-model="verificationCode"
                @input="verifyError = ''"
                type="text" 
                maxlength="6"
                placeholder="000000" 
                autofocus
                class="w-full text-center tracking-[10px] text-2xl font-mono font-bold py-3 bg-slate-50 border border-slate-300 rounded-2xl focus:outline-none focus:border-[#0B4D34] focus:bg-white transition" />
            </div>
            <div class="flex items-center justify-between max-w-xs mx-auto mt-2 text-[11px] text-slate-400">
              <span>⏱️ Code expires in 15 mins</span>
              <span v-if="attemptsRemaining !== null && attemptsRemaining < 5" class="text-amber-600 font-medium">
                {{ attemptsRemaining }} attempts left
              </span>
            </div>
          </div>

          <!-- Submit Button -->
          <button 
            type="button"
            @click="handleVerifyCode"
            :disabled="verifying || verificationCode.length !== 6"
            class="w-full py-3 px-4 bg-gradient-to-r from-[#0B4D34] to-[#107550] hover:brightness-110 text-white font-bold text-xs rounded-xl transition shadow-md disabled:opacity-50 flex items-center justify-center gap-2 cursor-pointer">
            <span v-if="verifying" class="inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
            <span>{{ verifying ? 'Verifying Code...' : 'Verify Email & Complete Registration' }}</span>
          </button>

          <!-- Resend Code Action with Cooldown Timer -->
          <div class="pt-3 border-t border-slate-100 flex items-center justify-between text-xs">
            <span class="text-slate-500">Didn't receive code?</span>
            <button 
              type="button"
              @click="handleResendCode"
              :disabled="resendCooldown > 0 || resendingCode"
              class="text-[#0B4D34] font-semibold hover:underline disabled:opacity-50 disabled:no-underline">
              {{ resendCooldown > 0 ? `Resend in ${resendCooldown}s` : (resendingCode ? 'Dispatching...' : 'Resend Code') }}
            </button>
          </div>

          <!-- Return / Direct login link -->
          <div class="text-center pt-1">
            <button 
              type="button" 
              @click="$emit('switch-to-login')"
              class="text-xs text-slate-500 hover:text-slate-800 underline">
              Verify later & Go to Sign In
            </button>
          </div>
        </div>
      </template>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed } from 'vue';

const emit = defineEmits(['close', 'switch-to-login', 'register-success']);

const showPassword = ref(false);
const loading = ref(false);
const serverError = ref('');

// Step management (Step 1: 4 Form Fields, Step 2: 6-Digit Email Code Entry)
const currentStep = ref('form'); // 'form' | 'verify'
const registeredEmail = ref('');
const verificationCode = ref('');
const verifying = ref(false);
const verifyError = ref('');
const verifySuccess = ref('');
const attemptsRemaining = ref(null);
const resendCooldown = ref(0);
const resendingCode = ref(false);
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

// Supported Countries for international phone numbering
const countries = [
  { code: 'ET', name: 'Ethiopia', dial: '+251', flag: '🇪🇹', placeholder: '911 234567' },
  { code: 'US', name: 'United States', dial: '+1', flag: '🇺🇸', placeholder: '202 555 0143' },
  { code: 'GB', name: 'United Kingdom', dial: '+44', flag: '🇬🇧', placeholder: '7911 123456' },
  { code: 'KE', name: 'Kenya', dial: '+254', flag: '🇰🇪', placeholder: '712 345678' },
  { code: 'AE', name: 'UAE', dial: '+971', flag: '🇦🇪', placeholder: '50 123 4567' },
  { code: 'IN', name: 'India', dial: '+91', flag: '🇮🇳', placeholder: '98765 43210' },
  { code: 'DE', name: 'Germany', dial: '+49', flag: '🇩🇪', placeholder: '151 23456789' },
  { code: 'FR', name: 'France', dial: '+33', flag: '🇫🇷', placeholder: '6 12 34 56 78' },
  { code: 'CA', name: 'Canada', dial: '+1', flag: '🇨🇦', placeholder: '416 555 0199' },
  { code: 'SA', name: 'Saudi Arabia', dial: '+966', flag: '🇸🇦', placeholder: '50 123 4567' },
  { code: 'ZA', name: 'South Africa', dial: '+27', flag: '🇿🇦', placeholder: '71 123 4567' },
  { code: 'NG', name: 'Nigeria', dial: '+234', flag: '🇳🇬', placeholder: '802 123 4567' },
  { code: 'CN', name: 'China', dial: '+86', flag: '🇨🇳', placeholder: '138 0013 8000' }
];

const selectedCountry = ref(countries[0]);

// Mandatory rule: All 4 fields must be empty on initial load
const form = reactive({
  full_name: '',
  phone: '',
  email: '',
  password: ''
});

const errors = reactive({
  full_name: '',
  phone: '',
  email: '',
  password: ''
});

// Full Name Validation Live
const nameValidation = computed(() => {
  if (!form.full_name) return { valid: false, message: '' };
  const trimmed = form.full_name.trim().replace(/\s+/g, ' ');
  if (trimmed.length < 3) return { valid: false, message: 'Name must be at least 3 characters.' };
  if (trimmed.length > 30) return { valid: false, message: 'Name cannot exceed 30 characters.' };
  // Unicode letters only
  const nameRegex = /^[\p{L}]+(?: [\p{L}]+)*$/u;
  if (!nameRegex.test(trimmed)) {
    return { valid: false, message: 'Alphabetic and Unicode letters only (no numbers or symbols).' };
  }
  return { valid: true, normalized: trimmed };
});

function validateNameLive() {
  if (!form.full_name) {
    errors.full_name = '';
    return;
  }
  errors.full_name = nameValidation.value.valid ? '' : nameValidation.value.message;
}

// International Phone Validation Live
const phoneValidation = computed(() => {
  if (!form.phone) return { valid: false, e164: '' };
  const cleaned = form.phone.replace(/[^\d+]/g, '');
  let normalized;
  if (cleaned.startsWith('+')) {
    normalized = cleaned;
  } else {
    normalized = `${selectedCountry.value.dial}${cleaned.replace(/^0+/, '')}`;
  }
  const e164Regex = /^\+[1-9]\d{6,14}$/;
  const valid = e164Regex.test(normalized);
  return { valid, e164: normalized };
});

function handlePhoneInput(e) {
  // Enforce numbers and optional leading + only
  form.phone = e.target.value.replace(/[^\d\s\-\+\(\)]/g, '');
  if (!form.phone) {
    errors.phone = '';
    return;
  }
  const cleanedDigits = form.phone.replace(/\D/g, '');
  if (cleanedDigits.length < 6) {
    errors.phone = 'Phone number is too short for the selected country.';
  } else if (cleanedDigits.length > 15) {
    errors.phone = 'Phone number cannot exceed 15 digits.';
  } else {
    errors.phone = phoneValidation.value.valid ? '' : 'Please enter a valid phone number.';
  }
}

function onCountryChange() {
  if (form.phone) {
    handlePhoneInput({ target: { value: form.phone } });
  }
}

// Email Validation Live
const emailValid = computed(() => {
  if (!form.email) return false;
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email.trim());
});

function validateEmailLive() {
  if (!form.email) {
    errors.email = '';
    return;
  }
  errors.email = emailValid.value ? '' : 'Please enter a valid email format (e.g. name@domain.com).';
}

// Strong Password Validation Live
const passChecklist = computed(() => {
  const p = form.password;
  const minLength = p.length >= 8;
  const hasUpper = /[A-Z]/.test(p);
  const hasLower = /[a-z]/.test(p);
  const hasDigit = /[0-9]/.test(p);
  const hasSpecial = /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?`~]/.test(p);
  const allMet = minLength && hasUpper && hasLower && hasDigit && hasSpecial;
  return { minLength, hasUpper, hasLower, hasDigit, hasSpecial, allMet };
});

const strengthScore = computed(() => {
  const c = passChecklist.value;
  let score = 0;
  if (c.minLength) score++;
  if (c.hasUpper) score++;
  if (c.hasLower) score++;
  if (c.hasDigit) score++;
  if (c.hasSpecial) score++;
  return score; // 0 to 5
});

const strengthPercent = computed(() => {
  return (strengthScore.value / 5) * 100;
});

const strengthLabel = computed(() => {
  const s = strengthScore.value;
  if (s <= 2) return 'Weak';
  if (s === 3) return 'Fair';
  if (s === 4) return 'Good';
  return 'Strong';
});

const strengthColorText = computed(() => {
  const s = strengthScore.value;
  if (s <= 2) return 'text-rose-500';
  if (s === 3) return 'text-amber-500';
  if (s === 4) return 'text-blue-500';
  return 'text-emerald-600';
});

const strengthBg = computed(() => {
  const s = strengthScore.value;
  if (s <= 2) return 'bg-rose-500';
  if (s === 3) return 'bg-amber-500';
  if (s === 4) return 'bg-blue-500';
  return 'bg-emerald-600';
});

function validatePasswordLive() {
  if (!form.password) {
    errors.password = '';
    return;
  }
  errors.password = passChecklist.value.allMet ? '' : 'Password does not meet all security requirements.';
}

const isFormValid = computed(() => {
  return (
    nameValidation.value.valid &&
    phoneValidation.value.valid &&
    emailValid.value &&
    passChecklist.value.allMet
  );
});

async function handleRegister() {
  if (loading.value) return; // Prevent duplicate submission
  serverError.value = '';

  validateNameLive();
  validateEmailLive();
  validatePasswordLive();

  if (!isFormValid.value) {
    serverError.value = 'Please correct all highlighted fields before submitting.';
    return;
  }

  loading.value = true;
  try {
    const res = await fetch('/api/v1/auth/register', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        full_name: nameValidation.value.normalized,
        phone: phoneValidation.value.e164,
        email: form.email.trim().toLowerCase(),
        password: form.password
      })
    });

    const data = await res.json();

    if (!res.ok) {
      serverError.value = data.error || 'Registration failed. Please try again.';
      return;
    }

    // Move to Step 2: 6-Digit Email Code Verification Screen
    registeredEmail.value = form.email.trim().toLowerCase();
    currentStep.value = 'verify';
    verifySuccess.value = data.message || 'We have sent a 6-digit verification code to your email.';
    startResendCooldown(60);
  } catch (err) {
    serverError.value = 'Network error: Unable to contact registration server.';
  } finally {
    loading.value = false;
  }
}

async function handleVerifyCode() {
  if (verifying.value || verificationCode.value.length !== 6) return;
  verifying.value = true;
  verifyError.value = '';
  verifySuccess.value = '';

  try {
    const res = await fetch('/api/v1/auth/verify-code', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        email: registeredEmail.value,
        code: verificationCode.value.trim()
      })
    });

    const data = await res.json();
    if (!res.ok) {
      verifyError.value = data.error || 'Verification failed. Please check the code and try again.';
      if (typeof data.attemptsRemaining === 'number') {
        attemptsRemaining.value = data.attemptsRemaining;
      }
      return;
    }

    verifySuccess.value = 'Email verified successfully! Welcome to Ethiopian Boutique Stays.';
    
    // Store token if returned
    if (data.token) {
      localStorage.setItem('hotel_auth_token', data.token);
      localStorage.setItem('hotel_user', JSON.stringify(data.user));
    }

    setTimeout(() => {
      // Clear all form inputs so login form is never populated with registration details
      form.full_name = '';
      form.phone = '';
      form.email = '';
      form.password = '';
      emit('register-success', {
        message: 'Account verified successfully! You may now sign in.'
      });
    }, 1200);
  } catch (err) {
    verifyError.value = 'Network error: Failed to verify code.';
  } finally {
    verifying.value = false;
  }
}

async function handleResendCode() {
  if (resendCooldown.value > 0 || resendingCode.value) return;
  resendingCode.value = true;
  verifyError.value = '';

  try {
    const res = await fetch('/api/v1/auth/resend-code', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email: registeredEmail.value })
    });
    const data = await res.json();
    if (!res.ok) {
      verifyError.value = data.error || 'Failed to resend verification code.';
      return;
    }
    verifySuccess.value = data.message || 'A fresh 6-digit code has been dispatched to your email.';
    startResendCooldown(60);
    verificationCode.value = '';
  } catch (err) {
    verifyError.value = 'Network error: Failed to resend code.';
  } finally {
    resendingCode.value = false;
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
