/**
 * Validates a post-login redirect path from query params or route guards.
 */
export function isValidRedirectPath(path) {
  if (typeof path !== 'string' || !path.startsWith('/')) {
    return false;
  }

  if (path.startsWith('//') || path.includes('\\')) {
    return false;
  }

  return true;
}

export function redirectPathname(path) {
  if (typeof path !== 'string') {
    return '';
  }

  return path.split('#')[0].split('?')[0] || '/';
}

const GUEST_ONLY_PATHS = new Set(['/login', '/register', '/management/login']);

const PUBLIC_PATHS = new Set(['/', '/community', '/marketplace', '/calendar']);

const COMMUNITY_VENDOR_PATHS = new Set(['/dashboard', '/profile', '/vendor-booking']);

function isPublicPath(pathname) {
  return PUBLIC_PATHS.has(pathname);
}

function isManagementPath(pathname) {
  return pathname === '/admin' || pathname.startsWith('/staff/');
}

function isCommunityVendorPath(pathname) {
  return COMMUNITY_VENDOR_PATHS.has(pathname);
}

/**
 * Role-aware redirect allowlist. Rejects paths the user cannot access.
 */
export function isRedirectAllowedForUser(auth, redirectCandidate) {
  if (!isValidRedirectPath(redirectCandidate)) {
    return false;
  }

  const pathname = redirectPathname(redirectCandidate);

  if (GUEST_ONLY_PATHS.has(pathname)) {
    return false;
  }

  if (isPublicPath(pathname)) {
    return true;
  }

  if (auth.isCmartWorker) {
    return isManagementPath(pathname);
  }

  if (auth.role === 'uum') {
    return pathname === '/uum';
  }

  if (auth.role === 'community') {
    if (isCommunityVendorPath(pathname)) {
      if (pathname === '/vendor-booking') {
        return true;
      }

      return auth.isVendorUser;
    }

    return false;
  }

  return false;
}

/**
 * Resolve where to send the user after public login or registration.
 */
export function resolvePostAuthRedirect(auth, redirectCandidate = null) {
  if (isRedirectAllowedForUser(auth, redirectCandidate)) {
    return redirectCandidate;
  }

  return auth.homeForUser();
}

/**
 * Resolve where to send management users after operations login.
 */
export function resolveManagementPostAuthRedirect(auth, redirectCandidate = null) {
  if (isRedirectAllowedForUser(auth, redirectCandidate)) {
    return redirectCandidate;
  }

  return auth.homeForUser();
}

/**
 * Build a login URL that returns the user to a community action after auth.
 */
export function loginPathWithRedirect(path = '/community') {
  return `/login?redirect=${encodeURIComponent(path)}`;
}

/**
 * Build a register URL that returns the user after account creation.
 */
export function registerPathWithRedirect(path = '/community') {
  return `/register?redirect=${encodeURIComponent(path)}`;
}

export const COMMUNITY_BECOME_VENDOR_HASH = '#become-vendor';

export const COMMUNITY_REVIEW_INTENT_PATH = '/community#share-feedback';

export function communityVisitorFallbackPath() {
  return `/community${COMMUNITY_BECOME_VENDOR_HASH}`;
}
