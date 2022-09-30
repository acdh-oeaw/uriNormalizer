<?php

/*
 * The MIT License
 *
 * Copyright 2019 Austrian Centre for Digital Humanities.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

namespace acdhOeaw;

use EasyRdf\Graph;
use EasyRdf\Resource;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use GuzzleHttp\Psr7\Utils;

/**
 * A simply utility class normalizing the URIs
 *
 * @author zozlak
 */
class UriNormalizer {

    static private self $obj;

    /**
     * Initializes a global singleton instance of the UriNormalizer.
     * 
     * @param array<UriNormalizerRule|array<string, string>|\stdClass>|null $mappings 
     *   a set of normalization rules to be used. If they are not  
     *   UriNormalizerRule objects, an attempt to cast them is made with the 
     *   `UriNormalizerRule::factory()`. If null is passed, rules provided by 
     *   the UriNormRules::getRules() class are used.
     * @param string $idProp a default RDF property to be used by the 
     *   `normalizeMeta()` method
     * @param ClientInterface|null $client a PSR-18 HTTP client to be used to 
     *   resolve URIs. If not provided, a new instance of a `\GuzzleHttp\Client` 
     *   is used.
     */
    static public function init(?array $mappings = null, string $idProp = '',
                                ?ClientInterface $client = null): void {
        self::$obj = new UriNormalizer($mappings, $idProp, $client);
    }

    /**
     * A static version of the normalize() method.
     * 
     * Call `UriNormalizer::init()` before first use.
     * 
     * @param string $uri
     * @param bool $requireMatch
     * @return string
     * @see normalize()
     */
    static public function gNormalize(string $uri, bool $requireMatch = true): string {
        return self::$obj->normalize($uri, $requireMatch);
    }

    /**
     * A static version of the normalizeMeta() method.
     * 
     * Call `UriNormalizer::init()` before first use.
     * 
     * @param Resource $res metadata to be processed
     * @param string $idProp id property URI (if not provided, value passed to 
     *   the `UriNormalizer::init()` is used)
     * @param bool $requireMatch should an exception be rised if the $uri 
     *   matches no rule
     * @see normalizeMeta()
     */
    static public function gNormalizeMeta(Resource $res, string $idProp = '',
                                          bool $requireMatch = true): void {
        self::$obj->normalizeMeta($res, $idProp, $requireMatch);
    }

    /**
     * A static version of the resolve() method.
     * 
     * Call `UriNormalizer::init()` before first use.
     * 
     * @param string $uri
     * @return ResponseInterface
     * @see resolve()
     */
    static public function gResolve(string $uri): ResponseInterface {
        return self::$obj->resolve($uri);
    }

    /**
     * A static version of the fetch() method.
     * 
     * Call `UriNormalizer::init()` before first use.
     * 
     * @param string $uri
     * @return Resource
     * @see fetch()
     */
    static public function gFetch(string $uri): Resource {
        return self::$obj->fetch($uri);
    }

    /**
     *
     * @var array<UriNormalizerRule>
     */
    private array $mappings;
    private string $idProp;
    private ClientInterface $client;

    /**
     * 
     * @var array<string, ResponseInterface>
     */
    private array $cacheResolve = [];

    /**
     * 
     * @var array<string, Resource>
     */
    private array $cacheFetch = [];

    /**
     * @param array<UriNormalizerRule|array<string, string>|\stdClass>|null $mappings  
     *   a set of normalization rules to be used. If they are not UriNormRule 
     *   objects, an attempt to cast them is made with the 
     *   `UriNormRule::factory()`. If null is passed, rules provided by the
     *   UriNormRules::getRules() class are used.
     * @param string $idProp a default RDF property to be used by the 
     *   `normalizeMeta()` method
     * @param ClientInterface|null $client a PSR-18 HTTP client to be used to 
     *   resolve URIs. If not provided, a new instance of a `\GuzzleHttp\Client` 
     *   is used.
     */
    public function __construct(?array $mappings = null, string $idProp = '',
                                ?ClientInterface $client = null) {
        if ($mappings === null) {
            $mappings = UriNormRules::getRules();
        }

        $this->mappings = array_map(fn($x) => UriNormalizerRule::factory($x), $mappings);
        $this->idProp   = $idProp;
        $this->client   = $client ?? new Client();
    }

    /**
     * Returns a normalized URIs.
     * 
     * @param string $uri URI to be normalized
     * @param bool $requireMatch should an exception be rised if the $uri 
     *   matches no rule
     * @return string
     * @throws UriNormalizerException
     */
    public function normalize(string $uri, bool $requireMatch = true): string {
        foreach ($this->mappings as $rule) {
            $count = 0;
            $norm  = preg_replace('`' . $rule->match . '`', $rule->replace, $uri, 1, $count);
            if ($count) {
                return $norm ?: throw new UriNormalizerException("Wrong normalization rule: match $rule->match replace $rule->replace");
            }
        }
        if ($requireMatch) {
            throw new UriNormalizerException("$uri doesn't match any rule");
        }
        return $uri;
    }

