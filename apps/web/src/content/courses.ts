/**
 * Full course content, sourced from the 2026 brochures (docs/ brochures; do
 * NOT invent topics). Per founder instruction, Data Engineering excludes
 * real-time/Kafka from the core syllabus — those live in `exploreLater` as
 * optional self-study. Python Backend has no brochure yet: minimal entry only.
 */

export type CourseModuleContent = {
  title: string;
  hook: string;
  topics: string[];
};

export type CourseProjectContent = {
  title: string;
  body: string;
  points: string[];
};

export type CourseDetail = {
  code: string;
  slug: string;
  name: string;
  tagline: string;
  live: boolean;
  hero: string;
  duration: string;
  format: string;
  access: string;
  projectsLabel: string;
  outcomes: string[];
  roles: string[];
  modules: CourseModuleContent[];
  tools: string[];
  projects: CourseProjectContent[];
  exploreLater?: {
    note: string;
    items: string[];
  };
  /** True when the full brochure syllabus is loaded. */
  hasSyllabus: boolean;
};

export const courseDetails: CourseDetail[] = [
  {
    code: "DE",
    slug: "data-engineering",
    name: "Data Engineering",
    tagline: "Pipelines, warehouses, and the modern data stack.",
    live: true,
    hero: "Build the pipelines companies are hiring for right now. A six-month, project-first program whose syllabus is rebuilt every month from the questions asked in real Data Engineering interviews.",
    duration: "6 months",
    format: "Live online + recordings",
    access: "1 year unlimited",
    projectsLabel: "3 CV-ready",
    outcomes: [
      "Design and ship production batch data pipelines on AWS and Azure",
      "Answer the SQL, Spark and system-design questions asked in this month's interviews",
      "Build and orchestrate medallion-architecture ETL on Databricks and Delta Lake",
      "Walk interviewers through real projects you built and deployed yourself",
      "Explain your work clearly and confidently — communication is a scored module here",
    ],
    roles: ["Data Engineer", "Big Data Engineer", "ETL Developer", "Analytics Engineer", "Cloud Data Engineer"],
    modules: [
      {
        title: "Python for Data Engineering",
        hook: "Core Python through to the depth interviewers actually probe.",
        topics: [
          "Intro, variables, data types, casting, strings, booleans",
          "Operators, lists, tuples, sets, dictionaries",
          "If-else, loops, functions, lambda functions",
          "Classes, objects, inheritance, iterators, polymorphism",
          "Scope, modules, dates, math, JSON, RegEx, PIP",
          "Try-except, user input, file handling",
        ],
      },
      {
        title: "Pandas & Data Wrangling",
        hook: "The library every data interview assumes you know cold.",
        topics: [
          "Pandas intro, Series, DataFrames",
          "Read CSV, read JSON, analyze data",
          "Cleaning empty cells, wrong formats, duplicates",
          "Correlations and Pandas plotting",
        ],
      },
      {
        title: "SQL & Data Modeling",
        hook: "The round that decides most Data Engineering interviews.",
        topics: [
          "Select, distinct, where, order by, and/or/not",
          "Insert, update, delete, null handling",
          "Min/max, count, sum, avg, like, wildcards, in, between, aliases",
          "Joins: inner, left, right, self · group by, having, exists, case",
          "Stored procedures, operators",
          "Create/drop/alter DB & tables, primary & foreign keys, views",
          "Window functions, star & snowflake schemas, query optimization",
        ],
      },
      {
        title: "Apache Spark & PySpark",
        hook: "Distributed processing — the single biggest differentiator on your CV.",
        topics: [
          "Architecture, SparkSession, SparkContext, cluster managers",
          "RDDs, parallelize, repartition/coalesce, broadcast variables, accumulators",
          "DataFrames: StructType/StructField, select, collect, withColumn, withColumnRenamed",
          "where/filter, drop/dropDuplicates, orderBy, groupBy, joins, union, unionByName",
          "UDFs, transform/apply, map/flatMap, foreach",
          "SQL functions: aggregate, window, date/timestamp, JSON, pivot, partitionBy, fillna",
        ],
      },
      {
        title: "AWS for Data Engineering",
        hook: "The exact services named in current job descriptions.",
        topics: [
          "Cloud computing benefits, EC2 (types, pricing, scaling, auto-scaling)",
          "S3, RDS, Redshift, Lambda, containers, load balancing, availability zones",
          "IAM, CloudWatch, CloudFormation, Elastic Beanstalk, cloud compliance",
          "Glue transformations and Redshift warehousing",
        ],
      },
      {
        title: "Databricks & Delta Lake",
        hook: "The modern lakehouse stack interviews now assume.",
        topics: [
          "Workspace, clusters, notebooks, DBFS · interactive vs job clusters",
          "Connecting to external sources: S3, ADLS, GCS",
          "RDD vs DataFrame vs Dataset · transformations vs actions",
          "Delta tables, ACID transactions, schema enforcement & evolution, time travel",
          "Medallion architecture (Bronze → Silver → Gold), SCDs, error handling & retries",
        ],
      },
      {
        title: "Advanced Databricks & Orchestration",
        hook: "Production-grade pipelines, not notebooks.",
        topics: [
          "Autoloader (cloudFiles), CSV/JSON/Parquet ingestion, managed vs external tables",
          "Job scheduling, alerts & retries, run history",
          "MERGE/UPSERTs, OPTIMIZE & Z-ORDER, VACUUM & data retention",
          "Partitioning, bucketing, caching & persisting",
          "Multi-task DAGs, Airflow/ADF integration, parameterized pipelines",
          "Unity Catalog RBAC, auditing & lineage, Delta Sharing, Delta Live Tables",
        ],
      },
      {
        title: "Microsoft Azure Data Engineering",
        hook: "The second cloud that doubles your job-market surface area.",
        topics: [
          "IaaS/PaaS/SaaS, regions, availability zones, resource groups, Azure AD & RBAC",
          "VMs, App Services, Functions · Blob, ADLS Gen2, File Storage",
          "Azure SQL, Cosmos DB, Synapse (dedicated vs serverless SQL pools)",
          "Azure Data Factory: Copy Data tool, integration runtime, advanced pipelines",
          "Azure Databricks, HDInsight, batch pipeline architectures",
          "Purview governance & lineage, App Insights monitoring, cost management",
        ],
      },
      {
        title: "Git, GitHub & GitHub Actions for Data Engineering",
        hook: "Version control and CI/CD — what separates an engineer from someone who writes scripts.",
        topics: [
          "Git fundamentals & version control",
          "GitHub repositories, branching, merging & pull requests",
          "Git workflow for Data Engineering projects",
          "GitHub Actions fundamentals",
          "Creating workflows using YAML",
          "Automating Python-based API data extraction",
          "CI/CD for data pipelines",
          "Loading data into Azure Blob Storage / AWS S3 using GitHub Actions",
        ],
      },
      {
        title: "Interview Engineering",
        hook: "The module no other institute has — because no one else monitors 50 interviews a day.",
        topics: [
          "Live question bank from monitored interviews, refreshed monthly",
          "Weekly technical + HR mock interviews, AI-analysed",
          "Communication & confidence coaching — scored, not optional",
          "Readiness decided on your mock data, not on a sales target",
        ],
      },
    ],
    tools: [
      "Python", "SQL", "Pandas", "PySpark", "Apache Spark",
      "AWS (S3, Glue, Redshift, Lambda, EC2)", "Databricks", "Delta Lake",
      "Azure (ADF, Synapse, ADLS Gen2)", "Airflow", "Git",
    ],
    projects: [
      {
        title: "End-to-end batch pipeline",
        body: "Raw data on S3 → Glue transformations → Redshift warehouse → live BI dashboard.",
        points: ["Ingest from multiple sources", "Transform & cleanse at scale", "Load into a cloud warehouse", "Ship a live dashboard"],
      },
      {
        title: "Medallion lakehouse on Databricks",
        body: "Bronze → Silver → Gold with Delta Lake, SCDs and full data quality handling.",
        points: ["Autoloader ingestion", "ACID Delta tables", "Slowly changing dimensions", "Time travel & audit"],
      },
      {
        title: "Production Airflow deployment",
        body: "Orchestrated, monitored, alerting-enabled workflows — deployed, not just coded.",
        points: ["DAG design & dependencies", "Retries & SLAs", "Alerting", "Parameterized runs"],
      },
    ],
    exploreLater: {
      note: "The core program is batch-first, because that's what the monitored interviews test. If a specific JD asks for streaming, here is your self-study map — and your mentor will point you at it when it matters.",
      items: [
        "Kafka — producers, consumers, topics",
        "Spark Structured Streaming — triggers, checkpointing, watermarks",
        "Stateful streaming aggregations",
        "Real-time clickstream pipelines (Kafka → Spark → Delta sink)",
      ],
    },
    hasSyllabus: true,
  },
  {
    code: "DC",
    slug: "devops-cloud",
    name: "DevOps & Cloud",
    tagline: "Ship, scale, and run production systems.",
    live: true,
    hero: "Own the pipeline from commit to production. A six-month, hands-on program whose syllabus is rebuilt every month from real DevOps interview questions — build, automate, deploy, monitor.",
    duration: "6 months",
    format: "Live online + recordings",
    access: "1 year unlimited",
    projectsLabel: "4 CV-ready",
    outcomes: [
      "Build and run CI/CD pipelines that take code from commit to production automatically",
      "Deploy, scale and troubleshoot containerized applications on Kubernetes",
      "Provision real cloud infrastructure with Terraform — and defend every choice in an interview",
      "Monitor and alert like an SRE, with Prometheus and Grafana dashboards you built",
      "Handle the scenario-based questions DevOps interviews now lead with",
    ],
    roles: ["DevOps Engineer", "Site Reliability Engineer", "Cloud Engineer", "Platform Engineer", "Build & Release Engineer"],
    modules: [
      {
        title: "DevOps Fundamentals",
        hook: "The framing every interview opens with.",
        topics: [
          "SDLC and the DevOps lifecycle",
          "Agile & Scrum basics",
          "CI vs CD — and why interviewers test the distinction",
          "IaC overview, benefits of DevOps",
        ],
      },
      {
        title: "Linux & Shell Scripting",
        hook: "The round every DevOps interview starts with.",
        topics: [
          "Linux basics, file system hierarchy, essential commands",
          "User management, permissions, process management",
          "Bash automation and scripting",
          "Networking fundamentals",
        ],
      },
      {
        title: "Git & GitHub",
        hook: "Version control the way teams actually use it.",
        topics: [
          "Git basics, branching strategies",
          "Merge & rebase",
          "Remote repositories, GitHub workflow",
          "Pull requests and code review",
        ],
      },
      {
        title: "Docker & Containerization",
        hook: "Containers, properly — not just “docker run”.",
        topics: [
          "Images and layers",
          "Containers: run, manage, interact",
          "Dockerfile & multi-stage builds",
          "Docker Hub & registries",
        ],
      },
      {
        title: "Kubernetes",
        hook: "Where most DevOps candidates fail — and where we spend the most time.",
        topics: [
          "Pods — the smallest deployable units",
          "Deployments, updates and rollbacks",
          "Services & Ingress: exposing applications",
          "ReplicaSets and autoscaling",
          "Helm charts",
          "Real troubleshooting scenarios",
        ],
      },
      {
        title: "IaC & Configuration Management",
        hook: "Terraform in depth, Ansible to the level roles actually ask for.",
        topics: [
          "Terraform providers: interacting with cloud platforms",
          "Terraform modules: reusable infrastructure components",
          "Variables for dynamic configuration",
          "State file management and remote state",
          "Ansible inventory, roles and playbooks",
          "Where configuration management still fits alongside Terraform",
        ],
      },
      {
        title: "Jenkins & CI/CD Pipelines",
        hook: "Pipeline-as-code, the way production teams run it.",
        topics: [
          "Pipeline: automate build, test and deploy workflows",
          "Pipeline as Code with Jenkinsfile",
          "Agents: distributing jobs for scalability",
          "GitHub Actions fundamentals",
        ],
      },
      {
        title: "AWS for DevOps",
        hook: "The cloud layer under everything above.",
        topics: [
          "IAM — manage access securely",
          "EC2, VPC, ELB, Auto Scaling",
          "Route53 DNS routing, S3 object storage",
          "ECR container registry, Amazon RDS",
          "Multi-AZ architecture and high availability",
        ],
      },
      {
        title: "Monitoring, Observability & SRE",
        hook: "What separates a DevOps engineer from a script-runner.",
        topics: [
          "Prometheus: metrics collection",
          "Grafana: dashboards and visualization",
          "Alerting and on-call fundamentals",
          "SRE principles: SLOs, error budgets",
        ],
      },
      {
        title: "Interview Engineering",
        hook: "The module no other institute has — because no one else monitors 50 interviews a day.",
        topics: [
          "Live question bank from monitored interviews, refreshed monthly",
          "Weekly technical + HR mock interviews, AI-analysed",
          "Scenario-based troubleshooting drills",
          "Communication & confidence coaching — scored, not optional",
        ],
      },
    ],
    tools: [
      "Linux", "Bash", "Git", "GitHub", "Docker", "Kubernetes", "Helm",
      "Jenkins", "GitHub Actions", "Terraform", "Ansible", "AWS",
      "Python", "PowerShell", "Prometheus", "Grafana",
    ],
    projects: [
      {
        title: "Full CI/CD pipeline",
        body: "A three-tier application taken from commit to automated production deployment with Jenkins.",
        points: ["Code commit triggers", "Automated build", "Automated testing", "Deploy to AWS"],
      },
      {
        title: "Docker deployment",
        body: "Containerize real applications and ship them.",
        points: ["Create Docker images", "Multi-stage builds", "Push to Docker Hub", "Run multi-container apps on Kubernetes"],
      },
      {
        title: "Terraform infrastructure",
        body: "A complete cloud environment provisioned end-to-end as code.",
        points: ["Write Terraform code", "Create AWS resources", "Manage state", "Full infrastructure automation"],
      },
      {
        title: "Microservices on Kubernetes",
        body: "Deployed, autoscaled and monitored with Prometheus and Grafana.",
        points: ["Pods & deployments", "Services & Ingress", "ConfigMaps & secrets", "Rolling updates"],
      },
    ],
    hasSyllabus: true,
  },
  {
    code: "DA",
    slug: "data-analytics",
    name: "Data Analytics",
    tagline: "SQL, dashboards, and decisions from data.",
    live: true,
    hero: "From data to decisions. Turn information into impact. A career-focused program whose syllabus is rebuilt every month from real Data Analyst interviews — Excel, SQL, Python, Statistics, Power BI.",
    duration: "5–6 months",
    format: "Live online + recordings",
    access: "1 year unlimited",
    projectsLabel: "5 CV-ready",
    outcomes: [
      "Analyze real business datasets and present insights that actually drive decisions",
      "Build interactive Power BI dashboards — from data modeling and DAX to scheduled refresh",
      "Clean, transform and explore data with Python (Pandas, NumPy) and advanced SQL",
      "Answer the SQL, case-study and business-scenario questions analyst interviews lead with",
      "Present with clarity — communication is a scored module, because analysts are judged on it",
    ],
    roles: ["Data Analyst", "Business Analyst", "Senior Data Analyst", "BI Analyst"],
    modules: [
      {
        title: "Data Analytics Fundamentals",
        hook: "The foundation, and the framing interviewers listen for.",
        topics: [
          "The analytics lifecycle: Define → Collect → Process → Analyze → Visualize → Act",
          "KPIs: choosing and defending the right metric",
          "How data actually drives business decisions",
        ],
      },
      {
        title: "Advanced Excel",
        hook: "Still the first tool most analyst interviews test.",
        topics: [
          "Excel functions: lookup, logical, text, date",
          "Pivot tables and pivot charts",
          "Dashboards: organize, analyze and visualize effectively",
          "Data cleaning in Excel",
        ],
      },
      {
        title: "SQL for Analysts",
        hook: "The single highest-leverage skill in an analyst interview.",
        topics: [
          "Select, where, order by, aggregations",
          "Joins: inner, left, right, self",
          "CTEs and subqueries",
          "Window functions — the question that separates candidates",
          "Group by, having, case statements",
        ],
      },
      {
        title: "Python for Analytics",
        hook: "The most powerful language for data analysis.",
        topics: [
          "Variables, loops, functions",
          "Pandas: DataFrames and structured data",
          "NumPy: numerical operations with arrays",
          "Data cleaning: missing values, duplicates, inconsistent data",
          "EDA: explore data and uncover hidden patterns",
          "Data visualization and business insights",
        ],
      },
      {
        title: "Statistics",
        hook: "Understand data. Uncover patterns. Make defensible decisions.",
        topics: [
          "Mean, median and central tendency",
          "Probability and distributions",
          "Hypothesis testing — assumptions and reliable conclusions",
          "Correlation: relationships between variables",
          "Regression: predict and forecast outcomes",
        ],
      },
      {
        title: "Power BI",
        hook: "Visualize data. Drive insights. Empower decisions.",
        topics: [
          "Data modeling: relationships and robust models",
          "DAX: calculated measures for deeper analysis",
          "Measures and visualizations",
          "Interactive dashboards for storytelling",
          "Power BI Service: publish, share, collaborate",
          "Scheduled refresh and automation",
          "Power Query for transformation",
        ],
      },
      {
        title: "Business Analytics Across Domains",
        hook: "Where the analysis meets the money.",
        topics: [
          "Sales analytics: performance, trends, forecasting, strategy",
          "Marketing analytics: campaign performance, customer behavior, ROI",
          "Finance analytics: budgeting, forecasting, cost optimization, risk",
          "HR analytics: performance, attrition, retention, workforce planning",
          "Customer analytics: segmentation, experience, lifetime value",
        ],
      },
      {
        title: "Interview Engineering",
        hook: "The module no other institute has — because no one else monitors 50 interviews a day.",
        topics: [
          "Live question bank from monitored interviews, refreshed monthly",
          "Weekly technical + case-study mock interviews, AI-analysed",
          "Business-scenario and guesstimate drills",
          "Communication & confidence coaching — scored, not optional",
        ],
      },
    ],
    tools: [
      "Microsoft Excel", "SQL", "Python", "NumPy", "Pandas", "Power BI",
      "Power Query", "DAX", "Statistics", "Git & GitHub",
    ],
    projects: [
      {
        title: "Retail Sales Dashboard",
        body: "Sales overview and trend analysis across products and regions.",
        points: ["Sales overview & trends", "Top products & categories", "Regional performance", "Profit & discount analysis"],
      },
      {
        title: "HR Attrition Dashboard",
        body: "Why people leave — and what the data says to do about it.",
        points: ["Employee overview", "Attrition analysis", "Department-wise insights", "Experience & age analysis"],
      },
      {
        title: "E-Commerce Analytics",
        body: "Traffic, revenue and the conversion funnel end to end.",
        points: ["Traffic & revenue overview", "Customer behavior analysis", "Top-selling products", "Conversion rate analysis"],
      },
      {
        title: "Financial Performance Dashboard",
        body: "Revenue, expense and profitability at executive standard.",
        points: ["Revenue & expense analysis", "Profitability insights", "Budget vs actual", "Cash flow overview"],
      },
      {
        title: "Executive KPI Dashboard",
        body: "The one-screen view a leadership team makes decisions from.",
        points: ["Business overview", "Key performance indicators", "Goal tracking", "Data-driven decision making"],
      },
    ],
    hasSyllabus: true,
  },
  {
    code: "PB",
    slug: "python-backend",
    name: "Python Backend",
    tagline: "APIs, databases, and production Python.",
    live: true,
    hero: "Production Python for the backend roles companies are hiring for — APIs, databases, and the engineering practices interviews test.",
    duration: "6 months",
    format: "Live online + recordings",
    access: "1 year unlimited",
    projectsLabel: "CV-ready",
    outcomes: [],
    roles: [],
    modules: [],
    tools: [],
    projects: [],
    hasSyllabus: false,
  },
];

