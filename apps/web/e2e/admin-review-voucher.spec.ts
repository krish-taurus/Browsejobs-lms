import { test, expect } from "@playwright/test";

/**
 * P2.6 DoD flow: staff signs in, sees the seeded review-reward voucher in the
 * voucher manager, then approves the seeded pending testimonial — which publishes
 * it and issues the voucher.
 *
 * Requires the Laravel API on :8000 with the local seed (admin
 * test@example.com / password) and the VoucherSeeder demo (review-reward voucher
 * "Testimonial reward" + one pending testimonial).
 */
test.use({ baseURL: "http://localhost:3000" });

test("admin manages vouchers and approves a testimonial", async ({ page }) => {
  await page.goto("/admin");
  await page.getByPlaceholder("Work email").fill("test@example.com");
  await page.getByPlaceholder("Password").fill("password");
  await page.getByRole("button", { name: "Sign in" }).click();
  await expect(page).toHaveURL(/\/admin\/curriculum/);

  // Voucher manager shows the seeded review-reward voucher + analytics tiles.
  await page.getByRole("link", { name: "Vouchers" }).first().click();
  await expect(page.getByRole("heading", { name: "Vouchers" })).toBeVisible();
  await expect(page.getByText("Total discounted")).toBeVisible();
  await expect(page.getByText("Testimonial reward").first()).toBeVisible();
  await expect(page.getByText("review reward").first()).toBeVisible();

  // Moderation queue: approve the seeded pending testimonial.
  await page.getByRole("link", { name: "Testimonials" }).first().click();
  await expect(page.getByRole("heading", { name: "Testimonials" })).toBeVisible();
  await page.getByRole("button", { name: "Approve & issue voucher" }).first().click();

  // It now appears under the Approved tab with an issued-voucher badge.
  await page.getByRole("button", { name: "approved" }).click();
  await expect(page.getByText(/voucher [A-Z0-9]+/).first()).toBeVisible();
});
