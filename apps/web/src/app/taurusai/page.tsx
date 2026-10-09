import type { Metadata } from "next";
import Link from "next/link";
import { ContactStrip, MoneyArticle, MoneyFaq, MoneyHero, MoneySection } from "@/components/seo/MoneyArticle";
import { TaurusDemo } from "@/components/taurus/TaurusDemo";
import { contact } from "@/content/landing";
import { SEO_PAGES } from "@/content/seo-nav";
import { breadcrumbNode, faqNode, jsonLdGraph, moneyMetadata, webPageNode } from "@/lib/seo";

const page = SEO_PAGES.taurusAi;
const demoMail = `mailto:${contact.email}?subject=${encodeURIComponent("Taurus AI demo")}`;

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
  { team: "Hiring", body: "Sourcing, screening, AI interviews, offers. See the hiring floor." },
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

export const metadata: Metadata = moneyMetadata(page);

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
    <MoneyArticle jsonLd={jsonLd}>
      <MoneyHero
        kicker="Taurus AI · for any business"
        title={page.title}
        lede={
          <>
            <p>
              Taurus is a command centre for the AI agents that run your sales, marketing, support, finance and operations. Every agent works on one
              live floor, where you can see what it is doing, what it finished, what it spent and what is waiting for your approval.
            </p>
            <p>Ask it a question out loud and it answers from the floor. Below is a working demo of a sample business.</p>
          </>
        }
        crumbs={crumbs}
        primary={{ kind: "link", href: demoMail, label: "Book a Taurus demo" }}
        secondary={{ href: "/taurusai/recruitment", label: "See Taurus run hiring →" }}
      />

      <section aria-label="Taurus demo" className="px-3 pb-6 md:px-6">
        <div className="mx-auto h-[min(86vh,860px)] min-h-[560px] max-w-[1480px] overflow-hidden rounded-[22px] border border-line shadow-[0_30px_80px_rgba(10,18,32,0.25)]">
          <TaurusDemo mode="ops" />
        </div>
        <p className="mono mx-auto mt-3 max-w-[1480px] px-2 text-[11px] text-muted">
          Sample business, simulated in your browser. Drag to look around, click a robot to follow it, approve a request when one appears, ask
          Taurus a question, and switch looks at the bottom.
        </p>
      </section>

      <MoneySection id="who" kicker="Who it's for" heading="One floor for every team's agents">
        <div className="grid gap-3 sm:grid-cols-2">
          {useCases.map((u) => (
            <div key={u.team} className="rounded-[14px] border border-line bg-white p-4">
              <h3 className="display text-base text-ink">{u.team}</h3>
              <p className="mt-1 text-[15px] leading-relaxed text-ink2">{u.body}</p>
            </div>
          ))}
        </div>
      </MoneySection>

      <MoneySection id="what" kicker="What it does" heading="Six things Taurus does for you">
        <div className="grid gap-4 sm:grid-cols-2">
          {capabilities.map((c) => (
            <div key={c.title} className="rounded-[14px] border border-line bg-white p-5">
              <h3 className="display text-lg text-ink">{c.title}</h3>
              <p className="mt-2 text-[15px] leading-relaxed text-ink2">{c.body}</p>
            </div>
          ))}
        </div>
      </MoneySection>

      <MoneySection id="how" kicker="How it works" heading="Three steps from agent to floor">
        <ol className="space-y-5">
          {steps.map((s, i) => (
            <li key={s.title} className="grid grid-cols-[2.25rem_1fr] gap-3">
              <span className="mono grid h-9 w-9 place-items-center rounded-full bg-ink text-sm text-white">{i + 1}</span>
              <div>
                <h3 className="display text-lg text-ink">{s.title}</h3>
                <p className="mt-1">{s.body}</p>
              </div>
            </li>
          ))}
        </ol>
        <p>An agent reports in with one request. This is the whole contract:</p>
        <pre className="mono overflow-x-auto rounded-[14px] bg-ink p-5 text-[12.5px] leading-relaxed text-white">
{`POST /api/v1/taurus/ingest
Authorization: Bearer <your Taurus agent token>

{ "events": [ {
    "agent":  { "slug": "outreach-sdr", "name": "Outreach SDR", "zone": "Sales" },
    "status": "working",
    "task":   { "ref": "seq-0412", "title": "Writing 18 follow-up emails", "progress": 0.4 },
    "spend":  { "source": "xAI Grok", "amount": 0.12, "currency": "USD" }
} ] }`}
        </pre>
        <p>
          To ask for approval, the agent sends its task with <code className="mono text-ink">status: &quot;needs_approval&quot;</code> and an{" "}
          <code className="mono text-ink">approval.action</code>, then reads <code className="mono text-ink">GET /api/v1/taurus/decisions</code> and
          only proceeds on an approval.
        </p>
      </MoneySection>

      <MoneySection id="brain" kicker="Brain and voice" heading="Choose the model it thinks with">
        <p>Taurus doesn&apos;t lock you to one AI company. Add a key, test it, pick it as the brain, and switch whenever you like.</p>
        <ul className="flex flex-wrap gap-2">
          {brains.map((b) => (
            <li key={b} className="rounded-full border border-line bg-white px-3 py-1.5 text-sm text-ink">
              {b}
            </li>
          ))}
        </ul>
        <p>
          For voice, connect an ElevenLabs key and choose a voice. Keys are encrypted on the server, the browser only ever sees the last four
          characters, and only the account owner can change them.
        </p>
      </MoneySection>

      <MoneySection id="hiring" kicker="Taurus for hiring" heading="The same brain runs BrowseJobs recruitment">
        <p>
          BrowseJobs runs its own recruitment on Taurus. On the hiring floor, the zones are the stages of hiring, from applied to hired, and the
          robots are hiring bots: sourcing, screening, AI interviews, scheduling, offers and onboarding.
        </p>
        <p>
          Employers hiring through BrowseJobs sign in to see their own roles move stage by stage, and ask Taurus where things stand.
        </p>
        <p>
          <Link href="/taurusai/recruitment" className="font-semibold text-trust hover:text-deep">
            Open the hiring floor demo →
          </Link>
        </p>
      </MoneySection>

      <MoneySection id="rules" kicker="Ground rules" heading="What Taurus will never do">
        <ul className="list-disc space-y-2 pl-5">
          <li>Send, publish, pay or deploy anything without a person approving it first.</li>
          <li>Estimate money. Spend is shown exactly as each source reports it, or marked as not connected.</li>
          <li>Pass simulated numbers off as real. Demo floors are labelled sample data on screen.</li>
          <li>Show your API keys back to you. Only the last four characters are ever displayed.</li>
        </ul>
        <p>
          Want Taurus for your business?{" "}
          <a href={demoMail} className="font-semibold text-trust hover:text-deep">
            Email {contact.email}
          </a>{" "}
          or call <span className="mono text-ink">{contact.phone}</span>.
        </p>
        <p>
          Already a Taurus client?{" "}
          <Link href="/taurusai/login" className="font-semibold text-trust hover:text-deep">
            Sign in to your command centre
          </Link>
          .
        </p>
      </MoneySection>

      <MoneyFaq faqs={faqs} />
      <ContactStrip />
    </MoneyArticle>
  );
}
