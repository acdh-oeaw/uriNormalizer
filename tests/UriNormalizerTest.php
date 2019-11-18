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

/**
 * Description of IndexerTest
 *
 * @author zozlak
 */
class UriNormalizerTest extends \PHPUnit\Framework\TestCase {

    const ID_PROP = 'https://id/prop';
    
    public function testInit(): void {
        $mappings = [
            '|^https?://([^.]*[.])?geonames[.]org/([0-9]+)(/.*)?$|'                              => 'https://www.geonames.org/\2',
            '|^https?://([^.]*[.])?gazetteer[.]dainst[.]org/([A-Za-z]+/)*([0-9]+)([^0-9].*)?$|'  => 'https://gazetteer.dainst.org/place/\3',
            '|^https?://([^.]*[.])?pleiades[.]stoa[.]org/places/([0-9]+)(/.*)?$|'                => 'https://www.pleiades.stoa.org/places/\2',
            '|^https?://([^.]*[.])?viaf[.]org/viaf/([0-9]+)(/.*)?$|'                             => 'https://viaf.org/viaf/\2',
            '|^https?://([^.]*[.])?d-nb[.]info/gnd/([0-9]+-[0-9]+)$|'                            => 'https://d-nb.info/gnd/\2',
            '|^https?://([^.]*[.])?wikidata[.]org/([A-Za-z:]+/)*(Q[0-9]+)([^[0-9].*)?$|'         => 'https://www.wikidata.org/entity/\3',
            '|^https?://([^.]*[.])?orcid[.]org/([0-9]{4})-?([0-9]{4})-?([0-9]{4})-?([0-9]{4})$|' => 'https://orcid.org/\2-\3-\4-\5',
            '|^https?://([^.]*[.])?n2t[.]net/ark:/99152/(p0[a-z0-9]+)$|'                         => 'https://n2t.net/ark:/99152/\2',
            '|^https?://([^.]*[.])?chronontology[.]dainst[.]org/period/([A-Za-z0-9]+)$|'         => 'https://chronontology.dainst.org/period/\2',
        ];
        UriNormalizer::init($mappings, self::ID_PROP);
        $this->assertNotEmpty(UriNormalizer::gNormalize('https://sample.uri'));
    }

    /**
     * @depends testInit
     */
    public function testGeonames(): void {
        $bad = [
            'http://aaa.geonames.org/276136/borj-ej-jaaiyat.html',
        ];
        foreach ($bad as $i) {
            $this->assertEquals('https://www.geonames.org/276136', UriNormalizer::gNormalize($i));
        }
    }

    /**
     * @depends testInit
     */
    public function testGazetteer(): void {
        $bad = [
            'https://gazetteer.dainst.org/place/2282705',
            'https://gazetteer.dainst.org/doc/2282705.rdf',
            'https://gazetteer.dainst.org/doc/shapefile/2282705',
            'https://gazetteer.dainst.org/doc/2282705',
            'http://aaa.gazetteer.dainst.org/doc/2282705',
        ];
        foreach ($bad as $i) {
            $this->assertEquals('https://gazetteer.dainst.org/place/2282705', UriNormalizer::gNormalize($i));
        }
    }

    /**
     * @depends testInit
     */
    public function testPleiades(): void {
        $bad = [
            'https://pleiades.stoa.org/places/658494',
            'http://pleiades.stoa.org/places/658494',
            'http://pleiades.stoa.org/places/658494/carthage',
            'http://pleiades.stoa.org/places/658494/ruins-of-ancient-church-at-kafr-nabo',
            'http://aaa.pleiades.stoa.org/places/658494',
        ];
        foreach ($bad as $i) {
            $this->assertEquals('https://www.pleiades.stoa.org/places/658494', UriNormalizer::gNormalize($i));
        }
    }

    /**
     * @depends testInit
     */
    public function testViaf(): void {
        $bad = [
            'http://viaf.org/viaf/8110691',
            'https://viaf.org/viaf/8110691',
            'http://viaf.org/viaf/8110691/rdf.xml',
            'http://viaf.org/viaf/8110691/marc21.xml',
            'http://aaa.viaf.org/viaf/8110691',
        ];
        foreach ($bad as $i) {
            $this->assertEquals('https://viaf.org/viaf/8110691', UriNormalizer::gNormalize($i));
        }
    }

    /**
     * @depends testInit
     */
    public function testGnd(): void {
        $bad = [
            'http://d-nb.info/gnd/4491366-7',
            'https://d-nb.info/gnd/4491366-7',
            'http://aaa.d-nb.info/gnd/4491366-7',
        ];
        foreach ($bad as $i) {
            $this->assertEquals('https://d-nb.info/gnd/4491366-7', UriNormalizer::gNormalize($i));
        }
    }

    /**
     * @depends testInit
     */
    public function testWikidata(): void {
        $bad = [
            'https://www.wikidata.org/wiki/Q42',
            'http://www.wikidata.org/entity/Q42',
            'http://www.wikidata.org/wiki/Special:EntityData/Q42',
            'http://www.wikidata.org/wiki/Special:EntityData/Q42.json',
            'http://www.wikidata.org/wiki/Special:EntityData/Q42.json?revision=112',
            'http://aaa.wikidata.org/wiki/Q42',
        ];
        foreach ($bad as $i) {
            $this->assertEquals('https://www.wikidata.org/entity/Q42', UriNormalizer::gNormalize($i));
        }
    }

    /**
     * @depends testInit
     */
    public function testOrcid(): void {
        $bad = [
            'https://orcid.org/0000-0002-5274-8278',
            'http://aaa.orcid.org/0000-0002-5274-8278',
            'https://orcid.org/0000000252748278',
            'https://orcid.org/0000-00025274-8278',
        ];
        foreach ($bad as $i) {
            $this->assertEquals('https://orcid.org/0000-0002-5274-8278', UriNormalizer::gNormalize($i));
        }
    }

    /**
     * @depends testInit
     */
    public function testPeriodo(): void {
        $bad = [
            'http://n2t.net/ark:/99152/p0m63njncbv',
            'https://n2t.net/ark:/99152/p0m63njncbv',
            'http://aaa.n2t.net/ark:/99152/p0m63njncbv',
        ];
        foreach ($bad as $i) {
            $this->assertEquals('https://n2t.net/ark:/99152/p0m63njncbv', UriNormalizer::gNormalize($i));
        }
    }

    /**
     * @depends testInit
     */
    public function testChronontology(): void {
        $bad = [
            'http://chronontology.dainst.org/period/rYh7ggsMyaSj',
            'https://chronontology.dainst.org/period/rYh7ggsMyaSj',
            'http://aaa.chronontology.dainst.org/period/rYh7ggsMyaSj',
        ];
        foreach ($bad as $i) {
            $this->assertEquals('https://chronontology.dainst.org/period/rYh7ggsMyaSj', UriNormalizer::gNormalize($i));
        }
    }

    /**
     * @depends testInit
     */
    public function testResource(): void {
        $graph = new Graph();
        $res = $graph->resource('.');
        $res->addResource(self::ID_PROP, 'http://aaa.geonames.org/276136/borj-ej-jaaiyat.html');
        UriNormalizer::gNormalizeMeta($res);
        $this->assertEquals('https://www.geonames.org/276136', $res->getResource(self::ID_PROP));
    }
}
