# Comparison: current rules vs RAG + LLM (ReAct)

Same test set (`test_messages.jsonl`, 62 fictional messages), same measures. Positive class = REVIEW.

| Measure | Baseline (rules-v1) | RAG + mock | Real model (later) |
|---|---|---|---|
| Accuracy | 67.7 % | 98.4 % | |
| Precision (REVIEW) | 92.3 % | 100.0 % | |
| Recall (REVIEW) | 38.7 % | 96.8 % | |
| False alarm rate | 3.2 % | 0.0 % | |
| Missed risks | 19 | 1 | |
| Decisions with at least one citation | 0.0 % | 48.4 % | |
| Average time per message | 0.04 ms | 50.88 ms | |

## Correct per category

| Category | Messages | Baseline | RAG + mock | Real model (later) |
|---|---|---|---|---|
| safe | 24 | 24 | 24 | |
| personal_data | 7 | 7 | 7 | |
| source_identity | 6 | 0 | 6 | |
| location | 5 | 0 | 5 | |
| financial | 5 | 3 | 5 | |
| security_risk | 6 | 2 | 5 | |
| borderline | 9 | 6 | 9 | |

## How to read this

- **The mock is not the contribution yet.** It is a rule-based stand-in that follows the same ReAct steps (countries, search, decide, explain) so the pipeline, citations, fail-safe and admin page can be built and tested without sending message text to a language model.
- **Its scores are optimistic.** The mock's patterns and this test set were written by the same person, so the mock has effectively seen the test. For the dissertation, use a held-out test set written by others (for example from the workshops) and keep this one for development.
- **The citations are SAMPLE passages**, not real law (see `ai/knowledge/README.md`).
- The baseline time is the PHP rules alone; the AI time includes the HTTP call and the vector search.
- Fill in the last column by setting `AI_MODEL` to the real model and running `python ai/eval/run_ai.py --label "Real model"`.
