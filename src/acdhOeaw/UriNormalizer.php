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

use EasyRdf\Resource;

/**
 * A simply utility class normalizing the URIs
 *
 * @author zozlak
 */
class UriNormalizer {

    static private $obj;
    
    /**
     * Initializes a global singleton instance of the UriNormalizer.
     * 
     * @param array $mappings array of value mappings. Normalization is made by
     *   running a `preg_replace($mappingKey, $mappingValue, $valueToMap)`
     * @param string $idProp a default RDF property to be used by the 
     *   `normalizeMeta()` method
     */
    static public function init(array $mappings = [], string $idProp = '') {
        self::$obj = new UriNormalizer($mappings, $idProp);
    }

    /**
     * A static version of the normalize() method.
     * 
     * Call `UriNormalizer::init()` before first use.
     * 
     * @param string $uri
     * @return string
     * @see normalize()
     */
    static public function gNormalize(string $uri): string {
        return self::$obj->normalize($uri);
    }
    
    /**
     * A static version of the normalizeMeta() method.
     * 
     * Call `UriNormalizer::init()` before first use.
     * 
     * @param Resource $res metadata to be processed
     * @param string $idProp id property URI (if not provided, value passed to 
     *   the object constructor is used)
     * @see normalizeMeta()
     */
    static public function gNormalizeMeta(Resource $res, string $idProp = ''): void {
        self::$obj->normalizeMeta($res, $idProp);
    }
    
    /**
     *
     * @var array
     */
    private $mappings;
    
    /**
     *
     * @var string
     */
    private $idProp;
    
    /**
     * @param array $mappings array of value mappings. Normalization is made by
     *   running a `preg_replace($mappingKey, $mappingValue, $valueToMap)`
     * @param string $idProp a default RDF property to be used by the 
     *   `normalizeMeta()` method
     */
    public function __construct(array $mappings = [], string $idProp = '') {
        $this->mappings = $mappings;
        $this->idProp = $idProp;
    }
    
    /**
     * Returns a normalized URIs.
     * 
     * If the passed URI doesn't match any rule it is returned without
     * modification.
     * 
     * @param string $uri URI to be normalized
     * @return string
     */
    public function normalize(string $uri): string {
        foreach ($this->mappings as $match => $replace) {
            $count = 0;
            $norm = preg_replace($match, $replace, $uri, 1, $count);
            if ($count) {
                return $norm;
            }
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
     */
    public function normalizeMeta(Resource $res, string $idProp = ''): void {
        $idProp = empty($idProp) ? $this->idProp : $idProp;
        foreach ($res->allResources($idProp) as $id) {
            $res->deleteResource($idProp, $id);
            $res->addResource($idProp, $this->normalize((string) $id));
        }
    }

}
