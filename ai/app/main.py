"""DiSSENT AI compliance service (RAG + ReAct). Internal only: the website calls it.

POST /check  {text, sender_country, receiver_country, has_attachment}
  -> {decision, risk_level, reasons, citations, steps, model_name, latency_ms}
GET /health  -> {status, model, passages, countries}

Privacy: the message text is never written to a log, and with the default MockModel it
never leaves this container. Any error gives REVIEW (fail safe).
"""
import logging
import time

from fastapi import FastAPI
from pydantic import BaseModel, Field

from .kb import KnowledgeBase
from .models import ModelUnavailable, get_model
from .react import AgentError, run

log = logging.getLogger('dissent-ai')

app = FastAPI(title='DiSSENT AI compliance check', docs_url=None, redoc_url=None, openapi_url=None)

STATE = {'kb': None, 'model': None, 'model_error': ''}


@app.on_event('startup')
def startup():
    STATE['kb'] = KnowledgeBase()
    try:
        STATE['model'] = get_model()
    except ModelUnavailable as error:
        STATE['model_error'] = str(error)
        log.warning('AI model not available: %s', error)


class CheckRequest(BaseModel):
    text: str = Field(default='', max_length=10000)
    sender_country: str = Field(default='', pattern=r'^([A-Za-z]{2})?$')
    receiver_country: str = Field(default='', pattern=r'^([A-Za-z]{2})?$')
    has_attachment: bool = False


def fail_safe(reason, model_name, started, steps=None):
    return {
        'decision': 'REVIEW',
        'risk_level': 'unknown',
        'reasons': [reason],
        'citations': [],
        'steps': steps or [],
        'model_name': model_name,
        'latency_ms': int((time.perf_counter() - started) * 1000),
    }


@app.get('/health')
def health():
    kb = STATE['kb']
    model = STATE['model']
    return {
        'status': 'ok' if model and kb and kb.size else 'degraded',
        'model': model.name if model else 'unavailable',
        'model_error': STATE['model_error'],
        'passages': kb.size if kb else 0,
        'countries': kb.countries if kb else [],
    }


@app.post('/check')
def check(req: CheckRequest):
    started = time.perf_counter()
    model = STATE['model']

    if model is None:
        return fail_safe('The AI model is not available (%s). A person should review this message.' % STATE['model_error'],
                         'unavailable', started)

    task = {'text': req.text, 'sender_country': req.sender_country.upper(),
            'receiver_country': req.receiver_country.upper(), 'has_attachment': req.has_attachment}
    try:
        result = run(task, model, STATE['kb'])
    except (AgentError, ModelUnavailable) as error:
        return fail_safe('The AI check could not finish (%s). A person should review this message.' % error, model.name, started)
    except Exception as error:  # never log the message text, only the kind of error
        log.error('AI check failed: %s', type(error).__name__)
        return fail_safe('The AI check could not finish. A person should review this message.', model.name, started)

    result['model_name'] = model.name
    result['latency_ms'] = int((time.perf_counter() - started) * 1000)
    return result
