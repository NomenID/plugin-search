# Search

Search published pages from the Pages plugin. The default engine queries the
`pages` table directly, so it works on a fresh install with nothing configured.

Optional engines: Meilisearch, OpenSearch, Elasticsearch. Configure one in
`config/plugins/search.json` (not in this directory — it survives plugin upgrades).

```json
{
    "base_path": "/search",
    "engine": "db",
    "per_page": 10,
    "cache_ttl": 300,
    "meilisearch": {
        "host": "http://127.0.0.1:7700",
        "api_key": "",
        "index": "nomen_pages"
    },
    "opensearch": {
        "host": "http://127.0.0.1:9200",
        "username": "",
        "password": "",
        "index": "nomen_pages"
    },
    "elasticsearch": {
        "host": "http://127.0.0.1:9200",
        "username": "",
        "password": "",
        "index": "nomen_pages"
    }
}
```

`engine` is one of `db` (default), `meilisearch`, `opensearch`, `elasticsearch`.
When an external engine is unreachable the search falls back to the database and
writes one line to the error log — the search box keeps working.

## Indexing

The database engine needs no index. After switching to an external engine, push
the pages once:

```bash
php bin/nomen plugins search:reindex
php bin/nomen plugins search:reindex --fresh   # drop the old documents first
```

Run it after editing pages, or put it on a timer.

## API

`/nomen-api?action=query&list=search&q=install&page=1&limit=10`

## License

Apache-2.0. See `LICENSE`.
