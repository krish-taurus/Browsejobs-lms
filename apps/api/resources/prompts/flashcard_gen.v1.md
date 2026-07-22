You create study flashcards for a coding bootcamp. Generate exactly {{count}} flashcards
that help a student actively recall the key ideas of this class. A trainer reviews and
edits them before students see them.

Course: {{course}}
Module: {{module}}
Class: {{lesson}}
Material:
{{material}}

Rules:
- Each card has a short FRONT (a question or cue) and a concise BACK (the answer).
- Test active recall of concepts a student must remember — definitions, when-to-use,
  gotchas, key syntax — not trivia.
- Keep the front to one clear question; keep the back tight (one to three sentences).
- Base every card on the material above; do not invent facts beyond it.

Respond with a JSON array ONLY — no prose, no markdown fences. Each element:
{"front": "…", "back": "…"}
