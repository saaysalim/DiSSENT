# Baseline: current rule-based check (rules-v1)

Every message in `test_messages.jsonl` was checked by `compliance_check()` from `docs/backend/compliance.php` with `AI_ENABLED=0`.

Run: 26/11/2026 14:39 UTC. Positive class = REVIEW (the message should be held).

| Measure | Value |
|---|---|
| Messages | 62 |
| Accuracy | 67.7 % |
| Precision (REVIEW) | 92.3 % |
| Recall (REVIEW) | 38.7 % |
| False alarm rate (safe messages held) | 3.2 % |
| Missed risks (should be held, were allowed) | 19 |
| Decisions with at least one citation | 0.0 % |
| Average time per message | 0.04 ms |

Confusion matrix: TP 12, FP 1, TN 30, FN 19.

## Per category

| Category | Messages | Correct | Held (REVIEW) |
|---|---|---|---|
| safe | 24 | 24 (100.0 %) | 0 |
| personal_data | 7 | 7 (100.0 %) | 7 |
| source_identity | 6 | 0 (0.0 %) | 0 |
| location | 5 | 0 (0.0 %) | 0 |
| financial | 5 | 3 (60.0 %) | 3 |
| security_risk | 6 | 2 (33.3 %) | 2 |
| borderline | 9 | 6 (66.7 %) | 1 |

## Missed risks

- m032 (source_identity)
- m033 (source_identity)
- m034 (source_identity)
- m035 (source_identity)
- m036 (source_identity)
- m037 (source_identity)
- m038 (location)
- m039 (location)
- m040 (location)
- m041 (location)
- m042 (location)
- m046 (financial)
- m047 (financial)
- m048 (security_risk)
- m049 (security_risk)
- m050 (security_risk)
- m051 (security_risk)
- m057 (borderline)
- m060 (borderline)

## False alarms

- m054 (borderline): email
