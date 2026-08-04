<template>
  <VendorPageShell test-id="vendor-payment-history-page-root">
    <VendorHistoryReceipts
      :records="paymentRecords"
      :loading="loadingHistory"
      :load-error="historyError"
      @retry="fetchPaymentHistory"
      @view-document="openBookingDocument"
      @submit-payment="openPaymentSubmission"
      @close="router.push('/dashboard')"
    />

    <VendorPaymentModal
      v-model="showPaymentModal"
      :booking-id="paymentBookingId"
      :amount="paymentInvoiceAmount"
      @submitted="onPaymentSubmitted"
    />
  </VendorPageShell>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useToast } from 'vue-toastification';
import VendorPageShell from '../../components/vendor/VendorPageShell.vue';
import VendorHistoryReceipts from '../../components/VendorHistoryReceipts.vue';
import VendorPaymentModal from '../../components/VendorPaymentModal.vue';
import api from '../../services/api';

const router = useRouter();
const toast = useToast();

const paymentRecords = ref([]);
const loadingHistory = ref(false);
const historyError = ref(false);
const showPaymentModal = ref(false);
const paymentBookingId = ref(null);
const paymentInvoiceAmount = ref(null);

const fetchPaymentHistory = async () => {
  loadingHistory.value = true;
  historyError.value = false;
  try {
    const { data } = await api.get('/vendor/history-receipts');
    paymentRecords.value = Array.isArray(data?.records) ? data.records : [];
  } catch (e) {
    console.error('Unable to retrieve vendor payment history from the API.', e);
    historyError.value = true;
    paymentRecords.value = [];
  } finally {
    loadingHistory.value = false;
  }
};

const openBookingDocument = async (bookingId) => {
  if (!bookingId) {
    toast.error('No document is available yet.');
    return;
  }
  try {
    const response = await api.get(`/bookings/${bookingId}/pdf`, { responseType: 'blob' });
    const file = new Blob([response.data], { type: 'application/pdf' });
    const fileUrl = URL.createObjectURL(file);
    window.open(fileUrl, '_blank', 'noopener,noreferrer');
    setTimeout(() => URL.revokeObjectURL(fileUrl), 60000);
    toast.success('Booking document opened.');
  } catch (error) {
    console.error('Unable to download booking document PDF:', error);
    toast.error('Unable to open booking document.');
  }
};

const openPaymentSubmission = (row) => {
  paymentBookingId.value = row?.booking_id ?? row?.id ?? null;
  paymentInvoiceAmount.value = row?.amount ?? row?.invoice?.amount ?? null;
  showPaymentModal.value = true;
};

const onPaymentSubmitted = async () => {
  await fetchPaymentHistory();
};

onMounted(fetchPaymentHistory);
</script>
