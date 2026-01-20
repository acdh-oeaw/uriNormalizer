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
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use quickRdf\Dataset;
use quickRdf\DatasetNode;
use quickRdf\DataFactory as DF;
use acdhOeaw\UriNormalizerException;

/**
 * Description of IndexerTest
 *
 * @author zozlak
 */
class UriNormalizerTest extends \PHPUnit\Framework\TestCase {

    const ID_PROP = 'https://id/prop';

    private function initCache(bool $withDb = true): UriNormalizerCache {
        $dbPath = $withDb ? __DIR__ . '/../tests.sqlite' : null;
        $cache  = new UriNormalizerCache($dbPath);
        $cache->clear();
        return $cache;
    }

    public function testInit(): void {
        $client   = new Client(['headers' => ['user-agent' => 'ARCHE-url-checker/1.0 (https://github.com/acdh-oeaw/arche-doorkeeper; mzoltak@oeaw.ac.at)']]);
        $mappings = UriNormRules::getRules();
        UriNormalizer::init($mappings, self::ID_PROP, $client);
        $url      = 'https://sample.uri';
        $this->assertEquals($url, UriNormalizer::gNormalize('https://sample.uri', false));
    }

    /**
     * 
     * @depends testInit
     */
    public function testGeonames(): void {
        $valid = 'https://sws.geonames.org/276136/';
        $bad   = [
            'http://aaa.geonames.org/276136/borj-ej-jaaiyat.html',
        ];
        foreach ($bad as $i) {
            $this->assertEquals($valid, UriNormalizer::gNormalize($i));
        }
        $this->assertInstanceOf(Request::class, UriNormalizer::gResolve($valid));
        $this->assertInstanceOf(DatasetNode::class, UriNormalizer::gFetch($valid));
    }

    /**
     * 
     * @depends testInit
     */
    public function testGazetteer(): void {
        $valid = 'https://gazetteer.dainst.org/place/2282705';
        $bad   = [
            'https://gazetteer.dainst.org/place/2282705',
            'https://gazetteer.dainst.org/doc/2282705.rdf',
            'https://gazetteer.dainst.org/doc/shapefile/2282705',
            'https://gazetteer.dainst.org/doc/2282705',
            'http://aaa.gazetteer.dainst.org/doc/2282705',
        ];
        foreach ($bad as $i) {
            $this->assertEquals($valid, UriNormalizer::gNormalize($i));
        }
        $this->assertInstanceOf(Request::class, UriNormalizer::gResolve($valid));
        $this->assertInstanceOf(DatasetNode::class, UriNormalizer::gFetch($valid));
    }
//  pleiades is behind a bots wall now
//    /**
//     * 
//     * @depends testInit
//     */
//    public function testPleiades(): void {
//        $valid = 'https://pleiades.stoa.org/places/658494';
//        $bad   = [
//            'https://pleiades.stoa.org/places/658494',
//            'http://pleiades.stoa.org/places/658494',
//            'http://pleiades.stoa.org/places/658494/carthage',
//            'http://pleiades.stoa.org/places/658494/ruins-of-ancient-church-at-kafr-nabo',
//            'http://aaa.pleiades.stoa.org/places/658494',
//        ];
//        foreach ($bad as $i) {
//            $this->assertEquals($valid, UriNormalizer::gNormalize($i));
//        }
//        $this->assertInstanceOf(Request::class, UriNormalizer::gResolve($valid));
//        $this->assertInstanceOf(DatasetNode::class, UriNormalizer::gFetch($valid));
//    }

    /**
     * 
     * @depends testInit
     */
    public function testViaf(): void {
        $valid = 'http://viaf.org/viaf/8110691';
        $bad   = [
            'http://viaf.org/viaf/8110691',
            'https://viaf.org/viaf/8110691',
            'http://viaf.org/viaf/8110691/rdf.xml',
            'http://viaf.org/viaf/8110691/marc21.xml',
            'http://aaa.viaf.org/viaf/8110691',
        ];
        foreach ($bad as $i) {
            $this->assertEquals($valid, UriNormalizer::gNormalize($i));
        }
        $this->assertInstanceOf(Request::class, UriNormalizer::gResolve($valid));
        $this->assertInstanceOf(DatasetNode::class, UriNormalizer::gFetch($valid));
    }

