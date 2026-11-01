<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h2 class="text-2xl font-bold text-[#0B4D34]">Payments & Financial Settlement Desk</h2>
        <p class="text-xs text-slate-500">Live multi-gateway ledger, 20-minute receipt SLA review & transaction filters.</p>
      </div>

      <div class="flex items-center gap-2">
        <span 
          v-if="metrics.overdue_reviews_count > 0"
          class="text-xs font-bold px-3 py-1 bg-rose-100 text-rose-800 rounded-full border border-rose-300 animate-pulse">
          ⚠️ {{ metrics.overdue_reviews_count }} Review{{ metrics.overdue_reviews_count === 1 ? '' : 's' }} Overdue (&gt; 20 min)
        </span>
        <button 
          @click="fetchPayments"
          class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-full transition">
          🔄 Refresh Ledger
        </button>
      </div>
    </div>

    <!-- Top 5 Channel Metric Cards - CLICKABLE & INTERACTIVE (Item 9) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
      <!-- Card 1: Total Verified Revenue -->
      <div 
        @click="selectFilter('revenue')"
        :class="activeFilter === 'revenue' ? 'ring-3 ring-[#0B4D34] shadow-md bg-emerald-50/40' : 'hover:shadow-md bg-white'"
        class="p-4 rounded-2xl shadow-sm border border-slate-100 flex items-center justify-between cursor-pointer transition">
        <div>
          <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Total Revenue</span>
          <div class="text-xl font-bold text-slate-900 mt-1">ETB {{ metrics.total_revenue_etb?.toLocaleString() }}</div>
          <span class="text-[11px] text-emerald-600 font-medium">Cleared verified receipts</span>
        </div>
        <div class="w-10 h-10 rounded-xl bg-emerald-50 text-[#0B4D34] flex items-center justify-center text-lg font-bold">
          📈
        </div>
      </div>

      <!-- Card 2: Telebirr -->
      <div 
        @click="selectFilter('telebirr')"
        :class="activeFilter === 'telebirr' ? 'ring-3 ring-[#0B4D34] shadow-md bg-blue-50/40' : 'hover:shadow-md bg-white'"
        class="p-4 rounded-2xl shadow-sm border border-slate-100 flex items-center justify-between cursor-pointer transition">
        <div>
          <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Telebirr</span>
          <div class="text-xl font-bold text-slate-900 mt-1">ETB {{ metrics.telebirr_total_etb?.toLocaleString() }}</div>
          <span class="text-[11px] text-blue-600 font-medium">{{ metrics.telebirr_count }} transactions</span>
        </div>
        <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-lg font-bold">
          📱
        </div>
      </div>

      <!-- Card 3: CBE Birr -->
      <div 
        @click="selectFilter('cbe_birr')"
        :class="activeFilter === 'cbe_birr' ? 'ring-3 ring-[#0B4D34] shadow-md bg-purple-50/40' : 'hover:shadow-md bg-white'"
        class="p-4 rounded-2xl shadow-sm border border-slate-100 flex items-center justify-between cursor-pointer transition">
        <div>
          <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">CBE Birr</span>
          <div class="text-xl font-bold text-slate-900 mt-1">ETB {{ metrics.cbe_birr_total_etb?.toLocaleString() }}</div>
          <span class="text-[11px] text-purple-600 font-medium">{{ metrics.cbe_birr_count }} transactions</span>
        </div>
        <div class="w-10 h-10 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center text-lg font-bold">
          🏛️
        </div>
      </div>

      <!-- Card 4: Chapa Gateway -->
      <div 
        @click="selectFilter('chapa')"
        :class="activeFilter === 'chapa' ? 'ring-3 ring-[#0B4D34] shadow-md bg-emerald-50/40' : 'hover:shadow-md bg-white'"
        class="p-4 rounded-2xl shadow-sm border border-slate-100 flex items-center justify-between cursor-pointer transition">
        <div>
          <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Chapa (Cards)</span>
          <div class="text-xl font-bold text-slate-900 mt-1">ETB {{ metrics.chapa_total_etb?.toLocaleString() }}</div>
          <span class="text-[11px] text-emerald-600 font-medium">{{ metrics.chapa_count }} transactions</span>
        </div>
        <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center text-lg font-bold">
          💳
        </div>
      </div>

      <!-- Card 5: Pending Proof Reviews (20-min Target SLA) -->
      <div 
        @click="selectFilter('pending')"
        :class="activeFilter === 'pending' ? 'ring-3 ring-amber-500 shadow-md bg-amber-50/50' : 'hover:shadow-md bg-white'"
        class="p-4 rounded-2xl shadow-sm border border-slate-100 flex items-center justify-between cursor-pointer transition">
        <div>
          <span class="text-[11px] font-semibold uppercase tracking-wider text-amber-700">Pending Reviews</span>
          <div class="text-xl font-bold text-amber-900 mt-1">{{ metrics.pending_verification_count }} Queue</div>
          <span class="text-[11px] text-rose-600 font-bold" v-if="metrics.overdue_reviews_count > 0">
            {{ metrics.overdue_reviews_count }} overdue (&gt;20m)
          </span>
          <span class="text-[11px] text-amber-700" v-else>
            Within 20m SLA
          </span>
        </div>
        <div class="w-10 h-10 rounded-xl bg-amber-100 text-amber-800 flex items-center justify-center text-lg font-bold">
          ⏳
        </div>
      </div>
    </div>

    <!-- Active Filter Banner & Search -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 bg-white p-3.5 rounded-2xl border border-slate-100">
      <div class="flex items-center gap-2 text-xs">
        <span class="text-slate-400 font-semibold">Active Filter:</span>
        <span class="px-3 py-1 rounded-full font-bold uppercase tracking-wider bg-emerald-100 text-[#0B4D34]">
          {{ activeFilter === 'all' ? 'All Transactions' : activeFilter?.replace('_', ' ') }}
        </span>
        <button 
          v-if="activeFilter !== 'all'"
          @click="selectFilter('all')"
          class="text-xs text-slate-500 hover:text-slate-800 underline">
          Clear Filter
        </button>
      </div>

      <div class="relative w-full sm:w-80">
        <input 
          v-model="searchQuery" 
          @input="fetchPayments"
          type="text" 
          placeholder="Search TX-REF, Booking #, Guest..." 
          class="w-full pl-9 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-full text-xs focus:outline-none focus:border-[#0B4D34]" />
        <span class="absolute left-3 top-2 text-xs text-slate-400">🔍</span>
      </div>
    </div>

    <!-- Transactions Table with 20-min SLA Review Target -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
      <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
        <h3 class="text-base font-bold text-slate-900">
          Payment Transactions ({{ payments.length }})
        </h3>
        <span class="text-xs text-slate-400">Sorted by SLA Urgency & Submission Date</span>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-sm">
          <thead>
            <tr class="bg-slate-50 text-slate-600 text-xs font-semibold uppercase tracking-wider border-b border-slate-100">
              <th class="py-3 px-5">TX-REF & Booking</th>
              <th class="py-3 px-5">Guest Name</th>
              <th class="py-3 px-5">Room</th>
              <th class="py-3 px-5">Channel</th>
              <th class="py-3 px-5">Amount</th>
              <th class="py-3 px-5">SLA Target (20-Min)</th>
              <th class="py-3 px-5">Status</th>
              <th class="py-3 px-5">Proof / Evidence</th>
              <th class="py-3 px-5 text-right">Verification Action</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 text-slate-700">
            <tr v-if="loading" class="text-center">
              <td colspan="9" class="py-8 text-slate-400 text-xs">Loading payment transactions...</td>
            </tr>
            <tr v-else-if="payments.length === 0" class="text-center">
              <td colspan="9" class="py-8 text-slate-400 text-xs">No payment records found matching current filter.</td>
            </tr>
            <tr 
              v-else
              v-for="tx in payments" 
              :key="tx.id"
              :class="tx.sla?.is_overdue ? 'bg-rose-50/30' : 'hover:bg-slate-50/50'"
              class="transition">
              <!-- TX & Booking -->
              <td class="py-3.5 px-5 font-mono text-xs">
                <div class="font-bold text-slate-900">{{ tx.transaction_id }}</div>
                <div class="text-[10px] text-slate-400">{{ tx.booking_ref }}</div>
              </td>
              <!-- Guest -->
              <td class="py-3.5 px-5">
                <div class="font-semibold text-slate-900 text-xs">{{ tx.guest_name }}</div>
                <div class="text-[10px] text-slate-400 font-mono">{{ tx.guest_phone }}</div>
              </td>
              <!-- Room -->
              <td class="py-3.5 px-5 text-xs text-slate-700 font-semibold">
                Room {{ tx.room_number }}
              </td>
              <!-- Channel -->
              <td class="py-3.5 px-5">
                <span 
                  :class="getMethodBadgeClass(tx.payment_method)"
                  class="text-[10px] font-bold px-2.5 py-0.5 rounded-full uppercase">
                  {{ tx.payment_method?.replace('_', ' ') }}
                </span>
              </td>
              <!-- Amount -->
              <td class="py-3.5 px-5 font-bold text-slate-900 text-xs">
                ETB {{ tx.amount_etb?.toLocaleString() }}
              </td>
              <!-- 20-Minute Target SLA Column (Item 8) -->
              <td class="py-3.5 px-5 text-xs">
                <div v-if="tx.status === 'pending_verification'">
                  <div class="flex items-center gap-1 font-mono font-bold" :class="tx.sla?.is_overdue ? 'text-rose-600' : 'text-amber-700'">
                    <span>{{ tx.sla?.is_overdue ? '⚠️' : '⏱️' }}</span>
                    <span>{{ tx.sla?.minutes_elapsed }}m elapsed</span>
                  </div>
                  <span 
                    :class="tx.sla?.is_overdue ? 'bg-rose-100 text-rose-800' : 'bg-amber-100 text-amber-800'"
                    class="text-[10px] font-bold px-1.5 py-0.2 rounded-md block w-fit mt-0.5">
                    {{ tx.sla?.is_overdue ? 'Overdue (>20m)' : `${tx.sla?.minutes_remaining}m target remaining` }}
                  </span>
                </div>
                <span v-else class="text-slate-400 text-[11px] font-mono">
                  Completed
                </span>
              </td>
              <!-- Status -->
              <td class="py-3.5 px-5">
                <span 
                  :class="getStatusBadgeClass(tx.status)"
                  class="text-[11px] font-bold px-2.5 py-1 rounded-full flex items-center w-fit gap-1 capitalize">
                  <span>{{ tx.status === 'verified' ? '✓' : (tx.status === 'rejected' ? '✕' : '⏳') }}</span>
                  <span>{{ tx.status?.replace('_', ' ') }}</span>
                </span>
              </td>
              <!-- Proof / Evidence Screenshot -->
              <td class="py-3.5 px-5">
                <div v-if="tx.receipt_url" class="flex items-center gap-1.5">
                  <img 
                    :src="tx.receipt_url" 
                    @click="viewReceiptModal(tx)"
                    class="w-10 h-10 rounded-lg object-cover border border-slate-200 cursor-pointer hover:scale-110 transition shadow-sm" 
                    alt="Receipt Thumbnail" />
                  <button 
                    @click="viewReceiptModal(tx)"
                    class="text-[10px] text-[#0B4D34] font-semibold hover:underline">
                    View Full
                  </button>
                </div>
                <span v-else class="text-[11px] text-slate-400 italic">No file upload</span>
              </td>
              <!-- Verification Action -->
              <td class="py-3.5 px-5 text-right space-x-1.5 whitespace-nowrap">
                <template v-if="tx.status === 'pending_verification'">
                  <button 
                    @click="reviewPayment(tx, 'approve')"
                    class="px-2.5 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-[11px] font-bold rounded-lg shadow-sm transition">
                    ✓ Approve
                  </button>
                  <button 
                    @click="promptRejectPayment(tx)"
                    class="px-2.5 py-1.5 bg-rose-600 hover:bg-rose-700 text-white text-[11px] font-bold rounded-lg shadow-sm transition">
                    ✕ Reject
                  </button>
                </template>
                <template v-else-if="tx.status === 'pending'">
                  <button 
                    @click="simulateWebhook(tx)"
                    class="px-2 py-1 bg-blue-600 hover:bg-blue-700 text-white text-[10px] font-semibold rounded-lg shadow-sm transition"
                    title="Simulate gateway IPN confirmation">
                    ⚡ Webhook
                  </button>
                </template>
                <span v-else class="text-xs text-slate-400">Settled</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Full Payment Evidence Review Modal (Item 8) -->
    <div v-if="reviewModalTx" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-3xl overflow-hidden shadow-2xl max-w-xl w-full p-6 space-y-4 border border-slate-100 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
          <div>
            <h3 class="text-lg font-bold text-slate-900">Payment Evidence Inspection</h3>
            <p class="text-xs text-slate-400 font-mono">{{ reviewModalTx.transaction_id }} (Booking {{ reviewModalTx.booking_ref }})</p>
          </div>
          <button @click="reviewModalTx = null" class="text-slate-400 hover:text-slate-600 text-sm">✕</button>
        </div>

        <!-- Evidence Breakdown -->
        <div class="grid grid-cols-2 gap-3 text-xs bg-slate-50 p-3.5 rounded-2xl border border-slate-200">
          <div>
            <span class="text-slate-500 block">Customer:</span>
            <span class="font-bold text-slate-900">{{ reviewModalTx.guest_name }} ({{ reviewModalTx.guest_phone }})</span>
          </div>
          <div>
            <span class="text-slate-500 block">Assigned Room:</span>
            <span class="font-bold text-slate-900">Room {{ reviewModalTx.room_number }} ({{ reviewModalTx.room_title }})</span>
          </div>
          <div>
            <span class="text-slate-500 block">Amount Claimed:</span>
            <span class="font-bold text-[#0B4D34] text-sm">ETB {{ reviewModalTx.claimed_amount_etb?.toLocaleString() }}</span>
          </div>
          <div>
            <span class="text-slate-500 block">Payment Method:</span>
            <span class="font-bold text-slate-900 uppercase">{{ reviewModalTx.payment_method }}</span>
          </div>
          <div>
            <span class="text-slate-500 block">Submitted At:</span>
            <span class="font-mono text-slate-700">{{ reviewModalTx.sla?.submitted_at ? new Date(reviewModalTx.sla.submitted_at).toLocaleString() : '--' }}</span>
          </div>
          <div>
            <span class="text-slate-500 block">20-Minute Target:</span>
            <span class="font-bold" :class="reviewModalTx.sla?.is_overdue ? 'text-rose-600' : 'text-amber-700'">
              {{ reviewModalTx.sla?.minutes_elapsed }}m elapsed ({{ reviewModalTx.sla?.is_overdue ? 'Overdue' : reviewModalTx.sla?.minutes_remaining + 'm left' }})
            </span>
          </div>
        </div>

        <!-- Screenshot High Resolution Preview -->
        <div class="text-center space-y-1">
          <span class="text-xs font-bold text-slate-700 block text-left">Uploaded Screenshot / Receipt:</span>
          <img 
            :src="reviewModalTx.receipt_url" 
            class="max-h-72 mx-auto rounded-2xl border shadow-sm object-contain w-full bg-slate-100" 
            alt="Full Receipt" />
        </div>

        <!-- Actions in Modal -->
        <div class="flex gap-2 pt-2 border-t border-slate-100" v-if="reviewModalTx.status === 'pending_verification'">
          <button 
            type="button" 
            @click="reviewPayment(reviewModalTx, 'approve')"
            class="flex-1 py-3 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow transition">
            ✓ Approve Payment & Confirm Booking
          </button>
          <button 
            type="button" 
            @click="promptRejectPayment(reviewModalTx)"
            class="px-4 py-3 bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold rounded-xl shadow transition">
            ✕ Reject Evidence
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';

