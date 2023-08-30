## Launching Solr in Docker

```rust
docker run -d -p 8983:8983 \
--env SOLR_JETTY_HOST="0.0.0.0" \
--env SOLR_LOG_LEVEL="WARN" \
--env SOLR_SECURITY_MANAGER_ENABLED="false" \
--env SOLR_REQUESTLOG_ENABLED="false" \
--name solr solr:9.2 \
solr-precreate gettingstarted
```

## Indexing documents from JSON file

```bash
curl 'https://raw.githubusercontent.com/dizzu/solr_workshop/master/books.json' > books.json
```
```bash
curl 'http://localhost:8983/solr/gettingstarted/update?commit=true' --data-binary @books.json -H 'Content-type:application/json'
