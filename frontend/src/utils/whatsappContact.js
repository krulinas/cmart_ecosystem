const WA_ME_PREFIX = 'https://wa.me/';

export const WHATSAPP_OPT_IN_PHONE_REQUIRED =
  'Add a valid business phone number before enabling WhatsApp contact.';

export function normalizeWhatsAppNumber(raw) {
  if (raw == null) return null;
  const trimmed = String(raw).trim();
  if (!trimmed) return null;

  const hasPlus = trimmed.startsWith('+');
  const digits = trimmed.replace(/\D+/g, '');
  if (!digits) return null;

  let normalized = digits;
  if (!hasPlus && /^0[1-9]\d{7,9}$/.test(digits)) {
    normalized = `60${digits.slice(1)}`;
  }

  if (!/^[1-9]\d{7,14}$/.test(normalized)) return null;
  return normalized;
}

export function vendorWhatsappContact(source) {
  const contact = source?.vendor?.whatsapp_contact;
  const url = String(contact?.url || '').trim();
  if (!contact?.available || !url.startsWith(WA_ME_PREFIX)) return null;
  return contact;
}