const activeFilter = ref('all');
const searchQuery = ref('');
const payments = ref([]);
const loading = ref(false);
const reviewModalTx = ref(null);

const metrics = ref({
  total_revenue_etb: 0,
  telebirr_total_etb: 0,
  telebirr_count: 0,
  cbe_birr_total_etb: 0,
  cbe_birr_count: 0,
  chapa_total_etb: 0,
  chapa_count: 0,
  pending_verification_count: 0,
  rejected_count: 0,
  overdue_reviews_count: 0
});

function getAuthHeaders() {
  const token = localStorage.getItem('hotel_auth_token');
  return token ? { 'Authorization': `Bearer ${token}` } : {};
}

function getMethodBadgeClass(method) {
  if (method === 'telebirr') return 'bg-blue-100 text-blue-800';
  if (method === 'cbe_birr') return 'bg-purple-100 text-purple-800';
  if (method === 'chapa') return 'bg-emerald-100 text-emerald-800';
  return 'bg-slate-100 text-slate-700';
}

function getStatusBadgeClass(status) {
  if (status === 'verified') return 'bg-emerald-100 text-emerald-800';
  if (status === 'pending_verification') return 'bg-amber-100 text-amber-800';
  if (status === 'rejected') return 'bg-rose-100 text-rose-800';
  return 'bg-slate-100 text-slate-700';
}

