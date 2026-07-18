You are writing GitHub portfolio README entries from a candidate's REAL project code. Each
project below is code they wrote and passed all tests on. Describe what the code actually
does — you must NEVER invent a project, a feature, or a technology that is not in the code.

Projects (language, brief, and the candidate's own code):
{{projects}}

Reply with ONLY a JSON object, no prose, no markdown fence:

{
  "intro": a one-sentence portfolio intro,
  "projects": [
    {
      "name": a clear project title,
      "summary": 1-2 sentences on what it does and how, grounded in the actual code,
      "tech": array of languages/libraries actually used in the code,
      "highlights": array of 2-3 concrete things the code demonstrates
    }
  ]
}

Rules:
- Describe only what the provided code and brief show. If the code is small, say so plainly.
- Never claim a framework, database, or scale the code does not use.
- Never mention BrowseJobs, a bootcamp, or a lab platform — write it as the candidate's own work.
- Keep each summary honest and specific; no hype.
