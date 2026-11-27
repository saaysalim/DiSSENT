# DiSSENT AI compliance check (RAG + ReAct)

Step 2 of the research plan: an **interpretable** compliance check for messages. It decides
**ALLOW** or **REVIEW**, explains why, and **cites the law passages** it used. A human
administrator always makes the final decision.

> **Status (26/11/2026): pipeline built and tested with a MOCK model and SAMPLE knowledge.**
> No real language model is connected and no real legal text is stored yet.

## How it works

```
Website (PHP)                       dissent-ai (Python FastAPI, internal)
send a message
  └─ compliance_check()  ──POST /check──►  ReAct loop
       AI_ENABLED=0: rules                   1. identify_countries   → which countries' rules apply
       AI_ENABLED=1: ask the AI              2. read the message     → which risks (personal data, source,
                                                                        location, money, passwords, file)
                                             3. search_passages      → for each risk, the best passage per
                                                                        country from the vector database
                                             4. final answer         → ALLOW/REVIEW, risk level, reasons,
  ◄──── decision, reasons, citations, steps ──                          citations, steps
  held? → Admin page: "AI suggestion – the admin decides"
           the admin approves or rejects; agreement is recorded
```

- **RAG (retrieval-augmented generation):** the knowledge files in `knowledge/<COUNTRY>/` are
  split into passages by `ingest.py` and stored in **Chroma**, a vector database that runs
  inside the container. Search finds the passages closest in meaning to each risk.
- **Embeddings run locally:** the all-MiniLM-L6-v2 model (ONNX) is downloaded once when the
  image is built. At runtime nothing is sent to another service. Chroma's usage reporting is off.
- **ReAct:** the model gives a *thought* and an *action*; the service runs the action and adds
  the *observation*; this repeats until the model gives its final answer. All steps are returned,
  so the admin can see how the decision was reached.
- **Citations are checked:** the service keeps only citations of passages it really retrieved
  in that run, so a model cannot invent law.
- **Fail safe:** if the model or the agent fails, the service answers REVIEW. If the service is
  down, slower than `AI_TIMEOUT_SECONDS`, or answers with invalid data, the website holds the
  message (`check_failed`), exactly as before.
- **Privacy:** the message text is never logged. Reasons and steps describe what was found
  ("a phone number") but never repeat it.

### Why Chroma?

| Option | Why (not) |
|---|---|
| **Chroma (chosen)** | Runs inside the Python service with files on disk (no extra server), filters by country, popular and well documented, easy to replace |
| FAISS | Fast, but no metadata filtering or storage of the passages built in |
| Qdrant / Weaviate | Good, but an extra server to run and secure; worth it only with many thousands of passages |
| pgvector | Would need PostgreSQL next to MySQL |

## Files

| File | What it does |
|---|---|
| `app/main.py` | FastAPI service: `POST /check`, `GET /health` |
| `app/react.py` | The ReAct loop, the tools and the checks on the final answer |
| `app/models.py` | The model interface: `MockModel` (used now), `OllamaModel` and `CloudModel` (stubs, switched off) |
| `app/mock_rules.py` | How the mock model "reads" a message (explainable patterns) |
| `app/kb.py` | Search in the vector database |
| `ingest.py` | Splits the knowledge files into passages and builds the vector database |
| `knowledge/` | One folder per country; **SAMPLE** summaries only (see `knowledge/README.md`) |
| `eval/` | Test set, baseline and AI evaluation (see below) |
| `tests/` | Service tests and PHP fail-safe tests |

## API

`POST /check`

```json
{"text": "...", "sender_country": "AF", "receiver_country": "DE", "has_attachment": false}
```

Answer:

```json
{"decision": "REVIEW", "risk_level": "high",
 "reasons": ["The message may contain details that could identify a source. ... (see AF-SRC-1, DE-SRC-1)"],
 "citations": [{"source": "SAMPLE privacy and safety rules for Afghanistan", "country": "AF",
                "passage_id": "AF-SRC-1", "quote": "SAMPLE summary, not legal text. ..."}],
 "steps": [{"thought": "...", "action": "identify_countries(sender=AF, receiver=DE)", "observation": "..."}],
 "model_name": "mock-rules-v1", "latency_ms": 31}
```

The website does not store a country for its members yet, so it sends empty countries and
the service searches all countries (see "Decisions" below).

## Running it

```powershell
docker compose up -d ai                 # build (first time ~10–30 min) and start; port 127.0.0.1:8090
docker compose ps ai                    # wait for "healthy"
```

Switch the website to the AI check: set `AI_ENABLED=1` in `.env`, then
`docker compose up -d web`. Back to the rules: `AI_ENABLED=0` and the same command.
Run the database migration first: `database/migrations/2026-11-26_ai_checks.sql`.

After changing knowledge files: `docker compose restart ai` (it ingests at start-up).

## Evaluation

```powershell
# Baseline (current rules), in a temporary container from the web image:
docker compose run --rm --no-deps -v "${PWD}/ai:/var/www/ai" web php /var/www/ai/eval/run_baseline.php
# AI service (needs dissent-ai running):
python ai/eval/run_ai.py
```

Results: `eval/results_baseline.md`, `eval/results_ai.md`, `eval/compare.md`.
The test set (`eval/test_messages.jsonl`, made by `eval/make_test_messages.py`) is fictional.
**The mock's scores are optimistic**: its patterns and the test set were written together.
Use a held-out test set written by others for the dissertation.

## Tests

```powershell
python ai/tests/test_service.py                                     # the service
docker cp ai/tests/. dissent-web:/tmp/aitests/
docker exec dissent-web php /tmp/aitests/php_failsafe.php           # PHP fail-safe
```

## Switching to a real model later

1. **Local model (keeps message text on the server):** install Ollama on the server (or as a
   Docker service on the internal network), pull a model, then in `.env`:
   `AI_MODEL=ollama`, `OLLAMA_URL=http://ollama:11434`, `OLLAMA_MODEL=<name>`.
   Finish `OllamaModel.next_step()` in `app/models.py`: send `REACT_PROMPT`, the task and the
   history, ask for JSON, and turn the answer into a `Step` or `Final`. Everything else
   (tools, citation check, fail-safe, admin page, evaluation) stays the same.
2. **Cloud model (message text leaves the server):** only after the supervisor approves, with a
   data processing agreement and an updated Privacy notice. Then `AI_MODEL=cloud`,
   `AI_ALLOW_CLOUD=1`, `AI_CLOUD_MODEL=<name>` and the key in `AI_API_KEY` (in `.env`, never in code),
   and finish `CloudModel.next_step()`.
3. Run `python ai/eval/run_ai.py --label "Real model"` and fill in the last column of `eval/compare.md`.
4. Replace the SAMPLE knowledge with the agreed legal texts (`knowledge/README.md`) and run the
   evaluation again.

## Decisions for the supervisor

- Which countries and which laws (and whether their texts may be stored and quoted).
- Local or cloud model; server size and cost (a 7–8B local model needs about 8–16 GB RAM,
  ideally a GPU; a cloud model costs per message and sends text off the server).
- Whether DiSSENT should store a country for each member (sensitive for exiled journalists).
- Who writes the held-out test set.
