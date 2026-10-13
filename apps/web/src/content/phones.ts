/**
 * Published phone lines. One source for display, tel:/E.164, and wa.me.
 *
 * OFFICE_PHONE is the company line: candidate and course pages, shared
 * footers, site-wide Organization JSON-LD, legal pages, and student answers.
 * EMPLOYER_PHONE is the hiring line: employer and Taurus page bodies, CTAs,
 * tel and WhatsApp links, and page-level schema on those surfaces.
 */

export type PublishedPhone = {
  /** Spaced display, e.g. "+91 79755 54666". */
  readonly display: string;
  /** tel: href and E.164, e.g. "+917975554666". */
  readonly tel: string;
  /** wa.me path digits, no plus. */
  readonly whatsapp: string;
};

export const OFFICE_PHONE = {
  display: "+91 79755 54666",
  tel: "+917975554666",
  whatsapp: "917975554666",
} as const satisfies PublishedPhone;

export const EMPLOYER_PHONE = {
  display: "+91 63634 02404",
  tel: "+916363402404",
  whatsapp: "916363402404",
} as const satisfies PublishedPhone;

export function telHref(phone: PublishedPhone): string {
  return `tel:${phone.tel}`;
}

export function whatsappHref(phone: PublishedPhone, text?: string): string {
  const base = `https://wa.me/${phone.whatsapp}`;
  return text ? `${base}?text=${encodeURIComponent(text)}` : base;
}
