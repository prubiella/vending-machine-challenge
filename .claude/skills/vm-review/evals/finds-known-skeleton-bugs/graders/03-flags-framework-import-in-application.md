---
type: llm
focus: trace
weight: 1
---

The review reports that HealthCheckQueryHandler in src/Application imports a Symfony class, breaking the rule that Application depends only on Domain, and that deptrac does not catch it.
