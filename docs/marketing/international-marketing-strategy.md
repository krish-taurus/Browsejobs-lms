# BrowseJobs International Go-To-Market Strategy
### Kenya · Nigeria · UAE · USA · Canada · Australia
**Version 1.1 · Planning document · IBrowseJobs Technologies Pvt Ltd**

**Changes in v1.1 (founder input, 2026-08):** no placement fee or revenue-share for candidates outside India; deployment support is an optional add-on at a flat **US$1,500**, identical in every market (§6.4 rewritten). **UAE added as a sixth market** and promoted into Wave 1 (§3.6, §3.7).
**Companion to:** `docs/browsejobs-lms-requirements.md` (PRD v1.8) and `docs/browsejobs-platform-spec-v1.txt` (Platform Spec v1.0).
**Status:** Strategy for founder approval. Nothing here overrides the PRD. Where this document requires new platform capability, it is listed in §12 as engineering work, not assumed.

---

## 0. How to read this document

Sections 1–7 are **decisions** — what we sell, to whom, at what price, with what words. Sections 8–11 are **execution** — channels, creative, communication. Sections 12–15 are **prerequisites and control** — what engineering must ship, who does what, what we spend, and how we know it worked. Section 16 is the **week-by-week plan**. Section 17 is where we agree in advance what failure looks like so we stop paying for it.

Every number marked *(assumption)* is a planning figure to be replaced by real data at first measurement. No figure in this document may be published in marketing copy until it comes from BrowseJobs internal data and carries the mandatory `DISCLAIMER` (Platform Spec §3.4).

---

## 1. Strategic diagnosis — the honest starting point

Good strategy starts with a diagnosis, not a wish list (Rumelt, *Good Strategy/Bad Strategy*). The diagnosis here is uncomfortable and it drives everything that follows.

**What BrowseJobs actually owns today:**

