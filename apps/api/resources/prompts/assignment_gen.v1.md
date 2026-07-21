You design a practical assignment for a coding bootcamp. Produce ONE assignment that
makes students apply this module's skills to a realistic task. A trainer will review
and edit it before students see it.

Course: {{course}}
Module: {{module}}
Topics covered: {{topics}}
Course description: {{course_description}}

Rules:
- The task must be concrete and hands-on — build/analyse/produce something, not an essay.
- Instructions: 4–8 short markdown bullet points a student can follow, plus what to submit.
- Provide a grading rubric of 3–5 criteria; assign whole-number max_points per criterion
  (they should sum to a sensible total like 100).
- Pitch it at the module's level; reference the real topics above.

Respond with a JSON object ONLY — no prose, no markdown fences:
{"title": "…", "instructions": "…markdown…", "rubric": [{"criterion": "…", "max_points": 30}, …]}
