import { ref, shallowRef } from 'vue';
import { isValidRedirectPath, redirectPathname } from './postAuthRedirect';

const AUTH_SKIP_PATHS = [
  '/auth/login',
  '/auth/register',
  '/auth/logout',
  '/auth/me',
];

let handling = false;

export const sessionExpired = ref(false);
export const sessionExpirySnapshot = shallowRef(null);

const listeners = new Set();

export function isSessionExpiryHandling() {
  return handling;
}

export function getSessionExpirySnapshot() {
  return sessionExpirySnapshot.value;
}

export function onSessionExpired(callback) {
  listeners.add(callback);
  return () => listeners.delete(callback);
}

export function isAuthAttemptUrl(url) {
  if (typeof url !== 'string' || !url) {
    return false;
  }

  return AUTH_SKIP_PATHS.some((path) => url.includes(path));
}

export function loginPathForLocation(fullPath) {
  const pathname = redirectPathname(fullPath || '/');
  if (pathname === '/admin' || pathname.startsWith('/organizer/')) {
    return '/management/login';
  }

  return '/login';
}

export function sanitizeReturnPath(path) {
  if (!isValidRedirectPath(path)) {
    return null;
  }

  return path;
}

export function currentLocationPath() {
  if (typeof window === 'undefined') {
    return '/';
  }

  return `${window.location.pathname}${window.location.search}${window.location.hash}` || '/';
}

export function beginSessionExpiry(details = {}) {
  if (handling) {
    return false;
  }

  const returnPath = sanitizeReturnPath(details.returnPath || currentLocationPath());
  const loginPath = details.loginPath || loginPathForLocation(returnPath || '/');

  handling = true;
  sessionExpirySnapshot.value = {
    returnPath,
    loginPath,
  };
  sessionExpired.value = true;

  listeners.forEach((callback) => {
    callback(sessionExpirySnapshot.value);
  });

  return true;
}

export function dismissSessionExpiryModal() {
  sessionExpired.value = false;
}

export function resetSessionExpiry() {
  handling = false;
  sessionExpired.value = false;
  sessionExpirySnapshot.value = null;
}
