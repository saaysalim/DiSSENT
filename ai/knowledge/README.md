# Knowledge base for the AI compliance check

One folder per country (ISO code). Each file holds short passages that the AI check
searches and cites. `ai/ingest.py` splits every file at the `## ` headings into passages
and stores them in the local vector database.

## Status: SAMPLE ONLY

**Every file here is a SAMPLE summary written for testing the pipeline. None of it is real
legal text, and none of it is legal advice.** Each passage ID starts with the country code
and every file says `status: SAMPLE` at the top, so a sample can never be mistaken for law.

## File format

```
title: <short title>
source: <where the real text will come from>
country: <ISO code>
status: SAMPLE

## <PASSAGE-ID> <topic>
<one short paragraph>
```

## Where the real documents should come from (to confirm with the supervisor)

The list below names *candidate* sources to check. Which laws are used, and whether
their official texts may be stored and quoted, must be agreed with the supervisor.

| Country | Candidate sources | Notes |
|---|---|---|
| IE (Ireland) | EU General Data Protection Regulation (GDPR); Data Protection Act 2018 | Official texts: EUR-Lex and the Irish Statute Book |
| DE (Germany) | GDPR; Federal Data Protection Act (BDSG) | Official texts: EUR-Lex and gesetze-im-internet.de |
| UA (Ukraine) | Law of Ukraine "On Personal Data Protection" | Official text: Verkhovna Rada website; check martial-law changes |
| IR (Iran) | Computer Crimes Law and other relevant laws | No general data protection law; legal advice needed on what to cite |
| AF (Afghanistan) | Laws in force are unclear since 2021 | Legal advice needed; journalist-safety guidance may be more useful than law |

Journalist ethics and source-protection guidance (for example from press councils or
press-freedom organisations) could be added as an extra folder once permission to
quote it is confirmed.

## Adding or changing documents

1. Put the file in the right country folder, in the format above.
2. Run the ingestion again: `docker compose restart ai` (the service ingests at start-up),
   or `docker compose exec ai python ingest.py`.
3. Run `python ai/eval/run_ai.py` to see how the change affects the results.
