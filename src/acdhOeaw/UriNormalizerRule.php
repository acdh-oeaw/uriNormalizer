<?php

/*
 * The MIT License
 *
 * Copyright 2022 zozlak.
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

use stdClass;

/**
 * A container for a URI normalization rule
 *
 * @author zozlak
 */
class UriNormalizerRule {

    /**
     * 
     * @param self|stdClass|array<string, string> $rule
     * @return self
     */
    static public function factory(self | stdClass | array $rule): self {
        if (is_array($rule)) {
            return new self($rule['match'], $rule['replace'], $rule['resolve'] ?? '', $rule['format'] ?? '');
        } elseif ($rule instanceof stdClass) {
            return new self($rule->match, $rule->replace, $rule->resolve ?? '', $rule->format ?? '');
        }
        return $rule;
    }

    public function __construct(public string $match, public string $replace,
                                public string $resolve, public string $format) {
        
    }
}
