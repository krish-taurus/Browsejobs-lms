/**
 * Full course content, sourced from the 2026 brochures (do NOT invent topics).
 * Generated from the brochure builders so the site and the PDFs cannot drift.
 *
 * Per founder instruction, optional/self-study topics live in `exploreLater`
 * rather than the core syllabus — Data Engineering excludes real-time/Kafka,
 * Python Backend excludes Docker, caching and FastAPI, and DevOps excludes
 * monitoring/observability.
 *
 * No pricing lives in this file — fees and the refund policy are elsewhere.
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
      "Walk interviewers through real projects you built, deployed and pushed to GitHub yourself",
      "Explain your work clearly and confidently — communication is a scored module here",
    ],
    roles: [
      "Data Engineer",
      "Big Data Engineer",
      "ETL Developer",
      "Analytics Engineer",
      "Cloud Data Engineer",
    ],
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
        title: "Agentic AI for Data Engineering",
        hook: "The skill now appearing in Data Engineering JDs — pipelines a model can read, repair and be trusted with.",
        topics: [
          "LLMs for data teams: tool calling, structured outputs, and when an agent beats a script",
          "Build an MCP server over your warehouse — expose schemas and query tools safely",
          "Pipeline copilots: agents that generate, test and repair Spark & SQL jobs",
          "LLM-as-a-judge for data quality — anomaly explanations and column-level checks",
          "Agentic ingestion: schema-drift detection and self-healing Autoloader jobs",
          "Text-to-SQL with guardrails: read-only roles, cost limits, human approval",
          "Token, latency and cost budgets for agents running inside a pipeline",
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
      "Python",
      "SQL",
      "Pandas",
      "PySpark",
      "Apache Spark",
      "AWS (S3, Glue, Redshift, Lambda, EC2)",
      "Databricks",
      "Delta Lake",
      "Azure (ADF, Synapse, ADLS Gen2)",
      "Airflow",
      "Git & GitHub",
      "GitHub Actions",
      "Terraform",
      "MCP",
      "LangGraph",
    ],
    projects: [
      {
        title: "End-to-end batch pipeline",
        body: "Raw data on S3 → Glue transformations → Redshift warehouse → live BI dashboard.",
        points: [
          "Ingest from multiple sources",
          "Transform & cleanse at scale",
          "Load into a cloud warehouse",
          "Ship a live dashboard",
        ],
      },
      {
        title: "Medallion lakehouse on Databricks",
        body: "Bronze → Silver → Gold with Delta Lake, SCDs and full data-quality handling.",
        points: [
          "Autoloader ingestion",
          "ACID Delta tables",
          "Slowly changing dimensions",
          "Time travel & audit",
        ],
      },
      {
        title: "Pipeline copilot on MCP",
        body: "An MCP server over your warehouse plus an agent that diagnoses a failed job and proposes the fix.",
        points: [
          "MCP server & tools",
          "Read-only query guardrails",
          "Failure triage agent",
          "Human approval gate",
        ],
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
    tagline: "Own the pipeline from commit to production.",
    live: true,
    hero: "Own the pipeline from commit to production. A six-month, hands-on program whose syllabus is rebuilt every month from real DevOps interview questions — build, automate, deploy, ship.",
    duration: "6 months",
    format: "Live online + recordings",
    access: "1 year unlimited",
    projectsLabel: "5 CV-ready",
    outcomes: [
      "Build and run CI/CD pipelines that take code from commit to production automatically",
      "Deploy, scale and troubleshoot containerized applications on Kubernetes",
      "Provision real cloud infrastructure with Terraform — and defend every choice in an interview",
      "Automate configuration across a fleet with Ansible, and script the rest in Bash",
      "Handle the scenario-based troubleshooting questions DevOps interviews now lead with",
    ],
    roles: [
      "DevOps Engineer",
      "Cloud Engineer",
      "Platform Engineer",
      "Build & Release Engineer",
    ],
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
          "Docker Compose for multi-container apps",
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
        title: "Terraform — Infrastructure as Code",
        hook: "Provision real cloud infrastructure, reproducibly.",
        topics: [
          "Providers: interacting with cloud platforms",
          "Modules: reusable infrastructure components",
          "Variables for dynamic configuration",
          "State file management and remote state",
        ],
      },
      {
        title: "Ansible",
        hook: "Configuration management and automation at scale.",
        topics: [
          "Inventory: defining and managing hosts",
          "Roles: organizing reusable tasks",
          "Playbooks: automating configuration with YAML",
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
        title: "Agentic AI for DevOps",
        hook: "AIOps has stopped being a buzzword — this is the part interviews now ask platform engineers about.",
        topics: [
          "Agent fundamentals for platform teams: tool calling, the agent loop, structured outputs",
          "MCP servers over cloud APIs, Kubernetes and your CI/CD system",
          "Incident-response agents: log triage, root-cause summaries, runbook execution",
          "Human-in-the-loop approval gates — why an agent never gets kubectl delete",
          "AI in the pipeline: PR review, IaC drift explanation, test-failure triage",
          "Self-healing infrastructure — and the failure modes that argue against it",
          "Securing agents: least privilege, secret handling, prompt injection through logs",
          "Observability for agents: tracing, token cost and budgets on non-deterministic systems",
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
      "Linux",
      "Bash",
      "Git & GitHub",
      "Docker",
      "Kubernetes",
      "Helm",
      "Jenkins",
      "GitHub Actions",
      "Terraform",
      "Ansible",
      "AWS (IAM, EC2, VPC, S3, ECR)",
      "Python",
      "PowerShell",
      "MCP",
    ],
    projects: [
      {
        title: "Full CI/CD pipeline",
        body: "A three-tier application taken from commit to automated production deployment with Jenkins.",
        points: [
          "Code commit triggers",
          "Automated build & test",
          "Deploy to AWS",
          "Pipeline as code",
        ],
      },
      {
        title: "Docker deployment",
        body: "Containerize real applications and ship them, not just run them locally.",
        points: [
          "Multi-stage images",
          "Docker Compose",
          "Push to a registry",
          "Run multi-container apps",
        ],
      },
      {
        title: "Terraform infrastructure",
        body: "A complete cloud environment provisioned end-to-end as code.",
        points: [
          "Write Terraform modules",
          "Create AWS resources",
          "Manage remote state",
          "Reproducible teardown",
        ],
      },
      {
        title: "Microservices on Kubernetes",
        body: "Deployed, autoscaled and rolled out without downtime.",
        points: [
          "Pods & deployments",
          "Services & Ingress",
          "ConfigMaps & secrets",
          "Rolling updates",
        ],
      },
      {
        title: "Incident-response agent",
        body: "An agent wired to your cluster over MCP that triages a failing service, summarises root cause from logs and events, and proposes a runbook step.",
        points: [
          "MCP over Kubernetes & logs",
          "Failure triage",
          "Approval before any write",
          "Traced & cost-capped",
        ],
      },
    ],
    exploreLater: {
      note: "The core program covers what the monitored interviews actually test. If a specific JD reaches past it, here is your self-study map — and your mentor will point you at the right one when it matters.",
      items: [
        "GitOps — ArgoCD, Flux",
        "Service mesh — Istio, Linkerd",
        "Azure DevOps & GCP equivalents",
        "Supply-chain security — Trivy, SBOMs, image signing",
        "Monitoring & observability — Prometheus, Grafana",
      ],
    },
    hasSyllabus: true,
  },
  {
    code: "DA",
    slug: "data-analytics",
    name: "Data Analytics",
    tagline: "From data to decisions. Turn information into impact.",
    live: true,
    hero: "From data to decisions. Turn information into impact — Excel, SQL, Python, Statistics and Power BI, in a program whose syllabus is rebuilt every month from real Data Analyst interviews.",
    duration: "5–6 months",
    format: "Live online + recordings",
    access: "1 year unlimited",
    projectsLabel: "4 CV-ready",
    outcomes: [
      "Analyze real business datasets and present insights that actually drive decisions",
      "Build interactive Power BI dashboards — from data modeling and DAX to scheduled refresh",
      "Clean, transform and explore data with Python (Pandas, NumPy) and advanced SQL",
      "Answer the SQL, case-study and business-scenario questions analyst interviews lead with",
      "Present with clarity — communication is a scored module, because analysts are judged on it",
    ],
    roles: [
      "Data Analyst",
      "Business Analyst",
      "Senior Data Analyst",
      "BI Analyst",
    ],
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
        title: "Agentic AI for Data Analytics",
        hook: "Analysts are now judged on whether they can use AI without being fooled by it.",
        topics: [
          "LLM fundamentals for analysts: prompting, context engineering, structured outputs",
          "Text-to-SQL agents on a real warehouse — and the guardrails that make them safe",
          "Agentic EDA: an agent that profiles a dataset and proposes the analysis",
          "RAG over business documents — grounding answers in policy, contracts and reports",
          "LLM-as-a-judge: validating AI-generated insight before a stakeholder sees it",
          "Automating the narrative: AI commentary in Power BI and scheduled insight digests",
          "Where AI is wrong: hallucination, bias, and the analyst's duty to verify",
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
      "Microsoft Excel",
      "SQL",
      "Python",
      "Pandas",
      "NumPy",
      "Power BI",
      "Power Query",
      "DAX",
      "Statistics",
      "Git & GitHub",
      "LLM APIs",
    ],
    projects: [
      {
        title: "Retail Sales Dashboard",
        body: "Sales overview and trend analysis across products and regions.",
        points: [
          "Sales overview & trends",
          "Top products & categories",
          "Regional performance",
          "Profit & discount analysis",
        ],
      },
      {
        title: "HR Attrition Dashboard",
        body: "Why people leave — and what the data says to do about it.",
        points: [
          "Employee overview",
          "Attrition analysis",
          "Department-wise insights",
          "Experience & age analysis",
        ],
      },
      {
        title: "E-Commerce Analytics",
        body: "Traffic, revenue and the conversion funnel end to end.",
        points: [
          "Traffic & revenue overview",
          "Customer behavior analysis",
          "Top-selling products",
          "Conversion rate analysis",
        ],
      },
      {
        title: "Financial Performance Dashboard",
        body: "Revenue, expense and profitability at executive standard.",
        points: [
          "Revenue & expense analysis",
          "Profitability insights",
          "Budget vs actual",
          "Cash flow overview",
        ],
      },
    ],
    exploreLater: {
      note: "The core program covers what the monitored interviews actually test. If a specific JD reaches past it, here is your self-study map — and your mentor will point you at the right one when it matters.",
      items: [
        "Tableau & Looker Studio",
        "Advanced forecasting & time series",
        "A/B testing & experiment design",
        "dbt for the analytics layer",
        "Google Analytics 4 & product analytics",
      ],
    },
    hasSyllabus: true,
  },
  {
    code: "PB",
    slug: "python-backend",
    name: "Python Backend",
    tagline: "Django APIs, real databases, and production Python.",
    live: true,
    hero: "Production Python for the backend roles companies are hiring for — Django APIs, real databases, and the engineering practices interviews test. A six-month, project-first program whose syllabus is rebuilt every month from real backend interviews.",
    duration: "6 months",
    format: "Live online + recordings",
    access: "1 year unlimited",
    projectsLabel: "3 CV-ready",
    outcomes: [
      "Design and ship production REST APIs with Django and Django REST Framework",
      "Model data properly in PostgreSQL — schemas, indexes, transactions and migrations",
      "Secure an API the way interviews expect: auth, authorization, validation and rate limits",
      "Run background work and async I/O without breaking under load",
      "Answer the system-design and debugging questions backend interviews lead with",
    ],
    roles: [
      "Backend Developer",
      "Python Developer",
      "API Developer",
      "Software Engineer",
      "Platform Engineer",
    ],
    modules: [
      {
        title: "Python for Backend Engineering",
        hook: "Core Python through to the depth interviewers actually probe.",
        topics: [
          "Data types, collections, comprehensions, slicing",
          "Functions, arguments, closures, decorators",
          "Classes, inheritance, dunder methods, dataclasses",
          "Modules, packaging, virtual environments, dependency management",
          "Exceptions, context managers, file and JSON handling",
          "Type hints and static checking",
        ],
      },
      {
        title: "Clean Code & Testing",
        hook: "The round that separates a scripter from an engineer.",
        topics: [
          "SOLID principles applied to real Python",
          "pytest and pytest-django: fixtures, parametrize, mocking, coverage",
          "Test-driven development on a real feature",
          "Debugging, logging and profiling",
          "Git workflow: branches, pull requests, code review",
        ],
      },
      {
        title: "Databases & SQL",
        hook: "Backend interviews are database interviews wearing a different hat.",
        topics: [
          "PostgreSQL fundamentals, data types, constraints",
          "Joins, aggregations, subqueries, window functions",
          "Schema design, normalization, indexing strategy",
          "Transactions, isolation levels, deadlocks",
          "Django ORM: querysets, relationships, select_related and the N+1 problem",
          "Django migrations and safe schema change",
        ],
      },
      {
        title: "Django & Production APIs",
        hook: "The framework named in most current Python backend job descriptions.",
        topics: [
          "Project and app layout, settings, URLs, views",
          "Django REST Framework: serializers, viewsets, routers",
          "Request/response validation and a consistent error envelope",
          "REST design, status codes, versioning, pagination and filtering",
          "Django admin, management commands, signals",
          "OpenAPI schema generation and testing endpoints with the DRF test client",
        ],
      },
      {
        title: "Authentication, Authorization & Security",
        hook: "The area candidates most often get caught out on.",
        topics: [
          "Password hashing, sessions vs tokens, JWT and refresh flows",
          "OAuth2 and third-party login",
          "Django permissions, groups and role-based access control",
          "OWASP Top 10 for APIs: injection, IDOR, SSRF, mass assignment",
          "Secrets management, CORS, CSRF, rate limiting",
        ],
      },
      {
        title: "Async Python, Queues & Background Jobs",
        hook: "Where “it worked on my laptop” stops being good enough.",
        topics: [
          "asyncio, coroutines, the event loop, Django async views",
          "Blocking vs non-blocking I/O — and how to tell which you wrote",
          "Celery: queues, workers, retries, idempotency",
          "Scheduled jobs and long-running task patterns",
          "WebSockets and server-sent events with Django Channels",
        ],
      },
      {
        title: "Performance & Scale",
        hook: "The system-design conversation, made concrete.",
        topics: [
          "Query optimization and hunting down the N+1",
          "Database connection pooling and tuning",
          "Load testing and finding the real bottleneck",
          "Horizontal scaling, statelessness, load balancing",
          "Observability: structured logs, metrics, tracing",
        ],
      },
      {
        title: "CI/CD & Cloud Deployment",
        hook: "Shipping it — because a project that isn't deployed doesn't count.",
        topics: [
          "GitHub Actions: test, build and deploy pipelines",
          "Environment configuration and twelve-factor practice",
          "Gunicorn and nginx, static and media file handling",
          "Deploying to AWS: EC2, RDS, S3, load balancing",
          "Zero-downtime deploys, health checks and rollback",
        ],
      },
      {
        title: "System Design for Backend Interviews",
        hook: "The round that decides your level — and your offer.",
        topics: [
          "Requirements, estimation and trade-off reasoning",
          "Designing a URL shortener, a rate limiter, a feed",
          "SQL vs NoSQL, sharding, replication, consistency",
          "Message queues and event-driven architecture",
          "Microservices vs monolith — and defending your choice",
        ],
      },
      {
        title: "Interview Engineering",
        hook: "The module no other institute has — because no one else monitors 50 interviews a day.",
        topics: [
          "Live question bank from monitored interviews, refreshed monthly",
          "Weekly technical + HR mock interviews, AI-analysed",
          "Live coding and debugging drills under time pressure",
          "Communication & confidence coaching — scored, not optional",
        ],
      },
    ],
    tools: [
      "Python",
      "Django",
      "Django REST Framework",
      "PostgreSQL",
      "Django ORM",
      "Celery",
      "Redis (queue)",
      "pytest",
      "pytest-django",
      "Gunicorn & nginx",
      "GitHub Actions",
      "AWS (EC2, RDS, S3)",
      "Git & GitHub",
    ],
    projects: [
      {
        title: "Production REST API",
        body: "A multi-resource Django API with auth, validation, pagination and a full test suite.",
        points: [
          "Django + DRF + PostgreSQL",
          "JWT auth & role-based access",
          "Migrations & fixtures",
          "90%+ test coverage",
        ],
      },
      {
        title: "Async job processing service",
        body: "Queue-backed background work with retries, idempotency and monitoring.",
        points: [
          "Celery workers",
          "Retry & dead-letter handling",
          "Scheduled jobs",
          "Worker metrics",
        ],
      },
      {
        title: "Automated deployment pipeline",
        body: "Commit to running service on AWS, automatically, with rollback.",
        points: [
          "GitHub Actions CI/CD",
          "Environment config & secrets",
          "Deploy to EC2 + RDS",
          "Health checks & rollback",
        ],
      },
    ],
    exploreLater: {
      note: "The core program covers what the monitored interviews actually test. If a specific JD reaches past it, here is your self-study map — and your mentor will point you at the right one when it matters.",
      items: [
        "Docker & containers",
        "Redis caching strategies — TTLs, invalidation",
        "FastAPI — the async-first alternative",
        "GraphQL with Strawberry",
        "Kubernetes basics",
      ],
    },
    hasSyllabus: true,
  },
  {
    code: "AE",
    slug: "ai-engineering",
    name: "AI Engineering",
    tagline: "Grounded retrieval, safe agents, and MCP servers.",
    live: true,
    hero: "Build the AI systems companies are actually hiring for: retrieval that stays grounded, agents that use tools safely, and MCP servers that connect them to real systems. A six-month, project-first program whose syllabus is rebuilt every month from real AI Engineer interviews.",
    duration: "6 months",
    format: "Live online + recordings",
    access: "1 year unlimited",
    projectsLabel: "5 CV-ready",
    outcomes: [
      "Ship a retrieval system that answers from your data with citations — and refuses when it can't",
      "Build agents that call tools, keep memory and hand off work, without going off the rails",
      "Write and publish MCP servers that connect a model to real systems safely",
      "Prove a non-deterministic feature works: eval sets, tracing, regression tests and cost budgets",
      "Answer the RAG, agent-design and guardrail questions AI Engineer interviews lead with",
    ],
    roles: [
      "AI Engineer",
      "LLM Application Engineer",
      "Agentic AI Engineer",
      "Applied AI Engineer",
      "AI Platform Engineer",
    ],
    modules: [
      {
        title: "Python & Async for AI Engineering",
        hook: "The language floor every AI Engineering interview assumes.",
        topics: [
          "Core Python, typing, dataclasses, decorators",
          "asyncio and concurrent API calls — the difference between a demo and a service",
          "Pydantic models and schema validation",
          "Packaging, environments and dependency management with uv",
          "Git, pull requests and code review",
        ],
      },
      {
        title: "LLM Foundations & the Provider Layer",
        hook: "Understanding the model you are engineering around.",
        topics: [
          "Tokens, context windows, temperature, sampling and why output varies",
          "Comparing providers: capability, latency, price, rate limits",
          "One interface, many models — a provider-agnostic gateway you can swap",
          "Running models locally with Ollama, and when local is the right answer",
          "Streaming, timeouts, retries and graceful degradation",
        ],
      },
      {
        title: "Prompting, Context Engineering & Structured Outputs",
        hook: "The term that replaced “prompt engineering” in this year's job descriptions.",
        topics: [
          "System prompts, few-shot patterns and instruction design",
          "Context engineering: what to put in the window, and what to leave out",
          "Structured outputs with JSON schema and Pydantic — parsing you can trust",
          "Chunking long inputs, summarisation chains and context compression",
          "Caching prompts and cutting token cost without losing quality",
        ],
      },
      {
        title: "RAG — Retrieval-Augmented Generation",
        hook: "The single most-asked topic in AI Engineer interviews.",
        topics: [
          "Embeddings, similarity and what a vector actually encodes",
          "Chunking strategies and why naive splitting breaks answers",
          "Vector stores: pgvector, Chroma, Qdrant — and choosing between them",
          "Hybrid search, metadata filtering and reranking",
          "Citations, groundedness and teaching a system to say “I don't know”",
          "Measuring retrieval: recall, precision and failure analysis",
        ],
      },
      {
        title: "Agent Fundamentals & Design Patterns",
        hook: "What an agent is, and — more usefully — when not to build one.",
        topics: [
          "The agent loop: reason, act, observe, repeat",
          "Agentic design patterns: reflection, planning, tool use, multi-agent",
          "Tool calling: definitions, schemas, parallel calls, error recovery",
          "Memory: short-term, long-term and retrieval-backed",
          "Agentic AI risks — and the failure modes interviewers ask you to name",
        ],
      },
      {
        title: "Building Agents with the Major Frameworks",
        hook: "Depth in the frameworks JDs name, not a tour of every library.",
        topics: [
          "LangChain core: models, tools, structured output binding",
          "LangGraph: state, nodes, edges, checkpointing and durable execution",
          "Human-in-the-loop interrupts and approval gates",
          "The OpenAI Agents SDK: agents, handoffs, sessions",
          "CrewAI compared — roles, tasks and when a crew fits better",
          "Choosing a framework, and defending the choice in an interview",
        ],
      },
      {
        title: "Multi-Agent Systems & Orchestration",
        hook: "Where most candidates' understanding runs out.",
        topics: [
          "Orchestrator-worker, planner-executor and debate patterns",
          "Handoffs, delegation and shared state between agents",
          "The A2A protocol: agent-to-agent communication",
          "Sandboxed execution — running model-written code without losing the host",
          "Loop limits, budgets and stopping conditions",
        ],
      },
      {
        title: "MCP — Model Context Protocol",
        hook: "The integration layer that turned agents from demos into products.",
        topics: [
          "Hosts, clients and servers — the MCP architecture",
          "Tools, resources and prompts: the three things a server exposes",
          "Local (stdio) vs remote (HTTP/SSE) transport",
          "Connecting an agent to existing MCP servers and marketplaces",
          "Writing your own MCP server over a real system, with auth",
          "Agentic RAG and long-term memory through MCP",
        ],
      },
      {
        title: "Guardrails, Safety & Prompt-Injection Defence",
        hook: "The module that decides whether your system is allowed near production.",
        topics: [
          "Input and output guardrails, and where each belongs",
          "Prompt injection, indirect injection and the lethal trifecta",
          "Least-privilege tool access and read-only by default",
          "PII handling, redaction and data-residency constraints",
          "Human approval gates and audit trails for agent actions",
          "Refusal, fallback and failing safely",
        ],
      },
      {
        title: "Evaluation, Observability & LLMOps",
        hook: "How you prove a non-deterministic feature works — the question that ends most interviews.",
        topics: [
          "Building an eval set from real usage, not vibes",
          "LLM-as-a-judge: rubrics, bias, and validating the judge itself",
          "Regression testing prompts and models before you ship",
          "Tracing with LangSmith: spans, latency, token accounting",
          "Cost budgets, model routing and caching strategy",
          "Monitoring drift and quality in production",
        ],
      },
      {
        title: "Deploying AI Systems to Production",
        hook: "Deployed, monitored and affordable — or it isn't engineering.",
        topics: [
          "Serving agents behind FastAPI with streaming responses",
          "Containerising an AI service and managing model configuration",
          "Queues and background execution for long-running agent runs",
          "Rate limiting, quotas and per-user token budgets",
          "Building a UI with Gradio, and shipping it",
          "CI/CD, staged rollout and rollback for prompt and model changes",
        ],
      },
      {
        title: "Interview Engineering",
        hook: "The module no other institute has — because no one else monitors 50 interviews a day.",
        topics: [
          "Live question bank from monitored AI Engineer interviews, refreshed monthly",
          "Weekly technical + HR mock interviews, AI-analysed",
          "System-design drills: design a RAG platform, design an agent runtime",
          "Communication & confidence coaching — scored, not optional",
        ],
      },
    ],
    tools: [
      "Python",
      "OpenAI API",
      "Anthropic API",
      "Ollama",
      "LangChain",
      "LangGraph",
      "LangSmith",
      "OpenAI Agents SDK",
      "CrewAI",
      "MCP",
      "Pydantic",
      "FastAPI",
      "pgvector",
      "Qdrant",
      "Docker",
      "Gradio",
      "Git & GitHub",
      "AWS",
    ],
    projects: [
      {
        title: "Grounded knowledge assistant",
        body: "RAG over a real document corpus — hybrid search, reranking, citations, and a measured refusal rate.",
        points: [
          "Chunking & embeddings",
          "Hybrid search + rerank",
          "Cited answers",
          "Retrieval eval set",
        ],
      },
      {
        title: "Autonomous research analyst",
        body: "A planner, searcher and writer working together to produce a sourced report from one question.",
        points: [
          "Multi-agent orchestration",
          "Web search tools",
          "Structured report output",
          "Loop & cost limits",
        ],
      },
      {
        title: "Your own MCP server",
        body: "An MCP server over a real system, authenticated, connected to a host and driven by an agent.",
        points: [
          "Tools, resources, prompts",
          "Local & remote transport",
          "Auth & least privilege",
          "Published & documented",
        ],
      },
      {
        title: "Guardrailed agent with human-in-the-loop",
        body: "An agent that takes real actions — behind input/output guardrails, approval gates and an audit trail.",
        points: [
          "Injection defence",
          "Approval interrupts",
          "Audit log",
          "Safe failure paths",
        ],
      },
      {
        title: "Eval harness & cost dashboard",
        body: "The evidence layer: regression tests over prompts and models, traced, with token spend per feature.",
        points: [
          "Eval set & rubrics",
          "LLM-as-a-judge",
          "LangSmith tracing",
          "Cost per request",
        ],
      },
    ],
    exploreLater: {
      note: "The core program goes deep on the frameworks named in live job descriptions rather than touring every library. If a specific JD asks for one of these, here is your self-study map — and your mentor will point you at it when it matters.",
      items: [
        "Google ADK — agents on Google's stack",
        "Pydantic AI — typed agents in pure Python",
        "Microsoft Agent Framework & Agno",
        "Mastra — agents in TypeScript",
        "n8n — low-code agent workflows",
        "Fine-tuning & LoRA",
      ],
    },
    hasSyllabus: true,
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
