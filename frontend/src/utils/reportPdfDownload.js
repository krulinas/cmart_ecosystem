/**
 * Authenticated Post-Event PDF download / preview helpers.
 * Kept separate from reportWorkflowApi so unit tests do not load axios.
 */

export function parseContentDispositionFilename(header) {
  if (!header || typeof header !== 'string') return null;
  const utf8 = /filename\*\s*=\s*UTF-8''([^;]+)/i.exec(header);
  if (utf8?.[1]) {
    try {
      return decodeURIComponent(utf8[1].trim());
    } catch {
      return utf8[1].trim();
    }
  }
  const quoted = /filename\s*=\s*"([^"]+)"/i.exec(header);
  if (quoted?.[1]) return quoted[1].trim();
  const bare = /filename\s*=\s*([^;]+)/i.exec(header);
  return bare?.[1] ? bare[1].trim().replace(/^["']|["']$/g, '') : null;
}

export function looksLikePdfBytes(bytes) {
  if (!bytes || bytes.length < 4) return false;
  return bytes[0] === 0x25 && bytes[1] === 0x50 && bytes[2] === 0x44 && bytes[3] === 0x46;
}

export function buildPostEventPdfFilename({ audience = 'organizer', eventSlug = 'event', version = 1 } = {}) {
  const safeSlug = String(eventSlug || 'event')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '') || 'event';
  const prefix = audience === 'cmart' ? 'cmart' : 'organizer';
  return `${prefix}-post-event-report-${safeSlug}-v${Number(version) || 1}.pdf`;
}

async function extractErrorMessage(response, contentType) {
  const fallback = 'Unable to download PDF.';
  try {
    if (contentType.includes('application/json')) {
      const data = await response.json();
      return data?.message || data?.error || fallback;
    }
    const text = await response.text();
    if (!text) return fallback;
    try {
      const data = JSON.parse(text);
      return data?.message || data?.error || fallback;
    } catch {
      const stripped = text.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
      return stripped.slice(0, 240) || fallback;
    }
  } catch {
    return fallback;
  }
}

async function fetchAuthorizedPdfResponse(url) {
  const token = localStorage.getItem('carboot_cmart_token');
  return fetch(url, {
    headers: {
      Accept: 'application/pdf',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
  });
}

/**
 * Inline preview in a new tab (blob URL). Prefer downloadAuthorizedPdf for Save/Download.
 */
export async function previewAuthorizedPdf(url) {
  const response = await fetchAuthorizedPdfResponse(url);
  const contentType = (response.headers.get('content-type') || '').toLowerCase();

  if (
    !response.ok
    || contentType.includes('application/json')
    || contentType.includes('text/html')
  ) {
    throw new Error(await extractErrorMessage(response, contentType));
  }

  const buffer = await response.arrayBuffer();
  if (!looksLikePdfBytes(new Uint8Array(buffer.slice(0, 5)))) {
    throw new Error('Server did not return a PDF document.');
  }

  const blob = new Blob([buffer], { type: 'application/pdf' });
  const objectUrl = URL.createObjectURL(blob);
  window.open(objectUrl, '_blank', 'noopener');
  setTimeout(() => URL.revokeObjectURL(objectUrl), 10 * 60_000);
  return {
    objectUrl,
    filename: parseContentDispositionFilename(response.headers.get('content-disposition')),
  };
}

/**
 * Genuine authenticated file download via <a download>. Does not open Chrome PDF Viewer.
 */
export async function downloadAuthorizedPdf(url, { fallbackFilename = 'report.pdf' } = {}) {
  const response = await fetchAuthorizedPdfResponse(url);
  const contentType = (response.headers.get('content-type') || '').toLowerCase();

  if (
    !response.ok
    || contentType.includes('application/json')
    || contentType.includes('text/html')
  ) {
    throw new Error(await extractErrorMessage(response, contentType));
  }

  const buffer = await response.arrayBuffer();
  const head = new Uint8Array(buffer.slice(0, 5));
  if (!looksLikePdfBytes(head)) {
    throw new Error('Server did not return a PDF document.');
  }

  if (!contentType.includes('application/pdf') && contentType && !contentType.includes('octet-stream')) {
    throw new Error('Unexpected Content-Type for PDF download.');
  }

  const filename = parseContentDispositionFilename(response.headers.get('content-disposition'))
    || fallbackFilename;
  const blob = new Blob([buffer], { type: 'application/pdf' });
  const objectUrl = URL.createObjectURL(blob);

  const anchor = document.createElement('a');
  anchor.href = objectUrl;
  anchor.download = filename;
  anchor.rel = 'noopener';
  anchor.style.display = 'none';
  document.body.appendChild(anchor);

  let revoked = false;
  const cleanup = () => {
    if (revoked) return;
    revoked = true;
    anchor.remove();
    URL.revokeObjectURL(objectUrl);
  };

  anchor.click();

  if (typeof requestAnimationFrame === 'function') {
    requestAnimationFrame(() => {
      setTimeout(cleanup, 750);
    });
  } else {
    setTimeout(cleanup, 750);
  }

  return { filename, objectUrl, cleanup };
}

/** @deprecated Use downloadAuthorizedPdf for Report Centre Download buttons. */
export async function openAuthorizedPdf(url) {
  return downloadAuthorizedPdf(url);
}
