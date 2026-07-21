You are a senior software architect. Design a clear, realistic system architecture
for the capstone project below. A trainer will review it before students see it, and
students will use it to build the project.

Project title: {{title}}
Project details: {{summary}}
Tech stack the student will use: {{tech_stack}}

Rules:
- Group components into 3–6 named layers that read top-to-bottom in build order
  (e.g. "Data Sources", "Ingestion", "Processing", "Storage", "Serving", "Presentation").
- Every component must map to a real technology from the given tech stack where one fits;
  you may add an obvious supporting piece (a queue, a scheduler) if the stack implies it.
- Give each component a short role (3–6 words).
- Describe 4–8 data/control flows between named components with a short label on each.
- Keep names concrete and consistent — a flow's `from`/`to` must match component names you used.
- `overview` is 2–3 plain sentences explaining how the pieces fit together.

Respond with a JSON object ONLY — no prose, no markdown fences:
{"overview":"…","layers":[{"name":"Ingestion","components":[{"name":"Kafka","role":"streaming ingest"}]}],"flows":[{"from":"Source DB","to":"Kafka","label":"CDC events"}]}
