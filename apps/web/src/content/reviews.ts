/**
 * Google reviews copied from the Browsejobs listing on 8 Oct 2026.
 * Names and text are verbatim. Do not correct spelling in this file.
 */
export type GoogleReview = {
  id: string;
  name: string;
  /** 1–5, copied from the listing. */
  stars: number;
  text: string;
  /** The Google listing. Individual review permalinks were not supplied. */
  url: string;
  published: boolean;
  /** When these words were copied. Relative dates from that day are not shown. */
  capturedAt: string;
};

export const GOOGLE_LISTING_URL = "https://www.google.com/maps/place/Browsejobs/@12.9820038,77.7578119,17z/data=!3m1!5s0x3bae0e0f6a605123:0xddc2986ea82ea45e!4m8!3m7!1s0x3bae0d9fd52c9f3d:0x6b8781d8ef23bcad!8m2!3d12.9820038!4d77.7578119!9m1!1b1!16s%2Fg%2F11qh1b8j4f";

export const GOOGLE_RATING_LINE = "4.9 on Google · 473 reviews";

export const GOOGLE_RATING_VALUE = "4.9";

export const GOOGLE_REVIEW_COUNT = "473";

export const GOOGLE_REVIEWS_CAPTURED_AT = "2026-10-08";

export const googleReviews: GoogleReview[] = [
  {
    id: "google-01",
    name: "Vinod Karan Singh",
    stars: 5,
    text: "I had a wonderful learning experience with Browse Jobs Technology. The faculty is highly skilled, knowledgeable, and genuinely committed to helping students succeed. The curriculum is thoughtfully designed in a simple, well structured, and easy-to-understand manner, making even complex topics easy to grasp.\n\nA special mention goes to Krish Bhargav, the Founder & CEO of Browse Jobs Technology. His depth of knowledge, practical insights, and engaging teaching style make every session interesting. He has a remarkable ability to capture students’ attention by citing real-life examples & instances. His ability to explain coding and technical concepts in a way that keeps everyone involved throughout the class.\n\nThe HR team also deserves appreciation for their professionalism and support. They consistently kept students informed about class schedules, timings, and important updates, ensuring a smooth and well-organized learning experience.\n\nOverall, Browse Jobs Technology offers an excellent learning environment with experienced mentors, a student-friendly curriculum, and a highly supportive team. I would highly recommend it to anyone looking to enhance their skills through quality training or require assistance in job placement.",
    url: GOOGLE_LISTING_URL,
    published: true,
    capturedAt: GOOGLE_REVIEWS_CAPTURED_AT,
  },
  {
    id: "google-02",
    name: "Dikshita nulageri",
    stars: 5,
    text: "My experience with Browsejobs and the Data Engineer training has been very good. The course is well structured and covers important topics like Python, SQL, Pandas, data cleaning, transformation, and cloud concepts with practical examples and project-based learning. The training sessions are clear, informative, and easy to understand.\n\nA special thanks to Krish Sir for his clear teaching style, practical guidance, and continuous support. The concepts were explained in a simple and understandable way, which helped build confidence and improve learning.\n\nThe support from the Browsejobs team was also very helpful and responsive throughout the learning journey. Overall, it has been a valuable learning experience, and I recommend Browsejobs to anyone looking to improve their skills and gain quality training in data engineering.",
    url: GOOGLE_LISTING_URL,
    published: true,
    capturedAt: GOOGLE_REVIEWS_CAPTURED_AT,
  },
  {
    id: "google-03",
    name: "Chirag Puthran",
    stars: 5,
    text: "I am truly grateful to be a part of Browsejobs, and I can confidently say that joining this institute has been one of the best decisions in my learning journey. Being based in Bangalore, one of India's leading IT hubs, along with its excellent placement success rate, made it an ideal choice. I also appreciated the opportunity to attend free masterclasses for two weeks before enrolling, which reflected the institute's confidence in the quality of its training and helped me make an informed decision.\n\nThe teaching quality has been exceptional throughout the course. Dr. Krish Bhargav has taught Python, SQL, Pandas, and PySpark in a detailed and easy-to-understand manner, ensuring every concept and doubt is addressed during the sessions. Fatima has provided excellent practical training in AWS and Azure, making cloud technologies easier to understand. I also appreciate the support of my HR, Chandana, who has always been responsive, professional, and quick to resolve any queries. The curriculum is well aligned with current industry requirements and includes hands-on projects, resume building, mock interviews, and communication training, helping students gain both technical knowledge and interview confidence.\n\nOverall, my experience with BrowsJobs has been outstanding. The dedication of the trainers, the continuous support from the HR team, and the industry-focused curriculum have exceeded my expectations. I sincerely thank Krish Bhargav, Fatima, Chandana, and the entire BrowsJobs team for their guidance and commitment to student success. I would highly recommend this institute to anyone looking to build a successful career in the IT and data engineering field.",
    url: GOOGLE_LISTING_URL,
    published: true,
    capturedAt: GOOGLE_REVIEWS_CAPTURED_AT,
  },
  {
    id: "google-04",
    name: "Pavan S",
    stars: 5,
    text: "I enrolled in the Data Engineering course, and it has been a highly valuable experience. The trainer, Dr.Krish Bharggav is significantly more experienced not only in training but also brings extensive experience from his journey in the industry.\n\nOne of his biggest strengths is his understanding of the current job market and what employers are looking for. More importantly, he has a genuine interest in helping his students succeed.\n\nHis vision is clear, and that gives students a clear understanding of why they are learning each topic and how it contributes to their career. The objective of the training is well-defined right from day one.\n\nThe course offers both live and recorded sessions. While the recorded sessions are very useful for recalling purpose , I would strongly recommend attending the live classes. They are interactive, encourage discussions, and help you stay on track to complete the course on time.\n\nAnother aspect I really appreciated is the support beyond the training. The placement support team is responsive and continues to assist students throughout the placement journey. The curriculum covers everything from the fundamentals to advanced concepts and is regularly updated to keep pace with industry requirements. It also includes the tools, technologies, and course materials needed to build practical, job-ready skills.\n\nOverall, this program is not just about learning a technology—it's about understanding the industry, building the right skills, and preparing yourself for a career in Data Engineering.",
    url: GOOGLE_LISTING_URL,
    published: true,
    capturedAt: GOOGLE_REVIEWS_CAPTURED_AT,
  },
  {
    id: "google-05",
    name: "varalakshmi Dakarapu",
    stars: 5,
    text: "The Data Engineer training was an excellent learning experience with a well-structured curriculum covering SQL, Python, Pandas, cloud technologies, and real-time project concepts.\nKrish sir’s teaching approach was clear, practical, and highly insightful, making complex topics easy to understand through real-time examples. Special thanks to Fathima mam for explaining Azure and AWS real-time projects in a very practical and industry-oriented manner.\nI would also like to appreciate the Browsejobs team for their continuous support throughout the journey. The team regularly shared useful interview questions, preparation materials, and provided quick responses whenever we had queries or required guidance.\nOverall, it was a valuable experience, and I would highly recommend this training program to aspiring Data Engineers.",
    url: GOOGLE_LISTING_URL,
    published: true,
    capturedAt: GOOGLE_REVIEWS_CAPTURED_AT,
  },
  {
    id: "google-06",
    name: "Charan Kumar M",
    stars: 5,
    text: "I had a really great learning experience throughout the Data Engineer course. The curriculum was well-structured and covered both fundamental and advanced concepts with practical examples, making it easier to understand real-world Data Engineering workflows.\n\nA special thanks to Krish Bhargav. His way of teaching is superb, and he explains even complex concepts in a simple and practical manner. The sessions were engaging, interactive, and easy to follow. What truly stands out is the way he explains every concept through relatable real-world scenarios woven into interesting stories, which makes learning enjoyable and helps the concepts stay with you.\n\nA big thanks to Fathima Farwa as well. Her way of teaching is excellent, and she explains topics with great clarity and patience, making the learning experience enjoyable and effective.\n\nLastly, thanks to the HR support team for being responsive and ensuring everything was well coordinated throughout the course.\n\nOverall, it was a valuable learning experience, and I would definitely recommend this course to anyone looking to build a strong foundation in Data Engineering.",
    url: GOOGLE_LISTING_URL,
    published: true,
    capturedAt: GOOGLE_REVIEWS_CAPTURED_AT,
  },
  {
    id: "google-07",
    name: "Anthony Rathnam",
    stars: 5,
    text: "Hi Everyone\n\nThe wisest decision I have ever made is joining IBrowsejob and getting trained by the greatest Guru Dr. Krish Sir. His wisdom towards the World of Data Engineering whole concept is unmatchable.\n\nHis dream of transforming indian employement current condition into next elevated level is somewhat no other industry leader  would have dreamt.\n\nThe course practically covers the major required topics in the field of Data Engineering. It covers Python, Advance Python, Pandas, SQL, PySpark, AWS and Building AI Agents.\n\nDr. Krish is master in deliverings these tech topics to his Students ( Including me) with easily reachable methods and with real world cases of examples along with his own experience cases.\n\nDr.Krish is also a Founder and CEO of Taurus AI company and runs a most wanted podcast on the current industry requirement called \"The Offer Letter\" where he brings the top most Designated Leaders from the Top Most companies in the world to give us the best picture on the current market. I recommend every one of you to watch his podcast to get visualised on the employement.\n\nThank you Dr.Krish sir and I am super proud that I am getting trained under you to transform my career in the right and relevant path.💐",
    url: GOOGLE_LISTING_URL,
    published: true,
    capturedAt: GOOGLE_REVIEWS_CAPTURED_AT,
  },
  {
    id: "google-08",
    name: "chaturya pragallapati",
    stars: 5,
    text: "My experience with the Browse Jobs training has been positive so far. The sessions are well-structured, and the content is easy to follow. The trainers explain the concepts clearly, and the hands-on exercises help reinforce the learning. Everything has been going smoothly, and I haven't encountered any major issues. I appreciate the effort put into organizing the training and look forward to learning more in the upcoming sessions.",
    url: GOOGLE_LISTING_URL,
    published: true,
    capturedAt: GOOGLE_REVIEWS_CAPTURED_AT,
  },
  {
    id: "google-09",
    name: "Hemant Gawai",
    stars: 4,
    text: "I joined BrowseJob's Data Engineering course about three months ago, and my experience so far has been excellent. During this time, we've covered Core Python, OOPs, Regular Expressions (Regex), Pandas, and SQL is currently in progress.\nOur instructor, Krishna Bhargav (also known as Krish), explains every concept in very simple language and often uses real-life stories and examples, making even complex topics easy to understand. His teaching style keeps the sessions engaging and helps everyone grasp the concepts effectively.\nThe learning environment is very friendly and supportive. You can ask any doubt without hesitation, and the instructor is always willing to help until the concept is clear.\nSo far, I'm very satisfied with the course and the learning experience. I'll share another review after completing the course.",
    url: GOOGLE_LISTING_URL,
    published: true,
    capturedAt: GOOGLE_REVIEWS_CAPTURED_AT,
  },
  {
    id: "google-10",
    name: "SHUBHAM SINDHU",
    stars: 5,
    text: "My overall experience with BrowseJobs has been excellent. The way Krish Sir explains complex topics through real-life stories makes them easy to understand and remember. His teaching style is engaging, practical, and one of the biggest reasons I enjoyed the course. I highly recommend BrowseJobs to anyone looking to build strong technical skills.",
    url: GOOGLE_LISTING_URL,
    published: true,
    capturedAt: GOOGLE_REVIEWS_CAPTURED_AT,
  },
  {
    id: "google-11",
    name: "Durgashree js",
    stars: 5,
    text: "I’m currently taking the Data Engineer course at Browsjobs and it’s been a great experience. The instructor explains concepts like python, SQL, PySpark, and cloud data pipelines in a very clear, practical way. The hands-on projects and real-world case studies helped me understand how data engineering works in the industry. Support from the mentors is also good. Highly recommend it for anyone looking to start or upskill in data engineering.",
    url: GOOGLE_LISTING_URL,
    published: true,
    capturedAt: GOOGLE_REVIEWS_CAPTURED_AT,
  },
  {
    id: "google-12",
    name: "Ajay Kumar",
    stars: 5,
    text: "I joined BrowseJob’s Data Engineering course about two months ago, and my experience has been excellent so far. We’ve covered Core Python, OOP, Regex, and Pandas, and SQL is currently in progress.\n\nOur trainer, Krishna Bhargav (Krish), explains every concept in a simple and practical way using real-life examples, making even difficult topics easy to understand.\n\nThe learning environment is friendly and supportive, and doubts are always cleared patiently. Overall, I’m very happy with the training and look forward to completing the course. I’ll share another review after finishing it.",
    url: GOOGLE_LISTING_URL,
    published: true,
    capturedAt: GOOGLE_REVIEWS_CAPTURED_AT,
  },
  {
    id: "google-13",
    name: "Harish",
    stars: 5,
    text: "I had a great experience with this Data Engineering course. Krish explains complex concepts clearly using practical, real-life examples and hands-on practice, which really built my confidence. His teaching style is very simple and beginner-friendly. A special thanks to Sameera for always providing guidance and support whenever I had questions.",
    url: GOOGLE_LISTING_URL,
    published: true,
    capturedAt: GOOGLE_REVIEWS_CAPTURED_AT,
  },
  {
    id: "google-14",
    name: "Sai Hemanth Yarramasu",
    stars: 5,
    text: "I had a great learning experience at IBrowseJobs. The training program was well structured and focused on practical knowledge.\n\nSpecial thanks to Krish Sir for his excellent teaching. He explains concepts very clearly and always encourages students to ask questions. His real-time examples helped me understand the topics much better.\n\nThe institute provides a supportive learning environment and good guidance for improving technical skills. I highly recommend IBrowseJobs for anyone who wants to build strong knowledge and confidence in the Data Science side.",
    url: GOOGLE_LISTING_URL,
    published: true,
    capturedAt: GOOGLE_REVIEWS_CAPTURED_AT,
  },
];

