---
type: llm
focus: trace
weight: 1
---

A handoff.md was written under .audit/vm-review/, and every script, file path and make target that it tells the fixer to run or read, outside the findings themselves, exists at eval/skeleton-v1 (git cat-file -e eval/skeleton-v1:<path>, git show eval/skeleton-v1:Makefile); none exists only in the newer working tree, such as etc/git/hooks-docker/run-in-php-image.sh.
