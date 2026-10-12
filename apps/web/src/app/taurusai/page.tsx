import type { Metadata } from "next";
import Link from "next/link";
import { ApShell } from "@/components/ap/ApShell";
import "@/components/ap/pages/taurus.css";
import { TaurusDemo } from "@/components/taurus/TaurusDemo";
import { contact } from "@/content/landing";
import { SEO_PAGES } from "@/content/seo-nav";
import { breadcrumbNode, faqNode, jsonLdGraph, moneyMetadata, webPageNode } from "@/lib/seo";

const page = SEO_PAGES.taurusAi;
const demoMail = `mailto:${contact.email}?subject=${encodeURIComponent("Taurus AI demo")}`;
const [titleName, titlePayoff] = page.title.split(": ");

const crumbs = [
  { name: "Home", path: "/" },
  { name: "Taurus AI", path: page.path },
] as const;

const useCases = [
  { team: "Sales", body: "Lead scoring, follow-up emails, CRM clean-up, meeting booking." },
  { team: "Marketing", body: "Social posts, ad budgets, video edits, SEO fixes." },
  { team: "Support", body: "Ticket replies, inbox triage, review responses, help articles." },
  { team: "Finance", body: "Invoice matching, payment chasers, market watch, reports." },
  { team: "Operations", body: "Data syncs, scheduling, weekly reports, backups." },
  { team: "Hiring", body: "Sourcing, screening, AI interviews, offers." },
] as const;

const capabilities = [
  {
    title: "See every agent, live",
    body: "Each agent is a robot at a desk in its team's zone. Its ring and visor show what it is doing: working, planning, waiting for you, in error, idle or offline. Finished work is carried to the Taurus core.",
  },
  {
    title: "Approve before anything goes out",
    body: "Anything that sends, publishes, pays or deploys stops and asks. The request appears over the robot that raised it. Nothing goes ahead until a person presses Approve.",
  },
  {
    title: "Know what's done and what's pending",
    body: "Running now, needs you, done today and failed today, counted from the updates your agents send. Every task keeps its timeline, from received to done.",
  },
  {
    title: "Spend as reported, never guessed",
    body: "Costs show exactly what each provider or agent reports: dollars, rupees or credits. If a source has no usage data, Taurus says so instead of estimating.",
  },
  {
    title: "Ask out loud",
    body: "Ask “what needs me right now?” and Taurus answers from the live floor, using the language model you choose. It only quotes numbers it can see.",
  },
  {
    title: "Hear the answer",
    body: "Connect an ElevenLabs voice and Taurus speaks its answers. Without one, it uses the browser's built-in voice.",
  },
] as const;

const steps = [
  {
    title: "Connect your agents",
    body: "Any agent that can make a web request can report in: custom code, Grok or OpenAI agents, n8n, Make or Zapier flows. Each update says what it is working on, how far along it is, anything it needs approved, and what it spent.",
  },
  {
    title: "Taurus builds the floor",
    body: "New agents appear as robots in their team's zone. Tasks, approvals, finished work and spend fill the panels around them and update every few seconds.",
  },
  {
    title: "You decide",
    body: "Approve or reject from the floor, or ask Taurus what's happening. Your agents check for decisions and carry on, so a person stays in charge of every action that leaves the business.",
  },
] as const;

const brains = ["xAI Grok", "Anthropic Claude", "OpenAI", "Google Gemini", "Moonshot Kimi", "DeepSeek", "Groq", "Any OpenAI-compatible API"];

const connectors = ["Custom code", "Grok agents", "OpenAI agents", "Claude agents", "n8n", "Make", "Zapier"];

