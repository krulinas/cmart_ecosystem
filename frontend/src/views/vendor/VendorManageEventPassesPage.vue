<template>
  <VendorPageShell test-id="vendor-manage-event-passes-root">
    <VendorEventPassesPanel
      :vendor-name="vendorName"
      @download-pass="downloadPassPdf"
    />
  </VendorPageShell>
</template>

<script setup>
import { computed } from 'vue';
import { useToast } from 'vue-toastification';
import VendorPageShell from '../../components/vendor/VendorPageShell.vue';
import VendorEventPassesPanel from '../../components/VendorEventPassesPanel.vue';
import api from '../../services/api';
import { useAuthStore } from '../../stores/auth';

const toast = useToast();
const auth = useAuthStore();

const vendorName = computed(
  () => auth.vendorBusinessProfile?.business_name || auth.user?.name || 'Vendor',
);

const downloadPassPdf = async (bookingId) => {
  if (!bookingId) {
    toast.error('No approved booking pass is available to download yet.');
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
</script>
