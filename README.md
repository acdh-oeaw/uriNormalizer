# URI Normalizer

[![Latest Stable Version](https://poser.pugx.org/acdh-oeaw/uri-normalizer/v/stable)](https://packagist.org/packages/acdh-oeaw/uri-normalizer)
![Build status](https://github.com/acdh-oeaw/uriNormalizer/workflows/phpunit/badge.svg?branch=master)
[![Coverage Status](https://coveralls.io/repos/github/acdh-oeaw/uriNormalizer/badge.svg?branch=master)](https://coveralls.io/github/acdh-oeaw/uriNormalizer?branch=master)
[![License](https://poser.pugx.org/acdh-oeaw/uri-normalizer/license)](https://packagist.org/packages/acdh-oeaw/uri-normalizer)

A simple class for normalizing external entity reference sources' URIs (Geonames, GND, etc. URIs).

Most entity-reference-sources properly resolve many variants of an entity URI, e.g. for the Geonames 
all URLs below represent exactly the same entity (and it's definitely not a full list):

* http://geonames.org/2761369
* https://geonames.org/2761369
* http://www.geonames.org/2761369
* https://www.geonames.org/2761369
* http://geonames.org/2761369/vienna
* https://geonames.org/2761369/vienna
* http://www.geonames.org/2761369/vienna
* https://www.geonames.org/2761369/vienna
* https://www.geonames.org/2761369/vienna/about.rdf
* https://www.geonames.org/2761369/vienna.html

Because of that entity URIs can't be simply tested for equality without normalization.

Similarly a normalization is needed for LOD metadata retrieval based on an URI, e.g. not all of above-listed URIs allow to retrieve RDF metadata of an entity and even when they do, we need to assure we need to use the write URI as a triples subject in the retrieved RDF metadata.

This package provides a simple framework for dealing with these issues.
It allows to define URI namespaces with each namespace having a separate rule for

* URI to RDF metadata subject normalization
* URI to RDF metadata retrieval URL normalization

## Installation

```
composer require acdh-oeaw/uri-normalizer
```

## Usage

### As a global singleton

```php
$mappings = [
  [
    "match"   => "^https?://(?:[^.]*[.])?geonames[.]org/([0-9]+)(/.*)?$",
    "replace" => "https://sws.geonames.org/\\1/",
    "resolve" => "https://sws.geonames.org/\\1/about.rdf",
    "format"  => "application/rdf+xml",
  ],
];
$idProp = 'https://some.id/property';

// URI as a string
\acdhOeaw\UriNormalizer::init($mappings, $idProp);
echo \acdhOeaw\UriNormalizer::gNormalize('http://geonames.org/2761369/vienna.html');
// gives 'https://sws.geonames.org/2761369/'

// with an EasyRdf resource
$graph = new EasyRdf\Graph();
$res = $graph->resource('.');
$res->addResource($idProp, 'http://aaa.geonames.org/276136/borj-ej-jaaiyat.html');
UriNormalizer::gNormalizeMeta($res);
(string) $res->getResource($idProp);
// gives 'https://sws.geonames.org/2761369/'

// Metadata retrieval
// print raw RDF metadata retrieved from the geonames
echo \acdhOeaw\UriNormalizer::gResolve('http://geonames.org/2761369/vienna.html')->getBody();
// print parsed RDF metadata retrieved from the geonames
echo \acdhOeaw\UriNormalizer::gFetch('http://geonames.org/2761369/vienna.html')->dump('text');

```

### As an object instance

```php
$mappings = [
  [
    "match"   => "^https?://(?:[^.]*[.])?geonames[.]org/([0-9]+)(/.*)?$",
    "replace" => "https://sws.geonames.org/\\1/",
    "resolve" => "https://sws.geonames.org/\\1/about.rdf",
    "format"  => "application/rdf+xml",
  ],
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
// gives 'https://sws.geonames.org/2761369/'

// Metadata retrieval
// print raw RDF metadata retrieved from the geonames
echo $n->resolve('http://geonames.org/2761369/vienna.html')->getBody();
// print parsed RDF metadata retrieved from the geonames
echo $n->fetch('http://geonames.org/2761369/vienna.html')->dump('text');

```

## Mappings

If not specified, mappings provided by the [arche-assets](https://github.com/acdh-oeaw/arche-assets) are used.

