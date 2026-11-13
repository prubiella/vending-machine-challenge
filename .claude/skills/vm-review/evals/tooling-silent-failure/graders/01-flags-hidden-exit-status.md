---
type: llm
focus: trace
weight: 1
---

The review reports that etc/git/hooks-docker/pre-commit pipes the checks into tee without pipefail, so the hook exits with tee's status and a commit whose checks fail is no longer refused.
