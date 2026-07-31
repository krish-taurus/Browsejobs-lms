/**
 * Razorpay Checkout loader.
 *
 * The store used to POST the order and then tell the student to "complete it in
 * the Razorpay window" — but nothing ever opened that window, so every Buy just
 * left a pending purchase behind. This loads the checkout script on demand and
 * opens it against the order the API created.
 */

const CHECKOUT_SRC = "https://checkout.razorpay.com/v1/checkout.js";

export type RazorpayOrder = {
  purchase_id: number;
  razorpay_order_id: string | null;
  amount_paise: number;
  razorpay_key_id: string;
  name?: string;
  prefill?: { name?: string; email?: string; contact?: string };
};

type RazorpayInstance = { open: () => void; on: (event: string, handler: (e: unknown) => void) => void };
type RazorpayCtor = new (options: Record<string, unknown>) => RazorpayInstance;

declare global {
  interface Window { Razorpay?: RazorpayCtor }
}

let loader: Promise<RazorpayCtor> | null = null;

/** Injects checkout.js once; resolves with the constructor. */
export function loadRazorpay(): Promise<RazorpayCtor> {
  if (typeof window === "undefined") {
    return Promise.reject(new Error("Razorpay can only load in the browser."));
  }
  if (window.Razorpay) {
    return Promise.resolve(window.Razorpay);
  }
  if (loader) {
    return loader;
  }

  loader = new Promise<RazorpayCtor>((resolve, reject) => {
    const script = document.createElement("script");
    script.src = CHECKOUT_SRC;
    script.async = true;
    script.onload = () => {
      if (window.Razorpay) {
        resolve(window.Razorpay);
      } else {
        loader = null;
        reject(new Error("Razorpay checkout loaded but did not initialise."));
      }
    };
    script.onerror = () => {
      loader = null;  // let a later attempt retry rather than caching the failure
      reject(new Error("Couldn't reach Razorpay. Check your connection and try again."));
    };
    document.head.appendChild(script);
  });

  return loader;
}

export type CheckoutOutcome = "paid" | "dismissed" | "failed";

/**
 * Opens checkout for an order the API already created. Resolves once the
 * student either pays, closes the window, or the payment fails — the wallet
 * itself is credited server-side by the Razorpay webhook, never here.
 */
export async function openCheckout(order: RazorpayOrder): Promise<CheckoutOutcome> {
  if (!order.razorpay_order_id) {
    throw new Error("The payment order wasn't created. Please try again.");
  }
  if (!order.razorpay_key_id) {
    throw new Error("Online payment isn't configured yet — please contact your counselor.");
  }

  const Razorpay = await loadRazorpay();

  return new Promise<CheckoutOutcome>((resolve) => {
    let settled = false;
    const finish = (outcome: CheckoutOutcome) => {
      if (!settled) { settled = true; resolve(outcome); }
    };

    const checkout = new Razorpay({
      key: order.razorpay_key_id,
      order_id: order.razorpay_order_id,
      amount: order.amount_paise,
      currency: "INR",
      name: "BrowseJobs",
      description: order.name ?? "Store purchase",
      prefill: order.prefill ?? {},
      theme: { color: "#1b6df0" },
      handler: () => finish("paid"),
      modal: { ondismiss: () => finish("dismissed") },
    });

    checkout.on("payment.failed", () => finish("failed"));
    checkout.open();
  });
}
