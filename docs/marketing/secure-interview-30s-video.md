# Secure Interview: 30-second film

Goal: show the problem (three ways people cheat remote interviews) and the catch, in 30 seconds, vertical 9:16 for socials with a 16:9 cut for the `/secure-interview` page (`FILM_SRC` in `page.tsx`).

Use staged footage or generated clips with actors/AI people. Do not rip YouTube clips of real people cheating: the faces are real, the rights are not ours, and a real person's face next to "cheater" is a defamation risk.

## Storyboard

| Time | Visual | On-screen text | VO / sound |
|---|---|---|---|
| 0-3s | Candidate on a video interview, confident, smiling. Interviewer's voice: "Walk me through partitioning in Spark." | none | Interviewer audio |
| 3-8s | Candidate answers fluently. Camera pulls back: a second laptop off-frame, a phone propped by the monitor, a friend crouched out of shot. | "Great answer." then "Not theirs." | VO: "Remote interviews are easy to fake." |
| 8-12s | Split screen. Left: the candidate. Right: gaze trace in red sweeping left to right like reading. | READING FROM A SCREEN | VO: "Reading from a screen." Soft tick sound as the trace lands |
| 12-16s | Waveform vs mouth. Mouth movement lags the audio by a visible beat. Amber warning. | LIP-SYNC DRIFT 180ms | VO: "A voice that is not their face." |
| 16-20s | Second voice detected: a whisper waveform, then candidate's answer starts after it. | SECOND VOICE, 3 PROMPTS | VO: "Someone feeding the answers." |
| 20-25s | Recruiter dashboard: integrity score drops, flag timeline with clips. Recruiter clicks a flag, clip plays. | INTEGRITY: FLAGGED. Evidence attached. | VO: "BrowseJobs flags it, and shows you the proof." |
| 25-30s | End card, BrowseJobs wordmark. | "The interview you can trust the answers to." / browsejobs.ai/secure-interview | VO: same line. |

Voice-over (about 55 words, fits 30s):
> Remote interviews are easy to fake. Reading from a screen. A voice that is not their face. Someone feeding the answers. BrowseJobs reads gaze, lip-sync and speaker audio live, flags what does not add up, and shows you the proof. The interview you can trust the answers to. BrowseJobs.

## Higgsfield prompts

Generate each as a 5-8s clip, then cut in any editor (CapCut / Premiere). Use Seedance 2.0 or Kling 3.0 for video; keep one consistent candidate by generating a still first and using it as the start frame.

1. **Candidate, confident** (start frame): "Medium close-up, young Indian software candidate in a plain shirt, home office, laptop webcam angle, soft daylight, speaking confidently to camera on a video interview, 4k, realistic."
2. **Pull-back reveal**: "Same candidate. Camera slowly pulls back to reveal a second laptop and a phone propped beside the monitor, and a friend crouched just out of frame whispering. Candidate keeps eye contact with the webcam. Realistic, handheld feel."
3. **Reading eyes**: "Extreme close-up of eyes scanning left to right in small steady steps as if reading text, reflection of a screen in the pupils, shallow depth of field."
4. **Lip-sync drift**: "Close-up of a person speaking on a webcam, slight uncanny mismatch between speech and mouth, mouth movement a fraction late, glitchy digital edge."
5. **Recruiter review**: "Over-the-shoulder shot of a recruiter at a clean dashboard with a red flag on a timeline, clicking a clip to review, calm office, soft light."

The overlays (gaze trace, waveforms, flag cards, dashboard) are better done as motion graphics than generated: the staged demo on the page (`IntegrityDemo.tsx`) is the visual reference, and it can be screen-recorded at 1080p for those beats.

## Claims check before this ships

- The film must say "flags" and "shows the proof", never "catches 100%" or "stops all cheating".
- Do not publish until the detection exists. Today's interviews do not capture camera or window-switch signals (see the FAQ on `/ai-hiring`).