const faqs = [
  {
    q: "What is Taurus AI?",
    a: "Taurus is a command centre for the AI agents that run parts of a business. It puts every agent on one live 3D floor, shows what each is doing, holds risky actions for a person to approve, tracks spend as reported, and answers questions about the floor by text or voice. BrowseJobs built it, and runs its own operations and its recruitment on it.",
  },
  {
    q: "What kind of business is it for?",
    a: "Any business that uses AI agents or automations for real work: sales, marketing, support, finance, operations or hiring. If your agents are scattered across tools and you can't see what they did today, Taurus is for you.",
  },
  {
    q: "Is the demo on this page real data?",
    a: "No. The floor on this page is a simulated business, and every number in it is labelled sample data. A live Taurus floor reads only the updates your own agents send.",
  },
  {
    q: "Which agents can connect?",
    a: "Anything that can make an HTTP request: agents you've built in code, agents on Grok, OpenAI or Claude, and no-code flows in n8n, Make or Zapier. Each one sends a short JSON update with a private token.",
  },
  {
    q: "Which language models can power Taurus?",
    a: "xAI Grok, Anthropic Claude, OpenAI, Google Gemini, Moonshot Kimi, DeepSeek, Groq, or any API that speaks the OpenAI format. You add a key, test the connection, and choose which one Taurus thinks with.",
  },
  {
    q: "How are API keys kept safe?",
    a: "Keys are encrypted on the server before they are saved. The browser only ever sees the last four characters, and only the account owner can add, test or remove them.",
  },
  {
    q: "Can Taurus take actions on its own?",
    a: "No. Taurus answers questions and shows the floor. Any action that sends, publishes, pays or deploys waits for a person to approve it, and the agent only proceeds once it sees that decision.",
  },
  {
    q: "How much does it cost?",
    a: "Pricing depends on how many agents you connect and how you want it set up, so we agree it on a short call before anything starts. There's no charge without a written agreement.",
  },
] as const;

const ingestExample = `POST /api/v1/taurus/ingest
Authorization: Bearer <your Taurus agent token>

{ "events": [ {
    "agent":  { "slug": "outreach-sdr", "name": "Outreach SDR", "zone": "Sales" },
    "status": "working",
    "task":   { "ref": "seq-0412", "title": "Writing 18 follow-up emails", "progress": 0.4 },
    "spend":  { "source": "xAI Grok", "amount": 0.12, "currency": "USD" }
} ] }`;

export const metadata: Metadata = moneyMetadata(page);

