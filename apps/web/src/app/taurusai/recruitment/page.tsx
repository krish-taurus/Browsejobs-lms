import type { Metadata } from "next";
import { ContactStrip, MoneyArticle, MoneyFaq, MoneyHero, MoneySection, TextLink } from "@/components/seo/MoneyArticle";
import { HiringDemo } from "@/components/taurus/HiringDemo";
import { SEO_PAGES } from "@/content/seo-nav";
import { breadcrumbNode, faqNode, jsonLdGraph, moneyMetadata, webPageNode } from "@/lib/seo";

const page = SEO_PAGES.taurusRecruitment;

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

export default function TaurusRecruitmentPage() {
  const jsonLd = jsonLdGraph([webPageNode(page), breadcrumbNode(crumbs), faqNode(faqs)]);

  return (
    <MoneyArticle jsonLd={jsonLd}>
      <MoneyHero
        kicker="Taurus hiring floor"
        title={page.title}
        lede={
          <>
            <p>
              Ask for the people you need on WhatsApp. Taurus sources, screens, calls and interviews candidates, and checks with you in the chat
              before every step that matters. You watch it all happen on one live floor.
            </p>
            <p>It runs on the same Taurus brain as our business floor. Sign in and you see what each bot is doing, where candidates are waiting and what needs a decision from you, and you can ask Taurus about any role, out loud.</p>
          </>
        }
        crumbs={crumbs}
        primary={{ kind: "link", href: "/employer", label: "Open your hiring floor" }}
        secondary={{ href: "/employers", label: "Talk to us about a role" }}
      />

      <section aria-label="Hiring floor demo" className="px-3 pb-6 md:px-6">
        <div className="mx-auto h-[min(90vh,900px)] min-h-[620px] max-w-[1480px]">
          <HiringDemo />
        </div>
        <p className="mono mx-auto mt-3 max-w-[1480px] px-2 text-[11px] text-muted">
          An illustration with sample numbers, simulated in your browser. Tap the green replies in the chat, or let the story play on its own. The
          glowing tokens travelling round the ring are candidates moving to the next stage.
        </p>
      </section>

      <MoneySection id="story" kicker="How a hire happens" heading="From one WhatsApp message to an offer">
        <ol className="space-y-4">
          {storySteps.map(([title, body], i) => (
            <li key={title} className="grid grid-cols-[2.25rem_1fr] gap-3">
              <span className="mono grid h-9 w-9 place-items-center rounded-full bg-ink text-sm text-white">{i + 1}</span>
              <div>
                <h3 className="display text-lg text-ink">{title}</h3>
                <p className="mt-1">{body}</p>
              </div>
            </li>
          ))}
        </ol>
        <p>Contacting candidates, running checks and releasing offers each wait for your yes. Nothing is sent on a bot&apos;s say-so.</p>
      </MoneySection>

      <MoneySection id="parameters" kicker="On the dashboard" heading="What each bot reports">
        <dl className="divide-y divide-line border-y border-line">
          {parameters.map(([term, def]) => (
            <div key={term} className="grid gap-1 py-3 sm:grid-cols-[11rem_1fr]">
              <dt className="font-semibold text-ink">{term}</dt>
              <dd>{def}</dd>
            </div>
          ))}
        </dl>
        <p>
          Scores stay empty until grading finishes, and nothing on the floor is a forecast. It shows where your pipeline stands now.
        </p>
      </MoneySection>

      <MoneySection id="employers" kicker="For employers" heading="Your real pipeline, once you sign in">
        <p>
          In the BrowseJobs employer workspace, Hiring floor builds this view from your own jobs, applications and AI interviews, and refreshes as
          candidates move. Filter by role to watch one opening at a time, and ask Taurus questions like “how many finalists do we have for the
          data engineer role?”. It answers from your pipeline, out loud if you like.
        </p>
        <p>
          Not hiring with BrowseJobs yet? <TextLink href="/employers">Talk to us about a role</TextLink>, or read how{" "}
          <TextLink href="/ai-hiring">AI hiring</TextLink> works.
        </p>
      </MoneySection>

      <MoneyFaq faqs={faqs} />
      <ContactStrip />
    </MoneyArticle>
  );
}
