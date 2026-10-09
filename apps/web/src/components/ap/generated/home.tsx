/* Generated from the Apple-direction demo (scratchpad/port/gen.py), then wired by hand where marked. */
import Link from "next/link";
import type { CSSProperties } from "react";
import { InterviewStartForm } from "@/components/auth/InterviewStartForm";
import { ApCounselForm } from "@/components/ap/ApCounselForm";
import { ApCourseTiles } from "@/components/ap/ApCourseTiles";
import { InViewVideo } from "@/components/argus/InViewVideo";

export function STop() {
  return (
    <section className="s-white hero" id="top">
  <div className="wrap center">
    <p className="eyebrow">BrowseJobs</p>
    <h1 className="h-hero"><span>Take a free</span> <span>AI interview.</span></h1>
    <p className="lead">Answer about 15 questions about your CV. Score 75% or more, and we send your CV to 3,000 HR recruiters.</p>
    <p className="fine">Based on BrowseJobs internal data. Historical figures — not a promise of individual outcome. Hiring depends on the live market and your performance.</p>
    <InterviewStartForm tone="ap" id="interview-start" />
    <div className="cta-row" style={{ marginTop: "14px" } as CSSProperties}><a className="more" href="#journey">See how it works <span className="chev" aria-hidden="true">›</span></a><a className="more" href="#counselling">Talk to a counsellor <span className="chev" aria-hidden="true">›</span></a></div>
  </div>
  <div className="hero-stage" data-scene="hero">
    <div className="hero-chip hero-chip--left" aria-hidden="true"><b>About 15 questions</b>From your CV</div>
    <div className="hero-chip hero-chip--right" aria-hidden="true"><b>75% is a pass</b>HR sees your CV</div>
    <div className="phone">
      <div className="phone-screen">
        <p className="eyebrow-sm">Sample question</p>
        <p className="ui-q">Walk me through a pipeline you would ship this month.</p>
        <div className="ui-wave" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div>
        <div className="ui-score">
          <svg className="ring ring-sm" viewBox="0 0 40 40" aria-hidden="true"><circle className="ring-track" cx="20" cy="20" r="15.9" pathLength="100" strokeWidth="4" /><circle className="ring-fill" cx="20" cy="20" r="15.9" pathLength="100" strokeWidth="4" /></svg>
          <div><p className="eyebrow-sm" style={{ color: "var(--ink)" } as CSSProperties}>75%</p><p className="eyebrow-sm">Pass mark</p></div>
        </div>
        <p className="ui-fine">A sample round. Not a promise of a job.</p>
      </div>
    </div>
  </div>
</section>
  );
}

export function SJourney() {
  return (
    <section className="s-white scene journey" data-scene="journey" data-len="460vh" id="journey">
  <div className="scene-pin chapter">
    <div className="wrap-wide">
      <div className="center journey-head">
        <h2 className="h-section">How it works, in 5 steps.</h2>
        <p className="short">In short: you take a free interview and get a score. Score 75% or more, and HR recruiters see your CV. Score less, and we help you try again.</p>
      </div>
      <div className="journey-grid">
        <div>
          <ol className="jsteps"><li data-step=""><span className="n">Step 01 of 05</span><h3>Share your CV.</h3><p>Upload your CV. The interview questions come from it.</p></li><li data-step=""><span className="n">Step 02 of 05</span><h3>Answer about 15 questions.</h3><p>Speak or type your answers, like a real interview. It&apos;s free.</p></li><li data-step=""><span className="n">Step 03 of 05</span><h3>Get your score out of 100.</h3><p>You see your score and where your answers were weak.</p><p className="note"><b>Below 75?</b> You get a free counselling call, a plan to improve, and you can take the interview again.</p></li><li data-step=""><span className="n">Step 04 of 05</span><h3>Score 75% or more, and HR sees your CV.</h3><p>We send your CV, with your score, to 3,000 HR recruiters.</p></li><li data-step=""><span className="n">Step 05 of 05</span><h3>Get a call for an interview.</h3><p>Passing makes an interview call almost 60% more likely. The job market still decides.</p><p className="fine">Based on BrowseJobs internal data. Historical figures — not a promise of individual outcome. Hiring depends on the live market and your performance.</p></li></ol>
          <div className="jbar" aria-hidden="true"><i></i></div>
        </div>
        <div className="stage3d" data-3d="journey" role="img" aria-label="A 3D path through the five steps: a CV, a microphone, a 75% score ring, a stack of HR inboxes, and a ringing phone. A loop goes back from the score to the interview for anyone below 75%."><div className="stage-fallback"><svg viewBox="0 0 460 120" aria-hidden="true"><path d="M30 80 C 110 20, 150 20, 230 70 S 350 120, 430 50" fill="none" stroke="#c9c9cf" strokeWidth="2" strokeDasharray="2 8" strokeLinecap="round" /><circle cx="30" cy="80" r="16" fill="#fff" stroke="#1d1d1f" strokeWidth="1.5" /><circle cx="130" cy="36" r="16" fill="#fff" stroke="#1d1d1f" strokeWidth="1.5" /><circle cx="230" cy="70" r="16" fill="#fff" stroke="#1d1d1f" strokeWidth="1.5" /><circle cx="330" cy="96" r="16" fill="#fff" stroke="#1d1d1f" strokeWidth="1.5" /><circle cx="430" cy="50" r="16" fill="#fff" stroke="#1d1d1f" strokeWidth="1.5" /></svg></div></div>
      </div>
    </div>
  </div>
</section>
  );
}

