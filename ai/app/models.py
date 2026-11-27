"""The language model behind the ReAct loop, as one interface with several implementations.

    AI_MODEL=mock    MockModel: rule-based, runs here, used now (default)
    AI_MODEL=ollama  OllamaModel: a local open model through Ollama (stub, switched off)
    AI_MODEL=cloud   CloudModel: a cloud API (stub, refused unless AI_ALLOW_CLOUD=1)

A model never runs tools itself. Each call to next_step() returns either a Step (a thought
and the action to run) or a Final answer; the agent (react.py) runs the action and adds
the observation to the history before asking again.
"""
import os
from dataclasses import dataclass, field

from .mock_rules import find_risks


class ModelUnavailable(Exception):
    """The chosen model cannot be used (not configured, not allowed, or a stub)."""


@dataclass
class Step:
    thought: str
    action: str          # 'identify_countries' or 'search_passages'
    args: dict = field(default_factory=dict)


@dataclass
class Final:
    thought: str
    decision: str        # 'ALLOW' or 'REVIEW'
    risk_level: str      # 'none', 'medium' or 'high'
    reasons: list = field(default_factory=list)   # [{'text': ..., 'passage_ids': [...]}]


class Model:
    name = 'model'

    def next_step(self, task, history):
        """task: dict with text, sender_country, receiver_country, has_attachment.
        history: list of dicts with thought, action, args, observation, data."""
        raise NotImplementedError


# ---------------------------------------------------------------------------
# MockModel: a fixed, explainable policy that follows the same ReAct pattern a
# real model will follow. It lets the whole pipeline (retrieval, citations,
# fail-safe, admin page, evaluation) be built and tested without sending any
# message text to a language model.
# ---------------------------------------------------------------------------

TOPIC = {
    'personal_data': ('personal data', 'sharing personal data of a person such as a phone number, email address, date of birth or ID number',
                      'Personal data about a person should only be shared when it is necessary.'),
    'source_identity': ('protection of sources', 'protecting confidential sources from being named or identified',
                        'A confidential source must not be named or made identifiable.'),
    'location': ('locations and movement', 'exact address, GPS coordinates, shelter, safe house or planned border crossing that can endanger people',
                 'An exact location or a planned movement can put people in danger.'),
    'financial': ('financial information', 'bank account IBAN, payment card number, crypto wallet or payment code',
                  'Bank, card and payment details are sensitive and can be misused.'),
    'security_risk': ('account security', 'sharing passwords, login details or links asking people to log in elsewhere',
                      'Passwords, login details and log-in links must not be shared in messages.'),
    'attachment': ('attachments', 'attachments such as photos and documents that cannot be checked automatically',
                   'The content of a file cannot be checked automatically, so a person should look at it.'),
}
HIGH = {'source_identity', 'location', 'security_risk'}


class MockModel(Model):
    name = 'mock-rules-v1'

    def next_step(self, task, history):
        if not history:
            return Step('I need to know which countries\' rules apply to this message.',
                        'identify_countries',
                        {'sender': task['sender_country'], 'receiver': task['receiver_country']})

        countries = history[0]['data']
        risks = find_risks(task['text'], task['has_attachment'])
        kinds = []
        for kind, _ in risks:
            if kind not in kinds:
                kinds.append(kind)
        searched = {h['args'].get('risk') for h in history if h['action'] == 'search_passages'}

        for kind in kinds:
            if kind not in searched:
                labels = ', '.join(label for k, label in risks if k == kind)
                topic, query, _ = TOPIC[kind]
                where = ', '.join(countries) if countries else 'the available'
                return Step('The message may contain %s. I will look up what the %s rules say about %s.' % (labels, where, topic),
                            'search_passages', {'query': query, 'countries': countries, 'risk': kind})

        if not kinds:
            return Final('I found no personal data, source details, exact locations, financial data, passwords or '
                         'attachments, so the message can be delivered.', 'ALLOW', 'none', [])

        reasons = []
        for kind in kinds:
            labels = ', '.join(label for k, label in risks if k == kind)
            passages = next(h['data'] for h in history if h['action'] == 'search_passages' and h['args'].get('risk') == kind)
            ids = [p['passage_id'] for p in passages]
            reasons.append({'text': 'The message may contain %s. %s' % (labels, TOPIC[kind][2]), 'passage_ids': ids})

        level = 'high' if (set(kinds) & HIGH or len(kinds) >= 3) else 'medium'
        return Final('Each risk is covered by the cited passages, so an administrator should review the message before it is delivered.',
                     'REVIEW', level, reasons)


# ---------------------------------------------------------------------------
# Real models: stubs, switched off by default.
# ---------------------------------------------------------------------------

# The prompt a real model will get (kept here so the design is documented).
REACT_PROMPT = """You check messages between journalists before delivery.
Decide ALLOW or REVIEW. REVIEW when the message may contain personal data, details that
identify a source, an exact location or planned movement, financial data, passwords or
an attachment. You may only cite passages returned by search_passages.
Answer with one JSON object per turn:
  {"thought": "...", "action": "identify_countries" | "search_passages", "args": {...}}
or, when you are done:
  {"thought": "...", "final": {"decision": "ALLOW|REVIEW", "risk_level": "none|medium|high",
   "reasons": [{"text": "...", "passage_ids": ["IE-PD-1"]}]}}
Never repeat personal data from the message in your answer."""


class OllamaModel(Model):
    """A local open model served by Ollama on the DiSSENT server (message text stays on the server).
    Stub: to finish it, send REACT_PROMPT + the task + the history to OLLAMA_URL/api/chat with
    format=json, parse the JSON into Step or Final, and handle time-outs (the agent turns any
    error into REVIEW)."""

    def __init__(self):
        self.url = os.environ.get('OLLAMA_URL', '').strip()
        self.name = 'ollama:' + os.environ.get('OLLAMA_MODEL', 'not-set')
        if not self.url:
            raise ModelUnavailable('AI_MODEL=ollama, but OLLAMA_URL is not set.')

    def next_step(self, task, history):
        raise ModelUnavailable('The Ollama model is a stub and is not implemented yet.')


class CloudModel(Model):
    """A cloud API. Message text would leave the server, so it is refused unless the
    supervisor has approved it and AI_ALLOW_CLOUD=1 is set (with AI_API_KEY in .env)."""

    def __init__(self):
        if os.environ.get('AI_ALLOW_CLOUD', '0') != '1':
            raise ModelUnavailable('Cloud models are switched off (AI_ALLOW_CLOUD is not 1): message text would leave the server.')
        if not os.environ.get('AI_API_KEY', '').strip():
            raise ModelUnavailable('AI_MODEL=cloud, but AI_API_KEY is not set.')
        self.name = 'cloud:' + os.environ.get('AI_CLOUD_MODEL', 'not-set')

    def next_step(self, task, history):
        raise ModelUnavailable('The cloud model is a stub and is not implemented yet.')


def get_model():
    choice = os.environ.get('AI_MODEL', 'mock').strip().lower() or 'mock'
    if choice == 'mock':
        return MockModel()
    if choice == 'ollama':
        return OllamaModel()
    if choice == 'cloud':
        return CloudModel()
    raise ModelUnavailable('Unknown AI_MODEL: %s' % choice)