    /**
     * Performs id URI normalization on all id properties of a given
     * metadata resource object.
     * 
     * The normalization is performed in-place, therefore the return type is void.
     * 
     * @param Resource $res metadata to be processed
     * @param string $idProp id property URI (if not provided, value passed to 
     *   the object constructor is used)
     * @param bool $requireMatch should an exception be rised if the $uri 
     *   matches no rule
     * @throws UriNormalizerException
     */
    public function normalizeMeta(Resource $res, string $idProp = '',
                                  bool $requireMatch = true): void {
        $idProp = empty($idProp) ? $this->idProp : $idProp;
        if (empty($idProp)) {
            throw new UriNormalizerException('Id property not defined');
        }

        foreach ($res->allResources($idProp) as $id) {
            $res->deleteResource($idProp, $id);
            $res->addResource($idProp, $this->normalize((string) $id, $requireMatch));
        }
    }

    /**
     * Resolves a given URI to the URL fetching its RDF metadata and returns
     * a corresponding PSR-7 response object.
     * 
     * Throws the UriNormalizerException if the resolving fails.
     * 
     * The final URL (which may differ from the supplied $uri parameter because
     * of normalization and redirects) is provided in the Location header of the
     * returned Response object.
     * 
     * @param string $uri
     * @param bool $cache should results be cached and cache used when available?
     *   While technically any sane RDF retrieval service will set HTTP response
     *   cache control headers to "no cache" it might may sense from the client
     *   perspective to assume that over the life time of the UriNormalizer
     *   object the retrieved results shouldn't change and can be cached. Be 
     *   aware that the cached Response objects don't preserve body.
     * @return ResponseInterface
     * @throws UriNormalizerException
     */
    public function resolve(string $uri, bool $cache = false): ResponseInterface {
        if ($cache && isset($this->cacheResolve[$uri])) {
            return $this->cacheResolve[$uri];
        }
        foreach ($this->mappings as $rule) {
            $count = 0;
            $url   = preg_replace("`" . $rule->match . "`", $rule->resolve, $uri, 1, $count);
            if ($count === 0 || empty($rule->resolve)) {
                continue;
            }

            $response = $this->fetchUrl($url, 'GET', $rule);
            $response = $response->withHeader('Location', (string) $url);

            if ($cache) {
                $this->cacheResolve[$uri]          = $response;
                $this->cacheResolve[(string) $url] = $response;
            }

            return $response;
        }
        throw new UriNormalizerException("$uri doesn't match any rule");
    }

    /**
     * Tries to fetch RDF metadata for a given URI.
     * 
     * Throws UriNormalizerException when the retrieval fails.
     * 
     * @param string $uri
     * @param bool $cache should results be cached and cache used when available?
     *   While technically any sane RDF retrieval service will set HTTP response
     *   cache control headers to "no cache" it might may sense from the client
     *   perspective to assume that over the life time of the UriNormalizer
     *   object the retrieved results shouldn't change and can be cached. 
     *   Remember the cached EasyRdf Resource objects are returned by reference
     *   and changing their state propagates to the cache!
     * @return Resource
     * @throws UriNormalizerException
     */
    public function fetch(string $uri, bool $cache = false): Resource {
        if ($cache && isset($this->cacheFetch[$uri])) {
            return $this->cacheFetch[$uri];
        }
        foreach ($this->mappings as $rule) {
            $count = 0;
            $url   = preg_replace("`" . $rule->match . "`", $rule->resolve, $uri, 1, $count);
            if ($count === 0 || empty($rule->resolve)) {
                continue;
            }

            $response = $this->fetchUrl($url, 'GET', $rule);
            $graph    = new Graph();
            $graph->parse((string) $response->getBody(), $rule->format);
            $meta     = $graph->resource($uri);
            if (count($meta->propertyUris()) === 0) {
                $meta = $graph->resource(preg_replace("`" . $rule->match . "`", $rule->replace, $url));
                if (count($meta->propertyUris()) === 0) {
                    throw new UriNormalizerException("RDF data fetched for $uri resolved to $url don't contain matching subject");
                }
            }

            if ($cache) {
                $this->cacheFetch[$uri]          = $meta;
                $this->cacheFetch[(string) $url] = $meta;
            }

            return $meta;
        }
        throw new UriNormalizerException("$uri doesn't match any rule");
    }

    /**
     * 
     * @param string $url
     * @param string $method
     * @param UriNormalizerRule $rule
     * @return ResponseInterface
     * @throws UriNormalizerException
     */
    private function fetchUrl(string &$url, string $method,
                              UriNormalizerRule $rule): ResponseInterface {
        $headers = ['Accept' => $rule->format];
        try {
            $redirectUrl = new Uri($url);
            do {
                $url         = $redirectUrl;
                $request     = new Request($method, $url, $headers);
                $response    = $this->client->sendRequest($request);
                $code        = $response->getStatusCode();
                $contentType = $response->getHeader('Content-Type')[0] ?? '';
                $contentType = trim(explode(';', $contentType)[0]);
                $redirectUrl = $response->getHeader('Location')[0] ?? null;
                if (!empty($redirectUrl)) {
                    $redirectUrl = UriResolver::resolve($url, new Uri($redirectUrl));
                }
            } while ($code >= 300 && $code < 400 && $redirectUrl !== null);
        } catch (ClientExceptionInterface $e) {
            throw new UriNormalizerException("Failed to fetch RDF data from $url with " . $e->getMessage());
        }
        if ($code !== 200 || $contentType !== $rule->format) {
            throw new UriNormalizerException("Failed to fetch RDF data from $url with code $code and content-type: $contentType");
        }
        return $response;
    }
}