export function SWhat75() {
  return (
    <section className="s-paper scene" data-scene="score" data-len="300vh" id="what-75">
  <div className="scene-pin chapter">
    <div className="wrap">
      <div className="center">
        <h2 className="h-section">What does 75% mean?</h2>
        <p className="short-answer"><b>Short answer:</b> 75 out of 100 is the pass mark. We call a pass a “clear”. A clear is not a job offer. It means HR gets to see you.</p>
      </div>
      <div className="score-grid">
        <div className="score-dial">
          <svg className="ring" viewBox="0 0 120 120" aria-hidden="true"><circle className="ring-track" cx="60" cy="60" r="54" pathLength="100" /><circle className="ring-fill" cx="60" cy="60" r="54" pathLength="100" /></svg>
          <div className="score-read"><span className="big">75%</span><span className="label">Clear</span></div>
        </div>
        <ol className="steps"><li data-at="0"><span className="n">01</span><h3>About 15 questions.</h3><p>They come from your CV.</p></li><li data-at="0.22"><span className="n">02</span><h3>The AI gives you a score.</h3><p>Out of 100, with notes on your weak answers.</p></li><li data-at="0.6"><span className="n">03</span><h3>75% or more is a clear.</h3><p>That means you are ready for HR.</p></li><li data-at="0.74"><span className="n">04</span><h3>Your CV goes out.</h3><p>We send it to 3,000 HR recruiters.</p></li><li data-at="0.88"><span className="n">05</span><h3>A better chance of a call.</h3><p>You are almost 60% more likely to get an interview call.</p></li></ol>
      </div>
      <div className="center"><p className="fine">Based on BrowseJobs internal data. Historical figures — not a promise of individual outcome. Hiring depends on the live market and your performance.</p></div>
    </div>
  </div>
</section>
  );
}

export function SBelow75() {
  return (
    <section className="s-white chapter" id="below-75">
  <div className="wrap path-grid">
    <div className="path-head">
      <h2 className="h-section">Scored below 75%? Here&apos;s what happens.</h2>
      <p className="lead">It&apos;s not a fail. It shows you exactly what to work on, and the retake is free.</p>
      <p className="fine">Based on BrowseJobs internal data. Historical figures — not a promise of individual outcome. Hiring depends on the live market and your performance.</p>
    </div>
    <ol className="path" data-scene="path"><span className="path-rail" aria-hidden="true"></span><span className="path-fill" aria-hidden="true"></span><span className="path-light" aria-hidden="true"></span><li><span className="n">01</span><h3>You see your score.</h3><p>And what held you back.</p></li><li><span className="n">02</span><h3>A free counselling call.</h3><p>A BrowseJobs counsellor calls you and explains your result.</p></li><li><span className="n">03</span><h3>A plan made for you.</h3><p>We suggest a course only if you need one.</p></li><li><span className="n">04</span><h3>Practise.</h3><p>Work on your plan. Retaking the interview is free.</p></li><li><span className="n">05</span><h3>Take the interview again.</h3><p>Same kind of questions. A new score.</p></li><li><span className="n">06</span><h3>Reach 75%.</h3><p>You&apos;re ready for HR.</p></li><li className="is-final"><span className="n">07</span><h3>Your CV goes to 3,000 HR recruiters.</h3><p>The same step as everyone who passes.</p></li></ol>
  </div>
</section>
  );
}

export function SCourses() {
  return (
    <section className="s-paper chapter" id="courses">
  <div className="wrap center">
    <p className="eyebrow">If you need help to get there</p>
    <h2 className="h-section">Courses that get you job-ready.</h2>
    <p className="lead">Six months, live online, with recordings. You build real projects and practise real interviews.</p>
  </div>
  <div className="wrap" style={{ marginTop: "clamp(36px,5vw,60px)" } as CSSProperties}><ApCourseTiles /></div>
  <div className="wrap center"><div className="cta-row"><Link className="btn btn-primary" href="/courses">Explore courses</Link><a className="more" href="#counselling">Talk to a counsellor <span className="chev" aria-hidden="true">›</span></a><a className="more" href="#top">Take the free AI interview <span className="chev" aria-hidden="true">›</span></a></div></div>
</section>
  );
}

export function SCounselling() {
  return (
    <section className="s-white chapter" id="counselling">
  <div className="wrap">
    <div className="tile form-tile">
      <div>
        <p className="eyebrow">Free</p>
        <h2 className="h-section" style={{ fontSize: "clamp(36px,4.6vw,56px)" } as CSSProperties}>Talk to a counsellor. It&apos;s free.</h2>
        <p className="lead">Leave your number. A BrowseJobs counsellor calls you back at a time that suits you.</p>
        <ul className="ticks">
          <li>A real person calls you back.</li>
          <li>You choose the time.</li>
          <li>Every call is recorded and checked by AI.</li>
        </ul>
      </div>
      <div>
        <ApCounselForm />
      </div>
    </div>
  </div>
</section>
  );
}

