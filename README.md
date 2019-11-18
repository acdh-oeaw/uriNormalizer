# URI Normalizer

A simple class for normalizing external entity reference sources' URIs (Geonames, GND, etc. URIs).

## Installation

```
composer require acdh-oeaw/uri-normalizer
```

## Usage

### As a global singleton

```php
$mappings = [
    '|^https?://([^.]*[.])?geonames[.]org/([0-9]+)(/.*)?$|' => 'https://www.geonames.org/\2'
];
$idProp = 'https://some.id/property';

// with a string
\acdhOeaw\UriNormalizer::init($mappings, $idProp);
echo \acdhOeaw\UriNormalizer::gNormalize('http://geonames.org/2761369/vienna.html');
// gives 'https://www.geonames.org/2761369'

// with an EasyRdf resource
$graph = new EasyRdf\Graph();
$res = $graph->resource('.');
$res->addResource($idProp, 'http://aaa.geonames.org/276136/borj-ej-jaaiyat.html');
UriNormalizer::gNormalizeMeta($res);
(string) $res->getResource($idProp);
// gives 'https://www.geonames.org/2761369'
```
### As an object instance

```php
$mappings = [
    '|^https?://([^.]*[.])?geonames[.]org/([0-9]+)(/.*)?$|' => 'https://www.geonames.org/\2'
];
$n = new \acdhOeaw\UriNormalizer($mappings);

echo $n->normalize('http://geonames.org/2761369/vienna.html');
// gives 'https://www.geonames.org/2761369'

// with an EasyRdf resource
$graph = new EasyRdf\Graph();
$res = $graph->resource('.');
$res->addResource($idProp, 'http://aaa.geonames.org/276136/borj-ej-jaaiyat.html');
$n->normalizeMeta($res);
(string) $res->getResource($idProp);
// gives 'https://www.geonames.org/2761369'
```

## Sample mappings

```php
$mappings = [
    '|^https?://([^.]*[.])?geonames[.]org/([0-9]+)(/.*)?$|'                             => 'https://www.geonames.org/\2',
    '|^https?://([^.]*[.])?gazetteer[.]dainst[.]org/([A-Za-z]+/)*([0-9]+)([^0-9].*)?$|'  => 'https://gazetteer.dainst.org/place/\3',
    '|^https?://([^.]*[.])?pleiades[.]stoa[.]org/places/([0-9]+)(/.*)?$|'                => 'https://www.pleiades.stoa.org/places/\2',
    '|^https?://([^.]*[.])?viaf[.]org/viaf/([0-9]+)(/.*)?$|'                             => 'https://viaf.org/viaf/\2',
    '|^https?://([^.]*[.])?d-nb[.]info/gnd/([0-9]+-[0-9]+)$|'                            => 'https://d-nb.info/gnd/\2',
    '|^https?://([^.]*[.])?wikidata[.]org/([A-Za-z:]+/)*(Q[0-9]+)([^[0-9].*)?$|'         => 'https://www.wikidata.org/entity/\3',
    '|^https?://([^.]*[.])?orcid[.]org/([0-9]{4})-?([0-9]{4})-?([0-9]{4})-?([0-9]{4})$|' => 'https://orcid.org/\2-\3-\4-\5',
    '|^https?://([^.]*[.])?n2t[.]net/ark:/99152/(p0[a-z0-9]+)$|'                         => 'https://n2t.net/ark:/99152/\2',
    '|^https?://([^.]*[.])?chronontology[.]dainst[.]org/period/([A-Za-z0-9]+)$|'         => 'https://chronontology.dainst.org/period/\2',
];
```