| Asset | Strength | Travels abroad? |
|---|---|---|
| Reverse-engineered syllabus from ~50 real interviews/day | Genuinely rare. Hard to copy. | **Yes** — the method travels; the *question data* is India-sourced and must be re-sourced per market |
| Free ladder (counselling → masterclass → bootcamp) | Best-in-class trust mechanic | **Yes** — unchanged |
| Radical-honesty brand voice | Differentiates in a category built on lies | **Yes** — and it is worth more abroad than at home |
| AI mock interviews + graded, video-verified evidence | Product-led proof | **Yes** — this is the strongest export |
| Placement network + employer relationships | Real | **No** — India only |
| Pay-after-placement fee (3 months' CTC) | Powerful in India | **Not exported** — replaced abroad by a flat US$1,500 optional deployment-support fee (§6.4) |
| INR pricing, Razorpay, IST scheduling | Works | **No** — engineering prerequisite, §12 |

**The central problem:** the funnel's emotional payoff is *"we place you."* Outside India we cannot say that, because it is not true, and because saying it is exactly the claim our own brand rules forbid (Platform Spec §3.3) and exactly the claim that regulators in the US, Canada and Australia prosecute. A strategy that quietly hopes nobody notices is not a strategy; it is a liability with a media budget.

**The resolution:** sell the part that *is* true and is *still* scarce — **the ability to walk into an interview and pass it, with evidence you can show.** Placement follows later, market by market, as the employer module (`docs/employer-module-requirements.md`) creates a real demand side. This is not a downgrade of the offer. In four of the five markets it is a *better* offer than what competitors are selling, because it is the only one the buyer will actually believe.

**Therefore: two businesses, one platform.**

| | **Cluster A — Growth markets** | **Cluster B — Diaspora & newcomer markets** |
|---|---|---|
| Markets | Kenya, Nigeria | UAE, Canada, Australia, USA |
| Core job-to-be-done | *"Get me a global-paying job from here."* | *"I already have skills. Get me past the interview in this country."* |
| Product | Full program: skilling → projects → mocks → global remote job access | Interview-readiness: gap diagnosis → targeted upskilling → proctored mocks → verified profile |
| Promise | Skills + evidence + access to global remote roles | Interview performance + verifiable proof, in writing |
| Price posture | Low ticket, high volume, local rails | Mid ticket, lower volume, card rails |
| Primary channel | WhatsApp, TikTok, creators, campus/community | LinkedIn, search, email, community orgs, YouTube |
| Placement fee model | No placement fee. Optional US$1,500 deployment support (§6.4) | No placement fee. Optional US$1,500 deployment support (§6.4) |
| Regulatory load | Moderate | **Heavy** — §11. UAE is moderate; USA is heaviest. |

Cluster A is a volume business run on trust and mobile. Cluster B is a credibility business run on precision and compliance. Do not let one contaminate the other: **do not run Nigerian creative in Canada, and do not run US compliance overhead in Kenya.**

**A note on the UAE.** It sits in Cluster B but is the odd one out, and in a useful way. In Canada and Australia the blocker is *being foreign*; in the UAE, being foreign is the norm — roughly nine in ten workers are expatriates. So the objection we are answering there is not "you lack local experience" but "there are four hundred other applicants with your exact profile." That makes the UAE the purest test of our actual differentiator: **evidence that you can pass the interview, when everyone's CV looks the same.**

---

## 2. The playbook this strategy is built on

Naming frameworks is cheap; the value is in which decision each one is actually driving. Here is exactly where each is load-bearing.

| Framework | Source | The decision it drives here |
|---|---|---|
| **Diagnosis → Guiding policy → Coherent action** | Rumelt | §1. We refuse the incoherent "export everything" plan and pick two coherent motions. |
| **Positioning as a deliberate choice** | April Dunford, *Obviously Awesome* | §5. Competitive alternatives differ by market, so the frame of reference differs by market — same product, different "compared to what." |
| **STP — segment, target, position** | Kotler | §3–§4. We target *segments*, not countries. "Canada" is not a segment; "Indian-trained IT professional, 0–24 months in Canada, no Canadian experience" is. |
| **Jobs-to-be-Done** | Christensen / Ulwick | §4–§5. The hiring job in Lagos ("earn in USD") is a different job from Toronto ("erase the Canadian-experience objection"). |
| **Mental & physical availability, category entry points, distinctive brand assets** | Byron Sharp / Ehrenberg-Bass | §9. Why we keep the ink Proof Engine panel, the mono numbers and the green/red promise cards in *every* market rather than localising the look — distinctive assets compound only if they are held constant. |
| **60/40 brand-vs-activation, with early-stage skew** | Binet & Field, IPA | §14. Year one runs ~35/65 brand/activation because we have no brand equity abroad to harvest; it moves toward 50/50 by month 12. |
| **Bullseye channel testing** | Weinberg & Mares, *Traction* | §8. We name 19 candidate channels, test 6 cheaply, and concentrate on 2 per market. Channel diversification early is the classic way to waste a budget. |
| **Value equation & offer construction** | Alex Hormozi, *$100M Offers* | §6. Dream outcome × perceived likelihood ÷ (time delay × effort). Our honesty doctrine is a *likelihood* multiplier — that is why it sells. |
| **Commitment & consistency, social proof, liking** | Cialdini, *Influence* | §7. Why the free ladder converts: three escalating free commitments before any ask. Keep the ladder intact abroad — do not shortcut to a paid offer. |
| **Category design** | *Play Bigger* | §9. "The Interview Index" — we do not compete for "best bootcamp," we create and own "the syllabus rebuilt from live interview demand." |
| **AARRR + North Star metric** | Dave McClure / Sean Ellis | §15. One north star per cluster, not a dashboard of vanity. |
| **Blue Ocean ERRC grid** | Kim & Mauborgne | §6.1. What we *eliminate* (fake guarantees, hype adjectives, opaque pricing) is as commercially important as what we add. |
| **Challenger Sale** | Dixon & Adamson | §8.6. The employer motion: lead with a teaching insight ("your funnel wastes 70% of recruiter hours on unscreened applicants"), not a feature list. |
| **Pirate-ship PR / earned media on proprietary data** | Standard data-journalism playbook | §9.2. The Interview Index is a link magnet and a journalist's dream because nobody else has the dataset. |

Two frameworks we deliberately **reject**: (a) growth-hacking via cold outbound at volume — illegal in Canada under CASL and in Australia under the Spam Act, and corrosive to a trust-first brand; (b) affiliate/commission-only lead resale — it puts our claims in the mouths of people we do not control, which is how education brands get fined.

---

## 3. Market intelligence

Figures below are planning-grade context for prioritisation, not marketing claims. Validate each with a primary source before any is used publicly.

### 3.1 Kenya
- **Shape of the market:** ~55M people, median age ~20. Nairobi is the most mature tech ecosystem in East Africa. English is the working language of business and education — no translation cost.
- **Money:** M-Pesa is the payment default, not a payment option. Card penetration is low. Any checkout without M-Pesa will fail regardless of how good the funnel is.
- **Competitive set:** Moringa School (premium local bootcamp), ALX (very large, heavily subsidised, community-driven), Andela (talent marketplace, not training), Zindi (data science community), university CS programs. Free/subsidised options are abundant — **we cannot win on price and must not try.** We win on evidence and interview specificity.
- **Buyer psychology:** high skepticism after several years of "digital skills" programmes that produced certificates and no work. Proof beats promises here more than anywhere.
- **Regulatory notes:** Data Protection Act 2019 (ODPC registration for controllers), Digital Service Tax and VAT obligations for non-resident digital suppliers, TVETA licensing questions for anything positioned as accredited vocational training. See §11.

### 3.2 Nigeria
- **Shape of the market:** ~230M people, median age ~18. The largest single pool of tech-ambitious youth in Africa. Lagos (Yaba) is the centre; Abuja and Port Harcourt secondary.
- **The dominant emotional driver:** foreign-currency income. Whether framed as remote work or relocation, the goal is earning outside the naira. Any message that does not connect to this is background noise. This is the single most important insight in this document for Nigeria.
- **Money:** Paystack and Flutterwave are the rails; bank transfer and USSD matter; naira volatility means **price in NGN but review monthly, or price in USD and let the PSP convert** (§6.2).
- **Competitive set:** AltSchool Africa (low monthly price, very large), ALX, Decagon (selective, financed model), Utiva, Semicolon, Tech4Dev, plus an enormous informal market of WhatsApp/Telegram "tech skill" sellers — which is precisely why an honesty-led brand can cut through.
- **Culture:** Nigerian tech X (Twitter) is unusually influential; Pidgin resonates in creative; community and church networks are real distribution.
- **Regulatory notes:** NDPA 2023 / NDPC; ARCON advertising vetting requirements for material exposed to the Nigerian market; FCCPA consumer protection. See §11.

### 3.3 United States
- **Do not target "America."** The addressable, winnable segment is the **immigrant and first-generation technical workforce** — H-1B/H-4/F-1 OPT/green-card holders and recent arrivals from South Asia and Africa — plus career-changers in adjacent IT roles (support, QA, ops) who need a specific interview-shaped upgrade.
- **Why this segment:** they already trust and search for India-origin training brands; their pain is not "learn to code" but "convert applications into offers in a market that keeps rejecting me"; and they are reachable without competing head-on with the enormous US bootcamp ad market.
- **Competitive set:** Springboard, Pathrise, Correlation One, Merit America, Per Scholas (free, nonprofit), Coursera/edX, and a large graveyard of collapsed bootcamps. **The graveyard is the message:** the category has a credibility vacuum and our honesty doctrine is built to fill it.
- **Regulatory notes — the heaviest in this document:** FTC Act §5 substantiation, the FTC Endorsement Guides and the 2024 fake-reviews rule, state vocational-school licensing (California BPPE, New York, Texas and others), TCPA and A2P 10DLC for SMS, CAN-SPAM for email, and Meta/Google **Special Ad Category** restrictions on employment-related advertising. Income-share/deferred-tuition products have drawn direct federal enforcement against bootcamps for misstated placement rates and unlicensed lending — see §6.4 and §11.3. **This is the reason the US launches without a deferred-fee product.**

### 3.4 Canada
- **The sharpest job-to-be-done of all five markets:** *"I have six years of IT experience from India and every employer here says I lack Canadian experience."* This objection is famous, specific, painful, and — critically — **solvable by our product**, because the fix is local-context interview performance and verifiable evidence, not more curriculum.
- **Segment:** new permanent residents and international students, concentrated in the GTA, Vancouver, Calgary and Montreal; large Indian, Nigerian and Filipino cohorts.
- **Competitive set:** Lighthouse Labs, BrainStation, provincially funded bridging programmes, and nonprofit newcomer-employment agencies (ACCES Employment and similar). **The nonprofits are partners, not competitors** — they have the audience and no product depth; we have product depth and no audience. See §8.5.
- **Regulatory notes:** CASL is the strictest anti-spam regime in the world — express or implied consent required before any commercial electronic message, with heavy penalties. The Competition Act's misleading-representation provisions carry revenue-linked penalties. PIPEDA plus Quebec's Law 25 and French-language obligations for Quebec marketing. Provincial career-college registration questions where a programme trains for employment for a fee. Employment-ad targeting restrictions apply as in the US. See §11.4.

### 3.5 Australia
- **Segment:** skilled migrants and recent international graduates, plus career-changers targeting the persistent cloud, data and cyber shortages. Sydney, Melbourne, Brisbane, Perth.
- **Competitive set:** Institute of Data, General Assembly, Academy Xi, TAFE and government-subsidised pathways.
- **Cultural note that must shape creative:** Australia lived through a national vocational-education funding scandal. Public and regulator tolerance for job-outcome claims and enrolment inducements is close to zero. **Understatement is the winning register here.** Our brand voice is already built for it — this is the market where "we will not promise you a job" is not a compliance concession but the strongest possible hook.
- **Regulatory notes:** Australian Consumer Law s18 misleading conduct (ACCC has an active history in education), Spam Act 2003, Do Not Call Register, Privacy Act/APPs, unfair-contract-terms regime with penalties, and ASQA/RTO rules — we must **not** imply accredited qualification status unless registered. See §11.5.

### 3.6 United Arab Emirates

- **Shape of the market:** ~11M people, of whom roughly nine in ten are expatriates. Dubai and Abu Dhabi dominate. English is the working language of business. The single largest expatriate community is South Asian — **an audience that already knows and searches for India-origin training brands.**
- **The job to be done:** *"There are four hundred applicants with my exact profile. Make me the one who gets the offer."* Not a skills gap and not a credibility gap — a **differentiation** gap. This is the purest fit for our evidence-led product of any market in this document.
- **Why it may be the best-fit market of the six:**
  - **Timezone is almost free.** GST is IST − 1:30. A class at 20:30 IST is 19:00 GST — local prime time in both countries simultaneously. No new trainer roster, no follow-the-sun cost.
  - **No FX risk.** The dirham is pegged to the US dollar, so a USD-denominated price is stable — unlike the naira.
  - **High willingness to pay**, tax-free incomes, mature card and wallet payment behaviour.
  - **Warm brand context.** Indian expatriates in the Gulf are the closest thing to our existing audience that exists outside India.
- **Competitive set:** a large field of locally licensed training institutes, the Indian edtech majors with Gulf operations, the global MOOCs, and a substantial informal market. Local institutes hold the permits; we hold the interview data.
- **Cultural and calendar notes that must shape the plan:** Ramadan materially changes both ad performance and webinar timing — schedule around it deliberately, and shift class times to post-iftar during the month. The working week is Monday–Friday. Content must be culturally reviewed; comparative advertising that disparages named competitors is not acceptable practice.
- **Regulatory notes:** training institutes operating in Dubai are permitted through **KHDA** (ADEK in Abu Dhabi, the Ministry of Education federally) — determine whether cross-border online delivery plus local marketing brings us into scope. Advertising is subject to UAE media-content rules and permit requirements. **UAE PDPL** governs personal data. VAT at 5% applies to services supplied to UAE customers, with registration obligations for non-resident suppliers of electronic services. **Most importantly for §6.4: UAE labour law prohibits charging a worker recruitment fees — the employer bears recruitment cost.** See §11.6.

### 3.7 Prioritisation

Scored 1–5 on reachability, willingness to pay, deliverability of our promise, competitive whitespace, and regulatory drag (inverted). *(assumption — re-score after Phase 0 research)*

| Market | Reach | WTP | Deliverable | Whitespace | Low reg. drag | Low ops friction | **Total** | Wave |
|---|---|---|---|---|---|---|---|---|
| **UAE** | 4 | 5 | 5 | 4 | 3 | **5** | **26** | **Wave 1** |
| Canada | 4 | 5 | 5 | 5 | 2 | 2 | **23** | **Wave 1** |
| Nigeria | 5 | 3 | 4 | 4 | 3 | 3 | **22** | **Wave 1** |
| Australia | 3 | 5 | 4 | 4 | 2 | 2 | **20** | Wave 2 |
| Kenya | 4 | 3 | 4 | 3 | 3 | 3 | **20** | Wave 2 |
| USA | 3 | 5 | 4 | 3 | 1 | 2 | **18** | Wave 3 |

*"Low ops friction" is added in v1.1 and it is the column that moves the UAE to the top: near-identical timezone, a dollar-pegged currency, English delivery and a warm audience mean the UAE costs less to run than any other market on this list.*

**Recommendation: launch UAE, Canada and Nigeria together in Wave 1.**

Three markets in Wave 1 rather than two, because the UAE adds almost no marginal operating cost — the same trainers, at nearly the same hour, in the same language, to an audience that already recognises us — while adding the highest-margin revenue in the set. The three together cover the whole strategy: **Nigeria proves Cluster A. Canada proves the hardest version of Cluster B. The UAE proves the cheapest version of Cluster B, and should be the first market to reach profitability.**

Kenya and Australia follow at day 90 on the proven playbook. The USA follows at day 180, organic-first, once licensing and claim-substantiation work is complete.

---

## 4. Segmentation and targeting

Six named segments. Everything downstream — creative, channel, sequence, landing page — is built per segment, never per country.

| # | Segment | Market | Trigger to buy | What they must believe to pay |
|---|---|---|---|---|
| **A1** | *The Naira Escape* — 22–30, degree or self-taught, employed or hustling, wants a remote role paying in foreign currency | NG | Sees a peer land a remote role | "This is the specific set of questions remote employers ask, and I will have proof I can answer them" |
| **A2** | *The Nairobi Proof-Seeker* — 21–28, has done a free programme already, has certificates and no offers | KE | Another rejection with no feedback | "This one is different because it shows me the actual interview and grades me" |
| **A3** | *The Campus Final-Year* — university student 6–12 months from graduating | NG, KE | Placement season | "I can enter the market ahead of my classmates" |
| **B1** | *The Canadian-Experience Wall* — 28–40, 3–10 yrs India/Africa IT experience, 0–24 months in Canada | CA | Third or fourth rejection at final round | "The gap is interview performance and local framing, not my skills — and this fixes exactly that" |
| **B2** | *The Visa-Clock Migrant* — F-1 OPT / recent grad / skilled-migrant with a time-bounded window | US, AU | Countdown pressure | "This compresses months of flailing into a measurable readiness score" |
| **B3** | *The Adjacent-Role Switcher* — support/QA/ops professional moving to cloud, data or DevOps | US, CA, AU, AE | Role eliminated, or a colleague switched | "The syllabus is what employers ask *this month*, not a 2019 curriculum" |
| **B4** | *The Gulf Differentiator* — 25–38, South Asian expat in the UAE, employed but stuck, competing against hundreds of identical CVs | AE | Passed over for a role they were qualified for | "Everyone here has my CV. This gives me something none of them can show." |

**Explicitly out of scope for year one:** absolute beginners with no technical background in Cluster B markets (long time-to-value, high refund risk, heaviest regulatory exposure); and anyone in any market who is looking for visa sponsorship or relocation assistance — **we do not provide immigration services and must never imply that we do.** This is a hard exclusion and must be enforced in ad copy review (§11.1).

---

## 5. Positioning and the messaging house

### 5.1 Positioning statements (Dunford method — frame of reference differs, product does not)

**Cluster A (NG, KE)**
> For ambitious African tech talent who keep collecting certificates that no employer respects, BrowseJobs is an interview-first training programme that rebuilds its syllabus every month from the questions companies are actually asking — so instead of a certificate, you finish with graded, video-verified proof that you can pass the interview. Unlike free skills programmes that end at a completion badge, every promise we make is in writing, and we tell you in advance what we cannot promise.

**Cluster B (CA, US, AU)**
> For experienced technologists who are getting interviews and not offers, BrowseJobs is an interview-readiness system built from live interview data — it finds the exact gap between your current answers and what interviewers in your market are asking, closes it, and gives you a proctored, graded record of your performance you can put in front of an employer. Unlike bootcamps that teach you skills you already have, we work on the thing that is actually costing you the offer.

### 5.2 The messaging house

**Roof (global, never localised — this is the distinctive brand asset):**
> **Built from real interviews.**
> *This syllabus was not written. It was reverse-engineered.*

**Three pillars (constant across markets, evidenced differently in each):**

| Pillar | Claim | Proof we show |
|---|---|---|
| **1. Live demand, not stale curriculum** | The syllabus is rebuilt from interviews happening now | The Proof Engine panel: LISTEN → EXTRACT → REBUILD, with the live counter and the monthly Interview Index |
| **2. Evidence, not certificates** | You leave with a graded, proctored, video-verified interview record | The mock-interview report and readiness score; "every application arrives pre-interviewed" |
| **3. Everything in writing** | What we promise and what we refuse to promise are both published | Green/red promise cards; 30-day money-back terms; "every call recorded & AI-monitored" |

**Floor (the honesty doctrine — the line that opens every market):**
> "Nobody can guarantee employment — the market decides. Here is what we put in writing instead."

### 5.3 Market-specific message angles

Same house. Different door.

| Segment | Headline angle | Opening line (for ads and landing hero) |
|---|---|---|
| A1 Nigeria | Foreign-currency outcome, honestly framed | *"Remote teams do not ask what school you attended. They ask these 40 questions. Here they are — free."* |
| A2 Kenya | Anti-certificate | *"You already have the certificate. Here is the part nobody trained you for: the interview."* |
| A3 Campus | Timing | *"Final year? The questions on this list are what you will face in eleven months. Start now."* |
| B1 Canada | Name the objection out loud | *"'You lack Canadian experience.' Here is what that sentence actually means — and the four things that fix it."* |
| B2 US/AU visa clock | Compression and measurement | *"Nine months left on your window. Stop guessing which gap is costing you the offer — measure it."* |
| B3 Switchers | Recency of the syllabus | *"The cloud interview changed in 2026. Most courses did not."* |

### 5.4 Banned language — enforce in code and in every review

Extends Platform Spec §3.3. Any of these in any market, any channel, any language is a stop-ship:

- "Guaranteed job", "100% placement", "assured placement", "job on completion", "we will place you"
- Any fixed or implied salary promise; any "earn $X" or "₦X million" hook
- Any statement or image implying visa sponsorship, relocation assistance, work-permit help, or immigration advice
- Any claim of accreditation, government recognition, degree equivalence, or university partnership that does not exist and is not documented
- Any fabricated or "adjusted" experience on a CV — including implying we will do it
- Hype adjectives: "world-class", "revolutionary", "best-in-class", "#1", "leading" (unqualified superlatives are separately unlawful in several of these markets)
- Any statistic without the mandatory `DISCLAIMER` component rendered immediately after it
- Urgency that is not literally true ("2 seats left" when it is not)
- Testimonials that are not from real, consented, verifiable students — and any testimonial presenting an atypical outcome without the disclaimer

**Enforcement mechanism (build it, do not rely on discipline):** a shared `BANNED_PHRASES` constant plus a CI lint over `content/`, ad-copy YAML and email templates, failing the build on a match. Marketing copy should be as testable as code. See §12.7.

---

## 6. Offer architecture and pricing

### 6.1 What we eliminate, reduce, raise and create (ERRC)

| | |
|---|---|
| **Eliminate** | Job guarantees. Fake scarcity. Opaque total cost. Hype adjectives. Commission-only lead resellers. |
| **Reduce** | Time-to-first-value (masterclass within 72h of signup, not 3 weeks). Curriculum breadth — depth on what is asked, nothing else. Upfront financial risk. |
| **Raise** | Specificity of proof. Speed of human contact after a lead. Quality of the written artefact the student leaves with. |
| **Create** | **The Interview Index** — a free monthly public report of what interviewers actually asked in each market. **The verified readiness record** — a shareable, employer-checkable proof of interview performance. |

### 6.2 The offer ladder by cluster

The free ladder is our single most valuable conversion mechanic. It **does not change abroad** — three free commitments before any ask (Cialdini: commitment and consistency; and it is why our CAC can survive a low-trust market).

**Cluster A — Nigeria, Kenya**

| Rung | Offer | Cost | Purpose |
|---|---|---|---|
| 01 FREE | Career Analysis Report (written, AI-drafted, human-approved) | Free | Lead capture + first written artefact |
| 02 FREE | Live Masterclass (90 min, local evening time) | Free | Primary conversion event |
| 03 FREE | 7-hour Bootcamp (real platform, real cohort) | Free | Product-led proof; engagement telemetry → lead score |
| 04 PAID | Full programme | **Local mid-ticket, see below** | Enrolment |

**Cluster B — UAE, Canada, Australia, USA**

| Rung | Offer | Cost | Purpose |
|---|---|---|---|
| 01 FREE | **Interview Gap Report** — one proctored AI mock + written diagnosis of exactly what is costing the offer | Free | The hook. This is the product demo *and* the lead magnet. Far stronger than a syllabus PDF for this segment. |
| 02 FREE | Live Masterclass — market-specific ("What Canadian interviewers actually ask") | Free | Conversion event |
| 03 FREE | 7-hour Bootcamp | Free | Proof |
| 04 PAID | **Interview-Ready programme** (12 weeks: targeted upskilling + unlimited proctored mocks + CV/ATS + verified readiness record) | **Mid-ticket, paid upfront or instalments** | Enrolment |

**Note the deliberate change at rung 01 for Cluster B.** For an experienced professional, a syllabus download is worthless — they know the syllabus. A free proctored mock that tells them *why they are losing offers* is a genuinely new, genuinely valuable artefact, and it is the single best use of the AI mock interviewer we have already built. It also does the qualification work for us: their score routes them to the right programme, and it gives the counsellor something real to talk about on the first call.

### 6.3 Price architecture

Prices are **decisions for the founder**, set here as a defensible starting frame. All figures must be server-owned (`config/fees.php`), never client-sent, and stored as integer minor units per currency (§12.1).

| Market | Currency | Registration / programme fee *(assumption — validate)* | Instalments | Optional Career Launch Support (§6.4) |
|---|---|---|---|---|
| Nigeria | NGN (review monthly for FX) | ₦180,000–₦250,000 | 3× monthly | US$1,500 — hard-currency roles only, see §6.4 |
| Kenya | KES | KES 45,000–65,000 | 3× monthly | US$1,500 — hard-currency roles only, see §6.4 |
| **UAE** | **AED** | **AED 4,500–6,500** | **3× monthly** | **US$1,500 flat** |
| Canada | CAD | CAD $1,200–1,800 | 3× monthly | US$1,500 flat, quoted in CAD |
| Australia | AUD | AUD $1,300–1,900 | 3× monthly | US$1,500 flat, quoted in AUD |
| USA | USD | USD $900–1,400 | 3× monthly | US$1,500 flat |

**Pricing principles:**
1. **Never convert the Indian price.** ₹30,000 is ~$360 — priced into Canada it signals "cheap and probably worthless" to a segment that equates price with seriousness. Cluster B prices are set against local *competitive alternatives* (§3), not against Indian cost.
2. **The add-on is never a condition of anything.** Enrolment, learning, mocks, certification and applying to any job must all work in full without it. Anything else makes it a gate, and a gated placement fee is exactly the instrument §6.4 exists to avoid.
3. **Never discount the headline price to close.** Use the existing voucher engine (PRD §6.8) with published, time-boxed, rule-based vouchers. Ad-hoc discounting destroys the honesty positioning faster than any competitor can.
4. **Publish the total cost.** In every market, one page shows every rupee/naira/dollar the student will ever pay. This is a differentiator in a category built on hidden fees, and it is a compliance asset in Cluster B.
5. **Keep the 30-day money-back guarantee everywhere.** It is the cheapest trust instrument we own and it is already in the brand promise. In Cluster B it also materially reduces regulatory risk on the enrolment conversation.

### 6.4 The deployment-support fee — how to structure it so it holds in all six markets

**The model (founder decision, 2026-08):** there is **no placement fee and no revenue-share for candidates outside India.** Deployment support is an **optional add-on at a flat US$1,500**, the same price in every market.

**Adopt it. This is a better instrument than the one this document originally proposed**, and it resolves the income-share problem outright:

- It is **flat, not proportional.** Nothing about it scales with the student's salary, so the central argument that it is an income-share agreement disappears.
- It is **small enough not to be a credit product.** US$1,500 is a service price, not a multi-year contingent obligation.
- It is **identical everywhere**, which makes it simple to publish, simple to defend, and impossible to accuse of opportunistic pricing.
- It is **honest in the brand's own terms** — the price does not rise because the student succeeded.

**But it introduces a different risk, and this one is serious.** The income-share problem is solved; an **employment-agency** problem takes its place. In most of these markets, charging a *job seeker* a fee connected to finding them work is licensed, restricted, or prohibited outright — and unlike the credit rules, this one bites hardest in the market that otherwise looks easiest.

| Market | The rule to check before charging a candidate anything placement-adjacent |
|---|---|
| 🇦🇪 **UAE** | **Recruitment fees may not be charged to the worker** — the employer bears recruitment cost. This is well established and actively enforced. **The strictest of the six on this point.** |
| 🇨🇦 Canada | Provincial employment-standards rules prohibit recruiters charging work seekers fees, with recruiter licensing regimes in some provinces |
| 🇦🇺 Australia | Employment agents are generally prohibited from charging work seekers a fee for finding them work |
| 🇺🇸 USA | Employment agencies are licensed state by state, with candidate-paid fees restricted or capped in many states |
| 🇰🇪 Kenya | Private employment agencies are regulated; charging job seekers is restricted |
| 🇳🇬 Nigeria | Overseas recruitment is licensed; charging candidates for foreign placement is tightly controlled |

**The fix is structural, not cosmetic. Three rules keep the US$1,500 clean everywhere:**

1. **It buys preparation, not placement.** Every deliverable must be something the student receives *whether or not they get a job*. The moment the fee is described as buying access to employers, submissions, or a placement outcome, it is an employment-agency fee and the table above applies.
2. **It is paid when the student opts in — never triggered by getting hired.** Contingency is the trap: an outcome-triggered fee is simultaneously a placement fee (agency rules) *and* deferred credit (lending rules). Charging it upfront on opt-in avoids both regimes at once. This is the single most important sentence in this section.
3. **Employers pay for placement. Candidates never do.** This is already the architecture of the employer module — a credit-based demand side. Keep candidate money and placement money on opposite sides of the marketplace and the entire category of risk goes away.

**What the US$1,500 should therefore contain** — all of it deliverable regardless of hiring outcome:

- ATS-grade CV and profile rebuild, market-specific
- Unlimited proctored mock interviews for the support window, human-reviewed
- The verified readiness record, published to their employer-facing profile
- Role-targeted interview coaching and salary-negotiation preparation
- Applications strategy and market-specific positioning coaching
- Onboarding and first-90-days support after they start a role

**Naming.** "Deployment" is Indian IT vernacular; abroad it reads as either opaque or as placement. Externally, name it for the preparation it delivers — **Career Launch Support** — and never as placement, deployment, recruitment or job assistance. The internal name can stay whatever the team already uses.

**Two open decisions for the founder:**

- **Does the flat US$1,500 apply to Cluster A local placements?** Against the Nigerian programme fee (~US$130) it is **eleven times the course price**, and against a local Lagos or Nairobi salary it is implausible; attach will be near zero and the optics are poor. Against a hard-currency remote role paying US$30–40k it is roughly 4–5% of first-year income — entirely rational and easy to justify. **Recommendation: keep the flat US$1,500 wherever the target role pays in hard currency (which is the whole Cluster A proposition anyway), and either price a lower local tier or decline to offer the add-on for local-market placements.** Do not let a defensible fee become an indefensible one through inattention.
- **Is US$1,500 charged in USD everywhere, or in local currency at a fixed equivalent?** Recommendation: **quote in local currency at a rate reviewed monthly**, because a USD price on a Canadian or Australian checkout adds friction and an FX surprise. The UAE is the exception where a USD price is effectively stable by peg.

**What this changes downstream:** the add-on is a second revenue line and it materially improves unit economics — see §14.2, where it moves the USA from clearly negative to roughly breakeven and makes the UAE the strongest market in the set. It also means **attach rate becomes a first-class metric** (§15.3), and the product must be able to sell, deliver and account for it (§12.12).

---

## 7. The funnel, localised

The funnel spine from PRD §5 is correct and stays. What changes is timing, channel, and language.

```
Ad / content / referral
   └─> Landing page (market-specific, geo-detected currency + timezone)
        └─> Rung 01 free artefact  (A: Career Report · B: Interview Gap Report)
             └─> Human/AI contact within 15 min          <-- speed-to-lead is the whole game
                  └─> Rung 02 Masterclass (local prime time)
                       └─> Rung 03 Bootcamp (free, real platform)
                            └─> Rung 04 Paid enrolment  (local currency, local rails)
                                 └─> Learning → mocks → verified readiness record
                                      └─> Employer module (Wave 3) → real placement claim
```

**The seven localisation rules:**

1. **Timezone is not a detail, it is the conversion.** Masterclasses must run at 19:00–20:30 *local*. WAT is IST−4:30, EAT is IST−2:30, GST is IST−1:30, ET is IST−9:30/10:30, AEST is IST+4:30/5:30. A single IST schedule will silently halve show rates in five markets. **The UAE is the happy exception** — 20:30 IST is 19:00 GST, so one class serves both countries at local prime time, which is a real and unusual operating advantage worth designing the Gulf schedule around. This is engineering work (§12.2), and it is the highest-ROI engineering item in this document.
2. **Speed-to-lead under 15 minutes.** The single largest, most reliably replicated finding in inbound lead research is that contact speed dominates almost every other variable. Automated WhatsApp/SMS instantly; human callback slot booked in the same interaction; counsellor coverage rostered to local evening hours, not Bengaluru business hours.
3. **Rung 01 differs by cluster** (§6.2) — do not ship one lead magnet for all five markets.
4. **Reminder cadence follows local norms.** Cluster A: WhatsApp at 24h/2h/10min (WhatsApp is the primary channel, not a fallback). Cluster B: email + calendar invite at 24h/1h, SMS only with explicit opt-in, **no WhatsApp cold contact in Canada** (§11.4).
5. **Show-rate insurance.** Masterclass registrants who do not attend get the recording plus a single re-book link — once. Not a nudge ladder. Respectful frequency is a brand asset in Cluster B and a deliverability requirement everywhere.
6. **One primary CTA per view** (design system rule) — abroad this matters more, because a confused visitor in a low-trust category simply leaves.
7. **Every message is a magic link into the exact page** (PRD Design law #1). Unchanged, and it is a genuine competitive advantage against every competitor named in §3.

**Funnel benchmarks to target** *(assumptions — instrument, then replace)*

| Step | Cluster A target | Cluster B target |
|---|---|---|
| Landing → lead | 12–18% | 6–10% |
| Lead → masterclass registration | 45% | 35% |
| Registration → attendance | 40–50% | 55–65% |
| Attendance → bootcamp start | 35% | 30% |
| Bootcamp completion → paid | 12–18% | 15–22% |
| **Lead → paid (blended)** | **1.0–2.0%** | **1.5–3.0%** |

---

## 8. Channel strategy

### 8.1 Bullseye: 19 candidates → 6 tests → 2 per market

**Outer ring (all candidates considered):** Meta ads · TikTok ads · Google Search · YouTube ads · LinkedIn ads · SEO/content · creator partnerships · campus ambassadors · WhatsApp communities · Telegram · X/Twitter · community orgs & NGOs · employer partnerships · webinars · PR/earned media · referral programme · diaspora sponsorship · podcast sponsorships · offline meetups.

**Middle ring — the six we actually test in Phase 1** (cheap, fast, ~US$2–3k each, 3 weeks):

| # | Test | Market | Hypothesis | Kill criterion |
|---|---|---|---|---|
| T1 | Meta ads → masterclass | NG | CPL under US$1.50, show rate over 35% | CPL > US$3.00 after 2 creative rounds |
| T2 | TikTok organic + spark ads (creator-led) | NG | Lower CPL than T1 at equal show rate | Show rate < 20% (traffic quality fail) |
| T3 | LinkedIn ads + organic → Interview Gap Report | CA | CPL under CAD $25, lead-to-paid over 2% | CPL > CAD $60 |
| T4 | Google Search on objection keywords | CA | "canadian experience" cluster converts at 2× social | CPL > CAD $80 |
| T5 | Community-org partnership (newcomer agencies) | CA | 3 signed partners, 100 referred leads, near-zero CAC | < 1 signed partner in 6 weeks |
| T6 | Interview Index content + PR | NG + CA | 500 organic leads/month by month 4 | < 100 by month 4 |

**Inner ring — expected winners to concentrate on** *(hypothesis, to be proven by the tests)*: **Nigeria → creator-led TikTok/WhatsApp + Meta retargeting. Canada → search + community partnerships + LinkedIn. UAE → Meta/Instagram + expat community networks + search.** Concentrate ~70% of market budget on the winning two channels by month 4. Do not spread.

### 8.2 Cluster A channel detail (Nigeria, Kenya)

- **WhatsApp is the platform, not a notification tool.** Every masterclass gets a WhatsApp community; cohorts get groups; the counsellor lives there. Use the Cloud API with per-country senders (§12.4). Build a "study buddy" broadcast list of alumni proof.
- **TikTok and Reels short-form, creator-led.** The winning format is *the interview clip*: a real (consented, anonymised) mock interview question, the candidate's answer, and the coach note on what a real interviewer wanted. This is our product, filmed. It is inexhaustible, distinctive, and impossible for a competitor without our data to copy.
- **Creator programme.** 10–15 mid-tier local tech creators (10k–150k followers) per market on a flat-fee + performance structure. Non-negotiable creator terms: no earnings claims, no job promises, mandatory `#ad`/paid-partnership disclosure, our banned-phrase list attached as a schedule to the contract, and 48h pre-publication review. Creator claims are *our* liability in every one of these five countries.
- **Campus programme.** 6 universities in Nigeria, 4 in Kenya. Student ambassadors, a free masterclass on campus per semester, a final-year "Interview Index: campus edition." This is the cheapest lead source available to us and it compounds year over year.
- **Diaspora sponsorship mechanic.** A "sponsor a learner" checkout path so a relative in the UK/US/Canada can pay in GBP/USD/CAD for a learner in Lagos or Nairobi. This is a well-established payment reality in African education and it directly solves the affordability ceiling. Engineering: §12.1.

### 8.3 Cluster B channel detail (Canada, USA, Australia)

- **Search is the highest-intent channel and our positioning is search-shaped.** Build content and paid coverage around objection keywords, not product keywords: *"canadian experience requirement"*, *"why am i failing final round interviews"*, *"cloud engineer interview questions 2026"*, *"how to explain employment gap after immigrating"*.
- **LinkedIn organic > LinkedIn paid, at first.** The founder's own voice plus coach accounts publishing Interview Index findings weekly. Paid amplifies what already earns engagement.
- **YouTube long-form.** One 15–25 minute *real, consented, unedited* mock interview teardown per week per market. This is the highest-trust asset available to us and it doubles as sales collateral, ad creative, and SEO.
- **Community organisations.** Newcomer employment agencies, settlement services, professional immigrant networks, alumni associations. They have the trust and audience; we have the product. Offer them free masterclasses for their members with no revenue share and no data harvesting — reputation compounds and the referral traffic is close to zero CAC.
- **No cold outbound in Canada or Australia.** Ever. Consent-first list building only (§11.4, §11.5).

### 8.4 The channel × segment matrix

| Channel | A1 NG | A2 KE | A3 Campus | B1 CA | B2 US/AU | B4 UAE | B3 Switchers |
|---|---|---|---|---|---|---|---|
| Meta ads | ●●● | ●●● | ●● | ● | ● | ●●● | ●● |
| TikTok | ●●● | ●●● | ●●● | ○ | ● | ● | ● |
| Google Search | ● | ● | ○ | ●●● | ●●● | ●●● | ●●● |
| LinkedIn | ● | ● | ○ | ●●● | ●●● | ●●● | ●●● |
| YouTube | ●● | ●● | ●● | ●●● | ●●● | ●● | ●●● |
| WhatsApp comms | ●●● | ●●● | ●●● | ●* | ●* | ●●● | ●* |
| Creators | ●●● | ●●● | ●●● | ● | ● | ●● | ● |
| Campus | ●● | ●● | ●●● | ○ | ● | ● | ○ |
| Community orgs | ● | ●● | ● | ●●● | ●● | ●●● | ● |
| SEO / Interview Index | ●● | ●● | ●● | ●●● | ●●● | ●●● | ●●● |
| Referral | ●●● | ●●● | ●● | ●● | ●● | ●●● | ●● |

●●● primary · ●● secondary · ● opportunistic · ○ no · \* opt-in only, never cold

### 8.5 Partnership motion (the highest-leverage, lowest-cost channel)

Three partnership types, in priority order:

1. **Newcomer / community employment organisations (CA, AU, US).** Offer: a free, co-branded masterclass series for their members, plus free Interview Gap Reports. Ask: introduction to their audience. This is the single best channel for segment B1 and it should be run by a person, not a campaign.
2. **Universities and tech hubs (NG, KE).** Offer: free final-year interview-readiness workshop plus a campus Interview Index. Ask: institutional endorsement and access to final-year cohorts.
3. **Employers (all markets, Wave 3).** This is the strategic one — see §8.6.

### 8.6 The employer motion — the bridge that makes placement true

The employer module already specifies a two-sided marketplace where *"every application arrives pre-interviewed."* That is a Challenger-style teaching pitch to a real, expensive recruiter pain, and it is the mechanism by which BrowseJobs earns the right to make placement claims in these markets.

**Sequence:** sign 25 employers per market on the free credit-capped tier → route graded candidates → measure real hire outcomes → *then*, and only then, market placement outcomes with substantiated data and the mandatory disclaimer.

**Year-one employer targets** *(assumption)*: Nigeria 40, Kenya 25, Canada 25, Australia 15, USA 15. Start with employers who already hire remote African talent and with immigrant-founded firms in Cluster B — warmest first.

---

## 9. Creative and the content engine

### 9.1 Distinctive brand assets — hold these constant in all five markets

Ehrenberg-Bass: distinctive assets only compound if they are never redesigned. Localise *language and casting*; never localise these:

- The ink navy Proof Engine panel with LISTEN → EXTRACT → REBUILD
- Every number in IBM Plex Mono — this is the brand's fingerprint and it reads as precision in every market
- The green/red promise-card pair
- The green FREE ladder with the single blue paid rung
- The mono uppercase kicker
- The footer line: *Every promise in writing · Every call recorded & AI-monitored.*

### 9.2 The Interview Index — the category-defining asset

**What it is:** a free monthly public report, per market, of what interviewers actually asked — top questions by role, what changed this month, what disappeared, and the honest note on sample size.

**Why it is the most important thing in this document after §1:** it is simultaneously our SEO engine, our PR hook, our creator content source, our lead magnet, our sales proof, our email newsletter, and our category claim. It is built from a dataset nobody else has. It converts our biggest product asset into our biggest marketing asset at near-zero marginal cost. And it is *entirely honest* — it reports data, it promises nothing.

**Operating model:** monthly cadence. Kenya and Nigeria launch on India data explicitly labelled as such (*"Global remote roles — India + remote interview data. Kenya-specific data begins Month 4."*) — **label the source honestly or the asset destroys the brand it is meant to build.** Local data accumulates as local cohorts run mocks; by month 6 each market has native data.

**Distribution per issue:** landing page (lightly gated) → PR to 15 tech publications per market → LinkedIn carousel + founder post → YouTube breakdown → 8–12 short-form clips → WhatsApp/Telegram community drop → email to the full consented list → sales enablement one-pager.

### 9.3 Creative formats that will do the work

| Format | Where | Why it works |
|---|---|---|
| **Real mock-interview teardown** (consented, graded, coach note) | YouTube, LinkedIn, TikTok | Product as content. Unfakeable. Highest trust-per-second of anything we can make. |
| **"What they actually asked"** single-question shorts | TikTok, Reels, Shorts | Infinite supply, native format, high save rate |
| **The green/red promise card, animated** | All paid social | Our differentiator in six seconds |
| **Founder direct-to-camera on one hard truth** | LinkedIn, YouTube | Honesty doctrine needs a human face |
| **Student proof with the disclaimer on-screen** | All | Social proof, compliantly |
| **Objection-named static ads** ("You lack Canadian experience.") | Meta, LinkedIn | Naming the exact pain outperforms every benefit claim |

**Creative volume plan:** 20 new assets per market per month in Cluster A (short-form velocity), 8 per market per month in Cluster B (quality over volume). Refresh Meta creative every 10–14 days; fatigue is the most common cause of a rising CPL.

### 9.4 The testimonial and review discipline

Extends PRD §5 Stage 3. Non-negotiable in all five markets: real students only, written consent on file, no incentive tied to a *public review* (incentivise the platform testimonial only — Google prohibits incentivised reviews and US rules on fake and incentivised reviews now carry direct penalties), material connections disclosed, and the mandatory `DISCLAIMER` rendered immediately after any outcome, salary or statistic — including inside video captions.

---

## 10. Communication strategy

### 10.1 Channel-by-market rules (this table is a compliance artefact — follow it exactly)

| Channel | NG | KE | AE | CA | US | AU |
|---|---|---|---|---|---|---|
| WhatsApp — transactional after opt-in | ✅ primary | ✅ primary | ✅ primary | ⚠️ express consent, CASL applies | ⚠️ opt-in, treat as SMS | ⚠️ Spam Act consent |
| WhatsApp — marketing broadcast | ✅ opt-in | ✅ opt-in | ✅ opt-in | ❌ | ❌ | ❌ |
| SMS | ✅ | ✅ | ⚠️ consent + TDRA rules | ⚠️ CASL express consent | ⚠️ TCPA + A2P 10DLC registration | ⚠️ Spam Act + DNC |
| Email — transactional | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Email — marketing | ✅ opt-in | ✅ opt-in | ⚠️ opt-in | ⚠️ **express/implied consent required, records kept** | ⚠️ CAN-SPAM: opt-out, physical address | ⚠️ consent + unsubscribe |
| Outbound calling | ✅ | ✅ | ⚠️ TDRA rules | ⚠️ DNC rules | ⚠️ TCPA, state rules | ⚠️ Do Not Call Register |
| Push / in-app | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |

**Global rule: consent is captured, timestamped, sourced and stored per contact per channel, and is auditable.** This is a platform requirement (§12.5), not a marketing habit. In Canada, the burden of proving consent is on us.

### 10.2 The core sequences

**Sequence A — Cluster A, lead → masterclass (WhatsApp-primary)**

| When | Channel | Content |
|---|---|---|
| T+0 min | WhatsApp + email | Career Report delivered + masterclass slot confirmed + calendar link |
| T+15 min | Counsellor call | Live human, local evening hours |
| T−24h | WhatsApp | Reminder + one-line "what you'll walk away with" |
| T−2h | WhatsApp | Reminder + magic join link |
| T−10 min | WhatsApp + push | "We're starting" |
| T+2h (attended) | WhatsApp | Bootcamp invite, one tap |
| T+2h (no-show) | WhatsApp | Recording + **one** re-book link. Then stop. |

**Sequence B — Cluster B, lead → masterclass (email-primary, consent-gated)**

| When | Channel | Content |
|---|---|---|
| T+0 min | Email | Interview Gap Report + readiness score + the three specific gaps |
| T+0 min | In-app | Book a free 20-min review with a coach |
| T+1 day | Email | "What your score means" + one anonymised teardown |
| T+3 days | Email | Masterclass invite, market-specific title |
| T−24h / T−1h | Email + calendar | Reminders |
| T+1 day post | Email | Recording + bootcamp invite |
| Ongoing | Email | Monthly Interview Index — the retention asset |

**Sequence C — the honesty sequence (run it in every market, unchanged)**
One email/WhatsApp in the middle of every nurture flow titled *"What we will not promise you."* It states plainly that no one can guarantee employment, lists what is in writing, and links the refund terms. Counter-intuitively this is consistently the strongest asset a trust-first brand can send — it is the message competitors structurally cannot copy.

### 10.3 Internal communication and sales enablement

- **Counsellor rosters by timezone.** WAT/EAT coverage 09:00–21:00 local; ET/PT and AEST coverage on a follow-the-sun roster. A lead in Toronto contacted at 04:00 ET is a wasted lead.
- **One market brief per market**, one page: segment, message, price, banned phrases, objection handling, escalation path. Every counsellor, creator and agency signs it.
- **Weekly 30-minute growth review** on a single dashboard (§15). Monthly deep-dive per market. Quarterly strategy re-score of §3.6.
- **All calls recorded and AI-monitored** — already the brand promise, and abroad it doubles as the claims-compliance control. Flag banned phrases in call transcripts automatically and escalate on hit.

---

## 11. Compliance and regulatory guardrails

**This section is a risk register, not legal advice. Every item requires sign-off from qualified local counsel before that market launches. Budget for it in §14 — it is line-itemed for a reason.**

### 11.1 Universal rules (all five markets)
- Never make, imply, or allow a partner to make a placement or salary promise (§5.4).
- Every statistic carries the `DISCLAIMER`, rendered by the shared component, in every medium including video.
- Substantiate before publishing: any outcome figure must be traceable to a query against our own data with a documented method and sample size. Keep the working. Regulators in three of these markets can demand it.
- Testimonials: real, consented, disclosed, non-incentivised for public reviews, atypical results labelled.
- Never imply immigration, visa or work-permit assistance.
- Never imply accreditation or government recognition we do not hold.
- Data: lawful basis, purpose limitation, retention schedule, deletion path, cross-border transfer assessment for every market.

### 11.2 Kenya & Nigeria
- **Kenya:** Data Protection Act 2019 — assess ODPC registration as a data controller; consent and cross-border transfer requirements. Tax: assess non-resident digital services VAT and Digital Service Tax obligations. Vocational-training positioning: confirm whether our offering triggers TVETA licensing; if not, never imply accreditation.
- **Nigeria:** NDPA 2023 and NDPC obligations. **ARCON:** advertising material exposed to the Nigerian market is subject to regulatory vetting and local-production requirements — factor approval time and local production into every campaign timeline; this is a schedule risk, not just a legal one. FCCPA consumer protection applies to our terms and refunds.

### 11.3 United States
- **FTC Act §5** — deception and substantiation. **Endorsement Guides** — disclosure of material connections with creators and students; "results not typical" does not cure an unsubstantiated general claim. **Fake-review rule** — no incentivised or insider reviews without clear disclosure.
- **State vocational-school licensing** — several states require registration or exemption for fee-charging career training. Determine state-by-state posture *before* running paid traffic there; consider launching in a narrow set of states first.
- **TCPA + A2P 10DLC** — SMS requires brand/campaign registration and documented consent. **CAN-SPAM** — functioning opt-out, physical postal address in every marketing email.
- **Meta and Google Special Ad Category** — advertising related to employment opportunities is subject to targeting restrictions. Assume our ads may be classified this way and build campaigns that work within the restrictions from day one rather than discovering it on rejection.
- **Deferred-tuition/ISA products** — see §6.4. Not in year one.

### 11.4 Canada
- **CASL** is the binding constraint on the entire communication plan. Commercial electronic messages require express or implied consent with records; identification and a working unsubscribe are mandatory; penalties are severe. **Practical consequence: our Canadian list must be built consent-first, and no WhatsApp or SMS marketing goes to a Canadian contact without documented express consent.**
- **Competition Act** — misleading representations, with revenue-linked penalties. Performance claims require adequate and proper testing *before* they are made.
- **PIPEDA** plus **Quebec Law 25** (consent, privacy officer, transfers) and **French-language requirements** for marketing directed at Quebec. Simplest year-one posture: **exclude Quebec from paid targeting until French creative and Law 25 compliance are ready** — a deliberate, documented choice, not an oversight.
- **Provincial career-college registration** — assess Ontario and BC requirements for fee-charging employment-oriented training.
- Employment-ad targeting restrictions apply as in the US.

### 11.5 Australia
- **Australian Consumer Law s18** — misleading or deceptive conduct. The ACCC has an active enforcement history in vocational education, and public sensitivity is high after the national VET funding scandal. **Treat every outcome-adjacent word as if it will be read aloud in a hearing.**
- **Spam Act 2003** — consent, sender identification, functional unsubscribe on email and SMS. **Do Not Call Register** for telemarketing.
- **Privacy Act / APPs** — including an APP 5 collection notice at every form.
- **Unfair contract terms** — penalties now apply; our enrolment terms, refund terms and any deferred obligation must be reviewed by Australian counsel.
- **ASQA/RTO** — do not imply accredited qualification status. Describe outcomes as skills, evidence and readiness — never as a qualification.

### 11.6 United Arab Emirates
- **Charging candidates for anything placement-adjacent is the headline risk.** UAE labour law places recruitment cost on the employer and prohibits charging the worker recruitment fees. The US$1,500 add-on must therefore be — and be documented as — a **preparation service purchased upfront**, never a fee for finding work, never triggered by an offer. See §6.4. **Get a written UAE counsel opinion on this specific fee before it is offered to a single UAE candidate.**
- **Training-provider permits:** institutes delivering training in Dubai are permitted through KHDA (ADEK in Abu Dhabi, the Ministry of Education federally). Determine whether cross-border online delivery combined with local marketing and local sales activity brings us into scope, and whether a free-zone or mainland presence is required.
- **Advertising:** UAE media-content rules and advertising permit requirements apply; comparative advertising naming competitors is not acceptable practice. Cultural review of every creative asset before publication.
- **Data:** UAE PDPL — lawful basis, notice, cross-border transfer assessment.
- **Tax:** VAT at 5% on services supplied to UAE customers; assess non-resident registration obligations for electronically supplied services. Corporate-tax position if any local presence is established.
- **Calendar:** plan campaigns and class times around Ramadan explicitly — both ad performance and evening availability shift materially.

### 11.7 The pre-launch compliance gate

No market goes live until every box is ticked. Store as a checklist in the repo and require sign-off:

- [ ] Local counsel opinion on offer, contract, refund terms and claims
- [ ] **Counsel opinion specifically on the US$1,500 Career Launch Support fee against local employment-agency and recruiter-fee rules (§6.4)**
- [ ] Employment-agency / recruiter licensing position determined for this market
- [ ] Entity/tax/VAT position determined and registered where required
- [ ] Privacy: lawful basis, notices, DSR path, cross-border transfer assessment, local registration if required
- [ ] Advertising pre-clearance where required (Nigeria/ARCON)
- [ ] Every claim on every page traced to substantiation evidence, filed
- [ ] `DISCLAIMER` rendering verified on every stat, every page, every video template
- [ ] Banned-phrase CI lint green across site, emails, ad copy and creator briefs
- [ ] Consent capture live and auditable per channel
- [ ] Unsubscribe/opt-out verified working on every channel
- [ ] Refund policy localised and published
- [ ] Complaint-handling and escalation path published with named owner

---

## 12. Platform readiness — engineering prerequisites

Marketing cannot outrun the product. These are the gaps between the platform as built and the platform this strategy needs. Each becomes a ticket against the PRD; none is optional for the market it blocks.

| # | Capability | Today | Needed | Blocks |
|---|---|---|---|---|
| **12.1** | **Multi-currency** | `config/fees.php` is INR-only, paise integers | Currency per tenant/market; integer minor units per currency; server-owned price catalogue per market; FX-safe display; diaspora cross-currency sponsorship path | **All 5 markets** |
| **12.2** | **Timezone-aware scheduling** | IST throughout | Per-batch and per-user timezone; local-time rendering in every message, reminder, calendar invite and dashboard; reminder jobs computed in local time | **All 5 markets — highest ROI item** |
| **12.3** | **Payment rails** | Razorpay only | Paystack/Flutterwave (NG), M-Pesa + card (KE), Stripe (US/CA/AU). `PaymentGateway` interface with per-market driver, mirroring the existing `CrmConnector` adapter pattern | Wave 1 |
| **12.4** | **Messaging per country** | WhatsApp Cloud API, single sender | Per-market WABA sender + template approval; A2P 10DLC registration for US SMS; per-market email sending domain and reputation | Wave 1 |
| **12.5** | **Consent ledger** | Basic | Per-contact, per-channel, per-purpose consent with source, timestamp, IP and proof; enforced at send time in the messaging service, not in the UI | **CA, AU, US — hard blocker** |
| **12.6** | **Geo tenancy** | Tenant 1 = BrowseJobs | Market as a first-class dimension: content, pricing, compliance copy, counsellor routing, reporting — reuse existing multi-tenancy rather than forking | All |
| **12.7** | **Claims lint** | None | `BANNED_PHRASES` constant + CI lint over content, email templates and ad-copy files; build fails on match. Extend the call-transcript pipeline to flag the same phrases in counsellor calls | All |
| **12.8** | **Market-local salary benchmarks** | Bengaluru/Pune/Hyderabad only, LPA | Per-market role/salary benchmark data with currency and local bands, or **suppress the salary UI entirely in markets where we lack data** — showing Indian LPA figures to a Toronto user is worse than showing nothing | CA, US, AU |
| **12.9** | **Localised landing pages** | Single India site | Market-routed pages with local currency, local timezone, local proof, local legal footer, hreflang, market-specific JSON-LD | All |
| **12.10** | **Interview Index publishing** | Data exists | Pipeline: extractions → per-market aggregation → human review → branded PDF (existing WeasyPrint pipeline) + web page + email issue | Month 2 |
| **12.12** | **Career Launch Support as a product** | Not modelled | The US$1,500 add-on as a first-class entitlement: opt-in purchase (never outcome-triggered), local-currency quoting, deliverable tracking, attach-rate reporting, and a refund path consistent with the 30-day guarantee | Wave 1 |
| **12.11** | **Attribution** | UTM capture, `funnel_events` | Server-side conversion API for Meta/Google/TikTok, market dimension on every funnel event, cohort-level CAC/LTV reporting | Month 1 |

**Sequencing note:** 12.2 (timezone) and 12.1/12.3 (currency and rails) are the true launch blockers. 12.5 (consent) blocks Cluster B specifically — with the partial exception of the UAE, where WhatsApp marketing is permitted on opt-in and the consent burden is closer to Cluster A. **The UAE is the cheapest market to make ready:** GST is a 90-minute offset from IST, AED is dollar-pegged, and delivery is in English. Everything else can ship in parallel with early traffic.

---

## 13. Team and ownership

Minimum viable structure for year one. Roles, not necessarily headcount — one person may hold several early.

| Role | Owns | Wave |
|---|---|---|
| **Head of International Growth** | The whole P&L, market prioritisation, budget allocation | Day 1 |
| **Performance Marketer** (1 per cluster) | Paid channels, creative testing, CPL/CAC | Day 1 |
| **Content & Interview Index Lead** | The monthly Index, SEO, YouTube, PR | Day 1 |
| **Market Lead — Nigeria** (local hire) | Creators, campus, communities, cultural accuracy | Wave 1 |
| **Market Lead — Canada** (local hire) | Community orgs, employer partnerships, compliance liaison | Wave 1 |
| **Market Lead — UAE** (local hire or Dubai-based contractor) | Expat community networks, employer partnerships, KHDA/permit liaison, cultural review | Wave 1 |
| **Counsellors** (timezone-rostered) | Speed-to-lead, masterclass conversion | Wave 1 |
| **Compliance & Claims Reviewer** | The §11.6 gate; sign-off on every asset before publication | Day 1 — **non-negotiable** |
| **Local counsel** (retained, per market) | Legal opinions, contract localisation | Before each market's launch |

**Hard rule:** no asset is published in any market without the Compliance & Claims Reviewer's sign-off, recorded. In a category this heavily regulated, one unapproved ad can cost more than the entire year's media budget.

**RACI for the recurring decisions:** budget reallocation — R: Head of Growth, A: Founder. Claim approval — R: Compliance Reviewer, A: Founder. Price changes — R: Head of Growth, A: Founder, C: local counsel. Creator contracts — R: Market Lead, A: Compliance Reviewer.

---

## 14. Budget model and unit economics

Illustrative on a **US$150,000** year-one international budget *(assumption — the structure matters more than the total; every line scales proportionally)*.

### 14.1 Allocation

| Line | Share | Amount | Note |
|---|---|---|---|
| Paid media — Canada | 16% | $24,000 | Hardest Cluster B test |
| Paid media — Nigeria | 14% | $21,000 | Cluster A volume test bed |
| **Paid media — UAE** | **13%** | **$19,500** | **Cheapest market to run, highest expected margin** |
| Paid media — Australia | 7% | $10,500 | Wave 2 |
| Paid media — Kenya | 6% | $9,000 | Wave 2 |
| Paid media — USA | 4% | $6,000 | Wave 3, retargeting only |
| Content & Interview Index production | 12% | $18,000 | The compounding asset |
| Creator programme | 7% | $10,500 | Cluster A weighted |
| **Legal, compliance & registrations** | **10%** | **$15,000** | Six jurisdictions, plus the §6.4 fee opinion in each. Do not cut this line. |
| Tooling, martech, attribution | 5% | $7,500 | |
| Experiment reserve | 6% | $9,000 | Unallocated by design |

**Brand vs activation:** ~35/65 in Q1–Q2, moving to ~45/55 by Q4 as the Interview Index builds mental availability (Binet & Field, adjusted for a business with zero brand equity in these markets).

### 14.2 Unit economics with the Career Launch add-on *(assumptions to be replaced by measured data)*

The flat US$1,500 add-on is a second revenue line, and it changes the picture enough that the Wave-3 decision on the USA rests on it. Attach rate is the variable that matters most and it is currently unknown — **measure it from the first cohort.**

| Market | Target CPL | Lead→paid | Implied CAC | Programme | Attach *(assumed)* | Add-on rev/student | **Total rev/student** | Payback |
|---|---|---|---|---|---|---|---|---|
| **UAE** | $16 | 2.3% | ~$696 | ~$1,300 | 40% | ~$600 | **~$1,900** | **Best in set — inside 1 cohort** |
| Nigeria | $1.20 | 1.5% | ~$80 | ~$130 | 12% | ~$180 | ~$310 | Inside 1 cohort |
| Kenya | $1.50 | 1.4% | ~$107 | ~$400 | 12% | ~$180 | ~$580 | Immediate |
| Canada | $22 | 2.2% | ~$1,000 | ~$1,100 | 30% | ~$450 | ~$1,550 | 1–2 instalments |
| Australia | $25 | 2.0% | ~$1,250 | ~$1,200 | 30% | ~$450 | ~$1,650 | 1–2 instalments |
| USA | $28 | 1.8% | ~$1,555 | ~$1,100 | 30% | ~$450 | ~$1,550 | **~Breakeven — still not scalable on paid** |

**Three things to read out of this table.**

1. **The UAE is the strongest market in the set** on these assumptions — roughly 2.7× revenue-to-CAC, on the lowest operating overhead of the six. That is why it moves into Wave 1.
2. **The USA moves from clearly negative to roughly breakeven.** That is an improvement, not a green light: breakeven on assumed numbers is a loss on real ones. The USA still launches last, organic-first, with paid restricted to retargeting.
3. **Cluster A attach is deliberately modelled low (12%)** because a flat US$1,500 against a local Lagos or Nairobi salary is implausible — see §6.4. If Cluster A attach is instead measured against hard-currency remote placements only, the realistic attach on *that* subset is much higher, and the number to track is **attach among students targeting hard-currency roles**, not attach overall.

**Timing caveat:** add-on revenue arrives later than programme revenue — it is purchased at the placement-preparation stage, not at enrolment. Track **payback on programme fee alone** as the primary gate, and treat add-on revenue as margin upside rather than as the thing that justifies a market. A market that only works if the add-on attaches is a market that does not work.

### 14.3 The stage-gate

Money is released in tranches, not committed up front:

- **Gate 1 (end of month 1):** any market where CPL is more than 2× target after two creative rounds is paused, not optimised.
- **Gate 2 (end of month 3):** a market must show a real lead→paid conversion, not just leads, to keep its budget.
- **Gate 3 (end of month 6):** CAC payback inside one cohort, or the market moves to organic-only.

---

## 15. Measurement

### 15.1 North Star

- **Cluster A north star:** *weekly bootcamp completions* — it is the closest leading indicator of revenue that is not gameable by lead volume.
- **Cluster B north star:** *weekly completed Interview Gap Reports* — it captures both acquisition and genuine product engagement in one number.
- **Company-level:** *verified placements per market* — the metric that eventually earns us the right to make the placement claim (§8.6).

### 15.2 The KPI tree

```
Revenue per market
├─ Enrolments
│  ├─ Bootcamp completions      <-- Cluster A north star
│  │  ├─ Masterclass attendance
│  │  │  ├─ Registrations
│  │  │  │  └─ Leads (by channel, by creative)
│  │  │  └─ Show rate            <-- timezone-sensitive, §7.1
│  │  └─ Bootcamp→paid rate      <-- offer & counsellor quality
│  └─ Gap Reports completed      <-- Cluster B north star
└─ Price realisation (discount leakage — target: zero)
```

### 15.3 Guardrail metrics — watch these as closely as the growth numbers

**Career Launch attach rate** (overall, and among students targeting hard-currency roles — §14.2), refund rate (<5%), complaint rate, unsubscribe rate (<0.5%), email spam-complaint rate (<0.1%), WhatsApp block rate, **ad account health per market**, review sentiment, counsellor call banned-phrase flags (**target: zero**), and speed-to-lead p50 and p90 (target: p90 under 15 minutes).

### 15.4 Instrumentation
Every lead carries market, segment, channel, campaign, creative and consent source. Server-side conversion APIs (privacy rules make browser pixels unreliable in these markets). One weekly dashboard, reviewed for 30 minutes, every week, by the same people.

---

## 16. The step-by-step implementation plan

### PHASE 0 — Foundation (Days 1–30) · *No paid spend*

**Week 1 — Decide**
1. Founder signs off §1 (two-cluster strategy), §3.7 (UAE + Canada + Nigeria in Wave 1), §6.4 (the US$1,500 add-on structured as upfront preparation, never outcome-triggered).
2. Appoint Head of International Growth and Compliance & Claims Reviewer.
3. Retain local counsel in the UAE, Canada and Nigeria; brief them on the offer, contract, refund terms, claims **and specifically on the US$1,500 fee against local employment-agency and recruiter-fee rules (§6.4). The UAE opinion is the one to get first — it is the strictest of the six on candidate-paid fees.**
4. Open engineering tickets for §12.1, §12.2, §12.3, §12.5, §12.7, §12.11 and §12.12 (Career Launch Support as a purchasable entitlement).

**Week 2 — Research and price**
5. Validate every *(assumption)* in §3 against primary sources; re-score §3.7.
6. Interview 10 people per Wave-1 market from the target segment. Ask what they tried, what failed, and what they paid for. This is worth more than any competitive analysis.
7. Set final prices per market (§6.3) and load them into the server-owned catalogue.
8. Competitive teardown: 5 competitors per market — offer, price, claims, funnel, ad library.

**Week 3 — Build the message**
9. Write the market briefs (one page each) from §5.
10. Ship the `BANNED_PHRASES` constant and the CI lint (§12.7).
11. Produce the first Interview Index issue (India + remote data, honestly labelled).
12. Draft all Sequence A/B/C copy; compliance review; legal review for Canada.

**Week 4 — Build the machine**
13. Ship localised landing pages for AE, CA and NG (§12.9) with local currency, timezone, legal footer.
14. Ship the Interview Gap Report as a self-serve free flow (Cluster B rung 01).
15. Consent ledger live (§12.5). Payment rails live for NG (Paystack/Flutterwave), CA and AE (Stripe). Publish the Career Launch Support page: flat price, full deliverable list, and the explicit statement that it is optional and buys preparation, not placement.
16. Counsellor roster for WAT, GST and ET evening hours — note that GST evening coverage costs almost nothing on top of the existing IST roster. Speed-to-lead automation tested end to end.
17. **Run the §11.7 compliance gate for the UAE, Canada and Nigeria. Do not proceed on a red box.**

### PHASE 1 — Wave 1 launch and channel discovery (Days 31–90)

**Weeks 5–7 — Test**
18. Launch tests T1–T6 (§8.1). Small budgets, clean measurement, 3 weeks minimum before judging.
19. First masterclass in each Wave-1 market, local prime time — the UAE session can run off the existing IST slot at 19:00 GST. Target 100 registrations, 40% attendance.
20. Recruit and contract 8 creators in Nigeria (with the banned-phrase schedule attached).
21. Open conversations with 10 Canadian community organisations; target 3 signed.
22. Publish Interview Index issue 2; begin PR outreach. Open UAE expat-community and employer conversations in Dubai and Abu Dhabi.

**Weeks 8–10 — Learn**
23. First paid cohorts enrol. Instrument every step; replace §7 benchmark assumptions with real numbers. **Begin measuring Career Launch attach from the first cohort that reaches placement-preparation stage.**
24. Kill the losing channels at Gate 1 (§14.3). No sentiment, no "one more week."
25. Double creative volume on the winning channel; refresh every 10–14 days.
26. Launch the referral programme to first cohorts (their networks are the cheapest lead source we will ever have).

**Weeks 11–13 — Consolidate**
27. Concentrate ~70% of each market's budget on its two winning channels.
28. First market-native Interview Index data appears; publish it prominently.
29. Sign the first 10 employers per Wave-1 market on the free tier (§8.6).
30. Gate 2 review. Write the Wave-2 playbook from what actually worked, not what was planned.

### PHASE 2 — Wave 2: Kenya and Australia (Days 91–180)

31. Run the §11.7 gate for Kenya and Australia. M-Pesa integration live for Kenya; Australian counsel review of terms and unfair-contract-terms exposure.
32. Clone the proven playbook — localise language, casting, price, timing and legal only. Do not redesign the funnel.
33. Kenya: campus programme at 4 universities; creator programme; Nairobi tech-community partnerships.
34. Australia: search + LinkedIn + community organisations. Understated creative register (§3.5).
35. Interview Index now runs four market editions monthly.
36. Employer module opens to Wave-1 markets; begin measuring real hire outcomes.
37. Gate 3 review across all four live markets. Any market failing CAC payback moves to organic-only.

### PHASE 3 — Wave 3: USA and compounding (Days 181–365)

38. US state-licensing posture determined; launch in a narrow, cleared set of states.
39. US launches **organic-first**: Interview Index, YouTube teardowns, LinkedIn, community and employer partnerships. Paid restricted to retargeting until unit economics clear (§14.2).
40. Substantiated placement data now exists for Wave-1 markets — publish it, with the disclaimer, and only then begin outcome-led messaging in those markets.
41. Review Career Launch attach and delivery quality per market; adjust price or scope only on measured data, never to close a sale.
42. Annual review: re-run §3.7 scoring, re-cut budget by measured CAC payback, retire what did not work.

---

## 17. Risks and kill criteria

| Risk | Likelihood | Impact | Mitigation | Kill criterion |
|---|---|---|---|---|
| Ad account banned for employment/outcome claims | Medium | High | Banned-phrase lint, pre-clearance, Special Ad Category compliance from day one | Two bans in one market → pause paid, go organic |
| Regulatory action on claims (US/CA/AU) | Low–Medium | **Severe** | §11 gate, counsel sign-off, substantiation file | Any formal notice → freeze all market activity pending counsel |
| We cannot deliver the promise (no local employers) | **High if unmanaged** | Severe | Never promise placement abroad; sell readiness; build employer side first | Any placement claim published without substantiation is a stop-ship incident |
| CAC exceeds payback in Cluster B | Medium | High | Stage gates §14.3 | Gate 3 failure → organic-only |
| FX volatility destroys Nigerian pricing | High | Medium | Monthly price review, or USD pricing with PSP conversion | Margin below 40% for two months → reprice |
| Career Launch fee reframed as a placement fee in copy or on a call | Medium | **Severe** | §6.4 rules in every brief; phrase lint; call-transcript flagging | Any instance → correct immediately, retrain, review how it shipped |
| Creator says something we cannot defend | Medium | High | Contractual schedule, 48h pre-publication review, takedown clause | One breach → terminate, publish correction |
| Brand dilution across five markets | Medium | Medium | Distinctive assets held constant (§9.1); localise language, never identity | — |
| Platform not ready (timezone/currency) at launch | Medium | High | §12 blockers gate the launch date, not the other way round | Launch slips rather than shipping a broken checkout |
| Support and counsellor load across timezones | High | Medium | Roster by timezone before launch, not after | p90 speed-to-lead over 60 min for two weeks → pause acquisition |

---

## 18. The one-paragraph version

Do not export the Indian business. Export the Indian *method*. In Nigeria and Kenya, sell a low-ticket, WhatsApp-and-creator-led programme to young people who want globally-paid work and are exhausted by worthless certificates — the free ladder works there almost unchanged. In Canada, Australia and the USA, sell something different to a different person: interview-readiness and verifiable proof to experienced immigrants who are getting interviews and not offers — no placement promise, no income-share agreement, and a flat, optional US$1,500 that buys preparation and is never triggered by getting hired. Bind both together with one asset nobody else can build: a monthly public Interview Index of what employers are actually asking, which is simultaneously our SEO engine, our PR hook, our lead magnet and our category claim. Launch the UAE, Canada and Nigeria together, because the UAE is nearly free to run alongside India and should be first to profit, Canada proves the hardest version of the strategy, and Nigeria proves the volume version. Gate every market on compliance and on CAC payback inside one cohort. And keep saying the thing that competitors structurally cannot say — that nobody can guarantee a job, and here is everything we will put in writing instead. That sentence is the product.

---

*Every promise in writing · Every call recorded & AI-monitored.*