export function SStories() {
  return (
    <section className="s-white chapter" id="stories">
  <div className="wrap center">
    <h2 className="h-section">Success stories.</h2>
    <p className="lead">How people moved into the role. The path is the same: AI interview, counselling, a course, a retake, then hired.</p>
    <div className="stories" data-reveal-kids="">
      <article className="tile tile-pad story">
        <p className="eyebrow-sm">Success story</p>
        <h3 className="h-card">From a 3.5-year support role to an Accenture offer</h3>
        <p className="route">Support role <i aria-hidden="true"></i> Accenture offer</p>
        <blockquote>“I was stucked for 3.5 yeas in support role but now with the help of browsejobs i am starting my new career journey.”</blockquote>
        <Link className="more" href="/reviews">See the message <span className="chev" aria-hidden="true">›</span></Link>
      </article>
      <article className="tile tile-pad story">
        <p className="eyebrow-sm">Success story</p>
        <h3 className="h-card">Stuck after a CS post-grad → now joining a third company</h3>
        <p className="route">CS post-grad <i aria-hidden="true"></i> Third company</p>
        <blockquote>“After Krish sir&apos;s class this is third company I am joining 😅 … 2 years back I was stuck in life I had strong regret I am not doing anything in life despite post graduation in computer science. Same month I came across Krish sir&apos;s master class &amp; without single doubt I just joined it to give one chance to myself”</blockquote>
        <p className="who">Pranjal</p>
        <Link className="more" href="/reviews">See the message <span className="chev" aria-hidden="true">›</span></Link>
      </article>
    </div>
    <div className="rating">
      <span className="big" aria-hidden="true">4.9</span>
      <div className="meta">
        <span className="stars" aria-hidden="true">★★★★★</span>
        <a className="more" href="https://www.google.com/maps/place/Browsejobs/@12.9820038,77.7578119,17z/data=!3m1!5s0x3bae0e0f6a605123:0xddc2986ea82ea45e!4m8!3m7!1s0x3bae0d9fd52c9f3d:0x6b8781d8ef23bcad!8m2!3d12.9820038!4d77.7578119!9m1!1b1!16s%2Fg%2F11qh1b8j4f" target="_blank" rel="noopener">4.9 on Google · 473 reviews <span className="chev" aria-hidden="true">›</span></a>
      </div>
    </div>
  </div>
  <div className="marquee" role="region" aria-label="Google reviews"><div className="marquee-row" style={{ "--dur": "78s" } as CSSProperties}><div className="marquee-set"><a className="review" href="https://www.google.com/maps/place/Browsejobs/@12.9820038,77.7578119,17z/data=!3m1!5s0x3bae0e0f6a605123:0xddc2986ea82ea45e!4m8!3m7!1s0x3bae0d9fd52c9f3d:0x6b8781d8ef23bcad!8m2!3d12.9820038!4d77.7578119!9m1!1b1!16s%2Fg%2F11qh1b8j4f" target="_blank" rel="noopener" aria-label="Vinod Karan Singh, 5 out of 5, Google review"><span className="stars" aria-hidden="true">★★★★★</span><p>I had a wonderful learning experience with Browse Jobs Technology. The faculty is highly skilled, knowledgeable, and genuinely committed to helping students succeed. …</p><span className="who">Vinod Karan Singh<small>Google review</small></span></a><a className="review" href="https://www.google.com/maps/place/Browsejobs/@12.9820038,77.7578119,17z/data=!3m1!5s0x3bae0e0f6a605123:0xddc2986ea82ea45e!4m8!3m7!1s0x3bae0d9fd52c9f3d:0x6b8781d8ef23bcad!8m2!3d12.9820038!4d77.7578119!9m1!1b1!16s%2Fg%2F11qh1b8j4f" target="_blank" rel="noopener" aria-label="Chirag Puthran, 5 out of 5, Google review"><span className="stars" aria-hidden="true">★★★★★</span><p>I am truly grateful to be a part of Browsejobs, and I can confidently say that joining this institute has been one of the best decisions in my learning journey. Being based in Bangalore, one of India&apos;s leading IT hubs, along with its excellent placement success rate, made it an ideal choice. …</p><span className="who">Chirag Puthran<small>Google review</small></span></a><a className="review" href="https://www.google.com/maps/place/Browsejobs/@12.9820038,77.7578119,17z/data=!3m1!5s0x3bae0e0f6a605123:0xddc2986ea82ea45e!4m8!3m7!1s0x3bae0d9fd52c9f3d:0x6b8781d8ef23bcad!8m2!3d12.9820038!4d77.7578119!9m1!1b1!16s%2Fg%2F11qh1b8j4f" target="_blank" rel="noopener" aria-label="varalakshmi Dakarapu, 5 out of 5, Google review"><span className="stars" aria-hidden="true">★★★★★</span><p>The Data Engineer training was an excellent learning experience with a well-structured curriculum covering SQL, Python, Pandas, cloud technologies, and real-time project concepts. Krish sir’s teaching approach was clear, practical, and highly insightful, making complex topics easy to understand through real-time examples. …</p><span className="who">varalakshmi Dakarapu<small>Google review</small></span></a><a className="review" href="https://www.google.com/maps/place/Browsejobs/@12.9820038,77.7578119,17z/data=!3m1!5s0x3bae0e0f6a605123:0xddc2986ea82ea45e!4m8!3m7!1s0x3bae0d9fd52c9f3d:0x6b8781d8ef23bcad!8m2!3d12.9820038!4d77.7578119!9m1!1b1!16s%2Fg%2F11qh1b8j4f" target="_blank" rel="noopener" aria-label="Anthony Rathnam, 5 out of 5, Google review"><span className="stars" aria-hidden="true">★★★★★</span><p>Hi Everyone The wisest decision I have ever made is joining IBrowsejob and getting trained by the greatest Guru Dr. Krish Sir. …</p><span className="who">Anthony Rathnam<small>Google review</small></span></a><a className="review" href="https://www.google.com/maps/place/Browsejobs/@12.9820038,77.7578119,17z/data=!3m1!5s0x3bae0e0f6a605123:0xddc2986ea82ea45e!4m8!3m7!1s0x3bae0d9fd52c9f3d:0x6b8781d8ef23bcad!8m2!3d12.9820038!4d77.7578119!9m1!1b1!16s%2Fg%2F11qh1b8j4f" target="_blank" rel="noopener" aria-label="Hemant Gawai, 4 out of 5, Google review"><span className="stars" aria-hidden="true">★★★★<span className="off">★</span></span><p>I joined BrowseJob&apos;s Data Engineering course about three months ago, and my experience so far has been excellent. During this time, we&apos;ve covered Core Python, OOPs, Regular Expressions (Regex), Pandas, and SQL is currently in progress. …</p><span className="who">Hemant Gawai<small>Google review</small></span></a><a className="review" href="https://www.google.com/maps/place/Browsejobs/@12.9820038,77.7578119,17z/data=!3m1!5s0x3bae0e0f6a605123:0xddc2986ea82ea45e!4m8!3m7!1s0x3bae0d9fd52c9f3d:0x6b8781d8ef23bcad!8m2!3d12.9820038!4d77.7578119!9m1!1b1!16s%2Fg%2F11qh1b8j4f" target="_blank" rel="noopener" aria-label="Durgashree js, 5 out of 5, Google review"><span className="stars" aria-hidden="true">★★★★★</span><p>I’m currently taking the Data Engineer course at Browsjobs and it’s been a great experience. The instructor explains concepts like python, SQL, PySpark, and cloud data pipelines in a very clear, practical way. …</p><span className="who">Durgashree js<small>Google review</small></span></a><a className="review" href="https://www.google.com/maps/place/Browsejobs/@12.9820038,77.7578119,17z/data=!3m1!5s0x3bae0e0f6a605123:0xddc2986ea82ea45e!4m8!3m7!1s0x3bae0d9fd52c9f3d:0x6b8781d8ef23bcad!8m2!3d12.9820038!4d77.7578119!9m1!1b1!16s%2Fg%2F11qh1b8j4f" target="_blank" rel="noopener" aria-label="Harish, 5 out of 5, Google review"><span className="stars" aria-hidden="true">★★★★★</span><p>I had a great experience with this Data Engineering course. Krish explains complex concepts clearly using practical, real-life examples and hands-on practice, which really built my confidence. …</p><span className="who">Harish<small>Google review</small></span></a></div><div className="marquee-set" aria-hidden="true" inert><a className="review" href="https://www.google.com/maps/place/Browsejobs/@12.9820038,77.7578119,17z/data=!3m1!5s0x3bae0e0f6a605123:0xddc2986ea82ea45e!4m8!3m7!1s0x3bae0d9fd52c9f3d:0x6b8781d8ef23bcad!8m2!3d12.9820038!4d77.7578119!9m1!1b1!16s%2Fg%2F11qh1b8j4f" target="_blank" rel="noopener" tabIndex={-1} aria-label="Vinod Karan Singh, 5 out of 5, Google review"><span className="stars" aria-hidden="true">★★★★★</span><p>I had a wonderful learning experience with Browse Jobs Technology. The faculty is highly skilled, knowledgeable, and genuinely committed to helping students succeed. …</p><span className="who">Vinod Karan Singh<small>Google review</small></span></a><a className="review" href="https://www.google.com/maps/place/Browsejobs/@12.9820038,77.7578119,17z/data=!3m1!5s0x3bae0e0f6a605123:0xddc2986ea82ea45e!4m8!3m7!1s0x3bae0d9fd52c9f3d:0x6b8781d8ef23bcad!8m2!3d12.9820038!4d77.7578119!9m1!1b1!16s%2Fg%2F11qh1b8j4f" target="_blank" rel="noopener" tabIndex={-1} aria-label="Chirag Puthran, 5 out of 5, Google review"><span className="stars" aria-hidden="true">★★★★★</span><p>I am truly grateful to be a part of Browsejobs, and I can confidently say that joining this institute has been one of the best decisions in my learning journey. Being based in Bangalore, one of India&apos;s leading IT hubs, along with its excellent placement success rate, made it an ideal choice. …</p><span className="who">Chirag Puthran<small>Google review</small></span></a><a className="review" href="https://www.google.com/maps/place/Browsejobs/@12.9820038,77.7578119,17z/data=!3m1!5s0x3bae0e0f6a605123:0xddc2986ea82ea45e!4m8!3m7!1s0x3bae0d9fd52c9f3d:0x6b8781d8ef23bcad!8m2!3d12.9820038!4d77.7578119!9m1!1b1!16s%2Fg%2F11qh1b8j4f" target="_blank" rel="noopener" tabIndex={-1} aria-label="varalakshmi Dakarapu, 5 out of 5, Google review"><span className="stars" aria-hidden="true">★★★★★</span><p>The Data Engineer training was an excellent learning experience with a well-structured curriculum covering SQL, Python, Pandas, cloud technologies, and real-time project concepts. Krish sir’s teaching approach was clear, practical, and highly insightful, making complex topics easy to understand through real-time examples. …</p><span className="who">varalakshmi Dakarapu<small>Google review</small></span></a><a className="review" href="https://www.google.com/maps/place/Browsejobs/@12.9820038,77.7578119,17z/data=!3m1!5s0x3bae0e0f6a605123:0xddc2986ea82ea45e!4m8!3m7!1s0x3bae0d9fd52c9f3d:0x6b8781d8ef23bcad!8m2!3d12.9820038!4d77.7578119!9m1!1b1!16s%2Fg%2F11qh1b8j4f" target="_blank" rel="noopener" tabIndex={-1} aria-label="Anthony Rathnam, 5 out of 5, Google review"><span className="stars" aria-hidden="true">★★★★★</span><p>Hi Everyone The wisest decision I have ever made is joining IBrowsejob and getting trained by the greatest Guru Dr. Krish Sir. …</p><span className="who">Anthony Rathnam<small>Google review</small></span></a><a className="review" href="https://www.google.com/maps/place/Browsejobs/@12.9820038,77.7578119,17z/data=!3m1!5s0x3bae0e0f6a605123:0xddc2986ea82ea45e!4m8!3m7!1s0x3bae0d9fd52c9f3d:0x6b8781d8ef23bcad!8m2!3d12.9820038!4d77.7578119!9m1!1b1!16s%2Fg%2F11qh1b8j4f" target="_blank" rel="noopener" tabIndex={-1} aria-label="Hemant Gawai, 4 out of 5, Google review"><span className="stars" aria-hidden="true">★★★★<span className="off">★</span></span><p>I joined BrowseJob&apos;s Data Engineering course about three months ago, and my experience so far has been excellent. During this time, we&apos;ve covered Core Python, OOPs, Regular Expressions (Regex), Pandas, and SQL is currently in progress. …</p><span className="who">Hemant Gawai<small>Google review</small></span></a><a className="review" href="https://www.google.com/maps/place/Browsejobs/@12.9820038,77.7578119,17z/data=!3m1!5s0x3bae0e0f6a605123:0xddc2986ea82ea45e!4m8!3m7!1s0x3bae0d9fd52c9f3d:0x6b8781d8ef23bcad!8m2!3d12.9820038!4d77.7578119!9m1!1b1!16s%2Fg%2F11qh1b8j4f" target="_blank" rel="noopener" tabIndex={-1} aria-label="Durgashree js, 5 out of 5, Google review"><span className="stars" aria-hidden="true">★★★★★</span><p>I’m currently taking the Data Engineer course at Browsjobs and it’s been a great experience. The instructor explains concepts like python, SQL, PySpark, and cloud data pipelines in a very clear, practical way. …</p><span className="who">Durgashree js<small>Google review</small></span></a><a className="review" href="https://www.google.com/maps/place/Browsejobs/@12.9820038,77.7578119,17z/data=!3m1!5s0x3bae0e0f6a605123:0xddc2986ea82ea45e!4m8!3m7!1s0x3bae0d9fd52c9f3d:0x6b8781d8ef23bcad!8m2!3d12.9820038!4d77.7578119!9m1!1b1!16s%2Fg%2F11qh1b8j4f" target="_blank" rel="noopener" tabIndex={-1} aria-label="Harish, 5 out of 5, Google review"><span className="stars" aria-hidden="true">★★★★★</span><p>I had a great experience with this Data Engineering course. Krish explains complex concepts clearly using practical, real-life examples and hands-on practice, which really built my confidence. …</p><span className="who">Harish<small>Google review</small></span></a></div></div><div className="marquee-row rev" style={{ "--dur": "86s" } as CSSProperties}><div className="marquee-set"><a className="review" href="https://www.google.com/maps/place/Browsejobs/@12.9820038,77.7578119,17z/data=!3m1!5s0x3bae0e0f6a605123:0xddc2986ea82ea45e!4m8!3m7!1s0x3bae0d9fd52c9f3d:0x6b8781d8ef23bcad!8m2!3d12.9820038!4d77.7578119!9m1!1b1!16s%2Fg%2F11qh1b8j4f" target="_blank" rel="noopener" aria-label="Dikshita nulageri, 5 out of 5, Google review"><span className="stars" aria-hidden="true">★★★★★</span><p>My experience with Browsejobs and the Data Engineer training has been very good. The course is well structured and covers important topics like Python, SQL, Pandas, data cleaning, transformation, and cloud concepts with practical examples and project-based learning. …</p><span className="who">Dikshita nulageri<small>Google review</small></span></a><a className="review" href="https://www.google.com/maps/place/Browsejobs/@12.9820038,77.7578119,17z/data=!3m1!5s0x3bae0e0f6a605123:0xddc2986ea82ea45e!4m8!3m7!1s0x3bae0d9fd52c9f3d:0x6b8781d8ef23bcad!8m2!3d12.9820038!4d77.7578119!9m1!1b1!16s%2Fg%2F11qh1b8j4f" target="_blank" rel="noopener" aria-label="Pavan S, 5 out of 5, Google review"><span className="stars" aria-hidden="true">★★★★★</span><p>I enrolled in the Data Engineering course, and it has been a highly valuable experience. The trainer, Dr.Krish Bharggav is significantly more experienced not only in training but also brings extensive experience from his journey in the industry. …</p><span className="who">Pavan S<small>Google review</small></span></a><a className="review" href="https://www.google.com/maps/place/Browsejobs/@12.9820038,77.7578119,17z/data=!3m1!5s0x3bae0e0f6a605123:0xddc2986ea82ea45e!4m8!3m7!1s0x3bae0d9fd52c9f3d:0x6b8781d8ef23bcad!8m2!3d12.9820038!4d77.7578119!9m1!1b1!16s%2Fg%2F11qh1b8j4f" target="_blank" rel="noopener" aria-label="Charan Kumar M, 5 out of 5, Google review"><span className="stars" aria-hidden="true">★★★★★</span><p>I had a really great learning experience throughout the Data Engineer course. The curriculum was well-structured and covered both fundamental and advanced concepts with practical examples, making it easier to understand real-world Data Engineering workflows. …</p><span className="who">Charan Kumar M<small>Google review</small></span></a><a className="review" href="https://www.google.com/maps/place/Browsejobs/@12.9820038,77.7578119,17z/data=!3m1!5s0x3bae0e0f6a605123:0xddc2986ea82ea45e!4m8!3m7!1s0x3bae0d9fd52c9f3d:0x6b8781d8ef23bcad!8m2!3d12.9820038!4d77.7578119!9m1!1b1!16s%2Fg%2F11qh1b8j4f" target="_blank" rel="noopener" aria-label="chaturya pragallapati, 5 out of 5, Google review"><span className="stars" aria-hidden="true">★★★★★</span><p>My experience with the Browse Jobs training has been positive so far. The sessions are well-structured, and the content is easy to follow. …</p><span className="who">chaturya pragallapati<small>Google review</small></span></a><a className="review" href="https://www.google.com/maps/place/Browsejobs/@12.9820038,77.7578119,17z/data=!3m1!5s0x3bae0e0f6a605123:0xddc2986ea82ea45e!4m8!3m7!1s0x3bae0d9fd52c9f3d:0x6b8781d8ef23bcad!8m2!3d12.9820038!4d77.7578119!9m1!1b1!16s%2Fg%2F11qh1b8j4f" target="_blank" rel="noopener" aria-label="SHUBHAM SINDHU, 5 out of 5, Google review"><span className="stars" aria-hidden="true">★★★★★</span><p>My overall experience with BrowseJobs has been excellent. The way Krish Sir explains complex topics through real-life stories makes them easy to understand and remember. …</p><span className="who">SHUBHAM SINDHU<small>Google review</small></span></a><a className="review" href="https://www.google.com/maps/place/Browsejobs/@12.9820038,77.7578119,17z/data=!3m1!5s0x3bae0e0f6a605123:0xddc2986ea82ea45e!4m8!3m7!1s0x3bae0d9fd52c9f3d:0x6b8781d8ef23bcad!8m2!3d12.9820038!4d77.7578119!9m1!1b1!16s%2Fg%2F11qh1b8j4f" target="_blank" rel="noopener" aria-label="Ajay Kumar, 5 out of 5, Google review"><span className="stars" aria-hidden="true">★★★★★</span><p>I joined BrowseJob’s Data Engineering course about two months ago, and my experience has been excellent so far. We’ve covered Core Python, OOP, Regex, and Pandas, and SQL is currently in progress. …</p><span className="who">Ajay Kumar<small>Google review</small></span></a><a className="review" href="https://www.google.com/maps/place/Browsejobs/@12.9820038,77.7578119,17z/data=!3m1!5s0x3bae0e0f6a605123:0xddc2986ea82ea45e!4m8!3m7!1s0x3bae0d9fd52c9f3d:0x6b8781d8ef23bcad!8m2!3d12.9820038!4d77.7578119!9m1!1b1!16s%2Fg%2F11qh1b8j4f" target="_blank" rel="noopener" aria-label="Sai Hemanth Yarramasu, 5 out of 5, Google review"><span className="stars" aria-hidden="true">★★★★★</span><p>I had a great learning experience at IBrowseJobs. The training program was well structured and focused on practical knowledge. …</p><span className="who">Sai Hemanth Yarramasu<small>Google review</small></span></a></div><div className="marquee-set" aria-hidden="true" inert><a className="review" href="https://www.google.com/maps/place/Browsejobs/@12.9820038,77.7578119,17z/data=!3m1!5s0x3bae0e0f6a605123:0xddc2986ea82ea45e!4m8!3m7!1s0x3bae0d9fd52c9f3d:0x6b8781d8ef23bcad!8m2!3d12.9820038!4d77.7578119!9m1!1b1!16s%2Fg%2F11qh1b8j4f" target="_blank" rel="noopener" tabIndex={-1} aria-label="Dikshita nulageri, 5 out of 5, Google review"><span className="stars" aria-hidden="true">★★★★★</span><p>My experience with Browsejobs and the Data Engineer training has been very good. The course is well structured and covers important topics like Python, SQL, Pandas, data cleaning, transformation, and cloud concepts with practical examples and project-based learning. …</p><span className="who">Dikshita nulageri<small>Google review</small></span></a><a className="review" href="https://www.google.com/maps/place/Browsejobs/@12.9820038,77.7578119,17z/data=!3m1!5s0x3bae0e0f6a605123:0xddc2986ea82ea45e!4m8!3m7!1s0x3bae0d9fd52c9f3d:0x6b8781d8ef23bcad!8m2!3d12.9820038!4d77.7578119!9m1!1b1!16s%2Fg%2F11qh1b8j4f" target="_blank" rel="noopener" tabIndex={-1} aria-label="Pavan S, 5 out of 5, Google review"><span className="stars" aria-hidden="true">★★★★★</span><p>I enrolled in the Data Engineering course, and it has been a highly valuable experience. The trainer, Dr.Krish Bharggav is significantly more experienced not only in training but also brings extensive experience from his journey in the industry. …</p><span className="who">Pavan S<small>Google review</small></span></a><a className="review" href="https://www.google.com/maps/place/Browsejobs/@12.9820038,77.7578119,17z/data=!3m1!5s0x3bae0e0f6a605123:0xddc2986ea82ea45e!4m8!3m7!1s0x3bae0d9fd52c9f3d:0x6b8781d8ef23bcad!8m2!3d12.9820038!4d77.7578119!9m1!1b1!16s%2Fg%2F11qh1b8j4f" target="_blank" rel="noopener" tabIndex={-1} aria-label="Charan Kumar M, 5 out of 5, Google review"><span className="stars" aria-hidden="true">★★★★★</span><p>I had a really great learning experience throughout the Data Engineer course. The curriculum was well-structured and covered both fundamental and advanced concepts with practical examples, making it easier to understand real-world Data Engineering workflows. …</p><span className="who">Charan Kumar M<small>Google review</small></span></a><a className="review" href="https://www.google.com/maps/place/Browsejobs/@12.9820038,77.7578119,17z/data=!3m1!5s0x3bae0e0f6a605123:0xddc2986ea82ea45e!4m8!3m7!1s0x3bae0d9fd52c9f3d:0x6b8781d8ef23bcad!8m2!3d12.9820038!4d77.7578119!9m1!1b1!16s%2Fg%2F11qh1b8j4f" target="_blank" rel="noopener" tabIndex={-1} aria-label="chaturya pragallapati, 5 out of 5, Google review"><span className="stars" aria-hidden="true">★★★★★</span><p>My experience with the Browse Jobs training has been positive so far. The sessions are well-structured, and the content is easy to follow. …</p><span className="who">chaturya pragallapati<small>Google review</small></span></a><a className="review" href="https://www.google.com/maps/place/Browsejobs/@12.9820038,77.7578119,17z/data=!3m1!5s0x3bae0e0f6a605123:0xddc2986ea82ea45e!4m8!3m7!1s0x3bae0d9fd52c9f3d:0x6b8781d8ef23bcad!8m2!3d12.9820038!4d77.7578119!9m1!1b1!16s%2Fg%2F11qh1b8j4f" target="_blank" rel="noopener" tabIndex={-1} aria-label="SHUBHAM SINDHU, 5 out of 5, Google review"><span className="stars" aria-hidden="true">★★★★★</span><p>My overall experience with BrowseJobs has been excellent. The way Krish Sir explains complex topics through real-life stories makes them easy to understand and remember. …</p><span className="who">SHUBHAM SINDHU<small>Google review</small></span></a><a className="review" href="https://www.google.com/maps/place/Browsejobs/@12.9820038,77.7578119,17z/data=!3m1!5s0x3bae0e0f6a605123:0xddc2986ea82ea45e!4m8!3m7!1s0x3bae0d9fd52c9f3d:0x6b8781d8ef23bcad!8m2!3d12.9820038!4d77.7578119!9m1!1b1!16s%2Fg%2F11qh1b8j4f" target="_blank" rel="noopener" tabIndex={-1} aria-label="Ajay Kumar, 5 out of 5, Google review"><span className="stars" aria-hidden="true">★★★★★</span><p>I joined BrowseJob’s Data Engineering course about two months ago, and my experience has been excellent so far. We’ve covered Core Python, OOP, Regex, and Pandas, and SQL is currently in progress. …</p><span className="who">Ajay Kumar<small>Google review</small></span></a><a className="review" href="https://www.google.com/maps/place/Browsejobs/@12.9820038,77.7578119,17z/data=!3m1!5s0x3bae0e0f6a605123:0xddc2986ea82ea45e!4m8!3m7!1s0x3bae0d9fd52c9f3d:0x6b8781d8ef23bcad!8m2!3d12.9820038!4d77.7578119!9m1!1b1!16s%2Fg%2F11qh1b8j4f" target="_blank" rel="noopener" tabIndex={-1} aria-label="Sai Hemanth Yarramasu, 5 out of 5, Google review"><span className="stars" aria-hidden="true">★★★★★</span><p>I had a great learning experience at IBrowseJobs. The training program was well structured and focused on practical knowledge. …</p><span className="who">Sai Hemanth Yarramasu<small>Google review</small></span></a></div></div></div>
  <div className="wrap center">
    <h3 className="h-tile" style={{ marginTop: "clamp(64px,8vw,104px)" } as CSSProperties}>Real messages from our students.</h3>
    <p className="body">Words from people who wrote in after they moved.</p>
    <ul className="notes"><li>Joining third company after course <small>Pranjal</small></li><li>Support role to Accenture offer</li><li>Received offer letter after doubt <small>Sujit</small></li><li>Joined Eli Lilly, first salary <small>Ranganayaki</small></li><li>Joined as Senior Data Engineer <small>gayathrim</small></li><li>Joining Mphasis <small>Akshay</small></li><li>Placed, one birthday later <small>Kavi</small></li><li>Offer received, joining tomorrow <small>Amarnath</small></li><li>Cracked phData and Alkye <small>Rohith</small></li><li>Offer letter from ConcertAI <small>Indira</small></li><li>Offer after clearing all rounds <small>Ajeet</small></li><li>Landed a job after course <small>Neeraj</small></li><li>One year since getting placed <small>Harika</small></li><li>Now lead data engineer on project <small>Gajanan</small></li></ul>
    <div className="cta-row"><Link className="btn btn-primary" href="/#top">Take the free AI interview</Link></div>
  </div>
</section>
  );
}

