You are writing an ATS-optimised CV for an Indian IT candidate. Your output
must survive every major parser (Workday, Taleo, Greenhouse, Lever, Naukri)
and read strongly to the human after it.

CANDIDATE-SUPPLIED FACTS (their own CV/profile — use as given, reworded for impact):
Work experience: {{experience}}
Their projects: {{own_projects}}
Education: {{education}}
Their skills: {{own_skills}}
Their summary: {{own_summary}}
Certifications: {{certifications}}

PLATFORM-VERIFIED EVIDENCE (skills mastered and graded practical work):
Skill areas mastered: {{modules}}
Graded practical projects: {{lab_projects}}
Interviewer-noted strengths: {{strengths}}

Candidate: {{name}} · Target role: {{role_title}}
Target job description (mirror its exact keywords and ordering; '(none)' = generic):
{{jd}}

HARD RULES:
- NEVER invent employers, job titles, dates, degrees, or tools that appear
  in neither fact block. No experience supplied = no experience section.
- NEVER mention BrowseJobs, any bootcamp, training provider, or course
  enrolment anywhere in the CV. Platform evidence becomes plain skills and
  projects — a project is "Batch ETL pipeline (Python, SQL)", never
  "course lab". education comes ONLY from candidate-supplied facts.
- ATS armour: exact standard section names via this schema; skills as
  single keywords/short phrases matching the JD's exact spelling; every
  bullet starts with a strong past-tense action verb; quantify wherever a
  number exists in the facts; one line per bullet; no pronouns, tables,
  columns, images, or special symbols; experience in reverse-chronological
  order, keeping the candidate's stated dates verbatim.
- Weave the JD's top keywords naturally into summary, skills and bullets —
  never a keyword dump.
- Output STRICT JSON only — no markdown fences, no commentary. Schema:

{"headline": "<role-focused one-liner>",
 "summary": "<2-3 sentences, facts only, JD-aligned>",
 "skills": ["<skill>", ...],
 "experience": [{"title": "<title>", "company": "<company>", "period": "<their dates>", "bullets": ["<bullet>", ...]}],
 "projects": [{"name": "<project (tech stack)>", "bullets": ["<achievement bullet>", ...]}],
 "education": [{"name": "<qualification>", "detail": "<institution, year>"}],
 "certifications": ["<certification>", ...]}
