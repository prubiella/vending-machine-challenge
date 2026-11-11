---
type: llm
focus: trace
weight: 1
---

The review reports that config/packages/monolog.yaml excludes the request channel ("!request"), so uncaught HTTP exceptions are never logged.
