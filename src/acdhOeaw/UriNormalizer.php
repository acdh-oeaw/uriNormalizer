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
use Psr\Http\Message\RequestInterface;
use Psr\SimpleCache\CacheInterface;
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
     * @param CacheInterface|null $cache instance of a PSR-16 compatible cache
     *   object for caching normalize()/resolve()/normalize() results
     * @see UriNormalizer::__construct()
     */
    static public function init(?array $mappings = null, string $idProp = '',
                                ?ClientInterface $client = null,
                                ?CacheInterface $cache = null): void {
        self::$obj = new UriNormalizer($mappings, $idProp, $client, $cache);
    }

    /**
     * A static version of the normalize() method.
     * 
     * Call `UriNormalizer::init()` before first use.
     * 
     * @param string $uri
     * @param bool $requireMatch
     * @return string
     * @see UriNormalizer::normalize()
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
     * @see UriNormalizer::normalizeMeta()
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
     * @return RequestInterface
     * @see UriNormalizer::resolve()
     */
    static public function gResolve(string $uri): RequestInterface {
        return self::$obj->resolve($uri);
    }

    /**
     * A static version of the fetch() method.
     * 
     * Call `UriNormalizer::init()` before first use.
     * 
     * @param string $uri
     * @return Resource
     * @see UriNormalizer::fetch()
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
    private CacheInterface $cache;

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
     * @param CacheInterface|null $cache instance of a PSR-16 compatible cache
     *   object for caching normalize()/resolve()/normalize() results
     */
    public function __construct(?array $mappings = null, string $idProp = '',
                                ?ClientInterface $client = null,
                                ?CacheInterface $cache = null) {
        if ($mappings === null) {
            $mappings = UriNormRules::getRules();
        }

        $this->mappings = array_map(fn($x) => UriNormalizerRule::factory($x), $mappings);
        $this->idProp   = $idProp;
        $this->client   = $client ?? new Client();
        if ($cache !== null) {
            $this->cache = $cache;
        }
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
        $cacheKey = 'n:' . $uri;
        $result   = isset($this->cache) ? $this->cache->get($cacheKey, null) : null;
        if ($result) {
            return $result;
        }
        foreach ($this->mappings as $rule) {
            $count = 0;
            $norm  = preg_replace('`' . $rule->match . '`', $rule->replace, $uri, 1, $count);
            if ($norm === null) {
                throw new UriNormalizerException("Wrong normalization rule: match $rule->match replace $rule->replace");
            }
            if ($count > 0) {
                $this->setCache('n:' . $uri, 'n:' . $norm, $norm);
                return $norm;
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
     * Resolves a given URI to a PSR-7 request fetching its RDF metadata.
     * 
     * Throws the UriNormalizerException if the resolving fails.
     * 
     * @param string $uri
     * @return Request
     * @throws UriNormalizerException
     */
    public function resolve(string $uri): Request {
        $cacheKey = 'r:' . $uri;
        $result   = isset($this->cache) ? $this->cache->get($cacheKey, null) : null;
        if ($result) {
            return $result;
        }
        foreach ($this->mappings as $rule) {
            $count = 0;
            $url   = (string) preg_replace("`" . $rule->match . "`", $rule->resolve, $uri, 1, $count);
            if ($count === 0 || empty($rule->resolve)) {
                continue;
            }

            $request = new Request('HEAD', $url, ['Accept' => $rule->format]);
            $this->fetchUrl($request);
            $this->setCache('r:' . $uri, 'r:' . $request->getUri(), $request);
            return $request;
        }
        throw new UriNormalizerException("$uri doesn't match any rule");
    }

    /**
     * Fetches RDF metadata for a given URI.
     * 
     * Throws UriNormalizerException when the retrieval fails.
     * 
     * @param string $uri
     * @return Resource
     * @throws UriNormalizerException
     */
    public function fetch(string $uri): Resource {
        $cacheKey = 'f:' . $uri;
        $result   = isset($this->cache) ? $this->cache->get($cacheKey, null) : null;
        if ($result) {
            return $result;
        }
        foreach ($this->mappings as $rule) {
            $count = 0;
            $url   = (string) preg_replace("`" . $rule->match . "`", $rule->resolve, $uri, 1, $count);
            if ($count === 0 || empty($rule->resolve)) {
                continue;
            }

            $request  = new Request('GET', $url, ['Accept' => $rule->format]);
            $response = $this->fetchUrl($request);
            $graph    = new Graph();
            $graph->parse((string) $response->getBody(), $rule->format);
            $meta     = $graph->resource($uri);
            if (count($meta->propertyUris()) === 0) {
                $altUri = preg_replace("`" . $rule->match . "`", $rule->replace, (string) $request->getUri());
                $meta   = $graph->resource($altUri);
                if (count($meta->propertyUris()) === 0) {
                    throw new UriNormalizerException("RDF data fetched for $uri resolved to $url does't contain matching subject");
                }
            }

            $this->setCache('f:' . $uri, 'f:' . $url, $meta);
            return $meta;
        }
        throw new UriNormalizerException("$uri doesn't match any rule");
    }

    /**
     * 
     * @param Request $request
     * @return ResponseInterface
     * @throws UriNormalizerException
     */
    private function fetchUrl(RequestInterface &$request): ResponseInterface {
        $accept = $request->getHeader('Accept')[0] ?? '';
        try {
            do {
                try {
                    $response = $this->client->sendRequest($request);
                } catch (ClientExceptionInterface $e) {
                    // for ORCID
                    if ($request->getMethod() === 'HEAD') {
                        $request  = $request->withMethod('GET');
                        $response = $this->client->sendRequest($request);
                    }
                    throw $e;
                }

                $code = $response->getStatusCode();

                // for ORCID
                if ($code >= 400 && $request->getMethod() === 'HEAD') {
                    $request  = $request->withMethod('GET');
                    $response = $this->client->sendRequest($request);
                    $code     = $response->getStatusCode();
                }

                $contentType = $response->getHeader('Content-Type')[0] ?? '';
                $contentType = trim(explode(';', $contentType)[0]);
                $redirectUrl = $response->getHeader('Location')[0] ?? null;
                if (!empty($redirectUrl)) {
                    $redirectUrl = UriResolver::resolve($request->getUri(), new Uri($redirectUrl));
                    $request     = $request->withUri($redirectUrl);
                }
            } while ($code >= 300 && $code < 400 && $redirectUrl !== null);
        } catch (ClientExceptionInterface $e) {
            $url = (string) $request->getUri();
            throw new UriNormalizerException("Failed to fetch RDF data from $url with " . $e->getMessage());
        }
        if ($code !== 200 || $contentType !== $accept) {
            $url = (string) $request->getUri();
            throw new UriNormalizerException("Failed to fetch RDF data from $url with code $code and content-type: $contentType");
        }
        return $response;
    }

    private function setCache(string $key1, string $key2, mixed $value): void {
        if (isset($this->cache)) {
            $this->cache->set($key1, $value);
            $this->cache->set($key2, $value);
        }
    }
}
