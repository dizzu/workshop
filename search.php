<?php

include __DIR__ . '/vendor/autoload.php';

$client = new SolrClient([
    'hostname' => '127.0.0.1',
    'port' => 8983,
    'path' => '/solr/gettingstarted',
]);

$query = new SolrQuery();

if (isset($_GET['q']) && !empty($_GET['q'])) {
    $queryString = $_GET['q'];
} else {
    $queryString = '*:*';
}

$range_start = 0;
$range_end = 1000;
$range_gap = 50;

// Searching
$query->setQuery($queryString);
$query->set('defType', 'edismax');
$query->set('qf', 'author_txt_en^50 authors_other_txt^50 title_txt_en^25 publisher_txt_en^2 subjects_txt');
$query->set('pf', 'author_txt_en^50 authors_other_txt^50 title_txt_en^25 publisher_txt_en^2 subjects_txt');
$query->set('mm', '75%');
$query->set('sow', 'true');
$query->setStart($_GET['start'] ?? 0);
$query->setRows(10);

if (!empty($_GET['publisher'])) {
    $query->addFilterQuery('{!tag=publisher_s} publisher_s: "' . $_GET['publisher'] . '"');
}

if (!empty($_GET['subject'])) {
    $query->addFilterQuery('{!tag=subjects_ss} subjects_ss: "' . $_GET['subject'] . '"');
}

if (!empty($_GET['price_range'])) {
    $price_range = $_GET['price_range'];
    $query->addFilterQuery('{!tag=price_f} price_f:[' . $price_range . ' TO ' . ($price_range + $range_gap) . ']');
}

// Faceting
$query->set('json.facet', json_encode([
    'subjects' => [
        'type' => 'terms',
        'field' => 'subjects_ss',
        'limit' => 100,
        'excludeTags' => ['subjects_ss'],
    ],
    'publishers' => [
        'type' => 'terms',
        'field' => 'publisher_s',
        'limit' => 100,
        'excludeTags' => ['publisher_s'],
    ],
    'price_ranges' => [
        'type' => 'range',
        'field' => 'price_f',
        'start' => $range_start,
        'end' => $range_end,
        'gap' => $range_gap,
        'excludeTags' => ['price_f'],
    ],
]));

if ($queryString == '*:*') {
    $query->set('bf', 'popularity_i');
}

// Highlighting
$query->set('hl', 'true');
// $query->set('hl.fl', 'title_txt_en author_txt_en publisher_txt_en subjects_txt');
$query->set('hl.fl', '*');
$query->set('hl.simple.pre', '<strong style="color: green;">');
$query->set('hl.simple.post', '</strong>');
$query->set('hl.mergeContiguous', 'true');
$query->set('hl.requireFieldMatch', 'true'); 
// words highlighted are not necessarily the ones that contributed to the score

if ($queryString == '*:*') {
    $queryString = '';
}

$queryResponse = $client->query($query);
$response = $queryResponse->getResponse();

$loader = new \Twig\Loader\FilesystemLoader('templates');
$twig = new \Twig\Environment($loader, [
    // 'debug' => true,
]);

echo $twig->render('index.html.twig', [
    'response' => $response,
    'query' => $query,
    'queryString' => $queryString,
    'selected_publisher' => $_GET['publisher'] ?? '',
    'selected_subject' => $_GET['subject'] ?? '',
    'range_start' => $range_start,
    'range_end' => $range_end,
    'range_gap' => $range_gap,
    'selected_price_range' => $_GET['price_range'] ?? '',
]);
echo '<br clear="all">';
dump($response->responseHeader->params);
dump($response);
