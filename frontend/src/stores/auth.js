import { computed, ref } from 'vue';
import { defineStore } from 'pinia';
import api from '../services/api';
import {
  hasAnyManagementRole,
  isCmartWorkerRole,
  isManagerOrAbove,
  isStaffRole,
  isManagerRole,
  isSuperAdminRole,
  normalizeRole,
  roleDisplayLabel,
  managementWorkspaceLabel,
  managementTierLabel,
} from '../utils/managementRoles';

const readStoredUser = () => {
  try {
    return JSON.parse(localStorage.getItem('carboot_cmart_user')) || null;
  } catch {
    return null;
  }
};

export const useAuthStore = defineStore('auth', () => {
  const token = ref(localStorage.getItem('carboot_cmart_token'));
  const user = ref(readStoredUser());
  const loading = ref(false);
  const sessionReady = ref(Boolean(token.value && user.value));

  const isAuthenticated = computed(() => Boolean(token.value && user.value));
  const role = computed(() => user.value?.role || null);
  const normalizedRole = computed(() => normalizeRole(role.value));
  const vendorStatus = computed(() => user.value?.vendor_status || 'none');
  const isApprovedVendor = computed(() => role.value === 'community' && vendorStatus.value === 'approved');
  const isCmartWorker = computed(() => isCmartWorkerRole(role.value));
  const isStaff = computed(() => isStaffRole(role.value));
  const isManager = computed(() => isManagerRole(role.value));
  const isSuperAdmin = computed(() => isSuperAdminRole(role.value));
  const isBoss = computed(() => isManagerOrAbove(role.value));
  const managementProfile = computed(() => user.value?.management_profile ?? null);
  const vendorBusinessProfile = computed(() => user.value?.vendor_business_profile ?? null);
  const roleLabel = computed(() => roleDisplayLabel(role.value, managementProfile.value));
  const workspaceLabel = computed(() =>
    isCmartWorker.value
      ? managementWorkspaceLabel(role.value, managementProfile.value)
      : null,
  );
  const tierLabel = computed(() => managementTierLabel(managementProfile.value, role.value));

  const persistSession = (payload) => {
    token.value = payload.token;
    user.value = payload.user;
    sessionReady.value = true;
    localStorage.setItem('carboot_cmart_token', payload.token);
    localStorage.setItem('carboot_cmart_user', JSON.stringify(payload.user));
  };

  const clearSession = () => {
    token.value = null;
    user.value = null;
    sessionReady.value = false;
    localStorage.removeItem('carboot_cmart_token');
    localStorage.removeItem('carboot_cmart_user');
  };

  const register = async (form) => {
    loading.value = true;
    try {
      const { data } = await api.post('/auth/register', form);
      persistSession(data);
      return data.user;
    } finally {
      loading.value = false;
    }
  };

  const login = async (credentials) => {
    loading.value = true;
    try {
      const { data } = await api.post('/auth/login', credentials);
      persistSession(data);
      return data.user;
    } finally {
      loading.value = false;
    }
  };

  const fetchMe = async () => {
    if (!token.value) {
      sessionReady.value = false;
      return null;
    }

    loading.value = true;
    try {
      const { data } = await api.get('/auth/me');
      user.value = data.user;
      sessionReady.value = true;
      localStorage.setItem('carboot_cmart_user', JSON.stringify(data.user));
      return data.user;
    } finally {
      loading.value = false;
    }
  };

  const ensureSession = async ({ refresh = false } = {}) => {
    if (!token.value) {
      sessionReady.value = false;
      return null;
    }

    if (!refresh && user.value) {
      sessionReady.value = true;
      return user.value;
    }

    return fetchMe();
  };

  const logout = async () => {
    try {
      if (token.value) {
        await api.post('/auth/logout');
      }
    } finally {
      clearSession();
    }
  };

  const isCommunityMember = computed(() => role.value === 'community');

  const hasAnyRole = (roles = []) => hasAnyManagementRole(role.value, roles) || roles.includes(role.value);

  const homeForUser = () => {
    if (isCmartWorker.value) return '/admin';
    if (role.value === 'uum') return '/uum';
    if (role.value === 'community') return '/dashboard';
    return '/';
  };

  const bookingPathForUser = () => {
    if (isApprovedVendor.value) return '/vendor-booking';
    if (isAuthenticated.value) return '/login';
    return '/login?redirect=/vendor-booking';
  };

  return {
    token,
    user,
    loading,
    sessionReady,
    isAuthenticated,
    role,
    normalizedRole,
    vendorStatus,
    isApprovedVendor,
    isCommunityMember,
    isCmartWorker,
    isBoss,
    isStaff,
    isManager,
    isSuperAdmin,
    roleLabel,
    managementProfile,
    vendorBusinessProfile,
    workspaceLabel,
    tierLabel,
    register,
    login,
    fetchMe,
    ensureSession,
    logout,
    clearSession,
    persistSession,
    hasAnyRole,
    homeForUser,
    bookingPathForUser,
  };
});