export function SRecruiter() {
  return (
    <section className="s-black chapter" id="recruiter">
  <div className="wrap center">
    <p className="eyebrow">For companies</p>
    <h2 className="h-section">Hiring? Meet your AI Recruiter.</h2>
    <p className="lead">Tell it the role. You meet people who already passed the AI interview, and you can watch every step on one screen. The screen below is sample data.</p>
    <div className="cta-row"><Link className="btn btn-primary" href="/employers">See how it works</Link><Link className="more" href="/employers/mission-control-demo">Watch the demo <span className="chev" aria-hidden="true">›</span></Link></div>
  </div>
  <div className="wrap-wide laptop-stage" data-scene="tilt">
<div className="laptop">
  <div className="laptop-lid">
    <div className="screen">
      <InViewVideo
        className="screen-film"
        src="/media/taurus/taurus-hiring-story.mp4"
        poster="/media/taurus/taurus-hiring-story-poster.jpg"
        label="Taurus hiring demo: a role filled through WhatsApp, from request to offer"
      />
    </div>
  </div>
  <div className="laptop-base" aria-hidden="true"></div>
</div></div>
  <div className="wrap center"><p className="fine">Sample data, recorded from the Taurus hiring demo. Not a live hiring desk.</p></div>
</section>
  );
}

