# URI Normalizer

[![Latest Stable Version](https://poser.pugx.org/acdh-oeaw/uri-normalizer/v/stable)](https://packagist.org/packages/acdh-oeaw/uri-normalizer)
![Build status](https://github.com/acdh-oeaw/uriNormalizer/workflows/phpunit/badge.svg?branch=master)
[![Coverage Status](https://coveralls.io/repos/github/acdh-oeaw/uriNormalizer/badge.svg?branch=master)](https://coveralls.io/github/acdh-oeaw/uriNormalizer?branch=master)
[![License](https://poser.pugx.org/acdh-oeaw/arche-core/license)](https://packagist.org/packages/acdh-oeaw/uri-normalizer)

A simple class for normalizing external entity reference sources' URIs (Geonames, GND, etc. URIs).

Most entity reference sources properly resolves many variants of an entity URI, e.g. for the Geonames 
all URLs below represent exactly the same entity (and it's definitely not a full list):

* http://geonames.org/2761369
* https://geonames.org/2761369
* http://www.geonames.org/2761369
* https://www.geonames.org/2761369
* http://geonames.org/2761369/vienna
* https://geonames.org/2761369/vienna
* http://www.geonames.org/2761369/vienna
* https://www.geonames.org/2761369/vienna

Because of that entity URIs can't be simply tested for equality without normalization.

This package provides a simple framework for performing such normalization.

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

## Mappings

If not specified, mappings provided by the [UriNormRules](https://github.com/acdh-oeaw/UriNormRules) are used.

