# AI check: RAG + mock (mock-rules-v1)

Every message in `test_messages.jsonl` was sent to the AI service (`POST /check`). Knowledge base: 30 passages (AF, DE, IE, IR, UA), SAMPLE summaries only. Latency is measured from the client (HTTP round trip).

Run: 26/11/2026 14:39 UTC. Positive class = REVIEW (the message should be held).

| Measure | Value |
|---|---|
| Messages | 62 |
| Accuracy | 98.4 % |
| Precision (REVIEW) | 100.0 % |
| Recall (REVIEW) | 96.8 % |
| False alarm rate (safe messages held) | 0.0 % |
| Missed risks (should be held, were allowed) | 1 |
| Decisions with at least one citation | 48.4 % |
| REVIEW decisions with at least one citation | 100.0 % |
| Average time per message | 50.9 ms |

Confusion matrix: TP 30, FP 0, TN 31, FN 1.

## Per category

| Category | Messages | Correct | Held (REVIEW) |
|---|---|---|---|
| safe | 24 | 24 (100.0 %) | 0 |
| personal_data | 7 | 7 (100.0 %) | 7 |
| source_identity | 6 | 6 (100.0 %) | 6 |
| location | 5 | 5 (100.0 %) | 5 |
| financial | 5 | 5 (100.0 %) | 5 |
| security_risk | 6 | 5 (83.3 %) | 5 |
| borderline | 9 | 9 (100.0 %) | 2 |

## Missed risks

- m048 (security_risk)

## False alarms

None.
