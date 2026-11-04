<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h2 class="text-2xl font-bold text-[#0B4D34]">Payments & Financial Settlement Desk</h2>
        <p class="text-xs text-slate-500">Live multi-gateway ledger, 20-minute receipt SLA review, official tax invoices & refunds.</p>
      </div>

      <div class="flex flex-wrap items-center gap-2">
        <span 
          v-if="metrics.overdue_reviews_count > 0"
          class="text-xs font-bold px-3 py-1 bg-rose-100 text-rose-800 rounded-full border border-rose-300 animate-pulse">
          ⚠️ {{ metrics.overdue_reviews_count }} Review{{ metrics.overdue_reviews_count === 1 ? '' : 's' }} Overdue (&gt; 20 min)
        </span>
        <button 
          @click="openRecordPaymentModal"
          class="px-4 py-2 bg-[#0B4D34] hover:bg-[#073826] text-white text-xs font-semibold rounded-full shadow-sm transition flex items-center gap-1.5">
          <span>➕</span> Record Payment
        </button>
        <button 
          @click="refreshCurrentTab"
          class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-full transition">
          🔄 Refresh
        </button>
      </div>
    </div>

    <!-- Top 5 Channel Metric Cards -->
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

      <!-- Card 5: Pending Proof Reviews -->
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

    <!-- Sub-Navigation Tabs: Transactions | Tax Invoices | Refunds -->
    <div class="bg-white p-2 rounded-2xl shadow-sm border border-slate-100 flex items-center gap-2">
      <button 
        @click="activeViewTab = 'transactions'"
        :class="activeViewTab === 'transactions' ? 'bg-[#0B4D34] text-white shadow-sm' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50'"
        class="px-5 py-2 rounded-xl text-xs font-bold transition flex items-center gap-2">
        <span>💳</span> Payment Transactions &amp; Receipts
      </button>
      <button 
        @click="activeViewTab = 'invoices'; fetchInvoices()"
        :class="activeViewTab === 'invoices' ? 'bg-[#0B4D34] text-white shadow-sm' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50'"
        class="px-5 py-2 rounded-xl text-xs font-bold transition flex items-center gap-2">
        <span>📄</span> Tax Invoices Ledger
      </button>
      <button 
        @click="activeViewTab = 'refunds'; fetchRefunds()"
        :class="activeViewTab === 'refunds' ? 'bg-[#0B4D34] text-white shadow-sm' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50'"
        class="px-5 py-2 rounded-xl text-xs font-bold transition flex items-center gap-2">
        <span>↩️</span> Refunds History
      </button>
    </div>

    <!-- TAB 1: Payment Transactions -->
    <div v-if="activeViewTab === 'transactions'" class="space-y-4">
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

      <!-- Transactions Table -->
      <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
          <h3 class="text-base font-bold text-slate-900">
            Payment Transactions ({{ payments.length }})
          </h3>
          <span class="text-xs text-slate-400">Sorted by SLA Urgency &amp; Submission Date</span>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse text-sm">
            <thead>
              <tr class="bg-slate-50 text-slate-600 text-xs font-semibold uppercase tracking-wider border-b border-slate-100">
                <th class="py-3 px-5">TX-REF &amp; Receipt</th>
                <th class="py-3 px-5">Guest Name</th>
                <th class="py-3 px-5">Room</th>
                <th class="py-3 px-5">Channel</th>
                <th class="py-3 px-5">Amount</th>
                <th class="py-3 px-5">SLA Target (20-Min)</th>
                <th class="py-3 px-5">Status</th>
                <th class="py-3 px-5">Proof / Evidence</th>
                <th class="py-3 px-5 text-right">Actions</th>
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
                <!-- TX & Receipt -->
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
                <!-- 20-Minute Target SLA Column -->
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
                    <span>{{ tx.status === 'verified' ? '✓' : (tx.status === 'rejected' ? '✕' : (tx.status === 'refunded' ? '↩️' : '⏳')) }}</span>
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
                <!-- Verification / Receipt Actions -->
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
                  <template v-else-if="tx.status === 'verified' || tx.status === 'completed'">
                    <button 
                      @click="openReceiptDialog(tx)"
                      class="px-2.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-800 text-[11px] font-semibold rounded-lg transition"
                      title="View Official Receipt">
                      🧾 Receipt
                    </button>
                    <button 
                      @click="openRefundDialog(tx)"
                      class="px-2.5 py-1.5 bg-amber-50 hover:bg-amber-100 text-amber-800 text-[11px] font-semibold rounded-lg border border-amber-200 transition"
                      title="Issue Refund">
                      ↩️ Refund
                    </button>
                  </template>
                  <span v-else class="text-xs text-slate-400">Settled</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- TAB 2: Invoices Ledger -->
    <div v-if="activeViewTab === 'invoices'" class="space-y-4">
      <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
          <h3 class="text-base font-bold text-slate-900">Persistent Tax Invoices</h3>
          <span class="text-xs text-slate-400">Unique fiscal invoices with zero duplication on refresh</span>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse text-sm">
            <thead>
              <tr class="bg-slate-50 text-slate-600 text-xs font-semibold uppercase tracking-wider border-b border-slate-100">
                <th class="py-3 px-5">Invoice #</th>
                <th class="py-3 px-5">Booking Ref</th>
                <th class="py-3 px-5">Guest</th>
                <th class="py-3 px-5">Issue Date</th>
                <th class="py-3 px-5">Total Amount</th>
                <th class="py-3 px-5">Paid</th>
                <th class="py-3 px-5">Balance Due</th>
                <th class="py-3 px-5">Status</th>
                <th class="py-3 px-5 text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-slate-700">
              <tr v-if="invoicesLoading" class="text-center">
                <td colspan="9" class="py-8 text-slate-400 text-xs">Loading tax invoices...</td>
              </tr>
              <tr v-else-if="invoicesList.length === 0" class="text-center">
                <td colspan="9" class="py-8 text-slate-400 text-xs">No invoices generated yet.</td>
              </tr>
              <tr v-else v-for="inv in invoicesList" :key="inv.id" class="hover:bg-slate-50/50 transition">
                <td class="py-3.5 px-5 font-mono text-xs font-bold text-slate-900">{{ inv.invoice_number }}</td>
                <td class="py-3.5 px-5 font-mono text-xs text-slate-600">{{ inv.booking?.reference_code || 'N/A' }}</td>
                <td class="py-3.5 px-5 text-xs font-semibold text-slate-800">{{ inv.booking?.guest?.full_name || 'Valued Guest' }}</td>
                <td class="py-3.5 px-5 text-xs text-slate-500">{{ inv.issue_date }}</td>
                <td class="py-3.5 px-5 text-xs font-bold text-slate-900">ETB {{ inv.total_amount_etb?.toLocaleString() }}</td>
                <td class="py-3.5 px-5 text-xs text-emerald-700 font-semibold">ETB {{ inv.paid_amount_etb?.toLocaleString() }}</td>
                <td class="py-3.5 px-5 text-xs font-bold" :class="inv.balance_due_etb > 0 ? 'text-rose-600' : 'text-slate-500'">
                  ETB {{ inv.balance_due_etb?.toLocaleString() }}
                </td>
                <td class="py-3.5 px-5">
                  <span :class="inv.status === 'paid' ? 'bg-emerald-100 text-emerald-800' : (inv.status === 'partially_paid' ? 'bg-amber-100 text-amber-800' : 'bg-rose-100 text-rose-800')"
                    class="text-[10px] font-bold px-2 py-0.5 rounded-full uppercase">
                    {{ inv.status?.replace('_', ' ') }}
                  </span>
                </td>
                <td class="py-3.5 px-5 text-right">
                  <a :href="'/api/v1/bookings/' + (inv.booking_id) + '/invoice'" target="_blank"
                    class="px-3 py-1.5 bg-[#0B4D34] hover:bg-[#073826] text-white text-[11px] font-semibold rounded-lg transition inline-block">
                    📄 View / Print
                  </a>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- TAB 3: Refunds Ledger -->
    <div v-if="activeViewTab === 'refunds'" class="space-y-4">
      <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
          <h3 class="text-base font-bold text-slate-900">Refunds Audit Ledger</h3>
          <span class="text-xs text-slate-400">Strictly tracked partial &amp; full refund history</span>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse text-sm">
            <thead>
              <tr class="bg-slate-50 text-slate-600 text-xs font-semibold uppercase tracking-wider border-b border-slate-100">
                <th class="py-3 px-5">Refund #</th>
                <th class="py-3 px-5">Original Payment</th>
                <th class="py-3 px-5">Amount (ETB)</th>
                <th class="py-3 px-5">Reason</th>
                <th class="py-3 px-5">Date</th>
                <th class="py-3 px-5">Status</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-slate-700">
              <tr v-if="refundsLoading" class="text-center">
                <td colspan="6" class="py-8 text-slate-400 text-xs">Loading refunds...</td>
              </tr>
              <tr v-else-if="refundsList.length === 0" class="text-center">
                <td colspan="6" class="py-8 text-slate-400 text-xs">No refund transactions recorded.</td>
              </tr>
              <tr v-else v-for="rf in refundsList" :key="rf.id" class="hover:bg-slate-50/50 transition">
                <td class="py-3.5 px-5 font-mono text-xs font-bold text-slate-900">{{ rf.refund_number }}</td>
                <td class="py-3.5 px-5 font-mono text-xs text-slate-600">{{ rf.payment?.transaction_id || rf.payment_id }}</td>
                <td class="py-3.5 px-5 text-xs font-bold text-rose-700">ETB {{ rf.amount_etb?.toLocaleString() }}</td>
                <td class="py-3.5 px-5 text-xs text-slate-600 max-w-xs truncate">{{ rf.reason }}</td>
                <td class="py-3.5 px-5 text-xs text-slate-500">{{ rf.processed_at ? new Date(rf.processed_at).toLocaleString() : rf.created_at }}</td>
                <td class="py-3.5 px-5">
                  <span class="bg-emerald-100 text-emerald-800 text-[10px] font-bold px-2 py-0.5 rounded-full uppercase">
                    {{ rf.status }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- MODAL 1: Record Front-Desk Payment -->
    <div v-if="showRecordModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-3xl overflow-hidden shadow-2xl max-w-lg w-full p-6 space-y-4 border border-slate-100">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
          <div>
            <h3 class="text-lg font-bold text-[#0B4D34]">Record Guest Payment</h3>
            <p class="text-xs text-slate-500">Record cash, card, bank transfer, or mobile payment directly.</p>
          </div>
          <button @click="showRecordModal = false" class="text-slate-400 hover:text-slate-600 text-sm">✕</button>
        </div>

        <form @submit.prevent="submitRecordPayment" class="space-y-4 text-xs">
          <div>
            <label class="block font-semibold text-slate-700 mb-1">Booking ID or Reference Code *</label>
            <input 
              v-model="paymentForm.booking_id" 
              required
              placeholder="e.g. bk-12345 or #ETH-12345" 
              class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-[#0B4D34]" />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block font-semibold text-slate-700 mb-1">Amount (ETB) *</label>
              <input 
                v-model.number="paymentForm.amount_etb" 
                type="number" 
                step="0.01" 
                min="0.01"
                required
                placeholder="ETB 0.00" 
                class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-[#0B4D34]" />
            </div>

            <div>
              <label class="block font-semibold text-slate-700 mb-1">Payment Method *</label>
              <select 
                v-model="paymentForm.payment_method" 
                required
                class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-[#0B4D34]">
                <option value="cash">Cash (Front Desk)</option>
                <option value="bank_transfer">Bank Transfer</option>
                <option value="telebirr">Telebirr Mobile Pay</option>
                <option value="cbe_birr">CBE Birr Mobile Pay</option>
                <option value="card">Credit / Debit Card (POS)</option>
                <option value="chapa">Chapa Gateway</option>
                <option value="other">Other Method</option>
              </select>
            </div>
          </div>

          <div>
            <label class="block font-semibold text-slate-700 mb-1">Transaction Reference / Slip #</label>
            <input 
              v-model="paymentForm.transaction_reference" 
              placeholder="e.g. TX-984712 or Slip #00392" 
              class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-[#0B4D34]" />
          </div>

          <div class="flex items-center gap-2 p-3 bg-emerald-50/60 rounded-xl border border-emerald-100">
            <input 
              type="checkbox" 
              id="is_advance_deposit"
              v-model="paymentForm.is_advance_deposit" 
              class="rounded text-[#0B4D34] focus:ring-[#0B4D34]" />
            <label for="is_advance_deposit" class="font-semibold text-emerald-950 cursor-pointer">
              Mark as Advance Reservation Deposit
            </label>
          </div>

          <div>
            <label class="block font-semibold text-slate-700 mb-1">Notes / Cashier Remarks</label>
            <textarea 
              v-model="paymentForm.notes" 
              rows="2"
              placeholder="e.g. Received at desk, customer requested receipt" 
              class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-[#0B4D34]"></textarea>
          </div>

          <div class="flex gap-2 pt-2 border-t border-slate-100">
            <button 
              type="button" 
              @click="showRecordModal = false"
              class="flex-1 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl transition">
              Cancel
            </button>
            <button 
              type="submit" 
              :disabled="recordSubmitting"
              class="flex-1 py-2.5 bg-[#0B4D34] hover:bg-[#073826] text-white font-bold rounded-xl shadow transition disabled:opacity-50">
              {{ recordSubmitting ? 'Recording...' : '✓ Record & Issue Receipt' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- MODAL 2: Official Payment Receipt View & Print -->
    <div v-if="receiptData" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-3xl overflow-hidden shadow-2xl max-w-md w-full p-6 space-y-4 border border-slate-100 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
          <div>
            <h3 class="text-base font-bold text-[#0B4D34]">Official Payment Receipt</h3>
            <p class="text-[11px] font-mono text-slate-400">{{ receiptData.receipt_number }}</p>
          </div>
          <button @click="receiptData = null" class="text-slate-400 hover:text-slate-600 text-sm">✕</button>
        </div>

        <!-- Receipt Print Template -->
        <div class="bg-slate-50 p-5 rounded-2xl border border-slate-200 space-y-3 font-sans text-xs" id="printable-receipt">
          <div class="text-center pb-2 border-b border-slate-200">
            <div class="font-black text-slate-900 text-sm">{{ receiptData.hotel?.name }}</div>
            <div class="text-[10px] text-slate-500">{{ receiptData.hotel?.address }}</div>
            <div class="text-[10px] text-slate-500">TIN: {{ receiptData.hotel?.tin }} | VAT: {{ receiptData.hotel?.vat_reg }}</div>
          </div>

          <div class="grid grid-cols-2 gap-2 text-[11px]">
            <div>
              <span class="text-slate-400 block">Receipt No:</span>
              <span class="font-mono font-bold text-slate-900">{{ receiptData.receipt_number }}</span>
            </div>
            <div>
              <span class="text-slate-400 block">Date &amp; Time:</span>
              <span class="font-mono text-slate-800">{{ receiptData.issue_date }}</span>
            </div>
            <div>
              <span class="text-slate-400 block">Guest Name:</span>
              <span class="font-bold text-slate-900">{{ receiptData.guest?.name }}</span>
            </div>
            <div>
              <span class="text-slate-400 block">Booking Ref:</span>
              <span class="font-mono text-slate-800">{{ receiptData.stay?.reference_code }}</span>
            </div>
          </div>

          <div class="p-3 bg-white rounded-xl border border-slate-200 text-center space-y-1">
            <span class="text-slate-400 uppercase text-[10px] font-bold">Amount Paid ({{ receiptData.payment?.payment_type }})</span>
            <div class="text-2xl font-black text-[#0B4D34]">ETB {{ receiptData.payment?.amount_etb?.toLocaleString() }}</div>
            <span class="text-[10px] text-slate-500 font-mono">Method: {{ receiptData.payment?.payment_method }} (Ref: {{ receiptData.transaction_id }})</span>
          </div>

          <div class="border-t border-slate-200 pt-2 text-[11px] space-y-1">
            <div class="flex justify-between">
              <span class="text-slate-500">Gross Stay Total:</span>
              <span class="font-bold">ETB {{ receiptData.balance_summary?.stay_gross_total_etb?.toLocaleString() }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-slate-500">Total Verified Payments:</span>
              <span class="font-bold text-emerald-700">ETB {{ receiptData.balance_summary?.total_verified_paid_etb?.toLocaleString() }}</span>
            </div>
            <div class="flex justify-between text-xs font-bold border-t border-slate-200 pt-1">
              <span>Remaining Balance:</span>
              <span :class="receiptData.balance_summary?.remaining_balance_after_payment_etb > 0 ? 'text-rose-600' : 'text-emerald-700'">
                ETB {{ receiptData.balance_summary?.remaining_balance_after_payment_etb?.toLocaleString() }}
              </span>
            </div>
          </div>

          <div class="text-center pt-2 text-[10px] text-slate-400 italic">
            Processed by: {{ receiptData.cashier?.name }} • Thank you for staying with us!
          </div>
        </div>

        <!-- Buttons -->
        <div class="flex gap-2 pt-2">
          <button 
            @click="receiptData = null" 
            class="flex-1 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition">
            Close
          </button>
          <button 
            @click="printReceipt" 
            class="flex-1 py-2.5 bg-[#0B4D34] hover:bg-[#073826] text-white text-xs font-bold rounded-xl shadow transition flex items-center justify-center gap-1.5">
            <span>🖨️</span> Print Receipt
          </button>
        </div>
      </div>
    </div>

    <!-- MODAL 3: Issue Refund Dialog -->
    <div v-if="refundModalTx" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-3xl overflow-hidden shadow-2xl max-w-md w-full p-6 space-y-4 border border-slate-100">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
          <div>
            <h3 class="text-base font-bold text-rose-700">Issue Payment Refund</h3>
            <p class="text-xs text-slate-400 font-mono">{{ refundModalTx.transaction_id }}</p>
          </div>
          <button @click="refundModalTx = null" class="text-slate-400 hover:text-slate-600 text-sm">✕</button>
        </div>

        <form @submit.prevent="submitRefund" class="space-y-4 text-xs">
          <div class="p-3 bg-amber-50 rounded-xl border border-amber-200 text-amber-900 space-y-1">
            <div class="flex justify-between">
              <span>Original Paid Amount:</span>
              <span class="font-bold">ETB {{ refundModalTx.amount_etb?.toLocaleString() }}</span>
            </div>
            <div class="flex justify-between">
              <span>Already Refunded:</span>
              <span class="font-bold">ETB {{ (refundModalTx.refunded_amount_etb || 0)?.toLocaleString() }}</span>
            </div>
            <div class="flex justify-between border-t border-amber-200 pt-1 font-bold text-rose-700">
              <span>Maximum Refundable:</span>
              <span>ETB {{ ((refundModalTx.amount_etb || 0) - (refundModalTx.refunded_amount_etb || 0))?.toLocaleString() }}</span>
            </div>
          </div>

          <div>
            <label class="block font-semibold text-slate-700 mb-1">Refund Amount (ETB) *</label>
            <input 
              v-model.number="refundForm.amount_etb" 
              type="number" 
              step="0.01" 
              min="0.01"
              :max="(refundModalTx.amount_etb || 0) - (refundModalTx.refunded_amount_etb || 0)"
              required
              class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-rose-600 font-bold text-slate-900" />
          </div>

          <div>
            <label class="block font-semibold text-slate-700 mb-1">Reason for Refund *</label>
            <textarea 
              v-model="refundForm.reason" 
              rows="3"
              required
              placeholder="e.g. Early departure, service dissatisfaction, duplicate charge correction" 
              class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-rose-600"></textarea>
          </div>

          <div class="flex gap-2 pt-2 border-t border-slate-100">
            <button 
              type="button" 
              @click="refundModalTx = null"
              class="flex-1 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl transition">
              Cancel
            </button>
            <button 
              type="submit" 
              :disabled="refundSubmitting"
              class="flex-1 py-2.5 bg-rose-600 hover:bg-rose-700 text-white font-bold rounded-xl shadow transition disabled:opacity-50">
              {{ refundSubmitting ? 'Processing...' : 'Confirm Refund' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Full Payment Evidence Review Modal -->
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
            ✓ Approve Payment &amp; Confirm Booking
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

const activeViewTab = ref('transactions');
const activeFilter = ref('all');
const searchQuery = ref('');
const payments = ref([]);
const loading = ref(false);
const reviewModalTx = ref(null);

const invoicesList = ref([]);
const invoicesLoading = ref(false);

const refundsList = ref([]);
const refundsLoading = ref(false);

const showRecordModal = ref(false);
const recordSubmitting = ref(false);
const paymentForm = ref({
  booking_id: '',
  amount_etb: null,
  payment_method: 'cash',
  transaction_reference: '',
  is_advance_deposit: false,
  notes: '',
});

const receiptData = ref(null);

const refundModalTx = ref(null);
const refundSubmitting = ref(false);
const refundForm = ref({
  amount_etb: null,
  reason: '',
});

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
  if (method === 'cash') return 'bg-amber-100 text-amber-800';
  return 'bg-slate-100 text-slate-700';
}

function getStatusBadgeClass(status) {
  if (status === 'verified') return 'bg-emerald-100 text-emerald-800';
  if (status === 'pending_verification') return 'bg-amber-100 text-amber-800';
  if (status === 'rejected') return 'bg-rose-100 text-rose-800';
  if (status === 'refunded') return 'bg-purple-100 text-purple-800';
  return 'bg-slate-100 text-slate-700';
}

function selectFilter(filterName) {
  activeFilter.value = filterName;
  fetchPayments();
}

function refreshCurrentTab() {
  if (activeViewTab.value === 'transactions') fetchPayments();
  else if (activeViewTab.value === 'invoices') fetchInvoices();
  else if (activeViewTab.value === 'refunds') fetchRefunds();
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

async function fetchInvoices() {
  invoicesLoading.value = true;
  try {
    const res = await fetch('/api/v1/invoices', { headers: getAuthHeaders() });
    const data = await res.json();
    invoicesList.value = data.data || [];
  } catch (err) {
    console.error('Error fetching invoices:', err);
  } finally {
    invoicesLoading.value = false;
  }
}

async function fetchRefunds() {
  refundsLoading.value = true;
  try {
    const res = await fetch('/api/v1/refunds', { headers: getAuthHeaders() });
    const data = await res.json();
    refundsList.value = data.data || [];
  } catch (err) {
    console.error('Error fetching refunds:', err);
  } finally {
    refundsLoading.value = false;
  }
}

function openRecordPaymentModal() {
  paymentForm.value = {
    booking_id: '',
    amount_etb: null,
    payment_method: 'cash',
    transaction_reference: '',
    is_advance_deposit: false,
    notes: '',
  };
  showRecordModal.value = true;
}

async function submitRecordPayment() {
  recordSubmitting.value = true;
  try {
    const idempotencyKey = 'idemp-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
    const res = await fetch('/api/v1/payments/record', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        ...getAuthHeaders()
      },
      body: JSON.stringify({
        ...paymentForm.value,
        idempotency_key: idempotencyKey,
      })
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.message || data.error || 'Failed to record payment');

    alert(`✓ Payment recorded successfully! Receipt #${data.receipt_number}`);
    showRecordModal.value = false;
    fetchPayments();
    if (data.payment) {
      openReceiptDialog(data.payment);
    }
  } catch (err) {
    alert(err.message);
  } finally {
    recordSubmitting.value = false;
  }
}

async function openReceiptDialog(tx) {
  try {
    const res = await fetch(`/api/v1/payments/${tx.id}/receipt`, { headers: getAuthHeaders() });
    const data = await res.json();
    if (res.ok && data.receipt) {
      receiptData.value = data.receipt;
    } else {
      alert(data.error || 'Failed to load receipt.');
    }
  } catch (err) {
    alert('Failed to load receipt.');
  }
}

function printReceipt() {
  window.print();
}

function openRefundDialog(tx) {
  refundModalTx.value = tx;
  const maxRefundable = (tx.amount_etb || 0) - (tx.refunded_amount_etb || 0);
  refundForm.value = {
    amount_etb: maxRefundable > 0 ? maxRefundable : 0,
    reason: '',
  };
}

async function submitRefund() {
  if (!refundModalTx.value) return;
  refundSubmitting.value = true;
  try {
    const res = await fetch(`/api/v1/payments/${refundModalTx.value.id}/refund`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        ...getAuthHeaders()
      },
      body: JSON.stringify(refundForm.value)
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.error || data.message || 'Refund failed');

    alert(`✓ Refund processed successfully! Ref #${data.refund?.refund_number}`);
    refundModalTx.value = null;
    fetchPayments();
  } catch (err) {
    alert(err.message);
  } finally {
    refundSubmitting.value = false;
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

onMounted(() => {
  fetchPayments();
});
</script>