/** Taurus AI, in the Apple-direction design: a product page with the live sample floor as the hero shot. */
export default function TaurusAiPage() {
  const jsonLd = jsonLdGraph([
    webPageNode(page),
    breadcrumbNode(crumbs),
    {
      "@type": "SoftwareApplication",
      name: "Taurus AI",
      applicationCategory: "BusinessApplication",
      operatingSystem: "Web",
      url: "https://browsejobs.ai/taurusai",
      description: page.description,
      publisher: { "@type": "Organization", name: "BrowseJobs", url: "https://browsejobs.ai" },
    },
    faqNode(faqs),
  ]);

  return (
    <>
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd) }} />
      <ApShell staticNav cta={{ label: "Book a demo", href: demoMail }}>
        <nav className="lnav lnav--dark" aria-label="Taurus AI">
          <div className="lnav-inner">
            <span className="lnav-title">
              Taurus AI<small>Agent command centre</small>
            </span>
            <div className="lnav-right">
              <ul className="lnav-links">
                <li>
                  <a href="#who">Overview</a>
                </li>
                <li>
                  <a href="#how">How it works</a>
                </li>
                <li>
                  <a href="#connect">Connect</a>
                </li>
                <li>
                  <a href="#rules">Security</a>
                </li>
                <li>
                  <a href="#faq">FAQ</a>
                </li>
              </ul>
              <a className="btn btn-primary" href={demoMail}>
                Book a demo
              </a>
            </div>
          </div>
        </nav>

        {/* hero, with the live sample floor as the product shot */}
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
            <p className="eyebrow">Taurus AI · for any business</p>
            <h1 className="h-hero tx-title">
              <span>{titleName}:</span> <span className="tx-payoff">{titlePayoff}</span>
            </h1>
            <p className="lead">
              Taurus is a command centre for the AI agents that run your sales, marketing, support, finance and operations. Every agent works on one
              live floor, where you can see what it is doing, what it finished, what it spent and what is waiting for your approval.
            </p>
            <p className="lead tx-lead-2">Ask it a question out loud and it answers from the floor. Below is a working demo of a sample business.</p>
            <div className="cta-row">
              <a className="btn btn-primary" href={demoMail}>
                Book a Taurus demo
              </a>
              <Link className="more" href="/taurusai/recruitment">
                See Taurus run hiring <span className="chev" aria-hidden="true">›</span>
              </Link>
            </div>
          </div>
          <div className="wrap-wide">
            <section className="tx-shot" aria-label="Taurus demo">
              <TaurusDemo mode="ops" />
            </section>
            <p className="fine tx-shot-note">
              Sample business, simulated in your browser. Drag to look around, click a robot to follow it, approve a request when one appears, ask
              Taurus a question, and switch looks at the bottom.
            </p>
          </div>
        </section>

        {/* who it's for */}
        <section className="s-white chapter" id="who">
          <div className="wrap">
            <div className="center tx-head">
              <p className="eyebrow">Who it&apos;s for</p>
              <h2 className="h-section">One floor for every team&apos;s agents.</h2>
            </div>
            <div className="tiles-3" data-reveal-kids="">
              {useCases.map((u) => (
                <article key={u.team} className="tile tile-pad tx-team">
                  <h3 className="h-card">{u.team}</h3>
                  <p className="body">
                    {u.body}
                    {u.team === "Hiring" && (
                      <>
                        {" "}
                        <Link className="tx-link" href="/taurusai/recruitment">
                          See the hiring floor.
                        </Link>
                      </>
                    )}
                  </p>
                </article>
              ))}
            </div>
          </div>
        </section>

        {/* capabilities */}
        <section className="s-paper chapter" id="what">
          <div className="wrap">
            <div className="center tx-head">
              <p className="eyebrow">What it does</p>
              <h2 className="h-section">Six things Taurus does for you.</h2>
            </div>
            <div className="tiles-3" data-reveal-kids="">
              {capabilities.map((c, i) => (
                <article key={c.title} className="tile tile-pad tx-cap">
                  <p className="eyebrow-sm num">{String(i + 1).padStart(2, "0")}</p>
                  <h3 className="h-card">{c.title}</h3>
                  <p className="body">{c.body}</p>
                </article>
              ))}
            </div>
          </div>
        </section>

        {/* how it works */}
        <section className="s-white chapter" id="how">
          <div className="wrap">
            <div className="center tx-head">
              <p className="eyebrow">How it works</p>
              <h2 className="h-section">Three steps from agent to floor.</h2>
            </div>
            <ol className="tx-steps" data-reveal-kids="">
              {steps.map((s, i) => (
                <li key={s.title}>
                  <span className="tx-n num" aria-hidden="true">
                    {i + 1}
                  </span>
                  <h3 className="h-card">{s.title}</h3>
                  <p className="body">{s.body}</p>
                </li>
              ))}
            </ol>
          </div>
        </section>

        {/* connect your agents */}
        <section className="s-black chapter" id="connect">
          <div className="wrap">
            <div className="center tx-head">
              <p className="eyebrow">Connect your agents</p>
              <h2 className="h-section">One request from any agent.</h2>
              <p className="lead">An agent reports in with one request. This is the whole contract:</p>
            </div>
            <div className="tx-code-tile">
              <div className="tx-code-bar">
                <span className="tx-dots" aria-hidden="true">
                  <i />
                  <i />
                  <i />
                </span>
                <span>Agent update</span>
              </div>
              <pre className="tx-code">{ingestExample}</pre>
            </div>
            <div className="tiles-2 tx-connect">
              <article className="tile tile-pad">
                <h3 className="h-card">Ask for approval</h3>
                <p className="body">
                  To ask for approval, the agent sends its task with <code>status: &quot;needs_approval&quot;</code> and an <code>approval.action</code>,
                  then reads <code>GET /api/v1/taurus/decisions</code> and only proceeds on an approval.
                </p>
              </article>
              <article className="tile tile-pad">
                <h3 className="h-card">Works with what you run</h3>
                <p className="body">Anything that can make an HTTP request. Each one sends a short JSON update with a private token.</p>
                <ul className="tx-pills" aria-label="Agents that can connect">
                  {connectors.map((c) => (
                    <li key={c}>{c}</li>
                  ))}
                </ul>
              </article>
            </div>
          </div>
        </section>

        {/* brain and voice */}
        <section className="s-paper chapter" id="brain">
          <div className="wrap">
            <div className="center tx-head">
              <p className="eyebrow">Brain and voice</p>
              <h2 className="h-section">Choose the model it thinks with.</h2>
              <p className="lead">Taurus doesn&apos;t lock you to one AI company. Add a key, test it, pick it as the brain, and switch whenever you like.</p>
            </div>
            <div className="tiles-2">
              <article className="tile tile-pad">
                <p className="eyebrow-sm">Brain</p>
                <h3 className="h-tile tx-tile-h">Bring your own model.</h3>
                <ul className="tx-pills" aria-label="Supported language models">
                  {brains.map((b) => (
                    <li key={b}>{b}</li>
                  ))}
                </ul>
              </article>
              <article className="tile tile-pad">
                <p className="eyebrow-sm">Voice</p>
                <h3 className="h-tile tx-tile-h">Give it a voice.</h3>
                <p className="body">
                  For voice, connect an ElevenLabs key and choose a voice. Keys are encrypted on the server, the browser only ever sees the last four
                  characters, and only the account owner can change them.
                </p>
                <ul className="tx-pills" aria-label="Voice">
                  <li>ElevenLabs</li>
                  <li>Browser voice when none is connected</li>
                </ul>
              </article>
            </div>
          </div>
        </section>

        {/* hiring */}
        <section className="s-white chapter" id="hiring">
          <div className="wrap">
            <div className="tile tile-pad tx-hiring">
              <div>
                <p className="eyebrow">Taurus for hiring</p>
                <h2 className="h-tile">The same brain runs BrowseJobs recruitment.</h2>
              </div>
              <div>
                <p className="body">
                  BrowseJobs runs its own recruitment on Taurus. On the hiring floor, the zones are the stages of hiring, from applied to hired, and the
                  robots are hiring bots: sourcing, screening, AI interviews, scheduling, offers and onboarding.
                </p>
                <p className="body">Employers hiring through BrowseJobs sign in to see their own roles move stage by stage, and ask Taurus where things stand.</p>
                <div className="cta-row">
                  <Link className="btn btn-primary" href="/taurusai/recruitment">
                    Open the hiring floor demo
                  </Link>
                  <Link className="more" href="/taurusai/hiring-demo">
                    Watch one hire <span className="chev" aria-hidden="true">›</span>
                  </Link>
                </div>
              </div>
            </div>
          </div>
        </section>

        {/* security / ground rules */}
        <section className="s-black chapter" id="rules">
          <div className="wrap">
            <div className="center tx-head">
              <p className="eyebrow">Ground rules</p>
              <h2 className="h-section">What Taurus will never do.</h2>
              <p className="lead">Your keys stay yours. Each client sees only their own agents.</p>
            </div>
            <div className="tiles-2" data-reveal-kids="">
              <article className="tile tile-pad">
                <h3 className="h-card">Taurus will never</h3>
                <ul className="ticks tx-ticks">
                  <li>Send, publish, pay or deploy anything without a person approving it first.</li>
                  <li>Estimate money. Spend is shown exactly as each source reports it, or marked as not connected.</li>
                  <li>Pass simulated numbers off as real. Demo floors are labelled sample data on screen.</li>
                  <li>Show your API keys back to you. Only the last four characters are ever displayed.</li>
                </ul>
              </article>
              <article className="tile tile-pad">
                <h3 className="h-card">Your keys, your floor</h3>
                <ul className="ticks tx-ticks">
                  <li>Keys are encrypted on the server before they are saved.</li>
                  <li>The browser only ever sees the last four characters.</li>
                  <li>Only the account owner can add, test or remove keys.</li>
                  <li>Each client business gets its own workspace, with its own agents and keys, and sees only its own floor.</li>
                </ul>
              </article>
            </div>
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

        {/* final CTA */}
        <section className="s-black chapter" id="book">
          <div className="wrap center">
            <h2 className="h-hero tx-closing-h">Want Taurus for your business?</h2>
            <p className="lead">
              <a className="tx-link" href={demoMail}>
                Email {contact.email}
              </a>{" "}
              or call <span className="num">{contact.phone}</span>.
            </p>
            <div className="cta-row">
              <a className="btn btn-primary" href={demoMail}>
                Book a Taurus demo
              </a>
              <Link className="more" href="/taurusai/login">
                Already a client? Sign in to your command centre <span className="chev" aria-hidden="true">›</span>
              </Link>
            </div>
            <p className="fine">
              {contact.entity}. {contact.address}. {contact.hours}.
            </p>
            <p className="fine tx-fine-tight">Every promise in writing · Every call recorded &amp; AI-monitored.</p>
          </div>
        </section>
      </ApShell>
    </>
  );
}
