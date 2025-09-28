<?php

namespace App\Importers;

use PDO;
use RuntimeException;

class WooCommerceImporter
{
    /**
     * @param PDO   $pdo
     * @param array $file
     * @return array{errors:array<int,string>, imported:int, updated:int, warning:?string}
     */
    public static function import(PDO $pdo, array $file): array
    {
        $result = [
            'errors' => [],
            'imported' => 0,
            'updated' => 0,
            'warning' => null,
        ];

        if (empty($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
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
                $sku = isset($map['sku']) ? trim((string)($row[$map['sku']] ?? '')) : '';

                $priceRaw = '';
                if (isset($map['regular price'])) {
                    $priceRaw = (string)($row[$map['regular price']] ?? '');
                } elseif (isset($map['price'])) {
                    $priceRaw = (string)($row[$map['price']] ?? '');
                }

                $priceSanitized = preg_replace('/[^0-9.,]/', '', $priceRaw ?? '');
                $priceSanitized = str_replace(',', '.', (string)$priceSanitized);
                $price = (float)$priceSanitized;

                $categoryName = 'Genel';
                if (isset($map['categories']) && !empty($row[$map['categories']])) {
                    $rawCategory = (string)$row[$map['categories']];
                    $parts = preg_split('/[>,|]/', $rawCategory) ?: [];
                    $categoryName = trim($parts[0] ?? '');
                    if ($categoryName === '') {
                        $categoryName = 'Genel';
                    }
                }



                $status = 'active';
                if (isset($map['status'])) {
                    $statusValue = strtolower(trim((string)$row[$map['status']]));
                    $status = $statusValue === 'publish' ? 'active' : 'inactive';
                }

                $categoryStmt = $pdo->prepare('SELECT id FROM categories WHERE name = :name LIMIT 1');
                $categoryStmt->execute(['name' => $categoryName]);
                $categoryId = $categoryStmt->fetchColumn();

                if (!$categoryId) {
                    $pdo->prepare('INSERT INTO categories (name, created_at) VALUES (:name, NOW())')->execute([
                        'name' => $categoryName,
                    ]);
                    $categoryId = $pdo->lastInsertId();
                }

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
                    $pdo->prepare('UPDATE products SET name = :name, category_id = :category_id, price = :price, description = :description, sku = :sku, status = :status, updated_at = NOW() WHERE id = :id')
                        ->execute([
                            'id' => $existingProductId,
                            'name' => $name,
                            'category_id' => $categoryId,
                            'price' => $price,
                            'description' => $description ?: null,
                            'sku' => $sku !== '' ? $sku : null,
                            'status' => $status,
                        ]);
                    $result['updated']++;
                } else {
                    $pdo->prepare('INSERT INTO products (name, category_id, price, description, sku, status, created_at) VALUES (:name, :category_id, :price, :description, :sku, :status, NOW())')
                        ->execute([
                            'name' => $name,
                            'category_id' => $categoryId,
                            'price' => $price,
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
}
