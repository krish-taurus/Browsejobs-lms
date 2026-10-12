import type { Metadata } from "next";
import Link from "next/link";
import { ApShell } from "@/components/ap/ApShell";
import "@/components/ap/pages/taurus.css";
import { HiringDemo } from "@/components/taurus/HiringDemo";
import { contact } from "@/content/landing";
import { SEO_PAGES } from "@/content/seo-nav";
import { breadcrumbNode, faqNode, jsonLdGraph, moneyMetadata, webPageNode } from "@/lib/seo";

const page = SEO_PAGES.taurusRecruitment;
const [titleName, titlePayoff] = page.title.split(": ");

const crumbs = [
  { name: "Home", path: "/" },
  { name: "Taurus AI", path: "/taurusai" },
  { name: "Hiring floor", path: page.path },
] as const;

const storySteps = [
  ["You ask on WhatsApp", "Message Taurus like you'd message a recruiter: “I need 5 software developers in Bangalore, 3–6 years, React and Node.”"],
  ["Sourcing", "The sourcing bot pulls matching profiles from the BrowseJobs graded pool and your job post."],
  ["CV screening", "The screening agent scores every CV against your brief, then asks on WhatsApp: “38 match. Shall I get in touch?”"],
  ["AI screening calls", "Once you say yes, the AI caller phones each candidate to confirm interest, notice period and expected salary."],
  ["Shortlist on WhatsApp", "The shortlist arrives in your chat with notes from every call, and Taurus asks whether to start L1."],
  ["L1 round", "Everyone on the shortlist gets a link to a role-specific AI interview, graded after it finishes."],
  ["L2 round", "Candidates who clear L1 move to a deeper technical AI round."],
  ["Pre-BGV (optional)", "If you want it for the role, and with each candidate's consent, employment history is checked on EPFO and documents on DigiLocker. A mismatch is flagged for you, never auto-rejected."],
  ["Your interview round", "Taurus books your panel's interviews with the finalists and collects the decision."],
  ["Offer", "Offer letters go out only after you approve them in the chat."],
] as const;

const parameters = [
  ["In stage", "How many candidates are sitting in each stage right now."],
  ["Moved today", "How many entered the stage since midnight IST."],
  ["Average score", "The mean screening or interview score, once grading has finished. Empty until then."],
  ["Time in stage", "How long candidates have waited in a stage, so stalls are visible."],
  ["Waiting for you", "Finalists to interview and offers to sign off. These pulse until you act."],
] as const;

const faqs = [
  {
    q: "What is the Taurus hiring floor?",
    a: "A live 3D view of a hiring pipeline. The ring is split into the stages of hiring, each stage has the bot that does its work, and candidates travel from stage to stage as they move. The panels show the numbers behind it, and the chat shows every question Taurus asks you on WhatsApp.",
  },
  {
    q: "Is the floor on this page real?",
    a: "No. This demo runs a simulation in your browser and every number on it is sample data. An employer signed in to BrowseJobs sees their own pipeline instead, built from their real jobs and applications.",
  },
  {
    q: "Can a bot reject a candidate or send an offer by itself?",
    a: "No. Automation can move a candidate forward or park them for review. It can't reject anyone, and it can't release an offer. Those stay with your team.",
  },
  {
    q: "Can candidates see this?",
    a: "No. The hiring floor is for the employer's team. Events describe the role and the stage, not the candidate's name.",
  },
  {
    q: "Can we talk to it?",
    a: "Yes. Type or ask out loud and Taurus answers from your live pipeline, in one or two sentences. It only quotes numbers it can see, and it can't move candidates or send offers for you.",
  },
  {
    q: "How do we get it for our roles?",
    a: "Post your roles in the BrowseJobs employer workspace and open Hiring floor from the menu. If you're not on BrowseJobs yet, talk to us about a role.",
  },
] as const;

export const metadata: Metadata = moneyMetadata(page);