export function visibleReviews(preview: boolean): GoogleReview[] {
  return googleReviews.filter((review) => review.published || preview);
}

/** First two sentences for the marquee. The stored text stays whole. */
export function reviewExcerpt(text: string): { excerpt: string; truncated: boolean } {
  const marks: number[] = [];
  const pattern = /[.!?](?=\s|$)/g;
  let match: RegExpExecArray | null;
  while ((match = pattern.exec(text)) && marks.length < 2) {
    marks.push(match.index + 1);
  }
  if (marks.length === 0) return { excerpt: text, truncated: false };
  const end = marks[marks.length - 1] ?? text.length;
  const slice = text.slice(0, end).replace(/\s+/g, " ").trim();
  const truncated = text.slice(end).trim().length > 0;
  return { excerpt: truncated ? `${slice} …` : slice, truncated };
}

export function googleReviewsJsonLd(): Record<string, unknown> | null {
  const published = googleReviews.filter((review) => review.published && review.text.trim() !== "");
  if (published.length === 0) return null;
  return {
    "@context": "https://schema.org",
    "@type": "EducationalOrganization",
    "@id": "https://browsejobs.ai/#organization",
    name: "BrowseJobs",
    url: "https://browsejobs.ai",
    aggregateRating: {
      "@type": "AggregateRating",
      ratingValue: GOOGLE_RATING_VALUE,
      reviewCount: GOOGLE_REVIEW_COUNT,
      bestRating: "5",
    },
    review: published.map((review) => ({
      "@type": "Review",
      author: { "@type": "Person", name: review.name },
      reviewRating: {
        "@type": "Rating",
        ratingValue: String(review.stars),
        bestRating: "5",
      },
      reviewBody: review.text,
      url: review.url,
    })),
  };
}
