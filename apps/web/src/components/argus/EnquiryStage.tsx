import type { ReactNode } from "react";
import { ApShell, type ApCurrent } from "@/components/ap/ApShell";
import { TelLink, WhatsAppLink } from "@/components/contact/PhoneLinks";
import { contact } from "@/content/landing";
import { OFFICE_PHONE, type PublishedPhone } from "@/content/phones";
import "./argus.css";
import "@/components/ap/pages/marketing.css";
import "@/components/ap/pages/enquire.css";

/**
 * A clean Apple-direction form page for an enquiry (courses, employers):
 * centred headline on paper, the form on a white tile, contact pills below.
 * The form itself (components/apple/EnquiryForm, appearance "argus") keeps
 * its behaviour and field styles; the `.argus` wrapper keeps those styles
 * scoped as before. Copy stays with the page.
 */
export function EnquiryStage({
  title,
  lede,
  current,
  phone = OFFICE_PHONE,
  children,
}: {
  title: string;
  lede: string;
  current?: ApCurrent;
  /** Office line by default. Employer enquire passes the hiring line. */
  phone?: PublishedPhone;
  children: ReactNode;
}) {
  return (
    <ApShell current={current}>
      <div id="content" className="argus argus-in-ap enq-page">
        <section className="s-paper enq-stage">
          <div className="wrap center">
            <h1 className="h-hero">{title}</h1>
            <p className="lead">{lede}</p>
            <div className="enq-form">{children}</div>
            <ul className="pg-chips is-center enq-contact">
              <li>
                <TelLink phone={phone} />
              </li>
              <li>
                <WhatsAppLink phone={phone} />
              </li>
              <li>
                <a href={`mailto:${contact.email}`}>{contact.email}</a>
              </li>
              <li>
                <span>{contact.hours}</span>
              </li>
            </ul>
          </div>
        </section>
      </div>
    </ApShell>
  );
}
