<template>
  <div class="space-y-6">
    <!-- Header with controls matching Screen 1 -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-5 rounded-2xl shadow-sm border border-slate-100">
      <div>
        <h2 class="text-2xl font-bold text-[#0B4D34]">Financial & Occupancy Reports</h2>
        <p class="text-sm text-slate-500">Live operational financial aggregations and room occupancy analytics.</p>
      </div>

      <div class="flex flex-wrap items-center gap-3">
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

    <!-- Charts Row -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Chart 1: Monthly Revenue & Occupancy Bar Chart (2 cols) -->
      <div class="lg:col-span-2 bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
        <div class="flex items-center justify-between mb-4">
          <div>
            <h3 class="text-base font-bold text-slate-900">Monthly Revenue & Occupancy (ETB)</h3>
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
            <!-- Background circle -->
            <path
              class="text-slate-100"
              stroke-width="5"
              stroke="currentColor"
              fill="none"
              d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
            />
            <!-- Suite: 55% (#0B4D34) -->
            <path
              stroke="#0B4D34"
              stroke-width="5"
              stroke-dasharray="55, 100"
              stroke-linecap="round"
              fill="none"
              d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
            />
            <!-- Deluxe: 30% (#D4A329) offset by 55 -->
            <path
              stroke="#D4A329"
              stroke-width="5"
              stroke-dasharray="30, 100"
              stroke-dashoffset="-55"
              stroke-linecap="round"
              fill="none"
              d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
            />
            <!-- Standard: 15% (#64748B) offset by 85 -->
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
          <div class="flex items-center gap-1.5">
            <span class="w-3 h-3 rounded-full bg-[#0B4D34]"></span>
            <span>Suite (55%)</span>
          </div>
          <div class="flex items-center gap-1.5">
            <span class="w-3 h-3 rounded-full bg-[#D4A329]"></span>
            <span>Deluxe (30%)</span>
          </div>
          <div class="flex items-center gap-1.5">
            <span class="w-3 h-3 rounded-full bg-[#64748B]"></span>
            <span>Standard (15%)</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Monthly Financial Summary Table (matching Screen 1) -->
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
const barChartData = ref([]);
const summaryRows = ref([]);

async function fetchReportData() {
  try {
    const res = await fetch(`/api/v1/reports/financial?period=${period.value}`);
    const data = await res.json();
    barChartData.value = data.bar_chart || [];
    summaryRows.value = data.monthly_summary || [];
  } catch (error) {
    console.error('Failed to load report data:', error);
  }
}

function exportReport(format) {
  alert(`Preparing official ${format} Financial & Occupancy Report download for hotel operations...`);
}

onMounted(() => {
  fetchReportData();
});
</script>
