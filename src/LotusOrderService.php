<?php declare(strict_types=1);

namespace App;

final class LotusOrderService
{
    private LotusClient $client;
    private LotusOrderRepository $repo;
    private Logger $logger;

    public function __construct(LotusClient $client, LotusOrderRepository $repo, Logger $logger)
    {
        $this->client = $client;
        $this->repo = $repo;
        $this->logger = $logger;
    }

    /**
     * İdempotent sipariş oluşturma.
     * Aynı local_order_id ile ikinci kez çağrılırsa API'ye gitmez, mevcut kaydı döndürür.
     */
    public function placeExternalOrder(int $localOrderId, int $lotusProductId, ?string $note = null): array
    {
        $existing = $this->repo->findByLocalOrderId($localOrderId);
        if ($existing) {
            $this->logger->info('Idempotensi: local_order_id=' . $localOrderId . ' için mevcut kayıt döndürüldü.');
            return $existing;
        }

        $this->logger->info('Lotus\'a sipariş oluşturuluyor: local_order_id=' . $localOrderId . ', product_id=' . $lotusProductId);
        $resp = $this->client->createOrder($lotusProductId, $note);

        if (!($resp['success'] ?? false)) {
            $msg = $resp['message'] ?? 'Bilinmeyen hata';
            $this->logger->error('Lotus createOrder başarısız: ' . $msg);
            throw new \RuntimeException($msg);
        }

        $data = $resp['data'] ?? [];
        $lotusOrderId = (int) ($data['order_id'] ?? 0);
        $status = (string) ($data['status'] ?? 'failed');
        $content = $data['content'] ?? null;

        $this->repo->insert($localOrderId, $lotusOrderId, $status, is_string($content) ? $content : null);

        return $this->repo->findByLocalOrderId($localOrderId) ?? [
            'local_order_id' => $localOrderId,
            'lotus_order_id' => $lotusOrderId,
            'status' => $status,
            'content' => $content,
        ];
    }

    /** pending siparişleri kontrol ederek tamamlananları günceller */
    public function pollPending(callable $onCompleted): void
    {
        $pending = $this->repo->findPending(100);
        foreach ($pending as $row) {
            $localId = (int) $row['local_order_id'];
            $lotusId = (int) $row['lotus_order_id'];

            try {
                $this->logger->info('Pending kontrol: local=' . $localId . ' lotus=' . $lotusId);
                $resp = $this->client->getOrder($lotusId);
                $data = $resp['data'] ?? null;
                if (is_array($data)) {
                    $status = (string) ($data['status'] ?? $row['status']);
                    $content = $data['content'] ?? $row['content'];
                    $this->repo->updateStatusContent($localId, $status, is_string($content) ? $content : null);
                    $this->repo->touchCheckedAt($localId);

                    if ($status === 'completed') {
                        $this->logger->info('Tamamlandı: local=' . $localId);
                        $onCompleted($localId, is_string($content) ? $content : '');
                    }
                }
            } catch (\Throwable $e) {
                $this->logger->error('Pending kontrol hatası: ' . $e->getMessage());
            }
        }
    }
}