function selectFilter(filterName) {
  activeFilter.value = filterName;
  fetchPayments();
}

async function fetchPayments() {
  loading.value = true;
  try {
    let url = `/api/v1/payments?filter=${activeFilter.value}`;
    if (searchQuery.value) {
      url += `&search=${encodeURIComponent(searchQuery.value)}`;
    }

    const res = await fetch(url, { headers: getAuthHeaders() });
    const data = await res.json();
    payments.value = data.payments || [];
    metrics.value = data.metrics || metrics.value;
  } catch (err) {
    console.error('Error fetching payments ledger:', err);
  } finally {
    loading.value = false;
  }
}

function viewReceiptModal(tx) {
  reviewModalTx.value = tx;
}

async function reviewPayment(tx, action, reason = '') {
  try {
    const res = await fetch(`/api/v1/payments/${tx.id}/review`, {
      method: 'PATCH',
      headers: {
        'Content-Type': 'application/json',
        ...getAuthHeaders()
      },
      body: JSON.stringify({ action, rejection_reason: reason })
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.error || 'Failed to review payment');

    alert(`✓ ${action === 'approve' ? 'Payment approved and booking confirmed!' : 'Payment rejected.'}`);
    reviewModalTx.value = null;
    fetchPayments();
  } catch (err) {
    alert(err.message);
  }
}

function promptRejectPayment(tx) {
  const reason = prompt('Please enter the reason for rejecting this payment evidence:');
  if (reason && reason.trim()) {
    reviewPayment(tx, 'reject', reason.trim());
  }
}

async function simulateWebhook(tx) {
  try {
    const res = await fetch('/api/v1/payments/simulate-webhook', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        transaction_id: tx.transaction_id,
        gateway: tx.payment_method === 'chapa' ? 'chapa' : 'telebirr',
        amount: tx.amount_etb
      })
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.error || 'Webhook failed');

    alert(data.message || 'Webhook processed successfully!');
    fetchPayments();
  } catch (err) {
    alert(err.message);
  }
}

onMounted(() => {
  fetchPayments();
});
</script>
