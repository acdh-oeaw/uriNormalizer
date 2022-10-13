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

use DateTimeImmutable;
use DateInterval;
use PDO;
use Psr\SimpleCache\CacheInterface;

/**
 * A PSR-16 compliant memory + (optional) sqlite database cache
 * for the UriNormalizer class.
 *
 * @author zozlak
 */
class UriNormalizerCache implements CacheInterface {

    private PDO $pdo;

    /**
     * 
     * @var array<string, mixed>
     */
    private array $memCache = [];

    public function __construct(?string $sqliteFile = null) {
        if (!empty($sqliteFile)) {
            $init      = !file_exists($sqliteFile);
            $this->pdo = new PDO("sqlite:$sqliteFile");
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            if ($init) {
                $this->pdo->query("
                CREATE TABLE cache (key text primary key, value text, expires timestamp)
            ");
            }
            $this->pdo->query("DELETE FROM cache WHERE expires < datetime()");
        }
    }

    public function clear(): bool {
        $this->memCache = [];
        if (isset($this->pdo)) {
            $this->pdo->query("DELETE FROM cache");
        }
        return true;
    }

    public function delete(string $key): bool {
        unset($this->memCache[$key]);
        if (isset($this->pdo)) {
            $this->pdo->prepare("DELETE FROM cache WHERE key = ?")->execute([$key]);
        }
        return true;
    }

    public function deleteMultiple($keys): bool {
        foreach ($keys as $i) {
            $this->delete($i);
        }
        return true;
    }

    public function get(string $key, mixed $default = null): mixed {
        if (isset($this->memCache[$key])) {
            return $this->memCache[$key];
        }
        if (isset($this->pdo)) {
            $query  = $this->pdo->prepare("SELECT value FROM cache WHERE key = ? AND expires > datetime()");
            $query->execute([$key]);
            $result = $query->fetch(PDO::FETCH_NUM);
            if ($result === false) {
                return $default;
            }
            $result               = unserialize($result[0]);
            $this->memCache[$key] = $result;
            return $result;
        } else {
            return $default;
        }
    }

    public function getMultiple($keys, mixed $default = null): iterable {
        foreach ($keys as $key) {
            yield $key => $this->get($key, $default);
        }
    }

    public function has(string $key): bool {
        if (isset($this->memCache[$key])) {
            return true;
        }
        if (isset($this->pdo)) {
            $query = $this->pdo->prepare("SELECT count(*) FROM cache WHERE key = ? AND expires > datetime()");
            $query->execute([$key]);
            return $query->fetchColumn() > 0;
        } else {
            return false;
        }
    }

    public function set(string $key, mixed $value,
                        null | int | DateInterval $ttl = null): bool {
        $this->memCache[$key] = $value;

        if (isset($this->pdo)) {
            if (is_int($ttl)) {
                $ttl = new DateInterval("P{$ttl}S");
            }
            $ttl     ??= new DateInterval("P1D");
            $expires = (new DateTimeImmutable())->add($ttl)->format('Y-m-d h:i:s');
            $query   = $this->pdo->prepare("INSERT OR REPLACE INTO cache (key, value, expires) VALUES (?, ?, ?)");
            $query->execute([$key, serialize($value), $expires]);
        }

        return true;
    }

    /**
     * 
     * @param iterable<string, mixed> $values
     * @param null|int|DateInterval $ttl
     * @return bool
     */
    public function setMultiple(iterable $values,
                                null | int | DateInterval $ttl = null): bool {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
        }
        return true;
    }
}