export function getCourseDetail(slug: string): CourseDetail | undefined {
  return courseDetails.find((c) => c.slug === slug);
}

/** Shared brochure content used on every course page. */
export const situationCards = [
  {
    kicker: "Switching domains?",
    body: "The real, industry-grade projects you build here become the centerpiece of your CV — hands-on work in the new domain, fully genuine, fully BGV-safe.",
  },
  {
    kicker: "Career gap?",
    body: "A structured internship with us, built around projects you actually deliver, fills your timeline with documented work. A gap is only a gap if it's empty.",
  },
  {
    kicker: "Fresher?",
    body: "Earn a free internship certificate with BrowseJobs.ai — the equivalent of six months of hands-on experience before your first interview.",
  },
] as const;

export const placementChannels = [
  {
    kicker: "01 · Naukri, done right",
    title: "Profile built by our team",
    body: "Your CV professionally optimised and loaded on Naukri by our team — not left to you.",
  },
  {
    kicker: "02 · 3,000+ HR network",
    title: "Broadcast to recruiters",
    body: "Your profile shared across our network of recruiters and HR professionals actively hiring.",
  },
  {
    kicker: "03 · Direct clients",
    title: "Straight to hiring companies",
    body: "Shared directly with our client companies hiring for this role right now.",
  },
] as const;

export const careerServices = [
  { service: "ATS Resume Building", what: "A resume that survives the filter and gets read by a human" },
  { service: "LinkedIn & GitHub", what: "Profile optimised for recruiter search; portfolio that showcases real work" },
  { service: "Mock Interviews", what: "Weekly technical + HR mocks, AI-analysed, with detailed feedback" },
  { service: "Communication Training", what: "A scored module — not an add-on. Delivery is what converts skill into offers" },
  { service: "Interview Question Bank", what: "Live bank drawn from monitored interviews for this role, this month" },
  { service: "1:1 Mentoring", what: "Personalised guidance at every step until you're hired" },
] as const;
