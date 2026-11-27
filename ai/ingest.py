"""Builds the local vector database from ai/knowledge/<COUNTRY>/*.md.

Each file is split at its "## <PASSAGE-ID> <topic>" headings. A passage longer than
MAX_WORDS is split again into overlapping chunks (IDs get -a, -b, ...), which is needed
once real legal texts replace the short samples.

Vector database: Chroma (embedded, stored in files; no separate server).
Embeddings: all-MiniLM-L6-v2 run locally with ONNX Runtime (downloaded once when the
Docker image is built). Nothing is sent to another service.

Run inside the container:  python ingest.py   (the service also runs it at start-up)
"""
import os
import re
import sys

KNOWLEDGE_DIR = os.environ.get('AI_KNOWLEDGE_DIR', '/app/knowledge')
DB_DIR = os.environ.get('AI_DB_DIR', '/app/data/chroma')
COLLECTION = 'dissent_law'
MAX_WORDS = 160
OVERLAP_WORDS = 30


def read_header(lines):
    """Returns the header fields (title, source, country, status) and the body lines."""
    header, i = {}, 0
    while i < len(lines) and not lines[i].startswith('## '):
        m = re.match(r'^(\w+):\s*(.*)$', lines[i].strip())
        if m:
            header[m.group(1).lower()] = m.group(2).strip()
        i += 1
    return header, lines[i:]


def split_passages(path, folder_country):
    with open(path, encoding='utf-8') as f:
        header, body = read_header(f.read().splitlines())

    country = header.get('country', folder_country).upper()
    title = header.get('title', os.path.basename(path))
    status = header.get('status', 'UNKNOWN').upper()
    passages, current = [], None

    for line in body:
        m = re.match(r'^##\s+([A-Z]{2}-[A-Z0-9-]+)\s*(.*)$', line)
        if m:
            current = {'id': m.group(1), 'topic': m.group(2).strip(), 'lines': []}
            passages.append(current)
        elif current is not None and line.strip():
            current['lines'].append(line.strip())

    out = []
    for p in passages:
        words = ' '.join(p['lines']).split()
        if not words:
            continue
        if len(words) <= MAX_WORDS:
            chunks = [words]
        else:
            step = MAX_WORDS - OVERLAP_WORDS
            chunks = [words[i:i + MAX_WORDS] for i in range(0, len(words), step)]
        for n, chunk in enumerate(chunks):
            pid = p['id'] if len(chunks) == 1 else '%s-%s' % (p['id'], chr(ord('a') + n))
            out.append({
                'id': pid,
                'text': ' '.join(chunk),
                'meta': {'passage_id': pid, 'country': country, 'topic': p['topic'],
                         'source': title, 'status': status, 'file': os.path.relpath(path, KNOWLEDGE_DIR)},
            })
    return out


def ingest():
    import chromadb
    from chromadb.config import Settings
    from chromadb.utils.embedding_functions import ONNXMiniLM_L6_V2

    passages = []
    for country in sorted(os.listdir(KNOWLEDGE_DIR)):
        folder = os.path.join(KNOWLEDGE_DIR, country)
        if not os.path.isdir(folder) or not re.fullmatch(r'[A-Z]{2}', country):
            continue
        for name in sorted(os.listdir(folder)):
            if name.endswith('.md'):
                passages += split_passages(os.path.join(folder, name), country)

    ids = [p['id'] for p in passages]
    if len(ids) != len(set(ids)):
        sys.exit('Duplicate passage IDs in the knowledge base.')

    client = chromadb.PersistentClient(path=DB_DIR, settings=Settings(anonymized_telemetry=False))
    try:
        client.delete_collection(COLLECTION)
    except Exception:
        pass
    collection = client.create_collection(COLLECTION, embedding_function=ONNXMiniLM_L6_V2(),
                                          metadata={'hnsw:space': 'cosine'})
    if passages:
        collection.add(ids=ids, documents=[p['text'] for p in passages], metadatas=[p['meta'] for p in passages])

    countries = sorted({p['meta']['country'] for p in passages})
    print('Ingested %d passages for %s into %s' % (len(passages), ', '.join(countries) or 'no countries', DB_DIR))
    return len(passages)


if __name__ == '__main__':
    ingest()
