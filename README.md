# URI Normalizer

[![Latest Stable Version](https://poser.pugx.org/acdh-oeaw/uri-normalizer/v/stable)](https://packagist.org/packages/acdh-oeaw/uri-normalizer)
![Build status](https://github.com/acdh-oeaw/uriNormalizer/workflows/phpunit/badge.svg?branch=master)
[![Coverage Status](https://coveralls.io/repos/github/acdh-oeaw/uriNormalizer/badge.svg?branch=master)](https://coveralls.io/github/acdh-oeaw/uriNormalizer?branch=master)
[![License](https://poser.pugx.org/acdh-oeaw/uri-normalizer/license)](https://packagist.org/packages/acdh-oeaw/uri-normalizer)

A class for **normalizing named entity URIs** from services like Geonames, GND, VIAF, ORCID, etc. and **retrieving RDF metadata** from them.

By default the rules from the [arche-assets](https://github.com/acdh-oeaw/arche-assets) library are used by you can supply your own ones.

Any PSR-16 compatible cache can be used to speed up normalization/retrieval of reccuring URIs.
A combined in-memory and persistent sqlite-based cache implementation is provided as well.

## Context

While looking at the named entity database services it's quite often difficult to tell which URL is a canonical URI for a given named entity.

Just let's take a quick look at a bunch (there are definitely more) of Geonames URLs describing exactly same Geonames named entity with id 2761369:

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

Which one of them is **the right one**? The actual answer is quite simple - **the one used as an RDF triples subject in the RDF metadata returned by a given service.**
So the first aim of this package is to provide a tool for transforming any URL coming from a given service and transform it into the canonical URI used by the service in the RDF metadata it returns.

But here we come to another issue - how to fetch the RDF metadata for a given named entity knowing its URI?

For some services (like ORCID or VIAF) it can be done just with an [HTTP content negotation](https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Accept) by requesting response in one of supported RDF formats. For other though you need to know a service-specific content negotation method, e.g. in Geonames you need to append `/about.rdf` to the canonical URI.
The second aim of this package is to allow you to retrieve RDF metadata from named entity URIs/URLs without being bothered by all those service-specific peculiarities.
And as such a retrieval involves quite some time, a caching option is also provided.

## Automatically generated documentation

https://acdh-oeaw.github.io/arche-docs/devdocs/classes/acdhOeaw-UriNormalizer.html

## Installation

```
composer require acdh-oeaw/uri-normalizer
```

## Usage

```php
###
# Initialization
###
$normalizer = new \acdhOeaw\UriNormalizer();

###
# string URL normalization
###
// returns 'https://sws.geonames.org/2761369/'
echo $normalizer->normalize('http://geonames.org/2761369/vienna.html');

###
# EasyRdf resource property normalization
###
$property = 'https://some.id/property';
$graph    = new EasyRdf\Graph();
$resource = $graph->resource('.');
$resource->addResource($property, 'http://aaa.geonames.org/276136/borj-ej-jaaiyat.html');
$normalizer->normalizeMeta($resource, $property);
// returns 'https://sws.geonames.org/276136/'
echo (string) $resource->getResource($property);

###
# Retrieve parsed/raw RDF metadata from URI/URL
###
// print parsed RDF metadata retrieved from the geonames
$metadata = $normalizer->fetch('http://geonames.org/2761369/vienna.html');
echo $metadata->dump('text') . "\n";

// get a PSR-7 request fetching the RDF metadata for a given geonames URL
$request = $normalizer->resolve('http://geonames.org/2761369/vienna.html');
echo $request->getUri() . "\n";

###
# Use your own normalization rules
# and supply a custom Guzzle HTTP client (can be any PSR-18 one) supplying authentication
###
$rules = [
  [
    "match"   => "^https://(?:my.)own.namespace/([0-9]+)(?:/.*)?$",
    "replace" => "https://own.namespace/\\1",
    "resolve" => "https://own.namespace/\\1",
    "format"  => "application/n-triples",
  ],
];
$client = new \GuzzleHttp\Client(['auth' => ['login', 'password']]);
$cache  = false;
$normalizer = new \acdhOeaw\UriNormalizer($rules, '', $client, $cache);
// returns 'https://own.namespace/123'
echo $normalizer->normalize('https://my.own.namespace/123/foo');
// obviously won't work but if the https://own.namespace would exist,
// it would be queried with the HTTP BASIC auth as set up above
$normalizer->fetch('https://my.own.namespace/123/foo');

###
# Use cache
###
$cache = new \acdhOeaw\UriNormalizerCache('db.sqlite');
$normalizer = new \acdhOeaw\UriNormalizer(cache: $cache);
// first retrieval should take 0.1-1 second depending on your connection speed
$t = microtime(true);
$metadata = $normalizer->fetch('http://geonames.org/2761369/vienna.html');
$t = (microtime(true) - $t);
echo $metadata->dump('text') . "\ntime: $t s\n";
// second retrieval should be very quick thanks to in-memory cache
$t = microtime(true);
$metadata = $normalizer->fetch('http://geonames.org/2761369/vienna.html');
$t = (microtime(true) - $t);
echo $metadata->dump('text') . "\ntime: $t s\n";
// a completely separate UriNormalizer instance still benefits from the persistent
// sqlite cache
$cache2 = new \acdhOeaw\UriNormalizerCache('db.sqlite');
$normalizer2 = new \acdhOeaw\UriNormalizer(cache: $cache);
$t = microtime(true);
$metadata = $normalizer2->fetch('http://geonames.org/2761369/vienna.html');
$t = (microtime(true) - $t);
echo $metadata->dump('text') . "\ntime: $t s\n";

###
# As a global singleton
###
// initialization is done with init() instead of a constructor
// the init() takes same parameters as the constructor
\acdhOeaw\UriNormalizer::init();
// all other methods (gNormalize(), gFetch() and gResolve()) also work in 
// the same way and take same parameters as their non-static counterparts
// returns 'https://sws.geonames.org/2761369/'
echo \acdhOeaw\UriNormalizer::gNormalize('http://geonames.org/2761369/vienna.html');
// fetch and cache parsed RDF metadata
echo \acdhOeaw\UriNormalizer::gFetch('http://geonames.org/2761369/vienna.html')->dump('text');
// fetch and cache raw RDF metadata
echo \acdhOeaw\UriNormalizer::gResolve('http://geonames.org/2761369/vienna.html')->getBody();
// normalize EasyRdf Resource property
$property = 'https://some.id/property';
$graph    = new EasyRdf\Graph();
$resource = $graph->resource('.');
$resource->addResource($property, 'http://aaa.geonames.org/276136/borj-ej-jaaiyat.html');
\acdhOeaw\UriNormalizer::gNormalizeMeta($resource, $property);
// returns 'https://sws.geonames.org/276136/'
echo (string) $resource->getResource($property);

```
