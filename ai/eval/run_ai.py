"""Runs ai/eval/test_messages.jsonl through the AI service and compares it with the baseline.

Writes results_ai.json, results_ai.md and compare.md (baseline vs RAG + mock, with an
empty column for the real model later). The measures are the same as run_baseline.php,
plus the share of decisions with at least one citation and the average latency.

    python ai/eval/run_ai.py                      # service at http://127.0.0.1:8090
    python ai/eval/run_ai.py --url http://127.0.0.1:8090 --label "RAG + mock"

Only uses the Python standard library. The test messages are fictional.
"""
import argparse
import json
import os
import time
import urllib.request
from datetime import datetime, timezone

HERE = os.path.dirname(os.path.abspath(__file__))
ORDER = ['safe', 'personal_data', 'source_identity', 'location', 'financial', 'security_risk', 'borderline']


def check(url, m, timeout):
    body = json.dumps({'text': m['text'], 'sender_country': m['sender_country'], 'receiver_country': m['receiver_country'],
                       'has_attachment': m['has_attachment']}).encode()
    req = urllib.request.Request(url.rstrip('/') + '/check', data=body, headers={'Content-Type': 'application/json'})
    started = time.perf_counter()
    try:
        with urllib.request.urlopen(req, timeout=timeout) as r:
            data = json.load(r)
    except Exception as error:  # the website would hold the message: REVIEW
        data = {'decision': 'REVIEW', 'risk_level': 'unknown', 'reasons': ['service error: %s' % type(error).__name__],
                'citations': [], 'steps': [], 'model_name': 'unavailable', 'latency_ms': None}
    data['_round_trip_ms'] = (time.perf_counter() - started) * 1000
    return data


def metrics(results):
    tp = fp = tn = fn = 0
    cats = {}
    for r in results:
        e, p = r['expected'] == 'REVIEW', r['predicted'] == 'REVIEW'
        tp += e and p; fp += (not e) and p; tn += (not e) and (not p); fn += e and (not p)
        c = cats.setdefault(r['category'], {'n': 0, 'correct': 0, 'held': 0})
        c['n'] += 1; c['correct'] += r['correct']; c['held'] += p
    n = len(results)
    ratio = lambda a, b: a / b if b else 0.0
    return {
        'n': n, 'tp': tp, 'fp': fp, 'tn': tn, 'fn': fn,
        'accuracy': ratio(tp + tn, n), 'precision': ratio(tp, tp + fp), 'recall': ratio(tp, tp + fn),
        'false_alarm_rate': ratio(fp, fp + tn),
        'missed_risks': [r['id'] for r in results if r['expected'] == 'REVIEW' and r['predicted'] != 'REVIEW'],
        'false_alarms': [r['id'] for r in results if r['expected'] == 'ALLOW' and r['predicted'] == 'REVIEW'],
        'with_citation': ratio(sum(1 for r in results if r['citations'] > 0), n),
        'review_with_citation': ratio(sum(1 for r in results if r['predicted'] == 'REVIEW' and r['citations'] > 0),
                                      sum(1 for r in results if r['predicted'] == 'REVIEW')),
        'avg_latency_ms': ratio(sum(r['latency_ms'] for r in results), n),
        'per_category': cats,
    }


pct = lambda x: '%.1f %%' % (100 * x)


def markdown(title, intro, m, results):
    md = ['# %s' % title, '', intro, '',
          'Run: %s UTC. Positive class = REVIEW (the message should be held).' % datetime.now(timezone.utc).strftime('%d/%m/%Y %H:%M'), '',
          '| Measure | Value |', '|---|---|',
          '| Messages | %d |' % m['n'], '| Accuracy | %s |' % pct(m['accuracy']),
          '| Precision (REVIEW) | %s |' % pct(m['precision']), '| Recall (REVIEW) | %s |' % pct(m['recall']),
          '| False alarm rate (safe messages held) | %s |' % pct(m['false_alarm_rate']),
          '| Missed risks (should be held, were allowed) | %d |' % m['fn'],
          '| Decisions with at least one citation | %s |' % pct(m['with_citation']),
          '| REVIEW decisions with at least one citation | %s |' % pct(m['review_with_citation']),
          '| Average time per message | %.1f ms |' % m['avg_latency_ms'], '',
          'Confusion matrix: TP %d, FP %d, TN %d, FN %d.' % (m['tp'], m['fp'], m['tn'], m['fn']), '',
          '## Per category', '', '| Category | Messages | Correct | Held (REVIEW) |', '|---|---|---|---|']
    for c in ORDER:
        if c in m['per_category']:
            x = m['per_category'][c]
            md.append('| %s | %d | %d (%s) | %d |' % (c, x['n'], x['correct'], pct(x['correct'] / x['n']), x['held']))
    byid = {r['id']: r for r in results}
    md += ['', '## Missed risks', ''] + (['- %s (%s)' % (i, byid[i]['category']) for i in m['missed_risks']] or ['None.'])
    md += ['', '## False alarms', ''] + (['- %s (%s): %s' % (i, byid[i]['category'], '; '.join(byid[i]['reasons'])[:200]) for i in m['false_alarms']] or ['None.'])
    return '\n'.join(md) + '\n'


