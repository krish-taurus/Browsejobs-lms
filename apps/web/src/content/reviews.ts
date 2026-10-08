/**
 * Google reviews. Leave this empty until a real review is copied from the
 * listing. Do not invent a name, star count, or quote.
 */
export type GoogleReview = {
  id: string;
  name: string;
  /** 1–5, copied from the listing. */
  stars: number;
  text: string;
  /** Direct URL of this review, or the Google listing. */
  url: string;
  published: boolean;
};

/** Set this to the Google Business listing when it is confirmed. */
export const GOOGLE_LISTING_URL = "";

export const googleReviews: GoogleReview[] = [];

export function visibleReviews(preview: boolean): GoogleReview[] {
  return googleReviews.filter((review) => review.published || preview);
}
