<template>
  <div class="max-w-4xl mx-auto space-y-6 pb-16">
    <!-- Top Step Bar -->
    <div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-100 flex items-center justify-between text-xs">
      <div class="flex items-center gap-2">
        <router-link to="/rooms" class="text-slate-400 hover:text-slate-700 flex items-center gap-1 font-medium">
          ← Back to Rooms
        </router-link>
        <span class="text-slate-300">/</span>
        <span class="font-bold text-slate-800">Secure Payment Checkout</span>
      </div>

      <div class="flex items-center gap-2">
        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
        <span class="font-mono text-slate-500 text-[11px]">256-Bit SSL Encrypted</span>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="bg-white p-16 rounded-3xl text-center text-slate-400 text-xs shadow-sm border border-slate-100">
      Loading reservation details & payment channels...
    </div>

    <!-- Error State -->
    <div v-else-if="fetchError" class="bg-white p-12 rounded-3xl text-center space-y-3 shadow-sm border border-slate-100">
      <div class="text-3xl">⚠️</div>
      <h3 class="text-base font-bold text-slate-800">{{ fetchError }}</h3>
      <router-link to="/rooms" class="inline-block px-5 py-2 bg-[#0B4D34] text-white text-xs font-bold rounded-full">
        Browse Available Suites
      </router-link>
    </div>

    <!-- Main Payment Grid -->
    <div v-else-if="booking" class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
      <!-- Left Column: Stay & Room Breakdown (5 cols) -->
      <div class="lg:col-span-5 bg-white rounded-3xl p-6 shadow-sm border border-slate-100 space-y-5">
        <!-- Room Banner Card -->
        <div class="relative h-40 rounded-2xl overflow-hidden bg-slate-800 shadow-inner">
          <img 
            :src="booking.room?.image_url" 
            :alt="booking.room?.title"
            class="w-full h-full object-cover" />
          <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent"></div>
          
          <div class="absolute bottom-3 left-4 text-white">
            <span class="text-xl font-black">Room {{ booking.room?.room_number }}</span>
            <span class="ml-2 text-[10px] bg-[#D4A329] text-slate-900 px-2 py-0.5 rounded font-bold uppercase">
              {{ booking.room?.category_name }}
            </span>
            <div class="text-xs text-slate-200 mt-0.5 line-clamp-1">{{ booking.room?.title }}</div>
          </div>
        </div>

        <!-- Reservation Reference & Guest -->
        <div class="p-3.5 bg-slate-50 rounded-2xl border border-slate-200 text-xs space-y-2">
          <div class="flex justify-between items-center border-b border-slate-200/80 pb-2">
            <span class="text-slate-400">Reservation Ref:</span>
            <span class="font-mono font-bold text-[#0B4D34] text-sm">{{ booking.reference_code }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-slate-400">Guest:</span>
            <span class="font-bold text-slate-800">{{ booking.guest?.full_name }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-slate-400">Phone:</span>
            <span class="font-mono text-slate-700">{{ booking.guest?.phone }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-slate-400">Dates:</span>
            <span class="font-medium text-slate-800">{{ booking.check_in_date }} → {{ booking.check_out_date }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-slate-400">Stay Duration:</span>
            <span class="font-bold text-slate-900">{{ booking.nights }} Night{{ booking.nights > 1 ? 's' : '' }}</span>
          </div>
        </div>

        <!-- Itemized Tax & Cost Breakdown -->
        <div class="space-y-2 text-xs pt-1">
          <h4 class="font-bold text-slate-800 text-[11px] uppercase tracking-wider">Financial Breakdown</h4>
          <div class="flex justify-between text-slate-600">
            <span>Accommodation Charge:</span>
            <span class="font-mono font-medium">{{ booking.subtotal_etb?.toLocaleString() }} ETB</span>
          </div>
          <div class="flex justify-between text-slate-600">
            <span>15% Value Added Tax (VAT):</span>
            <span class="font-mono font-medium">{{ booking.tax_vat_15?.toLocaleString() }} ETB</span>
          </div>
          <div class="flex justify-between text-slate-600">
            <span>10% Hospitality Service Charge:</span>
            <span class="font-mono font-medium">{{ booking.tax_service_10?.toLocaleString() }} ETB</span>
          </div>
          <div class="flex justify-between text-slate-600">
            <span>2% Tourism Development Levy:</span>
            <span class="font-mono font-medium">{{ booking.tax_tourism_2?.toLocaleString() }} ETB</span>
          </div>
          <div class="flex justify-between text-base font-black text-[#0B4D34] pt-2 border-t-2 border-[#0B4D34]">
            <span>Grand Total:</span>
            <span class="font-mono">{{ booking.total_price_etb?.toLocaleString() }} ETB</span>
          </div>
          <div class="text-right text-[11px] text-slate-400">
            ≈ ${{ booking.total_price_usd }} USD
          </div>
        </div>
      </div>

      <!-- Right Column: Dedicated Payment Form (7 cols) -->
      <div class="lg:col-span-7 bg-white rounded-3xl p-6 sm:p-8 shadow-sm border border-slate-100 space-y-6">
        <div>
          <div class="flex items-center justify-between">
            <h3 class="text-xl font-bold text-slate-900">Select Payment Method</h3>
            <span 
              :class="getStatusBadgeClass(booking.payment_status)"
              class="text-[10px] font-bold px-2.5 py-0.5 rounded-full uppercase">
              {{ booking.payment_status.replace('_', ' ') }}
            </span>
          </div>
          <p class="text-xs text-slate-500 mt-1">
            Choose your preferred Ethiopian payment channel to confirm your reservation.
          </p>
        </div>

        <!-- Success Message Banner -->
        <div v-if="successBanner" class="p-4 bg-emerald-50 border border-emerald-200 rounded-2xl text-xs text-emerald-900 space-y-2">
          <div class="flex items-center gap-2 font-bold text-sm text-emerald-800">
            <span>🎉</span>
            <span>{{ successBanner.title }}</span>
          </div>
          <p>{{ successBanner.message }}</p>
          <div class="flex items-center gap-3 pt-2">
            <router-link to="/my-bookings" class="px-4 py-1.5 bg-[#0B4D34] text-white font-bold rounded-lg transition text-[11px]">
              View My Bookings
            </router-link>
            <button @click="openInvoiceModal" class="px-3 py-1.5 bg-white border border-emerald-300 text-emerald-900 font-bold rounded-lg text-[11px]">
              Download Tax Invoice
            </button>
          </div>
        </div>

        <!-- Already Paid Message -->
        <div v-else-if="booking.payment_status === 'paid' || booking.payment_status === 'verified'" class="p-6 bg-emerald-50 border border-emerald-200 rounded-2xl text-center space-y-3">
          <div class="w-12 h-12 rounded-full bg-emerald-100 text-emerald-800 text-2xl flex items-center justify-center mx-auto">✓</div>
          <h4 class="font-bold text-slate-900 text-base">Payment Fully Cleared</h4>
          <p class="text-xs text-slate-600 max-w-sm mx-auto">
            This reservation is settled in full. You can access your digital room key card or view your tax invoice.
          </p>
          <div class="flex items-center justify-center gap-3 pt-2">
            <router-link to="/guest/portal" class="px-4 py-2 bg-[#0B4D34] text-white text-xs font-bold rounded-full">
              Open Guest VIP Key
            </router-link>
            <button @click="openInvoiceModal" class="px-4 py-2 bg-slate-100 text-slate-800 text-xs font-bold rounded-full">
              View Tax Invoice
            </button>
          </div>
        </div>

        <!-- Payment Channels Selection (when pending) -->
        <div v-else class="space-y-5">
          <!-- Method Selection Tabs -->
          <div class="grid grid-cols-3 gap-3">
            <button 
              type="button"
              @click="selectedMethod = 'cbe_birr'"
              :class="selectedMethod === 'cbe_birr' ? 'border-[#0B4D34] bg-emerald-50/50 shadow-sm' : 'border-slate-200 hover:bg-slate-50'"
              class="p-3.5 rounded-2xl border-2 text-center transition flex flex-col items-center justify-center gap-1.5 cursor-pointer">
              <span class="text-2xl">🏦</span>
              <span class="text-xs font-bold text-slate-800">CBE Birr / Bank</span>
              <span class="text-[10px] text-slate-400 font-medium">Receipt Upload</span>
            </button>

            <button 
              type="button"
              @click="selectedMethod = 'telebirr'"
              :class="selectedMethod === 'telebirr' ? 'border-[#0B4D34] bg-emerald-50/50 shadow-sm' : 'border-slate-200 hover:bg-slate-50'"
              class="p-3.5 rounded-2xl border-2 text-center transition flex flex-col items-center justify-center gap-1.5 cursor-pointer">
              <span class="text-2xl">📱</span>
              <span class="text-xs font-bold text-slate-800">Telebirr</span>
              <span class="text-[10px] text-slate-400 font-medium">SuperApp Pay</span>
            </button>

            <button 
              type="button"
              @click="selectedMethod = 'chapa'"
              :class="selectedMethod === 'chapa' ? 'border-[#0B4D34] bg-emerald-50/50 shadow-sm' : 'border-slate-200 hover:bg-slate-50'"
              class="p-3.5 rounded-2xl border-2 text-center transition flex flex-col items-center justify-center gap-1.5 cursor-pointer">
              <span class="text-2xl">💳</span>
              <span class="text-xs font-bold text-slate-800">Chapa Pay</span>
              <span class="text-[10px] text-slate-400 font-medium">Cards & Wallets</span>
            </button>
          </div>

          <!-- OPTION A: CBE BIRR & BANK TRANSFER (Screenshot Receipt Upload) -->
          <div v-if="selectedMethod === 'cbe_birr'" class="space-y-4 p-4 rounded-2xl bg-slate-50 border border-slate-200">
            <!-- Bank Account Notice -->
            <div class="bg-white p-3.5 rounded-xl border border-slate-200 space-y-1 text-xs">
              <div class="font-bold text-slate-900 flex items-center justify-between">
                <span>Commercial Bank of Ethiopia (CBE)</span>
                <span class="text-[10px] bg-emerald-100 text-emerald-800 font-bold px-2 py-0.5 rounded">Verified Account</span>
              </div>
              <div class="text-slate-600">Account Name: <strong>Ethiopian Luxury Resort PMS</strong></div>
              <div class="text-slate-600 font-mono">Account Number: <strong class="text-[#0B4D34]">1000294819201</strong></div>
              <div class="text-slate-400 text-[10px]">Transfer total of {{ booking.total_price_etb?.toLocaleString() }} ETB, then upload your transaction screenshot below.</div>
            </div>

            <!-- Receipt Dropzone & Inputs -->
            <div class="space-y-3">
              <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">
                  1. Transaction Screenshot / Receipt <span class="text-rose-500">*</span>
                </label>
                <input 
                  type="file" 
                  accept="image/*,.pdf"
                  @change="handleFileChange"
                  class="w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-[#0B4D34] file:text-white hover:file:bg-[#083c28] cursor-pointer" />
              </div>

              <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">
                  2. Bank Transaction Reference (FT / Ref #) <span class="text-rose-500">*</span>
                </label>
                <input 
                  v-model="cbeTxReference" 
                  type="text" 
                  placeholder="e.g. FT26319801824"
                  class="w-full px-3.5 py-2.5 bg-white border border-slate-200 rounded-xl text-xs font-mono focus:outline-none focus:border-[#0B4D34]" />
              </div>

              <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">
                  3. Paid Amount (ETB)
                </label>
                <input 
                  v-model="cbeAmount" 
                  type="number" 
                  class="w-full px-3.5 py-2.5 bg-white border border-slate-200 rounded-xl text-xs font-bold text-[#0B4D34] focus:outline-none focus:border-[#0B4D34]" />
              </div>
            </div>

            <!-- Submit Bank Transfer Button -->
            <button 
              @click="submitBankReceipt"
              :disabled="submitting || !receiptFile || !cbeTxReference"
              class="w-full py-3.5 bg-[#0B4D34] hover:bg-[#083c28] text-white font-bold text-xs rounded-xl shadow-md transition disabled:opacity-50 flex items-center justify-center gap-2 cursor-pointer">
              <span v-if="submitting" class="animate-spin text-sm">⏳</span>
              <span>{{ submitting ? 'Submitting Receipt to Accounts...' : 'Submit Payment Proof for Review' }}</span>
            </button>
          </div>

          <!-- OPTION B: TELEBIRR (H5 / QR / Gateway) -->
          <div v-else-if="selectedMethod === 'telebirr'" class="space-y-4 p-4 rounded-2xl bg-slate-50 border border-slate-200">
            <div class="text-center py-3 space-y-2">
              <div class="w-12 h-12 bg-emerald-100 text-emerald-800 text-2xl rounded-2xl flex items-center justify-center mx-auto">📱</div>
              <h4 class="font-bold text-slate-900 text-sm">Telebirr SuperApp Direct Payment</h4>
              <p class="text-xs text-slate-500 max-w-sm mx-auto">
                Pay instantly using your Telebirr mobile wallet with zero transaction fees.
              </p>
            </div>

            <div class="p-3.5 bg-white rounded-xl border border-slate-200 text-xs space-y-2">
              <div class="flex justify-between">
                <span class="text-slate-500">Amount Due:</span>
                <span class="font-bold text-[#0B4D34] text-sm">{{ booking.total_price_etb?.toLocaleString() }} ETB</span>
              </div>
              <div class="flex justify-between">
                <span class="text-slate-500">Merchant Code:</span>
                <span class="font-mono font-bold text-slate-800">100124 (Luxury Ethiopian Resort)</span>
              </div>
            </div>

            <button 
              @click="initializeGatewayPayment('telebirr')"
              :disabled="submitting"
              class="w-full py-3.5 bg-gradient-to-r from-[#0B4D34] to-[#107550] hover:brightness-110 text-white font-bold text-xs rounded-xl shadow-md transition flex items-center justify-center gap-2 cursor-pointer">
              <span v-if="submitting" class="animate-spin text-sm">⏳</span>
              <span>{{ submitting ? 'Initiating Telebirr...' : 'Proceed with Telebirr Pay' }}</span>
            </button>
          </div>

          <!-- OPTION C: CHAPA GATEWAY -->
          <div v-else-if="selectedMethod === 'chapa'" class="space-y-4 p-4 rounded-2xl bg-slate-50 border border-slate-200">
            <div class="text-center py-3 space-y-2">
              <div class="w-12 h-12 bg-emerald-100 text-emerald-800 text-2xl rounded-2xl flex items-center justify-center mx-auto">💳</div>
              <h4 class="font-bold text-slate-900 text-sm">Chapa Integrated Payment Gateway</h4>
              <p class="text-xs text-slate-500 max-w-sm mx-auto">
                Pay securely using Visa, MasterCard, Telebirr, or CBE Birr via Chapa.
              </p>
            </div>

            <div class="p-3.5 bg-white rounded-xl border border-slate-200 text-xs space-y-2">
              <div class="flex justify-between">
                <span class="text-slate-500">Order Total:</span>
                <span class="font-bold text-[#0B4D34] text-sm">{{ booking.total_price_etb?.toLocaleString() }} ETB</span>
              </div>
              <div class="flex justify-between">
                <span class="text-slate-500">Security:</span>
                <span class="text-emerald-700 font-semibold">✓ Chapa PCI-DSS Level 1 Encrypted</span>
              </div>
            </div>

            <button 
              @click="initializeGatewayPayment('chapa')"
              :disabled="submitting"
              class="w-full py-3.5 bg-gradient-to-r from-[#0B4D34] to-[#107550] hover:brightness-110 text-white font-bold text-xs rounded-xl shadow-md transition flex items-center justify-center gap-2 cursor-pointer">
              <span v-if="submitting" class="animate-spin text-sm">⏳</span>
              <span>{{ submitting ? 'Connecting to Chapa...' : 'Proceed to Chapa Secure Checkout' }}</span>
            </button>
          </div>

          <!-- Security Footnote -->
          <div class="pt-2 text-center text-[11px] text-slate-400 space-y-1">
            <p>🔒 256-Bit SSL Encrypted Payment Processing</p>
            <p>Zero raw card or CVV details are ever stored in the database.</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Official Tax Invoice Modal -->
    <TaxInvoiceModal 
      :is-open="showInvoiceModal" 
      :invoice-data="activeInvoiceData" 
      @close="showInvoiceModal = false" />
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import TaxInvoiceModal from '../components/TaxInvoiceModal.vue';

const route = useRoute();
const router = useRouter();

const booking = ref(null);
const loading = ref(false);
const fetchError = ref('');
const submitting = ref(false);

const selectedMethod = ref('cbe_birr');
const receiptFile = ref(null);
const cbeTxReference = ref('');
const cbeAmount = ref(0);

const successBanner = ref(null);
const showInvoiceModal = ref(false);
const activeInvoiceData = ref(null);

function getAuthHeaders() {
  const token = localStorage.getItem('hotel_auth_token');
  return token ? { 'Authorization': `Bearer ${token}` } : {};
}

async function fetchBookingDetails() {
  const id = route.params.id;
  if (!id) {
    fetchError.value = 'No reservation ID was provided.';
    return;
  }

  loading.value = true;
  fetchError.value = '';

  try {
    const res = await fetch(`/api/v1/bookings/${id}`, { headers: getAuthHeaders() });
    const data = await res.json();
    if (!res.ok) throw new Error(data.error || 'Failed to load booking details.');

    booking.value = data.booking;
    cbeAmount.value = data.booking.total_price_etb;
  } catch (err) {
    fetchError.value = err.message;
  } finally {
    loading.value = false;
  }
}

function handleFileChange(event) {
  const files = event.target.files;
  if (files && files.length > 0) {
    receiptFile.value = files[0];
  }
}

async function submitBankReceipt() {
  if (submitting.value || !receiptFile.value || !cbeTxReference.value) return;
  submitting.value = true;

  try {
    const formData = new FormData();
    formData.append('receipt', receiptFile.value);
    formData.append('booking_id', booking.value.id);
    formData.append('claimed_amount_etb', cbeAmount.value);
    formData.append('transaction_reference', cbeTxReference.value.trim());

    const res = await fetch('/api/v1/payments/upload-receipt', {
      method: 'POST',
      headers: getAuthHeaders(),
      body: formData
    });

    const data = await res.json();
    if (!res.ok) throw new Error(data.error || 'Failed to submit payment receipt');

    booking.value.payment_status = 'pending_verification';
    booking.value.booking_status = 'pending_verification';

    successBanner.value = {
      title: 'Payment Proof Submitted for Accounts Review',
      message: 'Your transfer proof has been recorded. Our front desk audit team reviews and verifies submissions within a 20-minute SLA window.'
    };
  } catch (err) {
    alert('Error: ' + err.message);
  } finally {
    submitting.value = false;
  }
}

async function initializeGatewayPayment(gateway) {
  if (submitting.value) return;
  submitting.value = true;

  try {
    const res = await fetch('/api/v1/payments/initialize', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        ...getAuthHeaders()
      },
      body: JSON.stringify({
        booking_id: booking.value.id,
        gateway: gateway,
        return_url: `${window.location.origin}/payment/${booking.value.id}?settled=true`
      })
    });

    const data = await res.json();
    if (!res.ok) throw new Error(data.error || 'Failed to initialize payment gateway');

    if (data.checkout_url) {
      // In production, redirects to Chapa / Telebirr portal
      window.location.href = data.checkout_url;
    }
  } catch (err) {
    alert(err.message);
  } finally {
    submitting.value = false;
  }
}

async function openInvoiceModal() {
  if (!booking.value) return;
  try {
    const res = await fetch(`/api/v1/bookings/${booking.value.id}/invoice`, { headers: getAuthHeaders() });
    const data = await res.json();
    if (data.invoice) {
      activeInvoiceData.value = data.invoice;
      showInvoiceModal.value = true;
    }
  } catch (err) {
    console.error('Invoice error:', err);
  }
}

function getStatusBadgeClass(status) {
  if (status === 'paid' || status === 'verified') return 'bg-emerald-100 text-emerald-800';
  if (status === 'pending_verification') return 'bg-amber-100 text-amber-800';
  return 'bg-slate-100 text-slate-700';
}

onMounted(() => {
  fetchBookingDetails();
});
</script>