def compare(base, ai, ai_label):
    rows = [('Accuracy', 'accuracy', pct), ('Precision (REVIEW)', 'precision', pct), ('Recall (REVIEW)', 'recall', pct),
            ('False alarm rate', 'false_alarm_rate', pct), ('Missed risks', 'fn', str),
            ('Decisions with at least one citation', 'with_citation', pct),
            ('Average time per message', 'avg_latency_ms', lambda v: '%.2f ms' % v)]
    md = ['# Comparison: current rules vs RAG + LLM (ReAct)', '',
          'Same test set (`test_messages.jsonl`, %d fictional messages), same measures. Positive class = REVIEW.' % ai['n'], '',
          '| Measure | Baseline (rules-v1) | %s | Real model (later) |' % ai_label, '|---|---|---|---|']
    for name, key, fmt in rows:
        md.append('| %s | %s | %s | |' % (name, fmt(base[key]), fmt(ai[key])))
    md += ['', '## Correct per category', '', '| Category | Messages | Baseline | %s | Real model (later) |' % ai_label, '|---|---|---|---|---|']
    for c in ORDER:
        b, a = base['per_category'].get(c), ai['per_category'].get(c)
        if b and a:
            md.append('| %s | %d | %d | %d | |' % (c, b['n'], b['correct'], a['correct']))
    md += ['', '## How to read this', '',
           '- **The mock is not the contribution yet.** It is a rule-based stand-in that follows the same ReAct steps '
           '(countries, search, decide, explain) so the pipeline, citations, fail-safe and admin page can be built and '
           'tested without sending message text to a language model.',
           '- **Its scores are optimistic.** The mock\'s patterns and this test set were written by the same person, so '
           'the mock has effectively seen the test. For the dissertation, use a held-out test set written by others '
           '(for example from the workshops) and keep this one for development.',
           '- **The citations are SAMPLE passages**, not real law (see `ai/knowledge/README.md`).',
           '- The baseline time is the PHP rules alone; the AI time includes the HTTP call and the vector search.',
           '- Fill in the last column by setting `AI_MODEL` to the real model and running `python ai/eval/run_ai.py --label "Real model"`.']
    return '\n'.join(md) + '\n'


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('--url', default=os.environ.get('AI_EVAL_URL', 'http://127.0.0.1:8090'))
    ap.add_argument('--label', default='RAG + mock')
    ap.add_argument('--timeout', type=float, default=10)
    args = ap.parse_args()

    with open(os.path.join(HERE, 'test_messages.jsonl'), encoding='utf-8') as f:
        messages = [json.loads(line) for line in f if line.strip()]

    health = json.load(urllib.request.urlopen(args.url.rstrip('/') + '/health', timeout=5))
    results = []
    for m in messages:
        d = check(args.url, m, args.timeout)
        results.append({'id': m['id'], 'category': m['category'], 'expected': m['expected'], 'predicted': d['decision'],
                        'correct': d['decision'] == m['expected'], 'risk_level': d.get('risk_level'),
                        'reasons': d.get('reasons', []), 'citations': len(d.get('citations', [])),
                        'cited_passages': [c['passage_id'] for c in d.get('citations', [])],
                        'steps': len(d.get('steps', [])), 'model_name': d.get('model_name'),
                        'latency_ms': round(d['_round_trip_ms'], 2), 'service_latency_ms': d.get('latency_ms')})

    m = metrics(results)
    with open(os.path.join(HERE, 'results_ai.json'), 'w', encoding='utf-8', newline='\n') as f:
        json.dump({'system': '%s (%s)' % (args.label, health.get('model')), 'test_set': 'ai/eval/test_messages.jsonl',
                   'service': {'model': health.get('model'), 'passages': health.get('passages'), 'countries': health.get('countries')},
                   'run_at': datetime.now(timezone.utc).strftime('%Y-%m-%d %H:%M:%S UTC'), 'metrics': m, 'results': results},
                  f, indent=2, ensure_ascii=False)
    with open(os.path.join(HERE, 'results_ai.md'), 'w', encoding='utf-8', newline='\n') as f:
        f.write(markdown('AI check: %s (%s)' % (args.label, health.get('model')),
                         'Every message in `test_messages.jsonl` was sent to the AI service (`POST /check`). Knowledge base: %s passages '
                         '(%s), SAMPLE summaries only. Latency is measured from the client (HTTP round trip).' % (
                             health.get('passages'), ', '.join(health.get('countries', []))), m, results))

    with open(os.path.join(HERE, 'results_baseline.json'), encoding='utf-8') as f:
        base = json.load(f)['metrics']
    with open(os.path.join(HERE, 'compare.md'), 'w', encoding='utf-8', newline='\n') as f:
        f.write(compare(base, m, args.label))

    print('%s: %d messages, accuracy %s, precision %s, recall %s, false alarms %s, missed %d, with citation %s, avg %.1f ms' % (
        args.label, m['n'], pct(m['accuracy']), pct(m['precision']), pct(m['recall']), pct(m['false_alarm_rate']), m['fn'],
        pct(m['with_citation']), m['avg_latency_ms']))


if __name__ == '__main__':
    main()
