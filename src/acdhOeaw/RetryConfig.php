<?php

/*
 * The MIT License
 *
 * Copyright 2026 zozlak.
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

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Client\ClientExceptionInterface;

/**
 * Container for URL resolution retry settings
 *
 * @author zozlak
 */
class RetryConfig {

    const SCALE_CONST = 'const';
    const SCALE_MULTI = 'multi';
    const SCALE_POWER = 'power';

    /**
     * 
     * @param array<int> $on
     */
    public function __construct(public int $number = 0, public float $delay = 0,
                                public string $scale = self::SCALE_CONST,
                                public array $on = [429, 502, 503, 504]) {
        
    }

    public function retry(ResponseInterface | ClientExceptionInterface $response,
                          int $attempt, RequestInterface $request): bool {
        if ($response instanceof ResponseInterface) {
            $code = $response->getStatusCode();
            if (in_array($code, [200, 201, 204])) {
                return false;
            }
            if (in_array($code, $this->on) && $attempt <= $this->number) {
                $this->sleep($attempt);
                return true;
            }
            throw new UriNormalizerException("Failed to fetch data from " . $request->getUri() . " with status code $code");
        } elseif ($response instanceof ClientExceptionInterface) {
            if ($attempt > $this->number) {
                throw new UriNormalizerException("Failed to fetch data from " . $request->getUri() . " with message " . $response->getMessage());
            }
            $this->sleep($attempt);
            return true;
        }
    }

    public function sleep(int $attempt): void {
        $fn = match ($this->scale) {
            self::SCALE_CONST => fn($x) => $this->delay,
            self::SCALE_MULTI => fn($x) => $this->delay * $x,
            self::SCALE_POWER => fn($x) => pow($this->delay, $x),
            default => throw new UriNormalizerException("Unknown scale $this->scale"),
        };
        usleep($fn($attempt) * 1000000);
    }
}
