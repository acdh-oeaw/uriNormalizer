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
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Utils;
use acdhOeaw\UriNormalizerException;
use acdhOeaw\UriNormalizerCache;

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
        $mappings = UriNormRules::getRules();
        UriNormalizer::init($mappings, self::ID_PROP);
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
        $this->assertInstanceOf(Resource::class, UriNormalizer::gFetch($valid));
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
        $this->assertInstanceOf(Resource::class, UriNormalizer::gFetch($valid));
    }

    /**
     * 
     * @depends testInit
     */
    public function testPleiades(): void {
        $valid = 'https://pleiades.stoa.org/places/658494';
        $bad   = [
            'https://pleiades.stoa.org/places/658494',
            'http://pleiades.stoa.org/places/658494',
            'http://pleiades.stoa.org/places/658494/carthage',
            'http://pleiades.stoa.org/places/658494/ruins-of-ancient-church-at-kafr-nabo',
            'http://aaa.pleiades.stoa.org/places/658494',
        ];
        foreach ($bad as $i) {
            $this->assertEquals($valid, UriNormalizer::gNormalize($i));
        }
        $this->assertInstanceOf(Request::class, UriNormalizer::gResolve($valid));
        $this->assertInstanceOf(Resource::class, UriNormalizer::gFetch($valid));
    }

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
        $this->assertInstanceOf(Resource::class, UriNormalizer::gFetch($valid));
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
        $this->assertInstanceOf(Resource::class, $norm->fetch($valid));

        // with within-gnd redirect to https://d-nb.info/gnd/118560077/about/lds.rdf
        $this->assertInstanceOf(Resource::class, $norm->fetch('https://d-nb.info/gnd/1089894554'));
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
        $this->assertInstanceOf(Resource::class, UriNormalizer::gFetch($valid));
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
        $this->assertInstanceOf(Resource::class, $norm->fetch($valid));
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
        $this->assertInstanceOf(Resource::class, UriNormalizer::gFetch($valid));
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
        $this->assertInstanceOf(Resource::class, UriNormalizer::gFetch($valid));
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
            $this->assertInstanceOf(Resource::class, UriNormalizer::gFetch($valid));
            $this->assertTrue(false);
        } catch (UriNormalizerException $e) {
            $this->assertEquals("$valid doesn't match any rule", $e->getMessage());
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
        $graph = new Graph();
        $res   = $graph->resource('.');
        $res->addResource(self::ID_PROP, 'http://aaa.geonames.org/276136/borj-ej-jaaiyat.html');
        UriNormalizer::gNormalizeMeta($res);
        $this->assertEquals('https://sws.geonames.org/276136/', $res->getResource(self::ID_PROP));
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
        $this->expectErrorMessage('Id property not defined');
        UriNormalizer::gNormalizeMeta((new Graph())->resource('.'));
    }

    public function testNoMatch(): void {
        UriNormalizer::init();
        $uri = 'http://foo/bar';
        $this->expectErrorMessage("$uri doesn't match any rule");
        UriNormalizer::gNormalize($uri);
    }

    public function testWrongResolveRule(): void {
        $rules = [
            ['match' => '.*', 'replace' => '', 'resolve' => 'http://foo/bar', 'format' => 'baz']
        ];
        $uri   = 'http://bar/foo';
        UriNormalizer::init($rules);
        $this->expectErrorMessageMatches("`^Failed to fetch RDF data from http://foo/bar with `");
        UriNormalizer::gResolve($uri);
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

        $this->assertEquals($r1->dump('text'), $r2->dump('text'));
        $this->assertSame($r2, $r3);

        $t3 = $t3 - $t2;
        $t2 = $t2 - $t1;
        $t1 = $t1 - $t0;
        $this->assertLessThan($t1 / 100, $t2);
        $this->assertLessThan($t1 / 100, $t3);
        $this->assertLessThan(0.0001, $t2);
        $this->assertLessThan(0.0001, $t3);
    }
}
