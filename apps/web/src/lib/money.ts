/**
 * Money helpers. All server amounts are integer paise (₹1 = 100 paise); never do
 * currency math on the client — format only.
 */

const INR = new Intl.NumberFormat("en-IN", {
  style: "currency",
  currency: "INR",
  minimumFractionDigits: 2,
  maximumFractionDigits: 2,
});

/** Format an integer-paise amount as an Indian-grouped ₹ string. */
export function formatPaise(paise: number): string {
  return INR.format(paise / 100);
}