    public function testGnd(): void {
        $norm  = new UriNormalizer();
        $valid = 'https://d-nb.info/gnd/4491366-7';
        $bad   = [
            'http://d-nb.info/gnd/4491366-7',
            'https://d-nb.info/gnd/4491366-7',
            'http://aaa.d-nb.info/gnd/4491366-7',
        ];
        foreach ($bad as $i) {
            $this->assertEquals($valid, $norm->normalize($i));
        }
        $this->assertInstanceOf(Request::class, $norm->resolve($valid));
        $this->assertInstanceOf(DatasetNode::class, $norm->fetch($valid));

        // with within-gnd redirect to https://d-nb.info/gnd/118560077/about/lds.rdf
        $this->assertInstanceOf(DatasetNode::class, $norm->fetch('https://d-nb.info/gnd/1089894554'));
    }

    /**
     * 
     * @depends testInit
     */
    public function testWikidata(): void {
        $valid = 'http://www.wikidata.org/entity/Q42';
        $bad   = [
            'https://www.wikidata.org/wiki/Q42',
            'http://www.wikidata.org/entity/Q42',
            'http://www.wikidata.org/wiki/Special:EntityData/Q42',
            'http://www.wikidata.org/wiki/Special:EntityData/Q42.json',
            'http://www.wikidata.org/wiki/Special:EntityData/Q42.json?revision=112',
            'http://aaa.wikidata.org/wiki/Q42',
        ];
        foreach ($bad as $i) {
            $this->assertEquals($valid, UriNormalizer::gNormalize($i));
        }
        $this->assertInstanceOf(Request::class, UriNormalizer::gResolve($valid));
        $this->assertInstanceOf(DatasetNode::class, UriNormalizer::gFetch($valid));
    }

    public function testOrcid(): void {
        $norm  = new UriNormalizer();
        $valid = 'https://orcid.org/0000-0002-5274-8278';
        $bad   = [
            'https://orcid.org/0000-0002-5274-8278',
            'http://aaa.orcid.org/0000-0002-5274-8278',
            'https://orcid.org/0000000252748278',
            'https://orcid.org/0000-00025274-8278',
        ];
        foreach ($bad as $i) {
            $this->assertEquals($valid, $norm->normalize($i));
        }
        $this->assertInstanceOf(Request::class, $norm->resolve($valid));
        $this->assertInstanceOf(DatasetNode::class, $norm->fetch($valid));
    }

    /**
     * @depends testInit
     */
    public function testIsni(): void {
        $valid = 'https://isni.org/isni/0000000128722353';
        $bad   = [
            'https://isni.org/isni/0000-0001-2872-2353',
            'http://isni.org/isni/0000000128722353/about.rdf',
            'https://isni.org/isni/0000000128722353',
        ];
        foreach ($bad as $i) {
            $this->assertEquals($valid, UriNormalizer::gNormalize($i));
        }
        $this->assertInstanceOf(Request::class, UriNormalizer::gResolve($valid));
        $this->assertInstanceOf(DatasetNode::class, UriNormalizer::gFetch($valid));
    }

    /**
     * 
     * @depends testInit
     */
    public function testPeriodo(): void {
        $valid = 'http://n2t.net/ark:/99152/p0m63njncbv';
        $bad   = [
            'http://n2t.net/ark:/99152/p0m63njncbv',
            'https://n2t.net/ark:/99152/p0m63njncbv',
            'http://aaa.n2t.net/ark:/99152/p0m63njncbv',
            'http://foo.perio.do/m63njncbv.ttl',
            'https://perio.do/m63njncbv',
        ];
        foreach ($bad as $i) {
            $this->assertEquals($valid, UriNormalizer::gNormalize($i), $i);
        }
        $this->assertInstanceOf(Request::class, UriNormalizer::gResolve($valid));
        $this->assertInstanceOf(DatasetNode::class, UriNormalizer::gFetch($valid));
    }

