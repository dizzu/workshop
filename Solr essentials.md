# Learn to use Solr as a search engine

<!-- @import "[TOC]" {cmd="toc" depthFrom=1 depthTo=6 orderedList=false} -->

<!-- code_chunk_output -->

- [Learn to use Solr as a search engine](#learn-to-use-solr-as-a-search-engine)
  - [Launching Solr in Docker](#launching-solr-in-docker)
  - [Indexing documents from JSON file](#indexing-documents-from-json-file)
  - [Lucene search is a bit different from what you're used to](#lucene-search-is-a-bit-different-from-what-youre-used-to)
  - [What are we looking for / the `q` (a.k.a `query`) parameter](#what-are-we-looking-for--the-q-aka-query-parameter)
  - [Where are we searching / the `qf` (a.k.a `query fields`) parameter](#where-are-we-searching--the-qf-aka-query-fields-parameter)
  - [What documents are we searching in / the `fq` (a.k.a `filter query`) parameter](#what-documents-are-we-searching-in--the-fq-aka-filter-query-parameter)
  - [How many words should match / the `mm` (a.k.a `minimum match`) parameter](#how-many-words-should-match--the-mm-aka-minimum-match-parameter)
  - [Where should we boost phrases / the `pf` (a.k.a `phrase fields`) parameter](#where-should-we-boost-phrases--the-pf-aka-phrase-fields-parameter)
  - [How should we sort the results / the `sort` parameter](#how-should-we-sort-the-results--the-sort-parameter)
  - [What fields should we return / the `fl` (a.k.a `field list`) parameter](#what-fields-should-we-return--the-fl-aka-field-list-parameter)
  - [How should we paginate the results / the `start` and `rows` parameters](#how-should-we-paginate-the-results--the-start-and-rows-parameters)
  - [How should we boost documents / the `boost` and `bf` parameters](#how-should-we-boost-documents--the-boost-and-bf-parameters)
    - [Using function queries](#using-function-queries)
  - [Grouping results / the `group` parameter and the `collapse` query parser](#grouping-results--the-group-parameter-and-the-collapse-query-parser)
  - [Summary of the query parameters](#summary-of-the-query-parameters)
  - [Faceting / the `json.facet` parameter](#faceting--the-jsonfacet-parameter)
    - [Terms facet / the `terms` facet type](#terms-facet--the-terms-facet-type)
    - [Range facet / the `range` facet type](#range-facet--the-range-facet-type)
  - [Highlighting / the `hl` parameter](#highlighting--the-hl-parameter)
  - [Split on whitespace (`sow`) - term centric vs field centric](#split-on-whitespace-sow---term-centric-vs-field-centric)
  - [schema.xml - dynamic fields](#schemaxml---dynamic-fields)
  - [schema.xml - copy fields](#schemaxml---copy-fields)

<!-- /code_chunk_output -->

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
```

## Lucene search is a bit different from what you're used to

Searching in Solr (or other Lucene based search engines) is quite different from searching in MySQL. The main difference is that in MySQL, you search in columns, while in Solr, you search in fields. So you need to think about the fields you want to search in and how you want to search in them.

For example, let's say we have a table called `documents` with the following columns:
`id: int`, `title: varchar`, `author: varchar`, `publisher: varchar`, `subjects: varchar`, `price: float`

So, you might start like you're used to with MySQL:

```sql
SELECT * FROM `documents` WHERE `author` LIKE '%John%'; # substring search
```

or using FULLTEXT indexes:
 
```sql
SELECT * FROM `documents` WHERE MATCH (`author`) AGAINST ('John'); # word search
```

## What are we looking for / the `q` (a.k.a `query`) parameter

The `q` parameter is the main query parameter. It's the *only* required parameter. It's used to specify the query string to search for. It can contain any combination of search terms, operators, and field specifications. If no field is specified, the default field is searched. The default field is determined by the `df` (a.k.a `default field`) parameter in the request handler definition in `solrconfig.xml`. Usually, the default field for the `select` request handler is `text` (which is a copy field that contains the content of all the other fields).

So the above SQL queries would be equivalent to:

```rust
q = author_txt_en: *John* // substring search
```
or 
```rust
q = author_txt_en: John // word search
```
Link: [search in author](http://localhost:8983/solr/gettingstarted/select?q=author_txt_en:John)
***

What if we want to search for John in both author and title fields? 

```sql
SELECT * FROM `documents` WHERE `author` LIKE '%John%' OR `title` LIKE '%John%';
```

which is equivalent to:

```rust
q = author_txt_en: John OR title_txt_en: John
```
Link: [search in author and title](http://localhost:8983/solr/gettingstarted/select?q=author_txt_en:John%20OR%20title_txt_en:John)
***

We can also search for John in the author and title, and boost the author (using Lucene syntax):

```rust
q = author_txt_en: John^2 OR title_txt_en: John
```
Link: [search in author and title using boost](http://localhost:8983/solr/gettingstarted/select?q=author_txt_en:John%5E2%20OR%20title_txt_en:John)
***

## Where are we searching / the `qf` (a.k.a `query fields`) parameter

What are query parsers?
Query parsers are used to parse the query string into a query object. The query object is then used to perform the search. The query parser is specified by the `defType` (a.k.a `default type`) parameter. The default query parser is the `lucene` query parser. The `lucene` query parser is a simple query parser that only supports the Lucene query syntax. You might be familiar with the Lucene query syntax if you've used Lucene or Elasticsearch before (for example Kibana uses Elasticsearch which uses Lucene under the hood). The Lucene query syntax is a powerful syntax that allows you to specify the query string in a very flexible way.

But we can do way better using the `edismax` query parser and the `qf` (a.k.a `query fields`) parameter. `qf` is a list of fields and boosts that specify which fields to search in and how much to boost them. It consists of a list of field names and boosts in the format `field_name^boost` separated by spaces. The default boost is 1.0. The `qf` parameter is only used by the `edismax` query parser (and `dismax` but we don't really need it anymore).

The `dismax` and `edismax` (a.k.a. `enhanced dismax`) query parsers are ***recommended*** if you're using the search capabilities of Solr. They provide advanced features that are not available in the standard query parser, like the ability to influence the score of a query, support for phrase queries, and advanced settings for controlling how the query is parsed and enhanced.

```rust
q = author_txt_en: John OR title_txt_en: John &
defType = edismax &
qf = author_txt_en^2 title_txt_en
```
Link: [search in author and title using edismax](http://localhost:8983/solr/gettingstarted/select?q=author_txt_en:John%20OR%20title_txt_en:John&defType=edismax&qf=author_txt_en%5E2%20title_txt_en)
***

We need to find a good balance between the boosts for the different fields. This is where a good understanding of the data, user behaviour and the search engine comes in handy. For example, if we know that the author is more important than the title, we can boost the author more:

```rust
q = John Football &
defType = edismax &
qf = author_txt_en^10 title_txt_en^5 publisher_txt_en^2 subjects_txt
```
Link: [search in all fields using edismax](http://localhost:8983/solr/gettingstarted/select?q=John%20Football&defType=edismax&qf=author_txt_en%5E10%20title_txt_en%5E5%20publisher_txt_en%5E2%20subjects_txt)
***

## What documents are we searching in / the `fq` (a.k.a `filter query`) parameter

What if we want to show only the books from a certain publisher? We can use the `fq` (a.k.a `filter query`) parameter:

```rust
q = John Football &
defType = edismax &
qf = author_txt_en^10 title_txt_en^5 publisher_txt_en^2 subjects_txt &
fq = publisher_txt_en: Boston
```
Link: [search in all fields using edismax and filter on publisher](http://localhost:8983/solr/gettingstarted/select?q=John%20Football&defType=edismax&qf=author_txt_en%5E10%20title_txt_en%5E5%20publisher_txt_en%5E2%20subjects_txt&fq=publisher_txt_en:Boston)

***Important note***: the `fq` parameter is not used in scoring, so it's highly recommended to use it for filtering, as it will greatly improve performance.

We can add multiple filter queries:

```rust
q = John Football &
defType = edismax &
qf = author_txt_en^10 title_txt_en^5 publisher_txt_en^2 subjects_txt &
fq = publisher_txt_en: Boston &
fq = price_f: [10 TO 100]
```
Link: [search in all fields using edismax and filter on publisher and price](http://localhost:8983/solr/gettingstarted/select?q=John%20Football&defType=edismax&qf=author_txt_en%5E10%20title_txt_en%5E5%20publisher_txt_en%5E2%20subjects_txt&fq=publisher_txt_en:Boston&fq=price_f:%5B10%20TO%20100%5D)

Not that the syntax for adding multiple filter queries may not be what you're used to, because fq is a multivalued parameter, so we can add multiple values for the same parameter.

***

## How many words should match / the `mm` (a.k.a `minimum match`) parameter

But now, in the smaller result set, there's no match for the ```Football``` part of the query. This is because the by default the `mm` (a.k.a `minimum match`) parameter is set to `0%` when q.op is set to `OR` (which is the default) and `100%` when q.op is set to `AND`. We can set the `q.op` parameter to `AND`:

```rust
q = John Football &
defType = edismax &
qf = author_txt_en^10 title_txt_en^5 publisher_txt_en^2 subjects_txt &
fq = publisher_txt_en: Boston &
q.op = AND
```
Link: [search in all fields using edismax and filter on publisher using AND](http://localhost:8983/solr/gettingstarted/select?q=John%20Football&defType=edismax&qf=author_txt_en%5E10%20title_txt_en%5E5%20publisher_txt_en%5E2%20subjects_txt&fq=publisher_txt_en:Boston&q.op=AND)
***

Now we don't have any matches because the `mm` parameter is set to `100%` by default when `q.op` is set to `AND`. We can set the `mm` parameter to `50%`:

```rust
q = John Football &
defType = edismax &
qf = author_txt_en^10 title_txt_en^5 publisher_txt_en^2 subjects_txt &
fq = publisher_txt_en: Boston &
mm = 50%
```
Link: [search in all fields using edismax and filter on publisher using AND and mm](http://localhost:8983/solr/gettingstarted/select?q=John%20Football&defType=edismax&qf=author_txt_en%5E10%20title_txt_en%5E5%20publisher_txt_en%5E2%20subjects_txt&fq=publisher_txt_en:Boston&mm=50%)
***

`mm` can also be used to find documents that match at least 2 terms and at most 75% of the terms if the number of terms is greater than 2:

```rust
q = John Football &
defType = edismax &
qf = author_txt_en^10 title_txt_en^5 publisher_txt_en^2 subjects_txt &
mm = 2<75%
```
Link: [search in all fields using edismax and mm](http://localhost:8983/solr/gettingstarted/select?q=John%20Football&defType=edismax&qf=author_txt_en%5E10%20title_txt_en%5E5%20publisher_txt_en%5E2%20subjects_txt&mm=2%3C75%25)
***

## Where should we boost phrases / the `pf` (a.k.a `phrase fields`) parameter

We can also use the `pf` (a.k.a `phrase fields`) parameter to boost documents that contain the terms in close proximity.
The `pf` parameter is similar to the `qf` parameter, but it is used to boost the score of documents in cases when all of the terms in the q parameter appear in close proximity. The `pf` parameter can be used to implement phrase searching. It takes a list of fields and boosts in the format field_name^boost separated by spaces. The default boost is 1.0.

```rust
q = complete works &
defType = edismax &
qf = author_txt_en^10 title_txt_en^5 publisher_txt_en^2 subjects_txt &
pf = author_txt_en^10 title_txt_en^5 publisher_txt_en^2 subjects_txt &
mm = 100%
```
Link: [search in all fields using edismax and pf](http://localhost:8983/solr/gettingstarted/select?q=complete%20works&defType=edismax&qf=author_txt_en%5E10%20title_txt_en%5E5%20publisher_txt_en%5E2%20subjects_txt&pf=author_txt_en%5E10%20title_txt_en%5E5%20publisher_txt_en%5E2%20subjects_txt&mm=100%)

Versus without `pf`:

Link: [search in all fields using edismax without pf](http://localhost:8983/solr/gettingstarted/select?q=complete%20works&defType=edismax&qf=author_txt_en%5E10%20title_txt_en%5E5%20publisher_txt_en%5E2%20subjects_txt&mm=100%)

***

## How should we sort the results / the `sort` parameter

What if we want to sort the results by price (ascending) and then by title (descending)? We can use the `sort` parameter:

```rust
q = complete works &
defType = edismax &
qf = author_txt_en^10 title_txt_en^5 publisher_txt_en^2 subjects_txt &
pf = author_txt_en^10 title_txt_en^5 publisher_txt_en^2 subjects_txt &
mm = 100% &
sort = price_f asc, title_txt_en desc
```
Link: [search in all fields using edismax and sort](http://localhost:8983/solr/gettingstarted/select?q=complete%20works&defType=edismax&qf=author_txt_en%5E10%20title_txt_en%5E5%20publisher_txt_en%5E2%20subjects_txt&pf=author_txt_en%5E10%20title_txt_en%5E5%20publisher_txt_en%5E2%20subjects_txt&mm=100%25&sort=price_f%20asc,%20title_txt_en%20desc)

***

## What fields should we return / the `fl` (a.k.a `field list`) parameter

We can also limit the fields that are returned using the `fl` (a.k.a `field list`) parameter:

```rust
q = complete works &
defType = edismax &
qf = author_txt_en^10 title_txt_en^5 publisher_txt_en^2 subjects_txt &
pf = author_txt_en^10 title_txt_en^5 publisher_txt_en^2 subjects_txt &
mm = 100% &
fl = id, title_txt_en, price_f
```
Link: [search in all fields using edismax and fl](http://localhost:8983/solr/gettingstarted/select?q=complete%20works&defType=edismax&qf=author_txt_en%5E10%20title_txt_en%5E5%20publisher_txt_en%5E2%20subjects_txt&pf=author_txt_en%5E10%20title_txt_en%5E5%20publisher_txt_en%5E2%20subjects_txt&mm=100%25&fl=id,%20title_txt_en,%20price_f)

We can also add aliases to the fields:

```rust
q = complete works &
defType = edismax &
qf = author_txt_en^10 title_txt_en^5 publisher_txt_en^2 subjects_txt &
pf = author_txt_en^10 title_txt_en^5 publisher_txt_en^2 subjects_txt &
mm = 100% &
fl = id, title: title_txt_en, price: price_f
```
Link: [search in all fields using edismax and fl with aliases](http://localhost:8983/solr/gettingstarted/select?q=complete%20works&defType=edismax&qf=author_txt_en%5E10%20title_txt_en%5E5%20publisher_txt_en%5E2%20subjects_txt&pf=author_txt_en%5E10%20title_txt_en%5E5%20publisher_txt_en%5E2%20subjects_txt&mm=100%25&fl=id,%20title:%20title_txt_en,%20price:%20price_f)
***

## How should we paginate the results / the `start` and `rows` parameters

We will eventually need to paginate the results. We can use the `start` and `rows` parameters:

*(`q` is now `john` instead of `complete works`)*

```rust
q = john &
defType = edismax &
qf = author_txt_en^10 title_txt_en^5 publisher_txt_en^2 subjects_txt &
pf = author_txt_en^10 title_txt_en^5 publisher_txt_en^2 subjects_txt &
fl = id, title_txt_en, price_f, author_txt_en &
start = 10 &
rows = 10 &
```
Link: [search in all fields using edismax and pagination](http://localhost:8983/solr/gettingstarted/select?q=john&defType=edismax&qf=author_txt_en%5E10%20title_txt_en%5E5%20publisher_txt_en%5E2%20subjects_txt&pf=author_txt_en%5E10%20title_txt_en%5E5%20publisher_txt_en%5E2%20subjects_txt&fl=id,%20title_txt_en,%20price_f,%20author_txt_en&start=10&rows=10)

***

## How should we boost documents / the `boost` and `bf` parameters

Introducing boosts using the `boost` parameter:
Let's say we want to boost the books that subjects_ss contains the term "Science":

This will multiply the score by 20 if the document contains the term "Science" in the subjects_ss field (and by 1 otherwise). This will surely put the books that contain the term "Science" in the subjects_ss field at the top of the results.

```rust
q = john &
defType = edismax &
qf = author_txt_en^10 title_txt_en^5 publisher_txt_en^2 subjects_txt &
pf = author_txt_en^10 title_txt_en^5 publisher_txt_en^2 subjects_txt &
start = 0 &
rows = 10 &
boost = if(exists(query({!v="subjects_ss:Science"})),20,1)
```
Link: [search in all fields using edismax and boost](http://localhost:8983/solr/gettingstarted/select?q=john&defType=edismax&qf=author_txt_en%5E10%20title_txt_en%5E5%20publisher_txt_en%5E2%20subjects_txt&pf=author_txt_en%5E10%20title_txt_en%5E5%20publisher_txt_en%5E2%20subjects_txt&fl=id,%20title_txt_en,%20price_f,%20author_txt_en&start=0&rows=10&boost=if(exists(query(%7B!v='subjects_ss:Science'%7D)),2,1))

What if we want to boost the books for which the `subjects_ss` field contains the term "Art" but boost the books for which the `subjects_ss` field contains the term "Aesthetics" even more?

*(`q` is now `art` instead of `john`)*

```rust
q = art &
defType = edismax &
qf = author_txt_en^10 title_txt_en^5 publisher_txt_en^2 subjects_txt &
pf = author_txt_en^10 title_txt_en^5 publisher_txt_en^2 subjects_txt &
start = 0 &
rows = 10 &
boost = if(exists(query({!v="subjects_ss:Art"})),if(exists(query({!v="subjects_ss:Aesthetics"})),1000,10),1)
```
Link: [search in all fields using edismax and boost](http://localhost:8983/solr/gettingstarted/select?q=art&defType=edismax&qf=author_txt_en%5E10%20title_txt_en%5E5%20publisher_txt_en%5E2%20subjects_txt&pf=author_txt_en%5E10%20title_txt_en%5E5%20publisher_txt_en%5E2%20subjects_txt&start=0&rows=10&boost=if(exists(query(%7B!v='subjects_ss:Art'%7D)),if(exists(query(%7B!v='subjects_ss:Aesthetics'%7D)),1000,10),1)&fl=*,score)

We can also boost these books by their popularity (using the `popularity_i` field), we will do this using the bf (a.k.a boost function) parameter, which is an additive boost (as opposed to the multiplicative boost we used before):

```rust
q = art &
defType = edismax &
qf = author_txt_en^10 title_txt_en^5 publisher_txt_en^2 subjects_txt &
pf = author_txt_en^10 title_txt_en^5 publisher_txt_en^2 subjects_txt &
start = 0 &
rows = 10 &
boost = if(exists(query({!v="subjects_ss:Art"})),if(exists(query({!v="subjects_ss:Aesthetics"})),20,10),1) &
bf = popularity_i
```
Link: [search in all fields using edismax and boost](http://localhost:8983/solr/gettingstarted/select?q=art&defType=edismax&qf=author_txt_en%5E10%20title_txt_en%5E5%20publisher_txt_en%5E2%20subjects_txt&pf=author_txt_en%5E10%20title_txt_en%5E5%20publisher_txt_en%5E2%20subjects_txt&start=0&rows=10&boost=if(exists(query(%7B!v='subjects_ss:Art'%7D)),if(exists(query(%7B!v='subjects_ss:Aesthetics'%7D)),1000,10),1)&bf=popularity_i&fl=*,score)

So now the score will be `(11.779475 + 157) * 1000 = 168779.475` meaning `(initial_score + bf) * boost`

If we add the debug=true, we can see the score breakdown for each document:

*you will need to copy the query from the browser and paste it in a text editor to see the full query, after replacing the `\n` with an actual new line*

```rust
168779.47998046875 = weight(FunctionScoreQuery(+(subjects_txt:art | (publisher_txt_en:art)^2.0 | (title_txt_en:art)^5.0 | (author_txt_en:art)^10.0) int(popularity_i), scored by boost(if(exists(query(subjects_ss:Art,def=0.0)),if(exists(query(subjects_ss:Aesthetics,def=0.0)),const(1000),const(20)),const(1))))), result of:
    168779.47998046875 = product of:
        168.77948 = sum of:
            11.779475 = max of:
                2.055708 = weight(subjects_txt:art in 8388) [SchemaSimilarity], result of:
                    2.055708 = score(freq=1.0), computed as boost * idf * tf from:
                        4.13779 = idf, computed as log(1 + (N - n + 0.5) / (n + 0.5)) from:
                            135 = n, number of documents containing term
                            8490 = N, total number of documents with field
                        0.496813 = tf, computed as freq / (freq + k1 * (1 - b + b * dl / avgdl)) from:
                            1.0 = freq, occurrences of term within document
                            1.2 = k1, term saturation parameter
                            0.75 = b, length normalization parameter
                            4.0 = dl, length of field
                            5.0502944 = avgdl, average length of field
                11.779475 = weight(title_txt_en:art in 8388) [SchemaSimilarity], result of:
                    11.779475 = score(freq=1.0), computed as boost * idf * tf from:
                        5.0 = boost
                        4.223415 = idf, computed as log(1 + (N - n + 0.5) / (n + 0.5)) from:
                            146 = n, number of documents containing term
                            10000 = N, total number of documents with field
                        0.5578176 = tf, computed as freq / (freq + k1 * (1 - b + b * dl / avgdl)) from:
                            1.0 = freq, occurrences of term within document
                            1.2 = k1, term saturation parameter
                            0.75 = b, length normalization parameter
                            3.0 = dl, length of field
                            5.48 = avgdl, average length of field
            157.0 = FunctionQuery(int(popularity_i)), product of:
                157.0 = int(popularity_i)=157
                1.0 = boost
        1000.0 = if(exists(query(subjects_ss:Art,def=0.0)=3.5320258),if(exists(query(subjects_ss:Aesthetics,def=0.0)=3.8708363),const(1000),const(20)),const(1))
```

### Using function queries

We can also use functions, for example to set a maximum popularity boost in `bf`:

A list of the available functions can be found here: [https://solr.apache.org/guide/solr/latest/query-guide/function-queries.html](https://solr.apache.org/guide/solr/latest/query-guide/function-queries.html)

```rust
q = art &
defType = edismax &
qf = author_txt_en^10 title_txt_en^5 publisher_txt_en^2 subjects_txt &
pf = author_txt_en^10 title_txt_en^5 publisher_txt_en^2 subjects_txt &
start = 0 &
rows = 10 &
boost = if(exists(query({!v='subjects_ss:Art'})),if(exists(query({!v='subjects_ss:Aesthetics'})),1000,10),1) &
bf = min(popularity_i, 100)
```
Link: [search in all fields using edismax and boost](http://localhost:8983/solr/gettingstarted/select?q=art&defType=edismax&qf=author_txt_en%5E10%20title_txt_en%5E5%20publisher_txt_en%5E2%20subjects_txt&pf=author_txt_en%5E10%20title_txt_en%5E5%20publisher_txt_en%5E2%20subjects_txt&start=0&rows=10&boost=if(exists(query(%7B!v='subjects_ss:Art'%7D)),if(exists(query(%7B!v='subjects_ss:Aesthetics'%7D)),1000,10),1)&bf=min(popularity_i,100)&fl=*,score)

So now the score will be `(11.779475 + 100) * 1000 = 111779.47` meaning `(initial_score + min(bf, 100)) * boost`

## Grouping results / the `group` parameter and the `collapse` query parser

***Note***: *Solr’s Collapse and Expand Results feature is newer and mostly overlaps with Result Grouping. There are features unique to both, and they have different performance characteristics. That said, in most cases Collapse and Expand is preferable to Result Grouping.*

*<center><p style="color: red;">Always group or collapse on single valued non-tokenized fields. Otherwise, you will get errors or unpredictable results.</p></center>*

As the `publisher_txt_en` field is a single valued text tokenized field, we can group by it, but the results will be unpredictable.

```rust
q = art &
defType = edismax &
qf = author_txt_en^10 title_txt_en^5 publisher_txt_en^2 subjects_txt &
pf = author_txt_en^10 title_txt_en^5 publisher_txt_en^2 subjects_txt &
start = 0 &
rows = 10 &
group = true &
group.field = publisher_txt_en & 
group.format = simple &
group.main = true & 
group.sort = popularity_i desc &
sort = popularity_i desc
```
Link: [search in all fields using edismax and group](http://localhost:8983/solr/gettingstarted/select?q=art&defType=edismax&qf=author_txt_en%5E10%20title_txt_en%5E5%20publisher_txt_en%5E2%20subjects_txt&pf=author_txt_en%5E10%20title_txt_en%5E5%20publisher_txt_en%5E2%20subjects_txt&start=0&rows=10&group=true&group.field=publisher_txt_en&group.format=simple&group.main=true&group.sort=popularity_i%20desc&sort=popularity_i%20desc)

So because the `publisher_txt_en` field is of type `text_en` which is a tokenized text field we need to add a copy field to it that is of type `string` which is a single valued non-tokenized field `publisher_s`:

**We need to reindex the data after adding the copy field.**

Now we can collapse the results by the `publisher_s` field:

```rust
q = art &
defType = edismax &
qf = author_txt_en^10 title_txt_en^5 publisher_txt_en^2 subjects_txt &
pf = author_txt_en^10 title_txt_en^5 publisher_txt_en^2 subjects_txt &
start = 0 &
rows = 10 &
fq = {!collapse field="publisher_s" sort="popularity_i desc"} &
sort = popularity_i desc
```
Link: [search in all fields using edismax and collapse](http://localhost:8983/solr/gettingstarted/select?q=art&defType=edismax&qf=author_txt_en%5E10%20title_txt_en%5E5%20publisher_txt_en%5E2%20subjects_txt&pf=author_txt_en%5E10%20title_txt_en%5E5%20publisher_txt_en%5E2%20subjects_txt&start=0&rows=10&fq=%7B!collapse%20field=publisher_s%20sort=%22popularity_i%20desc%22%7D&sort=popularity_i%20desc)

**Note: the collapse query parser is a post-filter, so it will not affect the score. It will however affect the number of results returned and the facets, as the results are filtered before the facets are calculated.**

Collapsing query parser parameters:
```rust
{!collapse field=field_name [nullPolicy=nullPolicy] [sort=sortSpec] [cost=cost] [nullPolicy=nullPolicy]}
```

* `field` - The field to collapse on. This field must be single valued and either be non-tokenized, or have a docValues property of true.
* `nullPolicy` - The policy to use when encountering a null value. The default is to ignore any documents with a null value in the collapse field. The other option is to treat all documents with a null value as having the same value. This is useful when collapsing on a field that is not required.
(***big performance improvement if nullPolicy=expand is used when you have lots of KNOWN unique values and use a null field to collapse on***).
* `sort` - The sort order of the collapsed documents. The default is to sort by score desc. 
(***big performance improvement if score desc is ommited***).
* `cost` - The cost of collapsing on this field. The default is 100. Can be used to determine the order of collapsing fields when collapsing on multiple fields.


## Summary of the query parameters

| Parameter | Description | Example | TL;DR |
| --- | --- | --- | --- |
| `q` | The query string | `q=John` | What you're searching for |
| `defType` | The query parser | `defType=edismax` | How you're searching for it |
| `qf` | The query fields | `qf=author_txt_en^10 title_txt_en^5 publisher_txt_en^2 subjects_txt` | The fields you're searching in |
| `pf` | The phrase fields | `pf=author_txt_en^10 title_txt_en^5 publisher_txt_en^2 subjects_txt` | The fields you're searching in for phrases |
| `fq` | The filter query | `fq=publisher_txt_en:Boston` | The fields you're filtering on |
| `mm` | The minimum match | `mm=50%` | The minimum number of terms that must match |
| `sort` | The sort | `sort=price_f asc, title_txt_en desc` | The sort order |
| `fl` | The field list | `fl=id, title_txt_en, price_f` | The fields you want to return |
| `start` | The start | `start=10` | The start point for pagination |
| `rows` | The rows | `rows=10` | The number of rows returned |
| `boost` | The boost | `boost=if(exists(query({!v="subjects_ss:Science"})),20,1)` | The boost by which you want to multiply the score |
| `bf` | The boost function | `bf=popularity_i` | The boost by which you want to add to the score |
| `debug` | The debug | `debug=true` | The debug mode |
| `q.op` | The query operator | `q.op=AND` | The operator used to combine the terms in the query string |
| `sow` | The split on whitespace | `sow=true` | Whether to split on whitespace or not |
| `df` | The default field | `df=text` | The default field in which to search if no field is specified |

## Faceting / the `json.facet` parameter

### Terms facet / the `terms` facet type

Faceting is a way of categorizing documents into groups that share a common characteristic. For example, we can facet on the newly added `publisher_s` field to get the number of books per publisher:

```rust
q = *:* &
rows = 0 &
json.facet = {
    publishers: {
        type: terms,
        field: publisher_s,
        limit: 1000
    }
}
```
Link: [facet on publisher_s](http://localhost:8983/solr/gettingstarted/select?q=*:*&rows=0&json.facet={publishers:{type:terms,field:publisher_s,limit:1000}})

We can also facet on the `subjects_ss` field to get the number of books per subject:

```rust
q = *:* &
rows = 0 &
json.facet = {
    subjects: {
        type: terms,
        field: subjects_ss,
        limit: 1000
    }
}
```
Link: [facet on subjects_ss](http://localhost:8983/solr/gettingstarted/select?q=*:*&rows=0&json.facet={subjects:{type:terms,field:subjects_ss,limit:1000}})

Or we can facet on both fields, to get the number of books per publisher and per subject (only the top 10 publishers and subjects):

```rust
q = *:* &
rows = 0 &
json.facet = {
    publishers: {
        type: terms,
        field: publisher_s,
        limit: 10
    },
    subjects: {
        type: terms,
        field: subjects_ss,
        limit: 10
    }
}
```
Link: [facet on publisher_s and subjects_ss](http://localhost:8983/solr/gettingstarted/select?q=*:*&rows=0&json.facet={publishers:{type:terms,field:publisher_s,limit:10},subjects:{type:terms,field:subjects_ss,limit:10}})

Also, combining the two facets, we can get the number of books per publisher and per subject:

```rust
q = *:* &
rows = 0 &
json.facet = {
    publishers: {
        type: terms,
        field: publisher_s,
        limit: 10,
        facet: {
            subjects: {
                type: terms,
                field: subjects_ss,
                limit: 10
            }
        }
    }
}
```
Link: [facet on publisher_s and subjects_ss](http://localhost:8983/solr/gettingstarted/select?q=*:*&rows=0&json.facet={publishers:{type:terms,field:publisher_s,limit:10,facet:{subjects:{type:terms,field:subjects_ss,limit:10}}}})

Let's get back to our search for `art` and facet on the `subjects_ss` field:

```rust
q = art &
defType = edismax &
qf = author_txt_en^10 title_txt_en^5 publisher_txt_en^2 subjects_txt &
pf = author_txt_en^10 title_txt_en^5 publisher_txt_en^2 subjects_txt &
start = 0 &
rows = 10 &
json.facet = {
    subjects: {
        type: terms,
        field: subjects_ss,
        limit: 1000
    }
}
```
Link: [search in all fields using edismax and facet on subjects_ss](http://localhost:8983/solr/gettingstarted/select?q=art&defType=edismax&qf=author_txt_en^10%20title_txt_en^5%20publisher_txt_en^2%20subjects_txt&pf=author_txt_en^10%20title_txt_en^5%20publisher_txt_en^2%20subjects_txt&start=0&rows=10&json.facet={subjects:{type:terms,field:subjects_ss,limit:1000}})

This would translate to showing the follwing books and filters in the UI:

| Filter by subject |
| --- |
| ☐ Art |
| ☐ Drawing |
| ☐ Aesthetics |
| ☐ Art, American |
| ☐ Indians of North America |
| ☐ Military art and science |
| ☐ Painting |
| ☐ Arts, American |
| ☐ Conduct of life |
| ☐ Indian art |
| ☐ Patchwork |
| + see more filters |

| Search results |
| --- |
| The collections of the Cincinnati Art Museum. |
| Lure of the West : treasures from the Smithsonian American Art Museum |
| American watercolors at the Pennsylvania Academy of the fine arts |
| On its own ground : celebrating the permanent collection of the Whitney Museum of American Art. |
| The art of arts : rediscovering painting |
| Art past, art present |
| The art of the shaman : rock art of California |
| The arts of life |
| Networked art |
| The arts of the beautiful |

We can now filter by subject, for example, if we filter by `Art` we will get the following results. Notice that the `subjects_ss` field is now filtered by `Art`, and we should tag the filter and exclude it in the facet, so that we could also select another subject:

```rust
q = art &
defType = edismax &
qf = author_txt_en^10 title_txt_en^5 publisher_txt_en^2 subjects_txt &
pf = author_txt_en^10 title_txt_en^5 publisher_txt_en^2 subjects_txt &
start = 0 &
rows = 0 & // so that we can better see the facets
fq = {!tag=subject_filter} subjects_ss:Art &
json.facet = {
    subjects: {
        type: terms,
        field: subjects_ss,
        limit: 1000,
        excludeTags: subject_filter
    }
}
```
Link: [search in all fields using edismax and facet on subjects_ss](http://localhost:8983/solr/gettingstarted/select?q=art&defType=edismax&qf=author_txt_en^10%20title_txt_en^5%20publisher_txt_en^2%20subjects_txt&pf=author_txt_en^10%20title_txt_en^5%20publisher_txt_en^2%20subjects_txt&start=0&rows=0&fq=%7B!tag=subject_filter%7D%20subjects_ss:Art&json.facet={subjects:{type:terms,field:subjects_ss,limit:1000,excludeTags:subject_filter}})

Versus if we don't exclude the filter, we will get the following results:

```rust
q = art &
defType = edismax &
qf = author_txt_en^10 title_txt_en^5 publisher_txt_en^2 subjects_txt &
pf = author_txt_en^10 title_txt_en^5 publisher_txt_en^2 subjects_txt &
start = 0 &
rows = 0 & // so that we can better see the facets
fq = subjects_ss:Art &
json.facet = {
    subjects: {
        type: terms,
        field: subjects_ss,
        limit: 1000
    }
}
```
Link: [search in all fields using edismax and facet on subjects_ss](http://localhost:8983/solr/gettingstarted/select?q=art&defType=edismax&qf=author_txt_en^10%20title_txt_en^5%20publisher_txt_en^2%20subjects_txt&pf=author_txt_en^10%20title_txt_en^5%20publisher_txt_en^2%20subjects_txt&start=0&rows=0&fq=subjects_ss:Art&json.facet={subjects:{type:terms,field:subjects_ss,limit:1000}})

If we also add result collapsing, we will have to tag the collapse filter and exclude it in the facet:

```rust
q = art &
defType = edismax &
qf = author_txt_en^10 title_txt_en^5 publisher_txt_en^2 subjects_txt &
pf = author_txt_en^10 title_txt_en^5 publisher_txt_en^2 subjects_txt &
start = 0 &
rows = 10 & 
fq = {!tag=subject_filter} subjects_ss:Art &
fq = {!tag=collapse_filter} {!collapse field="publisher_s" sort="popularity_i desc"} &
json.facet = {
    subjects: {
        type: terms,
        field: publisher_s,
        limit: 1000,
        excludeTags: [subject_filter, collapse_filter]
    }
}
```
Link: [search in all fields using edismax and facet on publisher_s](http://localhost:8983/solr/gettingstarted/select?q=art&defType=edismax&qf=author_txt_en^10%20title_txt_en^5%20publisher_txt_en^2%20subjects_txt&pf=author_txt_en^10%20title_txt_en^5%20publisher_txt_en^2%20subjects_txt&start=0&rows=10&fq=%7B!tag=subject_filter%7D%20subjects_ss:Art&fq=%7B!tag=collapse_filter%7D%20%7B!collapse%20field=%22publisher_s%22%20sort=%22popularity_i%20desc%22%7D&json.facet={publishers:{type:terms,field:publisher_s,limit:1000,excludeTags:[subject_filter,collapse_filter]}})

versus if we don't exclude the collapse filter, we will get the following results:
Link: [search in all fields using edismax and facet on publisher_s](http://localhost:8983/solr/gettingstarted/select?q=art&defType=edismax&qf=author_txt_en^10%20title_txt_en^5%20publisher_txt_en^2%20subjects_txt&pf=author_txt_en^10%20title_txt_en^5%20publisher_txt_en^2%20subjects_txt&start=0&rows=10&fq=%7B!tag=subject_filter%7D%20subjects_ss:Art&fq=%7B!tag=collapse_filter%7D%20%7B!collapse%20field=%22publisher_s%22%20sort=%22popularity_i%20desc%22%7D&json.facet={publishers:{type:terms,field:publisher_s,limit:1000}})

### Range facet / the `range` facet type

We can also facet on ranges, for example, we can facet on the `price_f` field to get the number of books per price range:

```rust
q = *:* &
rows = 0 &
json.facet = {
    price_ranges: {
        type: range,
        field: price_f,
        start: 0,
        end: 1000,
        gap: 100
    }
}
```
Link: [facet on price_f](http://localhost:8983/solr/gettingstarted/select?q=*:*&rows=0&json.facet={price_ranges:{type:range,field:price_f,start:0,end:1000,gap:100}})

## Highlighting / the `hl` parameter

We can highlight the search terms in the results using the `hl` parameter:

```rust
q = potically theories &
defType = edismax &
qf = author_txt_en^10 title_txt_en^5 publisher_txt_en^2 subjects_txt &
pf = author_txt_en^10 title_txt_en^5 publisher_txt_en^2 subjects_txt &
hl = true &
hl.fl = title_txt_en author_txt_en published_txt_en subjects_txt &
hl.simple.pre = <b> & // the tag to use before the highlighted term
hl.simple.post = </b> // the tag to use after the highlighted term
hl.requireFieldMatch = true 
```
Link: [search in all fields using edismax and highlight](http://localhost:8983/solr/gettingstarted/select?q=potically%20theories&defType=edismax&qf=author_txt_en^10%20title_txt_en^5%20publisher_txt_en^2%20subjects_txt&pf=author_txt_en^10%20title_txt_en^5%20publisher_txt_en^2%20subjects_txt&hl=true&hl.fl=title_txt_en%20author_txt_en%20published_txt_en%20subjects_txt&hl.simple.pre=%3Cb%3E&hl.simple.post=%3C/b%3E&hl.requireFieldMatch=true)

More details on highlighting can be found here: [https://solr.apache.org/guide/solr/latest/query-guide/highlighting.html](https://solr.apache.org/guide/solr/latest/query-guide/highlighting.html)

## Split on whitespace (`sow`) - term centric vs field centric

Many Solr teams have gotten stumped on the `sow=false` behavior introduced in Solr 7. To review, `sow=false` (split-on-whitespace=false) changes how the query is parsed. With `sow=false` the query is parsed using each field’s analyzer/settings, NOT assuming whitespace corresponds to an OR query.

The primary unexpected behavior we see is at times, the following Solr query produces different query structure depending on a number of factors:

```rust
qf = title description & 
defType = edismax &
q = blue shoes & 
sow=false
```

Much of the time, we see this query produce:

```rust
(title:blue | description:blue) (title:shoes | description:shoes)
```

This query is term-centric. It picks the highest relevance score per search term (that’s the | operator), then adds them together. The advantage to term-centric search, is that it ***biases results towards documents that have more of the user’s search terms.***

However, the query can unexpectedly flip to a field centric behavior. That is the structure flips to:

```rust
(title:blue title:shoes) | (description:blue description:shoes)
```

Here the query chooses the best field that matches the user’s query, not a document that matches both search terms.

So if we don't need multi-term synonyms, we can use `sow=true` to avoid this behavior as it will always produce a term-centric query and we don't have to rely on the fact that the query is analyzed consistently across fields.