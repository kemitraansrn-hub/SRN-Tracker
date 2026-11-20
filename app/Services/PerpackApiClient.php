<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Client tipis buat Perpack/Reglow Open API (static key, lihat
 * config/services.php 'perpack'). Pakai Http facade langsung (bukan
 * Guzzle/package baru) sama seperti GoogleCalendarService -- production
 * di-deploy FTP-only tanpa composer install, nambah vendor/ gede lewat FTP
 * rawan korup.
 */
class PerpackApiClient
{
    private const ORDERS_PATH = '/open/orders/reseller/list';

    private const ORDER_DETAILS_PATH = '/open/orders/reseller/detail/list';

    private const PAGE_LENGTH = 100;

    public function isConfigured(): bool
    {
        return (bool) (config('services.perpack.base_url') && config('services.perpack.api_key'));
    }

    /**
     * Daftar order reseller (satu baris per order), ditarik semua halaman.
     */
    public function listOrders(array $query): array
    {
        return $this->paginate(self::ORDERS_PATH, $query);
    }

    /**
     * Rincian produk per order reseller (satu baris per produk), ditarik semua halaman.
     */
    public function listOrderDetails(array $query): array
    {
        return $this->paginate(self::ORDER_DETAILS_PATH, $query);
    }

    private function paginate(string $path, array $query): array
    {
        $query['channel_id'] ??= -2;
        $query['length'] = self::PAGE_LENGTH;

        $rows = [];
        $page = 1;

        do {
            $query['page'] = $page;
            $body = $this->get($path, $query);
            $rows = array_merge($rows, $body['data'] ?? []);
            $totalPage = (int) ($body['metadata']['total_page'] ?? 1);
            $page++;
        } while ($page <= $totalPage);

        return $rows;
    }

    private function get(string $path, array $query): array
    {
        $baseUrl = rtrim((string) config('services.perpack.base_url'), '/');

        return Http::withHeaders(['X-Api-Key' => config('services.perpack.api_key')])
            ->timeout(30)
            ->get($baseUrl.$path, $query)
            ->throw()
            ->json();
    }
}
