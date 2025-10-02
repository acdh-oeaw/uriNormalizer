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

use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\RequestInterface;
use Psr\SimpleCache\CacheInterface;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use zozlak\RdfConstants as RDF;
use zozlak\ProxyClient;
use rdfInterface\DatasetInterface;
use rdfInterface\NamedNodeInterface;
use rdfInterface\BlankNodeInterface;
use rdfInterface\DataFactoryInterface;
use quickRdf\DataFactory as DF;
use quickRdf\DatasetNode;
use termTemplates\PredicateTemplate as PT;
use quickRdfIo\Util as RdfIoUtil;

/**
 * A simply utility class normalizing the URIs
 *
 * @author zozlak
 */
class UriNormalizer {

    const FORMAT_JSON = 'application/json';

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
     * @param DataFactoryInterface $dataFactory factory class to be used to create
     *   RDF terms. If not provided, a quickRdf\DataFactory from the sweetrdf/quick-rdf
     *   library is used.
     * @see UriNormalizer::__construct()
     */
    static public function init(?array $mappings = null, string $idProp = '',
                                ?ClientInterface $client = null,
                                ?CacheInterface $cache = null,
                                ?DataFactoryInterface $dataFactory = null): void {
        self::$obj = new UriNormalizer($mappings, $idProp, $client, $cache, $dataFactory);
    }

    /**
     * A static version of the normalizeNN() method.
     * 
     * Call `UriNormalizer::init()` before first use.
     * 
     * @param NamedNodeInterface|string $uri
     * @param bool $requireMatch
     * @return NamedNodeInterface
     * @see UriNormalizer::normalize()
     */
    static public function gNormalizeNN(NamedNodeInterface | string $uri,
                                        bool $requireMatch = true): NamedNodeInterface {
        return self::$obj->normalizeNN($uri, $requireMatch);
    }

    /**
     * A static version of the normalize() method.
     * 
     * Call `UriNormalizer::init()` before first use.
     * 
     * @param NamedNodeInterface|string $uri
     * @param bool $requireMatch
     * @return string
     * @see UriNormalizer::normalize()
     */
    static public function gNormalize(NamedNodeInterface | string $uri,
                                      bool $requireMatch = true): string {
        return self::$obj->normalize($uri, $requireMatch);
    }

    /**
     * A static version of the normalizeMeta() method.
     * 
     * Call `UriNormalizer::init()` before first use.
     * 
     * @param DatasetInterface $res metadata to be processed
     * @param NamedNodeInterface|string $idProp id property URI (if not provided, value passed to 
     *   the `UriNormalizer::init()` is used)
     * @param bool $requireMatch should an exception be rised if the $uri 
     *   matches no rule
     * @see UriNormalizer::normalizeMeta()
     */
    static public function gNormalizeMeta(DatasetInterface $res,
                                          NamedNodeInterface | string $idProp = '',
                                          bool $requireMatch = true): void {
        self::$obj->normalizeMeta($res, $idProp, $requireMatch);
    }

    /**
     * A static version of the resolve() method.
     * 
     * Call `UriNormalizer::init()` before first use.
     * 
     * @param NamedNodeInterface|string $uri
     * @return RequestInterface
     * @see UriNormalizer::resolve()
     */
    static public function gResolve(NamedNodeInterface | string $uri): RequestInterface {
        return self::$obj->resolve($uri);
    }

    /**
     * A static version of the fetch() method.
     * 
     * Call `UriNormalizer::init()` before first use.
     * 
     * @param NamedNodeInterface|string $uri
     * @return DatasetNode
     * @see UriNormalizer::fetch()
     */
    static public function gFetch(NamedNodeInterface | string $uri): DatasetNode {
        return self::$obj->fetch($uri);
    }

    /**
     *
     * @var array<UriNormalizerRule>
     */
    private array $mappings;
    private PT $idTmpl;
    private ClientInterface $client;
    private CacheInterface $cache;
    private DataFactoryInterface $dataFactory;

    /**
     * @param array<UriNormalizerRule|array<string, string>|\stdClass>|null $mappings  
     *   a set of normalization rules to be used. If they are not UriNormRule 
     *   objects, an attempt to cast them is made with the 
     *   `UriNormRule::factory()`. If null is passed, rules provided by the
     *   UriNormRules::getRules() class are used.
     * @param NamedNodeInterface|string $idProp a default RDF property to be used by the 
     *   `normalizeMeta()` method
     * @param ClientInterface|null $client a PSR-18 HTTP client to be used to 
     *   resolve URIs. If not provided, a new instance of a `\GuzzleHttp\Client` 
     *   is used.
     * @param CacheInterface|null $cache instance of a PSR-16 compatible cache
     *   object for caching normalize()/resolve()/normalize() results
     * @param DataFactoryInterface $dataFactory factory class to be used to create
     *   RDF terms. If not provided, a quickRdf\DataFactory from the sweetrdf/quick-rdf
     *   library is used.
     */
    public function __construct(?array $mappings = null,
                                NamedNodeInterface | string $idProp = '',
                                ?ClientInterface $client = null,
                                ?CacheInterface $cache = null,
                                ?DataFactoryInterface $dataFactory = null) {
        if ($mappings === null) {
            $mappings = UriNormRules::getRules();
        }

        $this->dataFactory = $dataFactory ?? new DF();

        $this->mappings = array_map(fn($x) => UriNormalizerRule::factory($x), $mappings);
        if (!empty($idProp)) {
            $this->idTmpl = new PT(is_string($idProp) ? $this->dataFactory::namedNode($idProp) : $idProp);
        }
        $this->client = $client ?? ProxyClient::factory();
        if ($cache !== null) {
            $this->cache = $cache;
        }
    }

