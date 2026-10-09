"use client";

import { useEffect } from "react";

const GA_ID = "G-WGGL1MS701";
const CLARITY_ID = "xiomzak7ll";

/**
 * Analytics starts on the first real interaction.
 * Loading Google and Clarity during first paint was blocking the main thread
 * and setting third-party cookies before the page was usable.
 */
export function Analytics() {
  useEffect(() => {
    let started = false;
    const start = () => {
      if (started) return;
      started = true;
      window.removeEventListener("pointerdown", start);
      window.removeEventListener("keydown", start);
      window.removeEventListener("scroll", start);

      const ga = document.createElement("script");
      ga.async = true;
      ga.src = `https://www.googletagmanager.com/gtag/js?id=${GA_ID}`;
      document.body.appendChild(ga);
      const inline = document.createElement("script");
      inline.text = `window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','${GA_ID}');`;
      document.body.appendChild(inline);

      const clarity = document.createElement("script");
      clarity.text = `(function(c,l,a,r,i,t,y){c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);})(window,document,"clarity","script","${CLARITY_ID}");`;
      document.body.appendChild(clarity);
    };

    window.addEventListener("pointerdown", start, { passive: true });
    window.addEventListener("keydown", start);
    window.addEventListener("scroll", start, { passive: true });
    return () => {
      window.removeEventListener("pointerdown", start);
      window.removeEventListener("keydown", start);
      window.removeEventListener("scroll", start);
    };
  }, []);

  return null;
}
