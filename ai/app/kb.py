"""Search in the local vector database (Chroma, embeddings run locally)."""
import os

DB_DIR = os.environ.get('AI_DB_DIR', '/app/data/chroma')
COLLECTION = 'dissent_law'


class KnowledgeBase:
    def __init__(self):
        import chromadb
        from chromadb.config import Settings
        from chromadb.utils.embedding_functions import ONNXMiniLM_L6_V2

        client = chromadb.PersistentClient(path=DB_DIR, settings=Settings(anonymized_telemetry=False))
        self.collection = client.get_collection(COLLECTION, embedding_function=ONNXMiniLM_L6_V2())
        metas = self.collection.get(include=['metadatas'])['metadatas']
        self.countries = sorted({m['country'] for m in metas})
        self.size = len(metas)

    def search(self, query, countries, per_country=1):
        """The best passage(s) for the query in each country. Returns dicts with
        passage_id, country, source, topic, status, text and distance."""
        found = []
        for country in countries:
            if country not in self.countries:
                continue
            res = self.collection.query(query_texts=[query], n_results=per_country,
                                        where={'country': country},
                                        include=['documents', 'metadatas', 'distances'])
            for doc, meta, dist in zip(res['documents'][0], res['metadatas'][0], res['distances'][0]):
                found.append({'passage_id': meta['passage_id'], 'country': meta['country'], 'source': meta['source'],
                              'topic': meta['topic'], 'status': meta['status'], 'text': doc,
                              'distance': round(float(dist), 4)})
        return found
