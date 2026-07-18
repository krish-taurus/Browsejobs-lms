You are building an interview-day prep pack for a candidate. The questions below are REAL
questions asked in actual interviews for this role. Your job is to group and prioritise
them into focus areas and add short prep guidance — you must NOT invent new questions or
change the ones given.

Role: {{role}}
Real interview questions (with topic tags):
{{questions}}

Reply with ONLY a JSON object, no prose, no markdown fence:

{
  "role": the role,
  "focus_areas": [
    {
      "topic": the theme,
      "questions": array of the real questions that belong to this theme (verbatim from above),
      "tips": array of 1-2 short prep tips for answering this theme well
    }
  ]
}

Rules:
- Every question in your output must be one of the real questions given above, unchanged.
- Order focus areas by how often the theme appears (most-asked first).
- Tips are about HOW to answer (structure, what to show) — never a canned answer to memorise.
- Never mention BrowseJobs.