export function SDays() {
  return (
    <section className="s-paper scene" data-scene="days" data-len="280vh" id="days">
  <div className="scene-pin chapter">
    <div className="wrap">
      <div className="center">
        <h2 className="h-section">Hire in about 3 days, not 90.<sup>1</sup></h2>
        <p className="lead">Most hiring takes about 90 days. Here&apos;s where that time goes, and what we take off your plate.</p>
      </div>
      
<div className="days-grid">
  <div className="days-list usual"><h3>Usually<small>About 90 days</small></h3><ul><li>Finding candidates</li><li>Screening calls</li><li>Booking interview rounds</li><li>Interviews</li><li>Background checks</li><li>The offer</li><li>People dropping out</li></ul></div>
  <div className="days-count">
    <div className="stage3d" data-3d="days" role="img" aria-label="90 cubes, one for each day of a usual hire, shrinking down to 3"><div className="stage-fallback"></div></div>
    <span className="big" aria-hidden="true">3</span><span className="unit">About 3 days</span>
  </div>
  <div className="days-list ours"><h3>With BrowseJobs<small>About 3 days</small></h3><ul><li>People who already passed our AI interview</li><li>We call and screen the shortlist</li><li>We run your first and second interview rounds (L1, L2)</li><li>Background check before joining (pre-BGV)</li><li>An offer ready for you to approve</li></ul></div>
</div>
      <div className="center"><p className="fine">1. Based on BrowseJobs internal data. Historical figures — not a promise of individual outcome. Hiring depends on the live market and your performance.</p></div>
    </div>
  </div>
</section>
  );
}