    /**
     * 
     * @depends testInit
     */
    public function testChronontology(): void {
        $valid = 'https://chronontology.dainst.org/period/rYh7ggsMyaSj';
        $bad   = [
            'http://chronontology.dainst.org/period/rYh7ggsMyaSj',
            'https://chronontology.dainst.org/period/rYh7ggsMyaSj',
            'http://aaa.chronontology.dainst.org/period/rYh7ggsMyaSj',
        ];
        foreach ($bad as $i) {
            $this->assertEquals($valid, UriNormalizer::gNormalize($i));
        }
        try {
            $this->assertInstanceOf(Request::class, UriNormalizer::gResolve($valid));
            $this->assertTrue(false);
        } catch (UriNormalizerException $e) {
            $this->assertEquals("$valid doesn't match any rule", $e->getMessage());
        }
        try {
            $this->assertInstanceOf(DatasetNode::class, UriNormalizer::gFetch($valid));
            $this->assertTrue(false);
        } catch (UriNormalizerException $e) {
            $this->assertEquals("$valid doesn't match any rule", $e->getMessage());
        }
    }

    /**
     * 
     * @depends testInit
     */
    public function testRor(): void {
        $norm  = new UriNormalizer();
        $valid = 'https://ror.org/05xs36f43';
        $this->assertEquals($valid, $norm->normalize($valid));
        $this->assertInstanceOf(Request::class, $norm->resolve($valid));
        $meta  = $norm->fetch($valid);
        $this->assertInstanceOf(DatasetNode::class, $meta);
        $this->assertGreaterThan(1, count($meta));

        $bad = 'https://ror.org/123';
        try {
            $this->assertInstanceOf(Request::class, $norm->resolve($bad));
        } catch (UriNormalizerException $e) {
            $this->assertEquals("Failed to fetch data from https://api.ror.org/v2/organizations/123 with status code 404", $e->getMessage());
        }
    }

    /**
     * 
     * @depends testInit
     */
    public function testArche(): void {
        $toTest = [
            'https://arche.acdh.oeaw.ac.at/api/1234'                       => 'https://arche.acdh.oeaw.ac.at/api/1234',
            'https://arche.acdh.oeaw.ac.at/api/1234/metadata'              => 'https://arche.acdh.oeaw.ac.at/api/1234',
            'https://arche-curation.acdh-dev.oeaw.ac.at/api/1234'          => 'https://arche-curation.acdh-dev.oeaw.ac.at/api/1234',
            'https://arche-curation.acdh-dev.oeaw.ac.at/api/1234/metadata' => 'https://arche-curation.acdh-dev.oeaw.ac.at/api/1234',
            'https://arche-dev.acdh-dev.oeaw.ac.at/api/1234'               => 'https://arche-dev.acdh-dev.oeaw.ac.at/api/1234',
            'https://arche-dev.acdh-dev.oeaw.ac.at/api/1234/metadata'      => 'https://arche-dev.acdh-dev.oeaw.ac.at/api/1234',
            'https://id.acdh.oeaw.ac.at/foo'                               => 'https://id.acdh.oeaw.ac.at/foo',
            'http://127.0.0.1/1234'                                        => 'http://127.0.0.1/1234',
            'http://127.0.0.1/1234/metadata'                               => 'http://127.0.0.1/1234',
            'http://127.0.0.1/api/1234'                                    => 'http://127.0.0.1/api/1234',
            'http://127.0.0.1/api/1234/metadata'                           => 'http://127.0.0.1/api/1234',
            'http://localhost/1234'                                        => 'http://localhost/1234',
            'http://localhost/api/1234/metadata'                           => 'http://localhost/api/1234',
        ];
        foreach ($toTest as $uri => $expected) {
            $this->assertEquals($expected, UriNormalizer::gNormalize($uri));
        }
    }

    /**
     * 
     * @depends testInit
     */
    public function testResource(): void {
        $graph = new Dataset();
        $graph->add(DF::quad(DF::namedNode('foo'), DF::namedNode(self::ID_PROP), DF::namedNode('http://aaa.geonames.org/276136/borj-ej-jaaiyat.html')));
        UriNormalizer::gNormalizeMeta($graph);
        $this->assertEquals('https://sws.geonames.org/276136/', $graph[0]->getObject()->getValue());
    }

