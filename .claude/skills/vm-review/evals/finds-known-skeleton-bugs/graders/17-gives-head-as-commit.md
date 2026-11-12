---
type: llm
focus: trace
weight: 1
---

Wherever review.md and handoff.md give the reviewed head's id, it is the commit eval/skeleton-v1 points at (git rev-parse 'eval/skeleton-v1^{commit}', 462e224), never the annotated tag object's id (git rev-parse eval/skeleton-v1, 76ec392).
