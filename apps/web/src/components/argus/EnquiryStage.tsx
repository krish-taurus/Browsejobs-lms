import type { ReactNode } from "react";
import { ArgusFrame } from "@/components/argus/ArgusFrame";
import { contact } from "@/content/landing";

const phoneHref = `tel:${contact.phone.replace(/[^\d+]/g, "")}`;

/** White Argus frame for an enquiry form. Copy stays with the page. */
export function EnquiryStage({ title, lede, children }: { title: string; lede: string; children: ReactNode }) {
  return (
    <ArgusFrame>
      <section className="argus-section argus-enquire" data-scene="grid">
        <div className="argus-enquire-copy">
          <h1 className="argus-h1">{title}</h1>
          <p className="argus-body argus-enquire-lede">{lede}</p>
          {children}
          <ul className="argus-contact-chips">
            <li>
              <a href={phoneHref}>{contact.phone}</a>
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
    </ArgusFrame>
  );
}
