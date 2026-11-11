---
type: llm
focus: trace
weight: 1
---

The review reports that the dockerised pre-commit hook does not pass GIT_INDEX_FILE into the container, so git commit -a (or a commit with paths) checks the old index and skips the checks.
