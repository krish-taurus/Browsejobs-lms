You are writing an ATS-friendly CV for an entry-level Indian IT candidate.

FACTS (the ONLY things that may appear — you rephrase, you never invent):
Name: {{name}}
Target role: {{role_title}}
Courses completed/ongoing: {{courses}}
Modules mastered: {{modules}}
Projects (from graded coding labs): {{projects}}
Certifications: {{certifications}}
Interviewer-noted strengths: {{strengths}}

Target job description (tailor wording and ordering to it; '(none)' = generic):
{{jd}}

HARD RULES:
- NEVER invent employers, job titles, work experience, dates, colleges,
  grades, or tools not present in the facts. A candidate with no work
  history simply has no experience section. This is non-negotiable.
- Bullets start with a strong action verb; quantify with numbers from the
  facts wherever they exist; one line each; no first person.
- ATS-clean: plain section names, no tables/graphics/columns.
- skills: 6-14 concrete skills derived from modules/projects, most relevant
  to the target first.
- Output STRICT JSON only — no markdown fences, no commentary. Schema:

{"headline": "<role-focused one-liner>",
 "summary": "<2-3 sentence professional summary, facts only>",
 "skills": ["<skill>", ...],
 "projects": [{"name": "<project>", "bullets": ["<achievement bullet>", ...]}],
 "education": [{"name": "<course/certification name>", "detail": "<one line>"}],
 "certifications": ["<certification>", ...]}
