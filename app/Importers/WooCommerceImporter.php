<?php

namespace App\Importers;

use App\Helpers;
use PDO;
use RuntimeException;

class WooCommerceImporter
{
    /**
     * @param PDO   $pdo
     * @param array $file
     * @return array{errors:array<int,string>, imported:int, updated:int, warning:?string}
     */
    public static function import(PDO $pdo, $file)
    {
        $result = [
            'errors' => [],
            'imported' => 0,
            'updated' => 0,
            'warning' => null,
        ];

        $fileError = isset($file['error']) ? $file['error'] : UPLOAD_ERR_NO_FILE;

        if (empty($file) || $fileError !== UPLOAD_ERR_OK) {
            $result['errors'][] = 'CSV dosyası yüklenemedi. Lütfen tekrar deneyin.';
            return $result;
        }

        $handle = fopen($file['tmp_name'], 'r');
        if (!$handle) {
            $result['errors'][] = 'CSV dosyası okunamadı.';
            return $result;
        }

        try {
            $firstLine = fgets($handle);
            if ($firstLine === false) {
                throw new RuntimeException('CSV başlık bilgisi okunamadı.');
            }

            $delimiter = (substr_count((string)$firstLine, ';') > substr_count((string)$firstLine, ',')) ? ';' : ',';
            rewind($handle);

            $headers = fgetcsv($handle, 0, $delimiter);
            if (!$headers) {
                throw new RuntimeException('CSV başlık bilgisi okunamadı.');
            }

            $map = [];
            foreach ($headers as $index => $header) {
                $map[strtolower(trim((string)$header))] = $index;
            }

            if (!array_key_exists('name', $map)) {
                throw new RuntimeException('CSV dosyasında ürün adını içeren "Name" sütunu bulunamadı.');
            }

            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                if (!is_array($row) || !isset($row[$map['name']]) || trim((string)$row[$map['name']]) === '') {
                    continue;
                }

                $name = trim((string)$row[$map['name']]);
                $sku = '';
                if (isset($map['sku'])) {
                    $skuValue = isset($row[$map['sku']]) ? $row[$map['sku']] : '';
                    $sku = trim((string)$skuValue);
                }

                $priceRaw = '';
                if (isset($map['regular price'])) {
                    $priceRaw = isset($row[$map['regular price']]) ? (string)$row[$map['regular price']] : '';
                } elseif (isset($map['price'])) {
                    $priceRaw = isset($row[$map['price']]) ? (string)$row[$map['price']] : '';
                }

                $priceSanitized = preg_replace('/[^0-9.,]/', '', $priceRaw);
                $priceSanitized = str_replace(',', '.', (string)$priceSanitized);
                $costPriceTry = (float)$priceSanitized;
                if ($costPriceTry < 0) {
                    $costPriceTry = 0.0;
                }
                $salePrice = Helpers::priceFromCostTry($costPriceTry);

                $categoryNames = ['Genel'];
                if (isset($map['categories']) && !empty($row[$map['categories']])) {
                    $rawCategory = (string)$row[$map['categories']];
                    $parts = preg_split('/[>|,]/', $rawCategory) ?: [];
                    $parsed = [];
                    foreach ($parts as $part) {
                        $clean = trim((string)$part);
                        if ($clean !== '') {
                            $parsed[] = $clean;
                        }
                    }
                    if ($parsed) {
                        $categoryNames = $parsed;
                    }
                }

                $description = Helpers::defaultProductDescription();

                $status = 'active';
                if (isset($map['status'])) {
                    $statusValue = strtolower(trim((string)$row[$map['status']]));
                    $status = $statusValue === 'publish' ? 'active' : 'inactive';
                }

                $categoryId = self::resolveCategoryPath($pdo, $categoryNames);

                $existingProductId = null;
                if ($sku !== '') {
                    $productStmt = $pdo->prepare('SELECT id FROM products WHERE sku = :sku LIMIT 1');
                    $productStmt->execute(['sku' => $sku]);
                    $existingProductId = $productStmt->fetchColumn();
                }

                if (!$existingProductId) {
                    $productStmt = $pdo->prepare('SELECT id FROM products WHERE name = :name AND category_id = :category LIMIT 1');
                    $productStmt->execute([
                        'name' => $name,
                        'category' => $categoryId,
                    ]);
                    $existingProductId = $productStmt->fetchColumn();
                }

                if ($existingProductId) {
                    $pdo->prepare('UPDATE products SET name = :name, category_id = :category_id, cost_price_try = :cost_price_try, price = :price, description = :description, sku = :sku, status = :status, updated_at = NOW() WHERE id = :id')
                        ->execute([
                            'id' => $existingProductId,
                            'name' => $name,
                            'category_id' => $categoryId,
                            'cost_price_try' => $costPriceTry,
                            'price' => $salePrice,
                            'description' => $description ?: null,
                            'sku' => $sku !== '' ? $sku : null,
                            'status' => $status,
                        ]);
                    $result['updated']++;
                } else {
                    $pdo->prepare('INSERT INTO products (name, category_id, cost_price_try, price, description, sku, status, created_at) VALUES (:name, :category_id, :cost_price_try, :price, :description, :sku, :status, NOW())')
                        ->execute([
                            'name' => $name,
                            'category_id' => $categoryId,
                            'cost_price_try' => $costPriceTry,
                            'price' => $salePrice,
                            'description' => $description ?: null,
                            'sku' => $sku !== '' ? $sku : null,
                            'status' => $status,
                        ]);
                    $result['imported']++;
                }
            }

            if ($result['imported'] === 0 && $result['updated'] === 0) {
                $result['warning'] = 'CSV dosyası işlendi ancak yeni ürün eklenmedi.';
            }
        } catch (RuntimeException $exception) {
            $result['errors'][] = $exception->getMessage();
        } finally {
            fclose($handle);
        }

        return $result;
    }

    /**
     * @param PDO     $pdo
     * @param string[] $names
     * @return int
     */
    private static function resolveCategoryPath(PDO $pdo, $names)
    {
        if (!is_array($names) || !$names) {
            $names = ['Genel'];
        }

        $parentId = null;

        foreach ($names as $name) {
            $name = trim((string)$name);
            if ($name === '') {
                continue;
            }

            $stmt = $pdo->prepare('SELECT id FROM categories WHERE name = :name AND parent_id <=> :parent LIMIT 1');
            $stmt->execute([
                'name' => $name,
                'parent' => $parentId,
            ]);
            $categoryId = $stmt->fetchColumn();

            if ($categoryId) {
                $parentId = (int)$categoryId;
                continue;
            }

            $insert = $pdo->prepare('INSERT INTO categories (name, parent_id, created_at) VALUES (:name, :parent_id, NOW())');
            $insert->execute([
                'name' => $name,
                'parent_id' => $parentId,
            ]);
            $parentId = (int)$pdo->lastInsertId();
        }

        if ($parentId === null) {
            $stmt = $pdo->prepare('SELECT id FROM categories WHERE name = :name AND parent_id IS NULL LIMIT 1');
            $stmt->execute(['name' => 'Genel']);
            $existing = $stmt->fetchColumn();
            if ($existing) {
                return (int)$existing;
            }

            $pdo->prepare('INSERT INTO categories (name, created_at) VALUES (:name, NOW())')->execute(['name' => 'Genel']);
            return (int)$pdo->lastInsertId();
        }

        return $parentId;
    }
}
