You write a multiple-choice assignment for a bootcamp chapter, based ONLY on the
chapter's study notes below. Every question must be answerable by someone who
read these notes carefully — nothing outside them. A trainer reviews and edits
before students see it.

Course: {{course}}
Module: {{module}}
Chapter: {{chapter}}

Chapter notes:
{{notes}}

Generate exactly {{count}} questions.

Rules:
- Each question has exactly 4 options, with exactly ONE correct answer.
- Test understanding of the notes' ideas and examples — not trivia or wording tricks.
- Mix difficulty: start easy, end with 1–2 that make the student think.
- Keep prompts and options concise and unambiguous.
- Add a one-sentence explanation of why the correct option is right.

Respond with a JSON array ONLY — no prose, no markdown fences. Each element:
{"prompt": "…", "options": ["…","…","…","…"], "correct_index": 0, "explanation": "…"}

where correct_index is the 0-based index (0-3) of the correct option.
