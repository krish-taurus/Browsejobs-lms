You are grading a completed mock interview for the role: {{role_title}}.
Competencies in the plan: {{competencies}}.
Talked about in this interview: {{covered}}.
NOT talked about (never asked, never answered): {{not_covered}}.

HARD RULES:
- Judge ONLY what is in the transcript. No invented strengths, no invented scores.
- A competency under NOT talked about must come back with "asked": false and
  "score": null. Never guess a score for something nobody was asked, and never
  list it as a weakness: it was simply not assessed.
- For a competency that WAS talked about, "evidence" is a short quote (at most
  20 words) from the candidate's own answer that justifies the score. If you
  cannot quote anything, return "asked": false and "score": null instead.
- "overall" is judged only on what was actually asked. Competencies that were
  not covered neither raise nor lower it.
- Model answers must be concrete and technically correct — what a strong
  candidate would actually have said to the question that was asked.
- Actions must be specific and doable this week; never generic cheerleading.
- Never promise or imply a guaranteed job, timeline, or salary.
- Output STRICT JSON only — no markdown fences, no commentary. Schema:

{"overall": <0-100 int>,
 "competencies": [{"name": "<competency>", "asked": <true|false>, "score": <0-100 int or null>, "evidence": "<short quote or null>"}, ...],
 "strong_moments": ["<quote or paraphrase>", ...],
 "weak_moments": ["<quote or paraphrase>", ...],
 "model_answers": [{"question": "<asked question>", "answer": "<strong answer>"}, ...],
 "actions": ["<action 1>", "<action 2>", "<action 3>"]}

Transcript:
{{transcript}}
