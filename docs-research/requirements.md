# DiSSENT – Requirements

Step 0 of the research plan: requirements defined from the workshops.
Version 1, 26/11/2026. Outside the web root (`docs/`), so it is never served by the website.

**Sources used**
- **W**: workshop on 25/11/2026 (testers used the site through a shared link) and the feedback items fixed on 26/11/2026 (README Progress Log).
- **S**: supervisor (research plan, roles plan, launch on dissent.ie).
- **R**: security review of the code (25–26/11/2026).
- **E**: existing behaviour that the code already enforces and that the tests check.

Each requirement has a number so the evaluation, the tests and the dissertation can refer to it.
"Status" says whether the current system already meets it (✅), partly (🔶) or not yet (❌).

---

## 1. Functional requirements

| ID | Requirement | Source | Status |
|---|---|---|---|
| FR-1 | A person can create an account with name, email, password, professional category and a required consent tick box (Terms of use and Privacy notice). The answer never reveals whether an email address is already registered. | W (registration feedback), R (account enumeration) | ✅ |
| FR-2 | A member can log in, log out, reset a forgotten password by email and change the password; changing it logs out other devices. | W, R | ✅ |
| FR-3 | Visitors see only public pages (Home, About, Media Centre, Verification, FAQ, Contact). Members' pages send visitors to Login and back afterwards. | W ("menu shows pages I cannot use") | ✅ |
| FR-4 | A member can submit a report (title, category from a fixed list or "Other", description, optional location and evidence link, one PDF up to 5 MB) and follow its status (Pending, Approved, Rejected with reason). | W (category list, clear upload errors) | ✅ |
| FR-5 | An administrator reviews every report before publication. Approved reports are published in the Media Centre and their fingerprint is recorded on the blockchain. | S, E | ✅ |
| FR-6 | Anyone can check on the Verification page whether a published report is recorded on the blockchain, by report ID or title only (not by journalist name). | W, S | ✅ (integrity method to be improved in Batch 3) |
| FR-7 | Members can message only their contacts. Contacts are made by message request with the exact email address; the receiver can accept, decline or block. Nobody can browse or search the list of members. | W (privacy of exiled journalists), R | ✅ |
| FR-8 | A message can carry one image or PDF (max 5 MB). Hidden data (EXIF, GPS) is removed from images, also from profile pictures. | W, R | ✅ (not from PDFs) |
| FR-9 | Every message is checked before delivery. A message that may be sensitive is held until an administrator approves or rejects it; the receiver never sees a rejected message. | S, E | ✅ (rule-based) |
| FR-10 | Unread messages and message requests are shown as numbers on each contact and on "Messaging" in the menu. | W | ✅ |
| FR-11 | Members can delete their account; published reports stay with "Deleted user", other people keep their conversations. | R (GDPR), E | ✅ |
| FR-12 | A User guide, an FAQ, Terms of use and a Privacy notice are available from every page. | W, S | ✅ |
| FR-13 | Partner media centres (for example MiCT, ECHO) get their own administrators who manage membership of their centre only. | S (roles plan) | ❌ planned |

## 2. Non-functional requirements

