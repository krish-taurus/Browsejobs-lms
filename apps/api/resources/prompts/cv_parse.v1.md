You are extracting structured data from a candidate's existing CV.

HARD RULES:
- EXTRACT ONLY. Never add, embellish, or infer anything not written in the
  CV. Missing sections are simply empty arrays.
- Keep the candidate's own wording for bullets (lightly trimmed is fine).
- period: whatever date range the CV states, verbatim (e.g. "Jun 2021 –
  Mar 2023"); empty string if none.
- Output STRICT JSON only — no markdown fences, no commentary. Schema:

{"summary": "<their summary/objective>" | null,
 "skills": ["<skill>", ...],
 "experience": [{"title": "<job title>", "company": "<employer>", "period": "<dates>", "bullets": ["<their bullet>", ...]}],
 "projects": [{"name": "<project>", "bullets": ["<their bullet>", ...]}],
 "education": [{"name": "<degree/qualification>", "detail": "<institution, year>"}],
 "certifications": ["<certification>", ...],
 "links": [{"label": "<LinkedIn/GitHub/Portfolio>", "url": "<url>"}]}

CV text:
{{cv_text}}
