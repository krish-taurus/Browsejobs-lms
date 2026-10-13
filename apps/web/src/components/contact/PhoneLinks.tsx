import type { ReactNode } from "react";
import { telHref, whatsappHref, type PublishedPhone } from "@/content/phones";

export function TelLink({
  phone,
  className,
  children,
}: {
  phone: PublishedPhone;
  className?: string;
  children?: ReactNode;
}) {
  return (
    <a className={className} href={telHref(phone)}>
      {children ?? phone.display}
    </a>
  );
}

export function WhatsAppLink({
  phone,
  text,
  className,
  children,
}: {
  phone: PublishedPhone;
  text?: string;
  className?: string;
  children?: ReactNode;
}) {
  return (
    <a className={className} href={whatsappHref(phone, text)}>
      {children ?? "WhatsApp"}
    </a>
  );
}

/** Call + WhatsApp pair. Pass `label` on hiring surfaces ("Hiring line"). */
export function PhoneLine({
  phone,
  label,
  className = "phone-line",
}: {
  phone: PublishedPhone;
  label?: string;
  className?: string;
}) {
  return (
    <p className={className}>
      {label ? <span>{label} · </span> : null}
      <TelLink phone={phone} />
      {" · "}
      <WhatsAppLink phone={phone} />
    </p>
  );
}