    public function testFactory(): void {
        UriNormalizer::init(null, self::ID_PROP);

        $bad = [
            'https://orcid.org/0000-0002-5274-8278',
            'http://aaa.orcid.org/0000-0002-5274-8278',
            'https://orcid.org/0000-0002-5274-8278/',
        ];
        foreach ($bad as $i) {
            $this->assertEquals('https://orcid.org/0000-0002-5274-8278', UriNormalizer::gNormalize($i));
        }
    }

    public function testError(): void {
        UriNormalizer::init();
        try {
            UriNormalizer::gNormalizeMeta(new Dataset());
            $this->assertTrue(false);
        } catch (UriNormalizerException $e) {
            $this->assertEquals('Id property not defined', $e->getMessage());
        }
    }

    public function testNoMatch(): void {
        UriNormalizer::init();
        $uri = 'http://foo/bar';
        try {
            UriNormalizer::gNormalize($uri);
            $this->assertTrue(false);
        } catch (UriNormalizerException $e) {
            $this->assertEquals("$uri doesn't match any rule", $e->getMessage());
        }
    }

    public function testWrongResolveRule(): void {
        $rules = [
            ['match' => '.*', 'replace' => '', 'resolve' => 'http://foo/bar', 'format' => 'baz']
        ];
        $uri   = 'http://bar/foo';
        UriNormalizer::init($rules);
        try {
            UriNormalizer::gResolve($uri);
            $this->assertTrue(false);
        } catch (UriNormalizerException $e) {
            $this->assertStringStartsWith("Failed to fetch data from http://foo/bar with message cURL error 6: Could not resolve host: foo", $e->getMessage());
        }
    }

    public function testResolveCache(): void {
        $emptyBody = Utils::streamFor('');
        $cache     = $this->initCache();
        $url       = 'https://d-nb.info/gnd/4491366-7';
        $n         = new UriNormalizer(cache: $cache);

        $t0 = microtime(true);
        $r1 = $n->resolve($url)->withBody($emptyBody);
        $t1 = microtime(true);
        $r2 = $n->resolve($url)->withBody($emptyBody);
        $t2 = microtime(true);
        $r3 = $n->resolve('https://d-nb.info/gnd/4491366-7/about/lds.ttl')->withBody($emptyBody);
        $t3 = microtime(true);

        $this->assertEquals($r1, $r2);
        $this->assertEquals($r2, $r3);

        $t3 = $t3 - $t2;
        $t2 = $t2 - $t1;
        $t1 = $t1 - $t0;
        $this->assertLessThan($t1 / 100, $t2);
        $this->assertLessThan($t1 / 100, $t3);
        $this->assertLessThan(0.0001, $t2);
        $this->assertLessThan(0.0001, $t3);
    }

    public function testFetchCache(): void {
        $cache = $this->initCache();
        $url   = 'https://d-nb.info/gnd/4491366-7';
        $n     = new UriNormalizer(cache: $cache);

        $t0 = microtime(true);
        $r1 = $n->fetch($url);
        $t1 = microtime(true);
        $r2 = $n->fetch($url);
        $t2 = microtime(true);
        $r3 = $n->fetch('https://d-nb.info/gnd/4491366-7/about/lds.ttl');
        $t3 = microtime(true);

        $this->assertTrue($r1->equals($r2));
        $this->assertSame($r2, $r3);

        $t3 = $t3 - $t2;
        $t2 = $t2 - $t1;
        $t1 = $t1 - $t0;
        $this->assertLessThan($t1 / 100, $t2);
        $this->assertLessThan($t1 / 100, $t3);
        $this->assertLessThan(0.0001, $t2);
        $this->assertLessThan(0.0001, $t3);
    }

