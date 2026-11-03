<template>
  <div v-if="isOpen" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white w-full max-w-2xl rounded-2xl shadow-2xl border border-slate-200 overflow-hidden my-8">
      
      <!-- Top Action Bar (hidden on print) -->
      <div class="bg-slate-100 px-6 py-3 flex items-center justify-between border-b border-slate-200 print:hidden">
        <span class="text-xs font-bold text-slate-700">Official Ethiopian Tax Invoice Preview</span>
        <div class="flex items-center gap-2">
          <button 
            @click="printInvoice"
            class="px-4 py-1.5 bg-[#0B4D34] hover:bg-[#073826] text-white text-xs font-semibold rounded-full shadow transition flex items-center gap-1.5">
            <span>🖨️</span> Print / Save PDF
          </button>
          <button 
            @click="$emit('close')"
            class="px-3 py-1.5 bg-slate-200 hover:bg-slate-300 text-slate-700 text-xs font-semibold rounded-full transition">
            ✕ Close
          </button>
        </div>
      </div>

      <!-- Printable Invoice Sheet -->
      <div id="printable-tax-invoice" class="p-8 space-y-6 text-slate-800 bg-white">
        <!-- Hotel Header -->
        <div class="flex items-start justify-between pb-6 border-b-2 border-[#0B4D34]">
          <div>
            <div class="flex items-center gap-2">
              <span class="text-2xl">🇪🇹</span>
              <h1 class="text-xl font-black text-[#0B4D34] tracking-tight uppercase">
                {{ invoiceData?.hotel_name || 'Ethiopian Luxury Resort & Heritage Suites' }}
              </h1>
            </div>
            <p class="text-xs text-slate-500 mt-1">{{ invoiceData?.hotel_address }}</p>
            <p class="text-xs text-slate-500">Tel: {{ invoiceData?.hotel_phone }} · Addis Ababa, Ethiopia</p>
          </div>

          <div class="text-right">
            <span class="inline-block bg-[#0B4D34] text-white font-mono font-bold text-xs px-3 py-1 rounded">
              TAX INVOICE
            </span>
            <div class="text-xs font-mono font-bold text-slate-900 mt-2">{{ invoiceData?.invoice_number }}</div>
            <div class="text-[10px] text-slate-400">Date: {{ invoiceData?.fiscal_date }}</div>
          </div>
        </div>

        <!-- TIN & Registration Meta -->
        <div class="grid grid-cols-2 gap-4 text-xs bg-slate-50 p-4 rounded-xl border border-slate-200">
          <div>
            <span class="text-[10px] text-slate-400 uppercase font-semibold block">Hotel Fiscal ID</span>
            <div class="font-mono font-bold text-slate-800">TIN: {{ invoiceData?.hotel_tin }}</div>
            <div class="font-mono text-slate-600 text-[11px]">VAT Reg: {{ invoiceData?.hotel_vat_reg }}</div>
          </div>
          <div>
            <span class="text-[10px] text-slate-400 uppercase font-semibold block">Guest & Reservation</span>
            <div class="font-bold text-slate-900">{{ invoiceData?.guest?.name }}</div>
            <div class="text-[11px] text-slate-600">{{ invoiceData?.guest?.phone }} · {{ invoiceData?.guest?.email }}</div>
            <div class="font-mono text-[10px] text-[#0B4D34] font-bold mt-0.5">Ref: {{ invoiceData?.reference_code }}</div>
          </div>
        </div>

        <!-- Stay Details Banner -->
        <div class="grid grid-cols-4 gap-2 text-center text-xs py-2 bg-emerald-50/60 rounded-lg border border-emerald-100">
          <div>
            <span class="text-[10px] text-slate-400 block">Room</span>
            <span class="font-bold text-slate-800">#{{ invoiceData?.room?.room_number }}</span>
          </div>
          <div>
            <span class="text-[10px] text-slate-400 block">Category</span>
            <span class="font-semibold text-slate-800">{{ invoiceData?.room?.category }}</span>
          </div>
          <div>
            <span class="text-[10px] text-slate-400 block">Check-In</span>
            <span class="font-mono font-medium text-slate-700">{{ invoiceData?.stay_period?.check_in }}</span>
          </div>
          <div>
            <span class="text-[10px] text-slate-400 block">Check-Out</span>
            <span class="font-mono font-medium text-slate-700">{{ invoiceData?.stay_period?.check_out }}</span>
          </div>
        </div>

        <!-- Itemized Charges Table -->
        <table class="w-full text-left text-xs border-collapse">
          <thead>
            <tr class="bg-slate-100 border-b border-slate-300 font-bold text-slate-700">
              <th class="py-2.5 px-3">Description</th>
              <th class="py-2.5 px-3 text-center">Qty / Nights</th>
              <th class="py-2.5 px-3 text-right">Unit Rate</th>
              <th class="py-2.5 px-3 text-right">Amount (ETB)</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200">
            <!-- Room charges -->
            <tr v-for="(rm, idx) in (invoiceData?.room_items || [])" :key="'rm-'+idx">
              <td class="py-2.5 px-3 font-semibold text-slate-800">
                🏨 {{ rm.description }}
              </td>
              <td class="py-2.5 px-3 text-center">{{ rm.nights }} nts</td>
              <td class="py-2.5 px-3 text-right font-mono">{{ rm.rate_per_night?.toLocaleString() }}</td>
              <td class="py-2.5 px-3 text-right font-mono font-bold">{{ rm.subtotal_etb?.toLocaleString() }}</td>
            </tr>
            <tr v-if="(!invoiceData?.room_items || invoiceData.room_items.length === 0) && invoiceData?.room">
              <td class="py-2.5 px-3 font-semibold text-slate-800">
                🏨 Accommodation: {{ invoiceData?.room?.title || ('Room ' + invoiceData?.room?.room_number) }}
              </td>
              <td class="py-2.5 px-3 text-center">{{ invoiceData?.room?.nights }} nts</td>
              <td class="py-2.5 px-3 text-right font-mono">{{ invoiceData?.room?.rate_per_night?.toLocaleString() }}</td>
              <td class="py-2.5 px-3 text-right font-mono font-bold">{{ (invoiceData?.breakdown?.room_charge || invoiceData?.room?.total)?.toLocaleString() }}</td>
            </tr>
            <!-- Incidental Service Charges (Mini-bar, Restaurant, Laundry, etc.) -->
            <tr v-for="(sc, sidx) in (invoiceData?.service_items || [])" :key="'sc-'+sidx" class="bg-amber-50/40">
              <td class="py-2 px-3 font-medium text-slate-800">
                <span class="text-[9px] uppercase font-bold text-amber-800 mr-1.5 px-1.5 py-0.5 bg-amber-100 rounded border border-amber-200">{{ sc.type }}</span>
                {{ sc.description }}
              </td>
              <td class="py-2 px-3 text-center font-mono">{{ sc.quantity || 1 }}</td>
              <td class="py-2 px-3 text-right font-mono text-slate-400">-</td>
              <td class="py-2 px-3 text-right font-mono font-bold text-amber-900">{{ sc.amount_etb?.toLocaleString() }}</td>
            </tr>
          </tbody>
        </table>

        <!-- Tax & Subtotal Breakdown -->
        <div class="flex justify-end pt-2">
          <div class="w-80 space-y-1.5 text-xs">
            <div class="flex justify-between text-slate-600">
              <span>Subtotal (Net Charges):</span>
              <span class="font-mono">{{ (invoiceData?.breakdown?.room_charge || (invoiceData?.financial?.total_amount_etb ? Math.round(invoiceData.financial.total_amount_etb / 1.27) : 0))?.toLocaleString() }} ETB</span>
            </div>
            <div class="flex justify-between text-slate-600">
              <span>VAT (15%):</span>
              <span class="font-mono">{{ (invoiceData?.taxes?.vat_15 || invoiceData?.breakdown?.vat_15 || 0)?.toLocaleString() }} ETB</span>
            </div>
            <div class="flex justify-between text-slate-600">
              <span>Service Charge (10%):</span>
              <span class="font-mono">{{ (invoiceData?.taxes?.service_charge_10 || invoiceData?.breakdown?.service_charge_10 || 0)?.toLocaleString() }} ETB</span>
            </div>
            <div class="flex justify-between text-slate-600">
              <span>Tourism Development Levy (2%):</span>
              <span class="font-mono">{{ (invoiceData?.taxes?.tourism_levy_2 || invoiceData?.breakdown?.tourism_levy_2 || 0)?.toLocaleString() }} ETB</span>
            </div>
            <div class="flex justify-between text-base font-black text-[#0B4D34] pt-2 border-t-2 border-[#0B4D34]">
              <span>Grand Total Settled:</span>
              <span class="font-mono">{{ (invoiceData?.financial?.total_amount_etb || invoiceData?.breakdown?.total_etb || 0)?.toLocaleString() }} ETB</span>
            </div>
            <div class="flex justify-between text-xs text-slate-500 font-semibold">
              <span>Total Paid:</span>
              <span class="font-mono text-emerald-700">{{ (invoiceData?.financial?.amount_paid_etb !== undefined ? invoiceData.financial.amount_paid_etb : invoiceData?.breakdown?.total_paid || 0)?.toLocaleString() }} ETB</span>
            </div>
            <div class="flex justify-between text-xs font-bold" :class="((invoiceData?.financial?.outstanding_balance_etb ?? invoiceData?.breakdown?.outstanding_balance) === 0) ? 'text-emerald-700' : 'text-rose-700'">
              <span>Balance Due:</span>
              <span class="font-mono">{{ ((invoiceData?.financial?.outstanding_balance_etb !== undefined ? invoiceData.financial.outstanding_balance_etb : invoiceData?.breakdown?.outstanding_balance) || 0)?.toLocaleString() }} ETB</span>
            </div>
          </div>
        </div>

        <!-- Footer Notice & QR Code Stamp -->
        <div class="pt-6 border-t border-slate-200 flex items-center justify-between text-[11px] text-slate-500">
          <div>
            <p class="font-bold text-slate-700">Thank you for staying at our luxury Ethiopian resort!</p>
            <p>This is a computer-generated tax invoice verified under Ethiopian Revenue Authority standards.</p>
          </div>
          <div class="text-center">
            <div class="w-16 h-16 bg-slate-100 border border-slate-300 rounded flex items-center justify-center font-mono text-[9px] text-slate-400 p-1">
              [QR VERIFIED]
            </div>
            <span class="text-[9px] font-mono text-slate-400">Fiscal Hash</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  isOpen: {
    type: Boolean,
    default: false
  },
  invoiceData: {
    type: Object,
    default: null
  }
});

defineEmits(['close']);

function printInvoice() {
  window.print();
}
</script>

<style>
@media print {
  body * {
    visibility: hidden;
  }
  #printable-tax-invoice, #printable-tax-invoice * {
    visibility: visible;
  }
  #printable-tax-invoice {
    position: absolute;
    left: 0;
    top: 0;
    width: 100%;
    margin: 0;
    padding: 20px;
    background: white;
  }
}
</style>