/** The Taurus hiring floor, in the Apple-direction design: the WhatsApp hiring story as the product shot. */
export default function TaurusRecruitmentPage() {
  const jsonLd = jsonLdGraph([webPageNode(page), breadcrumbNode(crumbs), faqNode(faqs)]);

  return (
    <>
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd) }} />
      <ApShell current="employers" staticNav cta={{ label: "Talk to us", href: "/employers" }}>
        <nav className="lnav lnav--dark" aria-label="Taurus hiring floor">
          <div className="lnav-inner">
            <span className="lnav-title">
              Taurus hiring floor<small>Powered by Taurus AI</small>
            </span>
            <div className="lnav-right">
              <ul className="lnav-links">
                <li>
                  <a href="#demo">Demo</a>
                </li>
                <li>
                  <a href="#story">How a hire happens</a>
                </li>
                <li>
                  <a href="#parameters">Dashboard</a>
                </li>
                <li>
                  <a href="#faq">FAQ</a>
                </li>
              </ul>
              <Link className="btn btn-primary" href="/employer">
                Open your floor
              </Link>
            </div>
          </div>
        </nav>

        {/* hero */}
        <section className="s-black tx-hero" id="top">
          <div className="wrap center">
            <nav aria-label="Breadcrumb" className="tx-crumbs">
              {crumbs.map((crumb, index) => (
                <span key={crumb.path}>
                  {index > 0 && <span aria-hidden="true">{" › "}</span>}
                  {index < crumbs.length - 1 ? <Link href={crumb.path}>{crumb.name}</Link> : <span aria-current="page">{crumb.name}</span>}
                </span>
              ))}
            </nav>
            <p className="eyebrow">Taurus hiring floor</p>
            <h1 className="h-hero tx-title">
              <span>{titleName}:</span> <span className="tx-payoff">{titlePayoff}</span>
            </h1>
            <p className="lead">
              Ask for the people you need on WhatsApp. Taurus sources, screens, calls and interviews candidates, and checks with you in the chat before
              every step that matters. You watch it all happen on one live floor.
            </p>
            <p className="lead tx-lead-2">
              It runs on the same Taurus brain as our business floor. Sign in and you see what each bot is doing, where candidates are waiting and what
              needs a decision from you, and you can ask Taurus about any role, out loud.
            </p>
            <div className="cta-row">
              <Link className="btn btn-primary" href="/employer">
                Open your hiring floor
              </Link>
              <Link className="more" href="/employers">
                Talk to us about a role <span className="chev" aria-hidden="true">›</span>
              </Link>
            </div>
          </div>
          <div className="wrap-wide" id="demo">
            <section className="tx-shot tx-shot--hiring" aria-label="Hiring floor demo">
              <HiringDemo />
            </section>
            <p className="fine tx-shot-note">
              An illustration with sample numbers, simulated in your browser. Tap the green replies in the chat, or let the story play on its own. The
              glowing tokens travelling round the ring are candidates moving to the next stage.
            </p>
            <div className="cta-row tx-shot-links">
              <Link className="more" href="/taurusai/hiring-demo">
                Watch it full screen <span className="chev" aria-hidden="true">›</span>
              </Link>
              <Link className="more" href="/employers/mission-control-demo">
                See the AI Recruiter demo <span className="chev" aria-hidden="true">›</span>
              </Link>
            </div>
          </div>
        </section>

        {/* the story, stage by stage */}
        <section className="s-white chapter" id="story">
          <div className="wrap">
            <div className="center tx-head">
              <p className="eyebrow">How a hire happens</p>
              <h2 className="h-section">From one WhatsApp message to an offer.</h2>
            </div>
            <ol className="tx-stages">
              {storySteps.map(([title, body], i) => (
                <li key={title}>
                  <span className="tx-n num" aria-hidden="true">
                    {i + 1}
                  </span>
                  <div>
                    <h3 className="h-card">{title}</h3>
                    <p className="body">{body}</p>
                  </div>
                </li>
              ))}
            </ol>
            <p className="tx-callout">Contacting candidates, running checks and releasing offers each wait for your yes. Nothing is sent on a bot&apos;s say-so.</p>
          </div>
        </section>

        {/* dashboard parameters */}
        <section className="s-paper chapter" id="parameters">
          <div className="wrap">
            <div className="center tx-head">
              <p className="eyebrow">On the dashboard</p>
              <h2 className="h-section">What each bot reports.</h2>
            </div>
            <dl className="tx-rows">
              {parameters.map(([term, def]) => (
                <div key={term}>
                  <dt>{term}</dt>
                  <dd>{def}</dd>
                </div>
              ))}
            </dl>
            <p className="fine center">Scores stay empty until grading finishes, and nothing on the floor is a forecast. It shows where your pipeline stands now.</p>
          </div>
        </section>

        {/* for employers */}
        <section className="s-black chapter" id="employers">
          <div className="wrap center">
            <p className="eyebrow">For employers</p>
            <h2 className="h-section">Your real pipeline, once you sign in.</h2>
            <p className="lead">
              In the BrowseJobs employer workspace, Hiring floor builds this view from your own jobs, applications and AI interviews, and refreshes as
              candidates move. Filter by role to watch one opening at a time, and ask Taurus questions like “how many finalists do we have for the data
              engineer role?”. It answers from your pipeline, out loud if you like.
            </p>
            <div className="cta-row">
              <Link className="btn btn-primary" href="/employer">
                Open your hiring floor
              </Link>
              <Link className="more" href="/employers">
                Talk to us about a role <span className="chev" aria-hidden="true">›</span>
              </Link>
            </div>
            <p className="fine">
              Not hiring with BrowseJobs yet? <Link href="/employers">Talk to us about a role</Link>, or read how <Link href="/ai-hiring">AI hiring</Link>{" "}
              works.
            </p>
          </div>
        </section>

        {/* FAQ */}
        <section className="s-paper chapter" id="faq">
          <div className="wrap center">
            <p className="eyebrow">Questions</p>
            <h2 className="h-section">Asked before you book.</h2>
            <div className="faq tx-faq">
              {faqs.map((item) => (
                <details key={item.q}>
                  <summary>
                    <h3 className="q">{item.q}</h3>
                  </summary>
                  <p className="answer">{item.a}</p>
                </details>
              ))}
            </div>
          </div>
        </section>

        {/* contact */}
        <section className="s-white chapter" id="contact">
          <div className="wrap center">
            <p className="eyebrow">Talk to us</p>
            <h2 className="h-section">Whitefield, Bengaluru.</h2>
            <p className="lead">
              {contact.entity}. {contact.address}. {contact.hours}.
            </p>
            <p className="tx-contact num">
              {contact.phone} · {contact.email}
            </p>
            <p className="fine">Every promise in writing · Every call recorded &amp; AI-monitored.</p>
          </div>
        </section>
      </ApShell>
    </>
  );
}
