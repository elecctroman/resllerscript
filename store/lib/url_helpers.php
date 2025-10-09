<?php

use App\Helpers;

if (!function_exists('store_slugify')) {
    function store_slugify(string $value): string
    {
        if (class_exists(Helpers::class)) {
            $slug = Helpers::slugify($value);
            if ($slug !== '') {
                return $slug;
            }
        }

        $slug = strtolower(trim($value));
        $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug);
        $slug = trim((string) $slug, '-');

        return $slug !== '' ? $slug : 'icerik';
    }
}

if (!function_exists('url_category')) {
    /**
     * @param array<string,mixed>|int $category
     */
    function url_category($category, ?string $lang = null): string
    {
        $id = 0;
        $slug = '';

        if (is_array($category)) {
            if (isset($category['id'])) {
                $id = (int) $category['id'];
            }
            if (isset($category['slug'])) {
                $slug = (string) $category['slug'];
            }
            if ($slug === '' && isset($category['name'])) {
                $slug = store_slugify((string) $category['name']);
            }
        } else {
            $id = (int) $category;
        }

        if ($id <= 0) {
            return store_url('kategori');
        }

        if ($slug === '') {
            $slug = 'kategori';
        }

        $path = 'kategori/' . rawurlencode($slug) . '/' . $id;
        if ($lang !== null && $lang !== '') {
            $path = $lang . '/' . $path;
        }

        return store_url($path);
    }
}

if (!function_exists('url_product')) {
    /**
     * @param array<string,mixed>|int $product
     */
    function url_product($product, ?string $lang = null): string
    {
        $id = 0;
        $slug = '';

        if (is_array($product)) {
            if (isset($product['id'])) {
                $id = (int) $product['id'];
            }
            if (isset($product['slug'])) {
                $slug = (string) $product['slug'];
            }
            if ($slug === '' && isset($product['name'])) {
                $slug = store_slugify((string) $product['name']);
            }
        } else {
            $id = (int) $product;
        }

        if ($id <= 0) {
            return store_url('urun');
        }

        if ($slug === '') {
            $slug = 'urun';
        }

        $path = 'urun/' . rawurlencode($slug) . '/' . $id;
        if ($lang !== null && $lang !== '') {
            $path = $lang . '/' . $path;
        }

        return store_url($path);
    }
}

if (!function_exists('url_search')) {
    function url_search(string $query, ?string $lang = null): string
    {
        $query = trim($query);
        $slug = $query !== '' ? store_slugify($query) : 'arama';

        $path = 'arama/' . rawurlencode($slug);
        if ($lang !== null && $lang !== '') {
            $path = $lang . '/' . $path;
        }

        $url = store_url($path);

        if ($query !== '' && stripos($url, '?q=') === false) {
            $url .= '?q=' . rawurlencode($query);
        }

        return $url;
    }
}
