"""The ReAct loop: identify countries -> search passages for each risk -> decide -> explain.

The model proposes (thought, action); this agent runs the action, records the observation
and asks the model again, until it gives a final answer. The agent then checks the answer:
- the decision must be ALLOW or REVIEW, and REVIEW needs at least one reason;
- a citation is kept only if that passage was really retrieved in this run, so the model
  cannot invent law passages.
Any problem raises AgentError, and the service answers REVIEW (fail safe).
"""
import re

from .models import Final, Step

MAX_STEPS = 12
QUOTE_CHARS = 180


class AgentError(Exception):
    pass


def tool_identify_countries(args, kb):
    wanted = []
    for key in ('sender', 'receiver'):
        code = (args.get(key) or '').strip().upper()
        if re.fullmatch(r'[A-Z]{2}', code) and code not in wanted:
            wanted.append(code)
    known = [c for c in wanted if c in kb.countries]
    if known:
        missing = [c for c in wanted if c not in kb.countries]
        text = 'Sender: %s, receiver: %s. Searching %s.' % (args.get('sender') or 'unknown', args.get('receiver') or 'unknown', ', '.join(known))
        if missing:
            text += ' No documents yet for %s.' % ', '.join(missing)
        return text, known
    return 'Countries not given or not in the knowledge base: searching all countries (%s).' % ', '.join(kb.countries), list(kb.countries)


def tool_search_passages(args, kb):
    countries = [c for c in args.get('countries') or [] if c in kb.countries] or list(kb.countries)
    passages = kb.search(str(args.get('query', ''))[:300], countries)
    if not passages:
        return 'No passages found.', []
    text = '; '.join('%s (%s, %s): "%s"' % (p['passage_id'], p['country'], p['status'], short(p['text'], 90)) for p in passages)
    return text, passages


TOOLS = {'identify_countries': tool_identify_countries, 'search_passages': tool_search_passages}


def short(text, limit):
    text = ' '.join(text.split())
    return text if len(text) <= limit else text[:limit].rsplit(' ', 1)[0] + '…'


def run(task, model, kb):
    history, steps, retrieved = [], [], {}

    for _ in range(MAX_STEPS):
        out = model.next_step(task, history)

        if isinstance(out, Final):
            steps.append({'thought': out.thought, 'action': 'final_answer',
                          'observation': '%s (risk: %s)' % (out.decision, out.risk_level)})
            return finish(out, retrieved, steps)

        if not isinstance(out, Step) or out.action not in TOOLS:
            raise AgentError('The model asked for an unknown action.')

        observation, data = TOOLS[out.action](out.args, kb)
        if out.action == 'search_passages':
            for p in data:
                retrieved[p['passage_id']] = p

        history.append({'thought': out.thought, 'action': out.action, 'args': out.args,
                        'observation': observation, 'data': data})
        shown_args = {k: v for k, v in out.args.items() if k != 'risk'}
        steps.append({'thought': out.thought,
                      'action': '%s(%s)' % (out.action, ', '.join('%s=%s' % (k, v) for k, v in shown_args.items())),
                      'observation': observation})

    raise AgentError('The model did not finish within %d steps.' % MAX_STEPS)


def finish(final, retrieved, steps):
    if final.decision not in ('ALLOW', 'REVIEW'):
        raise AgentError('Invalid decision.')
    if final.risk_level not in ('none', 'low', 'medium', 'high'):
        raise AgentError('Invalid risk level.')

    reasons, citations, cited = [], [], []
    for reason in final.reasons:
        ids = [i for i in reason.get('passage_ids', []) if i in retrieved]   # only passages really retrieved
        text = str(reason.get('text', '')).strip()
        if not text:
            continue
        reasons.append('%s (see %s)' % (text, ', '.join(ids)) if ids else text)
        for i in ids:
            if i not in cited:
                cited.append(i)

    for i in cited:
        p = retrieved[i]
        citations.append({'source': p['source'], 'country': p['country'], 'passage_id': i, 'quote': short(p['text'], QUOTE_CHARS)})

    if final.decision == 'REVIEW' and not reasons:
        raise AgentError('REVIEW without a reason.')
    if final.decision == 'ALLOW':
        reasons = reasons or ['No personal data, source details, exact locations, financial data, passwords or attachments were found.']

    return {'decision': final.decision, 'risk_level': final.risk_level, 'reasons': reasons,
            'citations': citations, 'steps': steps}
