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

use acdhOeaw\UriNormalizerCache;

/**
 * Description of IndexerTest
 *
 * @author zozlak
 */
class UriNormalizerCacheTest extends \PHPUnit\Framework\TestCase {

    private function initCache(bool $withDb, bool $clear): UriNormalizerCache {
        $dbPath = $withDb ? __DIR__ . '/../tests.sqlite' : null;
        $cache  = new UriNormalizerCache($dbPath);
        if ($clear) {
            $cache->clear();
        }
        return $cache;
    }

    public function testMemoryOnly(): void {
        $cache1 = $this->initCache(false, false);
        $cache2 = $this->initCache(false, false);

        $this->assertFalse($cache1->has('foo'));
        $this->assertNull($cache1->get('foo'));
        $this->assertEquals('bar', $cache1->get('foo', 'bar'));
        $res = iterator_to_array($cache1->getMultiple(['foo', 'bar'], 'baz'));
        $this->assertEquals(['baz', 'baz'], $res);

        $this->assertTrue($cache1->set('foo', 'bar'));
        $this->assertTrue($cache1->has('foo'));
        $this->assertEquals('bar', $cache1->get('foo'));
        $this->assertEquals('bar', $cache1->get('foo', 'baz'));
        $res = iterator_to_array($cache1->getMultiple(['foo', 'bar'], 'baz'));
        $this->assertEquals(['bar', 'baz'], $res);

        $this->assertFalse($cache2->has('foo'));
        $this->assertNull($cache2->get('foo'));

        $this->assertTrue($cache1->delete('nonexisting'));
        $this->assertTrue($cache1->has('foo'));
        $this->assertTrue($cache1->delete('foo'));
        $this->assertFalse($cache1->has('foo'));

        $this->assertTrue($cache1->setMultiple(['FOO', 'BAR']));
        $this->assertTrue($cache1->has(0));
        $this->assertTrue($cache1->has(1));
        $this->assertFalse($cache1->has(2));

        $this->assertTrue($cache1->deleteMultiple(['FOO', 'BAR']));
        $this->assertTrue($cache1->has(0));
        $this->assertTrue($cache1->has(1));
        $this->assertFalse($cache1->has(2));

        $this->assertTrue($cache1->deleteMultiple([0, 1]));
        $this->assertFalse($cache1->has(0));
        $this->assertFalse($cache1->has(1));
        $this->assertFalse($cache1->has(2));

        $this->assertTrue($cache1->set('foo', 'BAR'));
        $this->assertEquals('BAR', $cache1->get('foo'));
        $this->assertTrue($cache1->clear());
        $this->assertFalse($cache1->has('foo'));
    }

    public function testSqlite(): void {
        $cache1 = $this->initCache(true, true);
        $cache2 = $this->initCache(true, false);

        $this->assertFalse($cache1->has('foo'));
        $this->assertNull($cache1->get('foo'));
        $this->assertEquals('bar', $cache1->get('foo', 'bar'));
        $res = iterator_to_array($cache1->getMultiple(['foo', 'bar'], 'baz'));
        $this->assertEquals(['baz', 'baz'], $res);

        $this->assertTrue($cache1->set('foo', 'bar'));
        $this->assertTrue($cache1->has('foo'));
        $this->assertEquals('bar', $cache1->get('foo'));
        $this->assertEquals('bar', $cache1->get('foo', 'baz'));
        $res = iterator_to_array($cache1->getMultiple(['foo', 'bar'], 'baz'));
        $this->assertEquals(['bar', 'baz'], $res);

        $this->assertTrue($cache2->has('foo'));
        $this->assertEquals('bar', $cache2->get('foo'));

        $this->assertTrue($cache2->set('foo', 'baz'));
        // because memory cache takse precedense
        $this->assertEquals('bar', $cache1->get('foo', null));
        // new cache will pick it up though
        $cache3 = $this->initCache(true, false);
        $this->assertEquals('baz', $cache3->get('foo', null));

        $this->assertTrue($cache1->delete('nonexisting'));
        $this->assertTrue($cache1->has('foo'));
        $this->assertTrue($cache1->delete('foo'));
        $this->assertFalse($cache1->has('foo'));

        $this->assertTrue($cache1->setMultiple(['FOO', 'BAR']));
        $this->assertTrue($cache1->has(0));
        $this->assertTrue($cache1->has(1));
        $this->assertFalse($cache1->has(2));

        $this->assertTrue($cache1->deleteMultiple(['FOO', 'BAR']));
        $this->assertTrue($cache1->has(0));
        $this->assertTrue($cache1->has(1));
        $this->assertFalse($cache1->has(2));

        $this->assertTrue($cache1->deleteMultiple([0, 1]));
        $this->assertFalse($cache1->has(0));
        $this->assertFalse($cache1->has(1));
        $this->assertFalse($cache1->has(2));

        $this->assertTrue($cache1->set('foo', 'BAR'));
        $this->assertEquals('BAR', $cache1->get('foo'));
        $this->assertTrue($cache1->clear());
        $this->assertFalse($cache1->has('foo'));
    }
}