| ID | Requirement | Category | Source | Status |
|---|---|---|---|---|
| NFR-1 | Personal data is limited to what is needed. No list of members is visible to members; email addresses are never shown to other members. | Privacy | W, R | ✅ |
| NFR-2 | Passwords are stored only as secure hashes; reset links only as SHA-256 hashes, valid for 60 minutes, one use. | Security | R | ✅ |
| NFR-3 | Login and reset requests are limited (5 per email and place in 15 minutes; 100 per place for all emails, so a class on one Wi-Fi is not blocked). | Security | R, W (workshop on one Wi-Fi) | ✅ |
| NFR-4 | Every form is protected against cross-site requests (CSRF); every database query with user input uses prepared statements; all output is escaped. | Security | R | ✅ |
| NFR-5 | Security headers (Content-Security-Policy with a nonce, HSTS on HTTPS, no server version), one session cookie (HttpOnly, SameSite, Secure on HTTPS). No advertising or tracking. | Security, Privacy | R | ✅ |
| NFR-6 | Secrets (API key, AES key, SMTP password, AI keys) are kept in `.env` only, never in code or git. | Security | R | ✅ |
| NFR-7 | Internal services (database, Kafka, Java services, AI service) listen only on 127.0.0.1 or the internal Docker network. | Security | R | ✅ |
| NFR-8 | Every page works at 320, 390, 820 and 1440 px without sideways scrolling; tap targets are at least 44 px. | Accessibility | W (phones in the workshop) | ✅ |
| NFR-9 | Keyboard and screen-reader users can use every page: "Skip to main content", one `main` landmark, visible focus, labelled forms, text contrast of at least 4.5:1. | Accessibility | W, R | ✅ |
| NFR-10 | Movement can be turned off ("reduce motion" is respected). | Accessibility | W | ✅ |
| NFR-11 | Pages respond quickly on a normal connection; the message compliance check adds at most a few seconds (AI check: time limit `AI_TIMEOUT_SECONDS`, default 5 s). | Speed | S, W | 🔶 to measure |
| NFR-12 | Public pages read like a real, professional website (no placeholders or "prototype" wording); Terms and Privacy contain only statements that are true today. | Usability, Legal | S (launch on dissent.ie) | ✅ |
| NFR-13 | The system can be translated (English first, then Farsi with right-to-left layout). | Usability | W | ❌ planned |

## 3. AI requirements (contribution: RAG + LLM compliance check)

| ID | Requirement | Source | Status |
|---|---|---|---|
| AI-1 | The check gives a decision, **ALLOW** or **REVIEW**, and a risk level. | S | ✅ (mock model) |
| AI-2 | The check **explains** its decision in plain words (one reason per risk found). | S (interpretability) | ✅ (mock model) |
| AI-3 | Every reason **cites the law or policy passages** it used (source, country, passage ID and a short quote). A citation must come from the knowledge base; the service removes any citation it did not retrieve. | S | ✅ (sample passages only) |
| AI-4 | The check shows its **ReAct steps** (thought, action, observation) so a reviewer can see how it reached the decision. | S | ✅ |
| AI-5 | **A human administrator makes the final decision.** The AI result is shown as "AI suggestion – the admin decides"; the AI never rejects a message on its own. | S, R | ✅ |
| AI-6 | **Fail safe:** if the AI service is down, too slow, or answers with invalid data, the message is held (REVIEW), exactly like today. | S, R | ✅ |
| AI-7 | **No message text leaves the server** unless the supervisor approves a cloud model. The default model runs inside the DiSSENT server; a cloud model is switched off and needs an explicit setting. | S, R | ✅ |
| AI-8 | The country of the sender and the receiver decides which laws are searched. | S | 🔶 the website does not store a country yet (see decisions) |
| AI-9 | Whether the administrator agreed with the AI is recorded, so the AI can be evaluated with real use. | S (evaluation) | ✅ |
| AI-10 | The AI check can be switched on and off (`AI_ENABLED` in `.env`); when it is off, the current rules are used. | S, E | ✅ |
| AI-11 | The AI is evaluated on the same test set as the current rules (accuracy, precision, recall for REVIEW, false alarms, missed risks, results per category, share of decisions with a citation, speed). | S (current state → gap → contribution) | ✅ test set v1 |
| AI-12 | The service does not write message text to its logs. | R | ✅ |

## 4. Traceability to the research plan

| Research step | Requirements |
|---|---|
| (0) Requirements from the workshops | this document |
| (1) Evaluate the current system | FR-9, AI-11 → `ai/eval/results_baseline.md` |
| (2) Contribution: RAG + LLM (ReAct) check | AI-1 … AI-12 → `ai/`, `ai/eval/results_ai.md`, `ai/eval/compare.md` |

## 5. Open questions for the supervisor

1. Which countries and which laws should the knowledge base contain first (the samples now cover IE, AF, IR, DE, UA)?
2. Local model (for example Ollama on the DiSSENT server) or a cloud model (message text would leave the server: needs approval, a data processing agreement and an update to the Privacy notice)?
3. Should DiSSENT store a country for each member so the right laws are searched? For exiled journalists this is itself sensitive data.
4. Who writes the final test set? The current one (v1) was written together with the mock model, so the mock's scores are optimistic.
