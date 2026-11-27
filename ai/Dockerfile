# DiSSENT AI compliance service (FastAPI + Chroma + local ONNX embeddings).
FROM python:3.11.11-slim-bookworm

ENV PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1 \
    ANONYMIZED_TELEMETRY=False \
    HOME=/home/app \
    AI_KNOWLEDGE_DIR=/app/knowledge \
    AI_DB_DIR=/app/data/chroma

RUN useradd --create-home --home-dir /home/app --uid 10001 app

WORKDIR /app
COPY requirements.txt .
RUN pip install --no-cache-dir --retries 10 --timeout 120 -r requirements.txt
# Chroma 0.5 switches its usage reporting off through posthog's "disabled" flag, which newer
# posthog versions no longer honour. This version honours it, so nothing is sent.
RUN pip install --no-cache-dir --retries 10 --timeout 120 posthog==3.7.4

COPY app ./app
COPY ingest.py .
COPY knowledge ./knowledge
RUN mkdir -p /app/data && chown -R app:app /app/data

USER app

# Download the embedding model (all-MiniLM-L6-v2, ONNX) once, while building.
# At runtime the embeddings are made inside the container; nothing is sent anywhere.
RUN python -c "from chromadb.utils.embedding_functions import ONNXMiniLM_L6_V2 as E; E()(['warm up'])"

EXPOSE 8000

HEALTHCHECK --interval=15s --timeout=5s --start-period=60s --retries=5 \
    CMD python -c "import json,urllib.request; r=json.load(urllib.request.urlopen('http://127.0.0.1:8000/health', timeout=4)); exit(0 if r['passages'] > 0 else 1)"

# Build the vector database from the knowledge files, then start the service.
CMD ["sh", "-c", "python ingest.py && exec uvicorn app.main:app --host 0.0.0.0 --port 8000 --no-server-header --log-level warning"]
