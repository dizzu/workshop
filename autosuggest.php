<?php 

include __DIR__ . '/vendor/autoload.php';

$client = new SolrClient([
    'hostname' => '127.0.0.1',
    'port' => 8983,
    'path' => '/solr/gettingstarted',
]);

if (isset($_GET['term']) && !empty($_GET['term'])) {
    $words = explode(' ', $_GET['term']);
    $queryString = strtolower(end($words));
    array_pop($words);
} else {
    die();
}

if (empty($queryString)) {
    die();
}

$query = new SolrQuery();
// autosuggest using terms.prefix
$query->set('terms', 'true');
$query->set('terms.fl', 'title_txt');
$query->set('terms.prefix', $queryString);
$query->set('terms.limit', 10);
$query->set('terms.sort', 'count');
$response = $client->query($query);

foreach ($response->getArrayResponse()['terms']['title_txt'] as $term => $count) {
    $terms[] = $term;
}

if (empty($terms)) {
    die();
}
foreach ($terms as $term) {
    $results[] = trim(implode(' ', $words) . ' ' . $term);
}
echo json_encode($results);