    /**
     * Returns a normalized URI as a NamedNodeInterface object.
     * 
     * @param NamedNodeInterface|string $uri URI to be normalized
     * @param bool $requireMatch should an exception be rised if the $uri 
     *   matches no rule
     * @return NamedNodeInterface
     * @throws UriNormalizerException
     */
    public function normalizeNN(NamedNodeInterface | string $uri,
                                bool $requireMatch = true): NamedNodeInterface {
        if (is_string($uri)) {
            $uri = $this->dataFactory::namedNode($uri);
        }
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
                $norm = $this->dataFactory::namedNode($norm);
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
     * Returns a normalized URI as string.
     * 
     * @param NamedNodeInterface|string $uri URI to be normalized
     * @param bool $requireMatch should an exception be rised if the $uri 
     *   matches no rule
     * @return string
     * @throws UriNormalizerException
     */
    public function normalize(NamedNodeInterface | string $uri,
                              bool $requireMatch = true): string {
        return (string) $this->normalizeNN($uri, $requireMatch);
    }

    /**
     * Performs id URI normalization on all id properties of a given
     * metadata resource object.
     * 
     * The normalization is performed in-place, therefore the return type is void.
     * 
     * @param DatasetInterface $res metadata to be processed
     * @param NamedNodeInterface|string $idProp id property URI (if not provided, value passed to 
     *   the object constructor is used)
     * @param bool $requireMatch should an exception be rised if the $uri 
     *   matches no rule
     * @throws UriNormalizerException
     */
    public function normalizeMeta(DatasetInterface $res,
                                  NamedNodeInterface | string $idProp = '',
                                  bool $requireMatch = true): void {
        if (!empty($idProp)) {
            $idTmpl = new PT(is_string($idProp) ? $this->dataFactory::namedNode($idProp) : $idProp);
        } else {
            $idTmpl = $this->idTmpl ?? throw new UriNormalizerException('Id property not defined');
        }
        $res->forEach(fn($x) => $x->withObject($this->normalizeNN($x->getObject(), $requireMatch)), $idTmpl);
    }

    /**
     * Resolves a given URI to a PSR-7 request fetching its RDF metadata.
     * 
     * Throws the UriNormalizerException if the resolving fails.
     * 
     * @param NamedNodeInterface|string $uri
     * @return Request
     * @throws UriNormalizerException
     */
    public function resolve(NamedNodeInterface | string $uri): Request {
        $uri      = (string) $uri;
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
     * @param NamedNodeInterface|string $uri
     * @return DatasetNode
     * @throws UriNormalizerException
     */
    public function fetch(NamedNodeInterface | string $uri): DatasetNode {
        $uri      = (string) $uri;
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
            $meta     = new DatasetNode($this->dataFactory::namedNode($uri));
            if ($rule->format !== self::FORMAT_JSON) {
                $meta->add(RdfIoUtil::parse($response, $this->dataFactory, $rule->format));
            } else {
                $json  = json_decode((string) $response->getBody());
                $this->processJsonObject($json, $meta->getNode(), $meta);
                $class = $rule->class ?? '@type';
                $class = isset($json->$class) ? $json->$class : 'unknownClass';
                $meta->add(DF::quadNoSubject(DF::namedNode(RDF::RDF_TYPE), DF::namedNode($class)));
            }
            if (count($meta) === 0) {
                $altUri = preg_replace("`" . $rule->match . "`", $rule->replace, (string) $request->getUri());
                $meta   = $meta->withNode($this->dataFactory::namedNode($altUri));
                if (count($meta) === 0) {
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

                // for ORCID and VIAF
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

    private function processJsonObject(object $obj,
                                       BlankNodeInterface | NamedNodeInterface $sbj,
                                       DatasetInterface $dataset): void {
        foreach (get_object_vars($obj) as $k => $v) {
            $prop = DF::namedNode($k);
            if (!is_array($v)) {
                $v = [$v];
            }
            $allScalar = array_sum(array_map(fn($x) => !is_scalar($x), $v)) === 0;
            $n         = 0;
            foreach ($v as $i) {
                if ($i === null) {
                    continue;
                }
                if (is_scalar($i)) {
                    $p = $allScalar ? DF::namedNode($k . $n) : $prop;
                    $dataset->add(DF::quad($sbj, $p, DF::literal($i)));
                } else {
                    $blank = DF::blankNode();
                    $dataset->add(DF::quad($sbj, $prop, $blank));
                    $this->processJsonObject($i, $blank, $dataset);
                }
                $n++;
            }
        }
    }

    private function setCache(string $key1, string $key2, mixed $value): void {
        if (isset($this->cache)) {
            $this->cache->set($key1, $value);
            $this->cache->set($key2, $value);
        }
    }
}
