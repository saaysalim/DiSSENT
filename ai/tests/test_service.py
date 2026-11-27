"""Tests for the AI service (run from the host while dissent-ai is running):

    python ai/tests/test_service.py [--url http://127.0.0.1:8090]

Standard library only. Uses the fictional test set in ai/eval/test_messages.jsonl.
"""
import argparse
import json
import os
import re
import subprocess
import sys
import urllib.error
import urllib.request

HERE = os.path.dirname(os.path.abspath(__file__))
RESULTS = []


def ok(name, cond, detail=''):
    RESULTS.append(bool(cond))
    print(('PASS ' if cond else 'FAIL ') + name + ('' if cond else '  -> %s' % (detail,)))


def post(url, payload, raw=False):
    data = payload if raw else json.dumps(payload).encode()
    req = urllib.request.Request(url + '/check', data=data, headers={'Content-Type': 'application/json'})
    try:
        with urllib.request.urlopen(req, timeout=15) as r:
            return r.status, json.load(r)
    except urllib.error.HTTPError as e:
        return e.code, None


def valid_shape(d):
    if not isinstance(d, dict) or d.get('decision') not in ('ALLOW', 'REVIEW'):
        return 'decision'
    for key, kind in (('risk_level', str), ('reasons', list), ('citations', list), ('steps', list), ('model_name', str), ('latency_ms', int)):
        if not isinstance(d.get(key), kind):
            return key
    if d['decision'] == 'REVIEW' and not d['reasons']:
        return 'REVIEW without reasons'
    for c in d['citations']:
        if set(c) != {'source', 'country', 'passage_id', 'quote'} or not all(isinstance(v, str) and v for v in c.values()):
            return 'citation fields'
        if not c['passage_id'].startswith(c['country'] + '-'):
            return 'citation country does not match the passage'
    for s in d['steps']:
        if set(s) != {'thought', 'action', 'observation'} or not all(isinstance(v, str) for v in s.values()):
            return 'step fields'
    return ''


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('--url', default='http://127.0.0.1:8090')
    url = ap.parse_args().url.rstrip('/')

    health = json.load(urllib.request.urlopen(url + '/health', timeout=5))
    ok('health: ok, mock model, 30 sample passages for 5 countries',
       health['status'] == 'ok' and health['model'] == 'mock-rules-v1' and health['passages'] == 30
       and health['countries'] == ['AF', 'DE', 'IE', 'IR', 'UA'], health)

    with open(os.path.join(HERE, '..', 'eval', 'test_messages.jsonl'), encoding='utf-8') as f:
        messages = [json.loads(line) for line in f if line.strip()]

    bad_shape, bad_order, leaks, uncited, wrong_country = [], [], [], [], []
    for m in messages:
        status, d = post(url, {k: m[k] for k in ('text', 'sender_country', 'receiver_country', 'has_attachment')})
        problem = 'HTTP %s' % status if status != 200 else valid_shape(d)
        if problem:
            bad_shape.append((m['id'], problem)); continue
        actions = [s['action'].split('(')[0] for s in d['steps']]
        if not actions or actions[0] != 'identify_countries' or actions[-1] != 'final_answer':
            bad_order.append(m['id'])
        if d['decision'] == 'REVIEW' and not d['citations']:
            uncited.append(m['id'])
        if any(c['country'] not in (m['sender_country'], m['receiver_country']) for c in d['citations']):
            wrong_country.append(m['id'])
        # Privacy: the answer must not repeat personal data from the message.
        answer = json.dumps({k: d[k] for k in ('reasons', 'steps', 'citations')}, ensure_ascii=False)
        secrets = re.findall(r'[\w.+-]+@[\w.-]+|\d[\d ]{6,}\d|password \S+|Winter2026!|Herat#1234|Rahimi|Leila|Serhii', m['text'])
        if any(s in answer for s in secrets):
            leaks.append(m['id'])

    ok('all %d test messages: HTTP 200 and valid JSON (decision, risk_level, reasons, citations, steps, model_name, latency_ms)' % len(messages), not bad_shape, bad_shape)
    ok('ReAct trace starts with identify_countries and ends with final_answer', not bad_order, bad_order)
    ok('every REVIEW decision cites at least one passage', not uncited, uncited)
    ok('citations come only from the sender\'s or receiver\'s country', not wrong_country, wrong_country)
    ok('the answer never repeats personal data from the message (numbers, emails, passwords, names)', not leaks, leaks)

    status, d = post(url, {'text': 'Our source is Ahmad Rahimi, he works in the ministry.', 'sender_country': '', 'receiver_country': ''})
    ok('no countries given: searches all countries and says so', status == 200 and 'searching all countries' in d['steps'][0]['observation'] and len({c['country'] for c in d['citations']}) == 5, d and d['steps'][:1])
    status, d = post(url, {'text': 'Call +353 85 555 0101', 'sender_country': 'FR', 'receiver_country': 'IE'})
    ok('a country without documents is named; the other country is searched', status == 200 and 'No documents yet for FR' in d['steps'][0]['observation'] and all(c['country'] == 'IE' for c in d['citations']), d and d['steps'][:1])
    status, d = post(url, {'text': 'Thanks for the edits!', 'sender_country': 'IE', 'receiver_country': 'DE'})
    ok('a safe message: ALLOW, risk none, an explanation, no citation needed', status == 200 and d['decision'] == 'ALLOW' and d['risk_level'] == 'none' and d['reasons'] and not d['citations'], d)

    ok('input check: text over 10,000 characters is refused (422)', post(url, {'text': 'x' * 10001})[0] == 422)
    ok('input check: a country code that is not two letters is refused (422)', post(url, {'text': 'hi', 'sender_country': 'IRL'})[0] == 422)
    ok('input check: a body that is not JSON is refused (422)', post(url, b'not json', raw=True)[0] == 422)
    try:
        urllib.request.urlopen(url + '/docs', timeout=5); docs = 200
    except urllib.error.HTTPError as e:
        docs = e.code
    ok('no public API documentation pages (/docs is 404)', docs == 404, docs)

    # Inside the container: the cloud model is refused, and a broken model gives REVIEW, not ALLOW.
    script = r'''
import os
from app.models import get_model, ModelUnavailable, Step
from app.kb import KnowledgeBase
from app.react import run, AgentError
from app import main
os.environ['AI_MODEL'] = 'cloud'; os.environ['AI_ALLOW_CLOUD'] = '0'
try:
    get_model(); print('cloud: allowed')
except ModelUnavailable as e:
    print('cloud: refused')
os.environ['AI_MODEL'] = 'ollama'; os.environ['OLLAMA_URL'] = ''
try:
    get_model(); print('ollama: allowed')
except ModelUnavailable:
    print('ollama: refused')
class Broken:
    name = 'broken'
    def next_step(self, task, history):
        return Step('I will do something odd.', 'delete_everything', {})
class Liar:
    name = 'liar'
    def next_step(self, task, history):
        from app.models import Final
        return Final('done', 'REVIEW', 'high', [{'text': 'Invented law.', 'passage_ids': ['IE-FAKE-9']}])
kb = KnowledgeBase()
task = {'text': 'hello', 'sender_country': 'IE', 'receiver_country': 'DE', 'has_attachment': False}
try:
    run(task, Broken(), kb); print('broken: no error')
except AgentError:
    print('broken: AgentError')
r = run(task, Liar(), kb); print('liar citations:', len(r['citations']))
main.STATE['kb'] = kb; main.STATE['model'] = Broken()
print('service with broken model:', main.check(main.CheckRequest(text='hello'))['decision'])
'''
    out = subprocess.run(['docker', 'exec', '-i', '-w', '/app', 'dissent-ai', 'python', '-'], input=script, capture_output=True, text=True).stdout
    ok('cloud model refused while AI_ALLOW_CLOUD is not 1; Ollama refused without OLLAMA_URL', 'cloud: refused' in out and 'ollama: refused' in out, out)
    ok('a model asking for an unknown action stops the agent; the service then answers REVIEW', 'broken: AgentError' in out and 'service with broken model: REVIEW' in out, out)
    ok('a citation the model invents (not retrieved) is removed', 'liar citations: 0' in out, out)

    print('%d/%d passed' % (sum(RESULTS), len(RESULTS)))
    sys.exit(0 if all(RESULTS) else 1)


if __name__ == '__main__':
    main()
