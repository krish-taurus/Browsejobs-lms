import { Inter_Tight } from "next/font/google";

/**
 * Inter Tight for the signed-in Taurus surfaces (portal, sign-in, owner admin,
 * hiring floor), which sit outside <ApShell> and so don't get its font.
 * Same variable name as ApShell, so taurus-ui.css can use one stack.
 */
export const taurusDisplayFont = Inter_Tight({ subsets: ["latin"], weight: ["600", "700"], variable: "--font-inter-tight", display: "swap" });
