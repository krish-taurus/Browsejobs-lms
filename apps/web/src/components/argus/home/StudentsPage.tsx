import { SuccessStories } from "@/components/apple/SuccessStories";
import { WhatsAppMessages } from "@/components/apple/WhatsAppMessages";
import { HomeCounsel, HomeCourses } from "./HomePage";
import { HomeClose, HomeFaq, HomePath, HomeScore, StudentsAfter, StudentsHero, StudentsHow } from "./scenes";

export function StudentsPage() {
  return (
    <>
      <StudentsHero />
      <HomeScore />
      <HomePath />
      <HomeCourses interviewHref="#interview-start" />
      <HomeCounsel />
      <StudentsHow />
      <StudentsAfter />
      <SuccessStories fuller tone="argus" />
      <WhatsAppMessages variant="grid" />
      <HomeFaq />
      <HomeClose />
    </>
  );
}