    /**
     * Tests retry behavior.
     * 
     * It's worth remembering that the initial return code >=400 causes HEAD
     * to be turned into GET and is not counted as a retry. Once the switch
     * to GET occured, retries are done with GET.
     */
    public function testRetry(): void {
        $headers = ['content-type' => ['*/*']];
        $uri     = 'https://id.acdh.oeaw.ac.at/foo';

        // simple failure
        $retry  = new RetryConfig();
        $client = $this->createStub(ClientInterface::class);
        $client->method('sendRequest')->willThrowException($this->createStub(ClientExceptionInterface::class));
        $n      = new UriNormalizer(client: $client, retryCfg: $retry);
        try {
            $n->resolve($uri);
            $this->assertTrue(false);
        } catch (UriNormalizerException $e) {
            $this->assertEquals("Failed to fetch data from $uri with message ", $e->getMessage());
        }

        // simple failure with retry
        $retry  = new RetryConfig(2);
        $client = $this->createStub(ClientInterface::class);
        $client->method('sendRequest')->willReturn(
            new Response(502), // HEAD
            new Response(502), // GET
            new Response(502), // 1st retry GET
            new Response(502), // 2nd retry GET
        );
        $n      = new UriNormalizer(client: $client, retryCfg: $retry);
        try {
            $n->resolve($uri);
            $this->assertTrue(false);
        } catch (UriNormalizerException $e) {
            $this->assertEquals("Failed to fetch data from $uri with status code 502", $e->getMessage());
        }

        // retry with no delay
        $retry    = new RetryConfig(1);
        $client   = $this->createStub(ClientInterface::class);
        $client->method('sendRequest')->willReturn(
            new Response(502), // HEAD
            new Response(502), // GET
            new Response(200, $headers), // retry
        );
        $n        = new UriNormalizer(client: $client, retryCfg: $retry);
        $t0       = microtime(true);
        $response = $n->resolve($uri);
        $t1       = microtime(true);
        $this->assertInstanceOf(Request::class, $response);
        $this->assertLessThan(0.001, $t1 - $t0);

        // retry with delay
        $retry    = new RetryConfig(1, 0.5);
        $client   = $this->createStub(ClientInterface::class);
        $client->method('sendRequest')->willReturn(
            new Response(502), // HEAD
            new Response(502), // GET
            new Response(200, $headers), // 1st retry GET
        );
        $n        = new UriNormalizer(client: $client, retryCfg: $retry);
        $t0       = microtime(true);
        $response = $n->resolve($uri);
        $t1       = microtime(true);
        $this->assertInstanceOf(Request::class, $response);
        $this->assertLessThan(0.6, $t1 - $t0);
        $this->assertGreaterThan(0.5, $t1 - $t0);

        // multiple retries with delay and HEAD failure
        $retry    = new RetryConfig(2, 0.2, RetryConfig::SCALE_MULTI);
        $client   = $this->createStub(ClientInterface::class);
        $client->method('sendRequest')->willReturn(
            new Response(504), // HEAD
            new Response(503), // GET
            new Response(429), // 1st retry GET
            new Response(200, $headers), // 2nd retry GET
        );
        $n        = new UriNormalizer(client: $client, retryCfg: $retry);
        $t0       = microtime(true);
        $response = $n->resolve($uri);
        $t1       = microtime(true);
        $this->assertInstanceOf(Request::class, $response);
        $this->assertLessThan(0.7, $t1 - $t0);
        $this->assertGreaterThan(0.6, $t1 - $t0);

        // failure on no-retry code
        $retry  = new RetryConfig(2, 0, RetryConfig::SCALE_CONST, [429]);
        $client = $this->createStub(ClientInterface::class);
        $client->method('sendRequest')->willReturn(
            new Response(504), // HEAD
            new Response(504), // GET
        );
        $n      = new UriNormalizer(client: $client, retryCfg: $retry);
        try {
            $n->resolve($uri);
            $this->assertTrue(false);
        } catch (UriNormalizerException $e) {
            $this->assertEquals("Failed to fetch data from $uri with status code 504", $e->getMessage());
        }

        // failure on wrong mime
        $retry  = new RetryConfig(2, 0, RetryConfig::SCALE_CONST);
        $client = $this->createStub(ClientInterface::class);
        $client->method('sendRequest')->willReturn(new Response(200));
        $n      = new UriNormalizer(client: $client, retryCfg: $retry);
        try {
            $n->resolve($uri);
            $this->assertTrue(false);
        } catch (UriNormalizerException $e) {
            $this->assertEquals("Failed to fetch RDF data from $uri response content type not/set doesn't match expected application/n-triples", $e->getMessage());
        }
    }
}
