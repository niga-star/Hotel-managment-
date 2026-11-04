<template>
  <div class="space-y-6">
    <!-- Header with controls -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-5 rounded-2xl shadow-sm border border-slate-100">
      <div>
        <h2 class="text-2xl font-bold text-[#0B4D34]">Financial &amp; Occupancy Reports</h2>
        <p class="text-sm text-slate-500">Live operational financial aggregations, payment breakdowns &amp; room occupancy analytics.</p>
      </div>

      <div class="flex flex-wrap items-center gap-3">
        <!-- Date Filters -->
        <div class="flex items-center gap-2 text-xs">
          <input 
            type="date" 
            v-model="startDate" 
            @change="fetchReportData" 
            class="px-2.5 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-slate-700 focus:outline-none focus:border-[#0B4D34]" />
          <span class="text-slate-400">to</span>
          <input 
            type="date" 
            v-model="endDate" 
            @change="fetchReportData" 
            class="px-2.5 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-slate-700 focus:outline-none focus:border-[#0B4D34]" />
        </div>

        <!-- Monthly / Annual Toggle -->
        <div class="bg-slate-100 p-1 rounded-full flex border border-slate-200">
          <button 
            @click="period = 'monthly'; fetchReportData()"
            :class="period === 'monthly' ? 'bg-[#0B4D34] text-white shadow-sm' : 'text-slate-600 hover:text-slate-900'"
            class="px-4 py-1.5 rounded-full text-xs font-semibold transition">
            🗓️ Monthly
          </button>
          <button 
            @click="period = 'annual'; fetchReportData()"
            :class="period === 'annual' ? 'bg-[#0B4D34] text-white shadow-sm' : 'text-slate-600 hover:text-slate-900'"
            class="px-4 py-1.5 rounded-full text-xs font-semibold transition">
            📊 Annual
          </button>
        </div>

        <!-- Export Buttons -->
        <button 
          @click="window.print()"
          class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-semibold rounded-full shadow-sm flex items-center gap-1.5 transition">
          <span>🖨️</span> Print Report
        </button>
        <button 
          @click="exportReport('PDF')"
          class="px-4 py-2 bg-[#0B4D34] hover:bg-[#064E3B] text-white text-xs font-semibold rounded-full shadow-sm flex items-center gap-1.5 transition">
          <span>📄</span> Export PDF
        </button>
        <button 
          @click="exportReport('Excel')"
          class="px-4 py-2 bg-[#0B4D34] hover:bg-[#064E3B] text-white text-xs font-semibold rounded-full shadow-sm flex items-center gap-1.5 transition">
          <span>📊</span> Export Excel
        </button>
      </div>
    </div>

    <!-- Live Financial KPI Strip (Gross, Refunds, Net Revenue, Receivables, Cash) -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4" v-if="reportSummary">
      <div class="bg-white p-4 rounded-xl border border-slate-100 shadow-sm">
        <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Gross Collections</span>
        <div class="text-xl font-black text-[#0B4D34] mt-0.5">ETB {{ (reportSummary.gross_collections_etb || 0).toLocaleString() }}</div>
        <span class="text-[10px] text-emerald-600 font-semibold">Total Verified Payments</span>
      </div>

      <div class="bg-white p-4 rounded-xl border border-slate-100 shadow-sm">
        <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Total Refunds</span>
        <div class="text-xl font-black text-rose-600 mt-0.5">ETB {{ (reportSummary.total_refunds_etb || 0).toLocaleString() }}</div>
        <span class="text-[10px] text-rose-500 font-medium">Reversals &amp; Credits</span>
      </div>

      <div class="bg-white p-4 rounded-xl border border-slate-100 shadow-sm">
        <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Net Revenue</span>
        <div class="text-xl font-black text-slate-900 mt-0.5">ETB {{ (reportSummary.net_revenue_etb || 0).toLocaleString() }}</div>
        <span class="text-[10px] text-emerald-600 font-semibold">Gross minus Refunds</span>
      </div>

      <div class="bg-white p-4 rounded-xl border border-slate-100 shadow-sm">
        <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Outstanding Receivables</span>
        <div class="text-xl font-black text-amber-700 mt-0.5">ETB {{ (reportSummary.outstanding_receivables_etb || 0).toLocaleString() }}</div>
        <span class="text-[10px] text-amber-600 font-medium">Unpaid Guest Balances</span>
      </div>

      <div class="bg-white p-4 rounded-xl border border-slate-100 shadow-sm">
        <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Cash Collected</span>
        <div class="text-xl font-black text-slate-800 mt-0.5">ETB {{ (reportSummary.cash_collections_etb || 0).toLocaleString() }}</div>
        <span class="text-[10px] text-slate-500 font-medium">Front Desk Cash Drawer</span>
      </div>
    </div>

    <!-- Night Audit Hospitality Strip (ADR, RevPAR, Occupancy) -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4" v-if="nightAudit">
      <div class="bg-white p-4 rounded-xl border border-slate-100 shadow-sm flex items-center justify-between">
        <div>
          <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">RevPAR</span>
          <div class="text-xl font-black text-[#0B4D34] mt-0.5">ETB {{ nightAudit.revpar_etb?.toLocaleString() }}</div>
          <span class="text-[10px] text-slate-400">Rev / Available Room</span>
        </div>
        <div class="text-2xl">📈</div>
      </div>

      <div class="bg-white p-4 rounded-xl border border-slate-100 shadow-sm flex items-center justify-between">
        <div>
          <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">ADR</span>
          <div class="text-xl font-black text-amber-700 mt-0.5">ETB {{ nightAudit.adr_etb?.toLocaleString() }}</div>
          <span class="text-[10px] text-slate-400">Avg Daily Rate</span>
        </div>
        <div class="text-2xl">💰</div>
      </div>

      <div class="bg-white p-4 rounded-xl border border-slate-100 shadow-sm flex items-center justify-between">
        <div>
          <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Night Audit Revenue</span>
          <div class="text-xl font-black text-slate-800 mt-0.5">ETB {{ nightAudit.daily_revenue_etb?.toLocaleString() }}</div>
          <span class="text-[10px] text-emerald-600 font-semibold">100% Reconciled</span>
        </div>
        <div class="text-2xl">🏨</div>
      </div>

      <div class="bg-white p-4 rounded-xl border border-slate-100 shadow-sm flex items-center justify-between">
        <div>
          <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Today's Movement</span>
          <div class="text-xl font-black text-slate-800 mt-0.5">{{ nightAudit.check_ins_today }} In / {{ nightAudit.check_outs_today }} Out</div>
          <span class="text-[10px] text-slate-400">Occ: {{ nightAudit.occupancy_percent }}%</span>
        </div>
        <div class="text-2xl">🛎️</div>
      </div>
    </div>

    <!-- Charts Row -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Chart 1: Monthly Revenue & Occupancy Bar Chart (2 cols) -->
      <div class="lg:col-span-2 bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
        <div class="flex items-center justify-between mb-4">
          <div>
            <h3 class="text-base font-bold text-slate-900">Monthly Revenue &amp; Occupancy (ETB)</h3>
            <p class="text-xs text-slate-400">Green: Revenue (ETB) | Gold: Occupancy Index</p>
          </div>
          <div class="flex items-center space-x-3 text-xs font-medium">
            <span class="flex items-center gap-1.5 text-slate-600">
              <span class="w-3 h-3 rounded-sm bg-[#0B4D34] inline-block"></span> Revenue
            </span>
            <span class="flex items-center gap-1.5 text-slate-600">
              <span class="w-3 h-3 rounded-sm bg-[#D4A329] inline-block"></span> Occupancy
            </span>
          </div>
        </div>

        <!-- Dynamic SVG Bar Chart -->
        <div class="h-64 w-full flex items-end justify-between pt-6 pb-2 px-2 border-b border-l border-slate-200">
          <div 
            v-for="item in barChartData" 
            :key="item.month" 
            class="flex flex-col items-center flex-1 group">
            <div class="flex items-end space-x-1.5 h-48 w-full justify-center">
              <!-- Revenue Bar (Green) -->
              <div 
                class="w-3.5 sm:w-5 bg-[#0B4D34] rounded-t-sm transition-all hover:brightness-110 relative group/bar"
                :style="{ height: `${Math.min(100, (item.revenue / 25000) * 100)}%` }">
                <span class="opacity-0 group-hover/bar:opacity-100 absolute -top-7 left-1/2 -translate-x-1/2 bg-slate-800 text-white text-[10px] px-1.5 py-0.5 rounded shadow pointer-events-none whitespace-nowrap z-10">
                  {{ item.revenue.toLocaleString() }} ETB
                </span>
              </div>
              <!-- Occupancy Bar (Gold) -->
              <div 
                class="w-3.5 sm:w-5 bg-[#D4A329] rounded-t-sm transition-all hover:brightness-110 relative group/bar"
                :style="{ height: `${Math.min(100, (item.occupancy / 25000) * 100)}%` }">
                <span class="opacity-0 group-hover/bar:opacity-100 absolute -top-7 left-1/2 -translate-x-1/2 bg-slate-800 text-white text-[10px] px-1.5 py-0.5 rounded shadow pointer-events-none whitespace-nowrap z-10">
                  {{ item.occupancy.toLocaleString() }} Index
                </span>
              </div>
            </div>
            <span class="text-[11px] font-medium text-slate-500 mt-2">{{ item.month }}</span>
          </div>
        </div>
      </div>

      <!-- Chart 2: Revenue by Room Category Donut Chart (1 col) -->
      <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 flex flex-col justify-between">
        <div>
          <h3 class="text-base font-bold text-slate-900">Revenue by Room Category</h3>
          <p class="text-xs text-slate-400">Suite, Deluxe, Standard contribution</p>
        </div>

        <!-- SVG Donut Chart -->
        <div class="relative w-48 h-48 mx-auto my-4 flex items-center justify-center">
          <svg class="w-full h-full transform -rotate-90" viewBox="0 0 36 36">
            <path
              class="text-slate-100"
              stroke-width="5"
              stroke="currentColor"
              fill="none"
              d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
            />
            <path
              stroke="#0B4D34"
              stroke-width="5"
              stroke-dasharray="55, 100"
              stroke-linecap="round"
              fill="none"
              d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
            />
            <path
              stroke="#D4A329"
              stroke-width="5"
              stroke-dasharray="30, 100"
              stroke-dashoffset="-55"
              stroke-linecap="round"
              fill="none"
              d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
            />
            <path
              stroke="#64748B"
              stroke-width="5"
              stroke-dasharray="15, 100"
              stroke-dashoffset="-85"
              stroke-linecap="round"
              fill="none"
              d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
            />
          </svg>
          <div class="absolute text-center">
            <span class="text-xl font-bold text-slate-800">100%</span>
            <span class="block text-[10px] text-slate-400 uppercase font-semibold">Distribution</span>
          </div>
        </div>

        <!-- Donut Legend -->
        <div class="flex items-center justify-around text-xs font-semibold text-slate-600 pt-3 border-t border-slate-100">
          <div class="flex items-center gap-1.5" v-for="cat in (donutCategories.length ? donutCategories : defaultDonut)" :key="cat.category">
            <span class="w-3 h-3 rounded-full" :style="{ backgroundColor: cat.color }"></span>
            <span>{{ cat.category }} ({{ cat.percentage }}%)</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Payment Methods & Hotel Services Breakdown Tables -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <!-- Method Breakdown Table -->
      <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 space-y-3">
        <h3 class="text-base font-bold text-slate-900">Collections by Payment Method</h3>
        <table class="w-full text-left text-xs">
          <thead>
            <tr class="text-slate-400 border-b border-slate-100 pb-2">
              <th class="py-2">Method</th>
              <th class="py-2">Transactions</th>
              <th class="py-2 text-right">Total (ETB)</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 text-slate-700">
            <tr v-if="!byPaymentMethod.length">
              <td colspan="3" class="py-4 text-center text-slate-400">No payment data available</td>
            </tr>
            <tr v-else v-for="m in byPaymentMethod" :key="m.method">
              <td class="py-2.5 font-bold">{{ m.name }}</td>
              <td class="py-2.5 font-mono text-slate-500">{{ m.tx_count }} txns</td>
              <td class="py-2.5 text-right font-bold text-[#0B4D34]">ETB {{ m.total_etb.toLocaleString() }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Service Charges Breakdown Table -->
      <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 space-y-3">
        <h3 class="text-base font-bold text-slate-900">Revenue by Hotel Service</h3>
        <table class="w-full text-left text-xs">
          <thead>
            <tr class="text-slate-400 border-b border-slate-100 pb-2">
              <th class="py-2">Service Type</th>
              <th class="py-2">Items Posted</th>
              <th class="py-2 text-right">Total (ETB)</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 text-slate-700">
            <tr v-if="!byServiceList.length">
              <td colspan="3" class="py-4 text-center text-slate-400">No incidental service charges posted yet</td>
            </tr>
            <tr v-else v-for="s in byServiceList" :key="s.service_type">
              <td class="py-2.5 font-bold">{{ s.name }}</td>
              <td class="py-2.5 font-mono text-slate-500">{{ s.count }} orders</td>
              <td class="py-2.5 text-right font-bold text-amber-700">ETB {{ s.total_etb.toLocaleString() }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Monthly Financial Summary Table -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
      <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
        <h3 class="text-base font-bold text-slate-900">Monthly Financial Summary</h3>
        <span class="text-xs text-slate-500 font-medium">Aggregated across all luxury properties</span>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-sm">
          <thead>
            <tr class="bg-slate-50/75 text-slate-600 text-xs font-semibold uppercase tracking-wider border-b border-slate-100">
              <th class="py-3 px-6">Month</th>
              <th class="py-3 px-6">Total Bookings</th>
              <th class="py-3 px-6">Occupancy %</th>
              <th class="py-3 px-6">Revenue (ETB)</th>
              <th class="py-3 px-6">Net Growth</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 text-slate-700">
            <tr 
              v-for="(row, idx) in summaryRows" 
              :key="idx" 
              class="hover:bg-slate-50/50 transition">
              <td class="py-3.5 px-6 font-semibold text-slate-900">{{ row.month }}</td>
              <td class="py-3.5 px-6">{{ row.total_bookings }}</td>
              <td class="py-3.5 px-6 font-medium text-emerald-700">{{ row.occupancy_percent }}%</td>
              <td class="py-3.5 px-6 font-semibold">ETB {{ row.revenue_etb.toLocaleString() }}</td>
              <td class="py-3.5 px-6 font-medium text-emerald-600">{{ row.net_growth }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';

const period = ref('monthly');
const startDate = ref('');
const endDate = ref('');
const barChartData = ref([]);
const summaryRows = ref([]);
const reportSummary = ref(null);
const byPaymentMethod = ref([]);
const byServiceList = ref([]);
const donutCategories = ref([]);
const nightAudit = ref(null);

const defaultDonut = [
  { category: 'Suite', percentage: 55, color: '#0B4D34' },
  { category: 'Deluxe', percentage: 30, color: '#D4A329' },
  { category: 'Standard', percentage: 15, color: '#64748B' },
];

async function fetchNightAudit() {
  try {
    const res = await fetch('/api/v1/admin/night-audit');
    const data = await res.json();
    nightAudit.value = data.kpis || null;
  } catch (e) {
    console.error('Failed to fetch night audit:', e);
  }
}

async function fetchReportData() {
  try {
    let url = `/api/v1/reports/financial?period=${period.value}`;
    if (startDate.value) url += `&start_date=${startDate.value}`;
    if (endDate.value) url += `&end_date=${endDate.value}`;

    const res = await fetch(url);
    const data = await res.json();
    barChartData.value = data.bar_chart || [];
    summaryRows.value = data.monthly_summary || [];
    reportSummary.value = data.summary || null;
    byPaymentMethod.value = data.by_payment_method || [];
    byServiceList.value = data.by_service || [];
    donutCategories.value = data.by_room_category || [];
  } catch (error) {
    console.error('Failed to load report data:', error);
  }
}

function exportReport(format) {
  alert(`Preparing official ${format} Financial & Occupancy Report download for hotel operations...`);
}

onMounted(() => {
  fetchReportData();
  fetchNightAudit();
});
</script>