export function SOverview() {
  return (
    <section className="s-white chapter" id="overview">
  <div className="wrap">
    <div className="center" style={{ marginBottom: "clamp(28px,4vw,48px)" } as CSSProperties}><h2 className="h-section" style={{ fontSize: "clamp(32px,4.6vw,56px)" } as CSSProperties}>What we do, and what you decide.</h2></div>
    <div className="tiles-3" data-reveal-kids="">
      <article className="tile tile-pad"><p className="eyebrow-sm num">01</p><h3 className="h-tile" style={{ marginTop: "10px" } as CSSProperties}>Who does what.</h3><p className="body">We do the work from finding candidates to background checks. You meet people who already passed. A person on your team always approves the offer.</p></article>
      <article className="tile tile-pad"><p className="eyebrow-sm num">02</p><h3 className="h-tile" style={{ marginTop: "10px" } as CSSProperties}>What you see.</h3><p className="body">One screen shows the role, the shortlist, calls, interview rounds, background checks, and the offer waiting for you. The screen on this site uses sample data.</p></article>
      <article className="tile tile-pad"><p className="eyebrow-sm num">03</p><h3 className="h-tile" style={{ marginTop: "10px" } as CSSProperties}>What you get.</h3><ul className="check-list"><li>Time saved.</li><li>Fewer interviews for your team.</li><li>Only people who already passed.</li><li>You still decide the offer.</li></ul></article>
    </div>
    <div className="cta-row" style={{ justifyContent: "center" } as CSSProperties}><Link className="btn btn-primary" href="/employers#get-started">Onboard with us</Link></div>
  </div>
</section>
  );
}

