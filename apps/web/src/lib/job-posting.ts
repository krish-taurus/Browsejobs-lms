/**
 * JobPosting JSON-LD built only from fields we actually have.
 * Salary and street addresses are omitted when the source record does not
 * contain them — never invented to satisfy a Search Console warning.
 */

export type JobPostingInput = {
  id: string;
  title: string;
  description: string;
  company: string | null;
  datePosted: string | null;
  validThrough?: string | null;
  /** City or "City, Region" strings already stored on the posting. */
  locations: string[];
  remote: boolean;
  /** Canonical URL of this posting on browsejobs.ai, when one exists. */
  url?: string | null;
  directApply?: boolean;
  /** Annual CTC in paise. Only pass when the employer published the figure. */
  salaryMinPaise?: number | null;
  salaryMaxPaise?: number | null;
};

const CITY_REGION: Record<string, string> = {
  bengaluru: "Karnataka",
  bangalore: "Karnataka",
  hyderabad: "Telangana",
  pune: "Maharashtra",
  mumbai: "Maharashtra",
  chennai: "Tamil Nadu",
  kolkata: "West Bengal",
  ahmedabad: "Gujarat",
  noida: "Uttar Pradesh",
  gurugram: "Haryana",
  gurgaon: "Haryana",
  delhi: "Delhi",
};

const NON_PLACE = /^(remote|wfh|work from home|pan india|india|multiple|various|hybrid|onsite)$/i;

function postalAddress(raw: string): Record<string, string> | null {
  const text = raw.trim();
  if (!text || NON_PLACE.test(text)) return null;

  const pin = text.match(/\b(\d{6})\b/);
  const withoutPin = text.replace(/\b\d{6}\b/, "").replace(/[,\s]+$/, "").trim();
  const parts = withoutPin.split(",").map((part) => part.trim()).filter(Boolean);
  const locality = parts[0];
  if (!locality || NON_PLACE.test(locality)) return null;

  const writtenRegion = parts[1] && !/^india$/i.test(parts[1]) ? parts[1] : undefined;
  const region = writtenRegion ?? CITY_REGION[locality.toLowerCase()];

  const address: Record<string, string> = {
    "@type": "PostalAddress",
    addressLocality: locality,
    addressCountry: "IN",
  };
  if (region) address.addressRegion = region;
  if (pin) address.postalCode = pin[1];
  return address;
}

function baseSalary(minPaise?: number | null, maxPaise?: number | null): Record<string, unknown> | null {
  const min = minPaise && minPaise > 0 ? minPaise / 100 : null;
  const max = maxPaise && maxPaise > 0 ? maxPaise / 100 : null;
  if (min === null && max === null) return null;

  const value: Record<string, unknown> = {
    "@type": "QuantitativeValue",
    unitText: "YEAR",
  };
  if (min !== null && max !== null && min !== max) {
    value.minValue = min;
    value.maxValue = max;
  } else {
    value.value = min ?? max;
  }

  return {
    "@type": "MonetaryAmount",
    currency: "INR",
    value,
  };
}

/** A valid JobPosting, or null when title, description, or employer name is missing. */
export function buildJobPosting(job: JobPostingInput): Record<string, unknown> | null {
  const title = job.title.trim();
  const description = job.description.trim();
  const company = job.company?.trim() ?? "";
  if (!title || !description || !company) return null;

  const posting: Record<string, unknown> = {
    "@type": "JobPosting",
    title,
    description,
    hiringOrganization: {
      "@type": "Organization",
      name: company,
    },
    identifier: {
      "@type": "PropertyValue",
      name: "BrowseJobs",
      value: job.id,
    },
  };

  if (job.datePosted) posting.datePosted = job.datePosted;
  if (job.validThrough) posting.validThrough = job.validThrough;
  if (job.url) posting.url = job.url;
  if (job.directApply) posting.directApply = true;

  const address = job.locations.map(postalAddress).find((item) => item !== null) ?? null;
  if (address) {
    posting.jobLocation = { "@type": "Place", address };
  }
  if (job.remote) {
    posting.jobLocationType = "TELECOMMUTE";
    posting.applicantLocationRequirements = { "@type": "Country", name: "India" };
  }

  const salary = baseSalary(job.salaryMinPaise, job.salaryMaxPaise);
  if (salary) posting.baseSalary = salary;

  return posting;
}
