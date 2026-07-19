# Zoom Setup Guide — from zero to live classes

Anyone can follow this. No coding needed. Total time: about 45 minutes.
You will do six parts, in order. Don't skip ahead — Part D must happen before the last step of Part C.

---

## Part A — Buy the right Zoom plan (10 min)

1. Go to **zoom.us** and sign in (or create an account) with a company email, e.g. `hello@browsejobs.ai`. This account becomes the "owner" of everything — use a company login, not a personal one.
2. Click **Plans & Pricing** → choose **Zoom Workplace Pro**.
3. Choose the **number of licenses**. Rule of thumb:
   - 1 license = 1 class running at a time.
   - If two trainers will ever teach at the same time, buy 2. Three at once → 3.
   - You can add more licenses later in two clicks, so start small.
4. Pay. (Pro is roughly ₹1,300 per license per month billed yearly — check the page for current pricing.)

> Why Pro and not Free? Free accounts have a 40-minute limit and **no cloud recording**. Recording is what puts class replays inside the LMS, so Pro is the minimum.

### A2 — Add your trainers as licensed users

1. Still on zoom.us, open **Admin → User Management → Users → Add Users**.
2. Enter each trainer's email, set **User Type = Licensed**, send the invite.
3. Each trainer accepts the email invite. Done when every trainer shows as **Licensed** in the list.

### A3 — Switch on cloud recording

1. **Admin → Account Management → Account Settings → Recording** tab.
2. Turn ON **Cloud recording**. Leave the defaults.

---

## Part B — Create the Zoom app (10 min)

This gives you 3 secret codes the LMS needs. Treat them like passwords.

1. Go to **marketplace.zoom.us** and sign in with the same account.
2. Top-right: **Develop → Build App**.
3. Pick **Server-to-Server OAuth** → **Create**. Name it `BrowseJobs LMS`.
4. The first screen shows your three codes. Copy each into a private note:
   - **Account ID**
   - **Client ID**
   - **Client Secret**
5. Fill the required "Information" fields (app name, company name, your email).
6. Open the **Scopes** step → **Add Scopes**, then search and tick:
   - `meeting` → tick the **admin** scopes for **read**, **write/create**, **update**, and **delete** meetings
   - `cloud_recording` (or `recording`) → tick the **admin read** scope
   - `user` → tick the **admin read** scope
7. Click through to **Activate** the app. It must say **Activated**.

---

## Part C — Connect Zoom's events to the LMS (10 min)

This is how the LMS learns who attended and when a recording is ready.

1. In the same app, open the **Feature** (Event Subscriptions) section.
2. Copy the **Secret Token** shown there — this is your 4th code.
3. **STOP. Go do Part D now** (paste all 4 codes into the LMS). Then come back here.
4. Click **Add Event Subscription**:
   - Name: `LMS events`
   - Event notification endpoint URL: `https://api.browsejobs.ai/api/webhooks/zoom`
5. Click **Add Events** and tick exactly these three:
   - **Meeting → Participant/Host joined meeting**
   - **Meeting → Participant/Host left meeting**
   - **Recording → All Recordings have completed**
6. Click **Validate** next to the URL. It should turn green ✔ (this only works after Part D is done).
7. **Save**.

---

## Part D — Enter the 4 codes in the LMS (5 min)

1. Log in to the LMS admin panel as the **super admin**.
2. Open **Settings** (left sidebar, visible to super admin only) → find the **Zoom** section.
3. Paste the four codes into the four boxes:

   | LMS field | Where you got it |
   |---|---|
   | Account ID | Part B, step 4 |
   | Client ID | Part B, step 4 |
   | Client secret | Part B, step 4 |
   | Webhook secret token | Part C, step 2 |

4. Click **Save**. That's it — no restart, no redeploy. (Secrets show as `••••` afterwards; that's normal.)
5. Now go back and finish Part C steps 4–7.

---

## Part E — Tell the LMS about your licenses (5 min)

1. In the LMS admin panel, open **Zoom licenses** (left sidebar).
2. For each Zoom license you bought, click **Add**:
   - **Zoom user**: the trainer's email exactly as it appears in Zoom's user list
   - **Label**: anything human, e.g. "Ravi – seat 1"
3. **Allocate** each license to its mentor/trainer using the picker.

> This is what lets two classes run at the same time — each trainer hosts under their own seat. A trainer without a license still works: their classes host under the main account (but then only one at a time).

---

## Part F — Test it end to end (10 min)

1. Admin → **Batches** → pick a batch → schedule a test class 15 minutes from now. Leave **Record this class** ticked.
2. Within a minute the class should show a **Join** link (the LMS creates the Zoom meeting in the background).
3. Join as the trainer (start URL) and as a test student (join button in the student portal) from another browser.
4. Stay 2–3 minutes, then end the meeting for everyone.
5. Check the results:
   - **Attendance**: the student's join/leave time appears on the session in admin.
   - **Recording**: 5–20 minutes after the class ends (Zoom needs time to process), the replay appears in the student's **My Classes**.

If all of that works, you're live. 🎉

---

## If something doesn't work

| Symptom | Fix |
|---|---|
| Part C "Validate" stays red | The 4 codes aren't saved in LMS Settings yet, or the Webhook secret token doesn't match. Redo Part D, then Validate again. |
| Class scheduled but no Join link | Account ID / Client ID / Client secret wrong, app not **Activated**, or a missing meeting scope. Check Part B. Also confirm the server's background worker is running (it is started by the deploy script). |
| "User does not exist" type errors on scheduling | The email in **Zoom licenses** doesn't exactly match a **Licensed** user in Zoom. Fix the email or license the user (Part A2). |
| Attendance empty after class | The two Meeting participant events aren't ticked in Part C, or students joined with an email different from their LMS login email. |
| No recording appears | Cloud recording off (Part A3), "Record this class" was unticked, or the Recording event isn't ticked in Part C. Wait 20 minutes before judging — Zoom processing takes time. |
