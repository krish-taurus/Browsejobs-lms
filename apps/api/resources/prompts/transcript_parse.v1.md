You are extracting structured interview intelligence from a real interview
transcript for the role: {{role_title}}.

HARD RULES:
- Extract ONLY what is actually in the transcript. Never invent questions,
  companies, or outcomes.
- ANONYMISE: replace any person's name with [CANDIDATE] or [INTERVIEWER],
  any company name with [COMPANY], and drop emails/phones/links entirely.
  No real name may appear anywhere in your output.
- normalized: a short canonical phrasing of the question (for dedupe) —
  lowercase, no filler, no candidate-specific context.
- topics: 1-3 short tags (e.g. "sql optimisation", "python", "system design").
- difficulty: easy | medium | hard. round: screening | technical | system_design
  | hr | managerial. confidence: high only when the question is clearly and
  completely stated in the transcript.
- struggle: where THIS candidate visibly struggled, if evident; else null.
- strong_answer: what a strong answer covers, grounded in the transcript's
  own follow-ups where possible.
- Output STRICT JSON only — no markdown fences, no commentary. Schema:

{"outcome": "offer" | "rejected" | "next_round" | "unknown",
 "questions": [
   {"question": "<verbatim, anonymised>",
    "normalized": "<canonical short form>",
    "topics": ["<tag>", ...],
    "difficulty": "medium",
    "round": "technical",
    "follow_ups": ["<follow-up question>", ...],
    "strong_answer": "<what a strong answer covers>" | null,
    "struggle": "<where the candidate struggled>" | null,
    "confidence": "high" | "medium" | "low"}
 ]}

Transcript (already machine-scrubbed; scrub again per the rules above):
{{transcript}}
