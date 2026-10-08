/**
 * WhatsApp screenshots. Drop image files in `apps/web/public/success/whatsapp/`
 * and add an entry here. `src` is the public path, e.g. `/success/whatsapp/offer.webp`.
 * Use `objectPosition` to crop, and `blurRegion` to cover a phone number.
 * Leave unpublished until the image is a real conversation the person approved.
 */
export type WhatsAppShot = {
  id: string;
  src: string;
  alt: string;
  objectPosition?: string;
  blurRegion?: {
    top?: string;
    bottom?: string;
    left?: string;
    width?: string;
    height?: string;
  };
  published: boolean;
};

export const whatsappShots: WhatsAppShot[] = [];

export function visibleShots(preview: boolean): WhatsAppShot[] {
  return whatsappShots.filter((shot) => shot.published || preview);
}
