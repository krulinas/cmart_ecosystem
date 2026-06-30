const API_BASE_URL = (import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000/api').replace(/\/$/, '');

/** Google OAuth is opt-in — never show a fake provider button in production. */
export function isGoogleLoginEnabled() {
  return import.meta.env.VITE_ENABLE_GOOGLE_LOGIN === 'true';
}

export function getGoogleAuthUrl() {
  return `${API_BASE_URL}/auth/google`;
}

export function isDemoLoginHintsEnabled() {
  return import.meta.env.VITE_SHOW_DEMO_LOGIN_HINTS === 'true';
}
