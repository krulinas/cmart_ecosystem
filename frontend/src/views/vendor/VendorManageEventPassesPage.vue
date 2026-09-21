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
import { useI18n } from 'vue-i18n';
import { useToast } from 'vue-toastification';
import VendorPageShell from '../../components/vendor/VendorPageShell.vue';
import VendorEventPassesPanel from '../../components/VendorEventPassesPanel.vue';
import api from '../../services/api';
import { useAuthStore } from '../../stores/auth';

const { t } = useI18n();
const toast = useToast();
const auth = useAuthStore();

const vendorName = computed(
  () => auth.vendorBusinessProfile?.business_name || auth.user?.name || t('common.vendor'),
);

const downloadPassPdf = async (bookingId) => {
  if (!bookingId) {
    toast.error(t('vendor.noApprovedPassDownload'));
    return;
  }

  try {
    const response = await api.get(`/bookings/${bookingId}/pdf`, { responseType: 'blob' });
    const file = new Blob([response.data], { type: 'application/pdf' });
    const fileUrl = URL.createObjectURL(file);
    window.open(fileUrl, '_blank', 'noopener,noreferrer');
    setTimeout(() => URL.revokeObjectURL(fileUrl), 60000);
    toast.success(t('vendor.documentOpened'));
  } catch (error) {
    console.error('Unable to download booking document PDF:', error);
    toast.error(t('vendor.unableOpenDocument'));
  }
};
</script>