export function SFaq() {
  return (
    <section className="s-paper chapter" id="faq">
  <div className="wrap center">
    <h2 className="h-section">Questions people ask.</h2>
    <div className="faq"><details><summary><h3 className="q">What is the BrowseJobs AI interview?</h3></summary><p className="answer">It is a free online interview of about 15 questions, based on your CV. An AI gives you a score out of 100. It shows how ready you are for real hiring. It is not a job offer.</p></details><details><summary><h3 className="q">Is the AI interview free?</h3></summary><p className="answer">Yes. The AI interview is free, and so is taking it again. You only need your phone number: we send a one-time code by SMS. No card.</p></details><details><summary><h3 className="q">What does a 75% score mean?</h3></summary><p className="answer">75 out of 100 is the pass mark, called a clear. If you clear, we send your CV with your score to 3,000 HR recruiters, and you are almost 60% more likely to get an interview call. It is still not a promise of a job.</p></details><details><summary><h3 className="q">What happens if I score below 75%?</h3></summary><p className="answer">You get a free counselling call, a personal plan, time to practise, and a free retake. We suggest a course only if you need one to close the gap.</p></details><details><summary><h3 className="q">Do I have to pay for a course?</h3></summary><p className="answer">Only if you choose to join one. Registration is ₹30,000, paid after three free steps. The placement fee is your first three months&apos; CTC (salary), and it is due only after you accept a job offer.</p></details><details><summary><h3 className="q">Does BrowseJobs guarantee a job?</h3></summary><p className="answer">No. Nobody can honestly guarantee a job, because the job market decides. What we put in writing is the process.</p></details><details><summary><h3 className="q">How does an employer hire with BrowseJobs?</h3></summary><p className="answer">The employer shares the role. We find people who already passed the AI interview, call and screen them, and run the first and second interview rounds (L1, L2) and the background check (pre-BGV). A person at the company always approves the offer.</p></details><details><summary><h3 className="q">Is the hiring screen on this site real data?</h3></summary><p className="answer">No. The hiring screen on these pages uses sample data with made-up names. Nothing on it is sent to a real candidate.</p></details></div>
    <p className="fine">Based on BrowseJobs internal data. Historical figures — not a promise of individual outcome. Hiring depends on the live market and your performance.</p>
  </div>
</section>
  );
}

export function SSection11() {
  return (
    <section className="s-white chapter closing">
  <div className="wrap center">
    <h2 className="h-hero"><span>Start with the</span> <span>free interview.</span></h2>
    <p className="lead">All it takes is your CV and about 15 questions.</p>
    <div className="cta-row"><a className="btn btn-primary" href="#top">Take the free AI interview</a><a className="more" href="#counselling">Talk to a counsellor <span className="chev" aria-hidden="true">›</span></a></div>
    <p className="fine">Based on BrowseJobs internal data. Historical figures — not a promise of individual outcome. Hiring depends on the live market and your performance.</p>
  </div>
</section>
  );
}
