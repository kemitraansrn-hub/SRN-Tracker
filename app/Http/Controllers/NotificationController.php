<?php

namespace App\Http\Controllers;

use App\Services\NotificationCenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Hapus" notifikasi dari dropdown lonceng — sama persis mekanismenya
 * kayak dismiss otomatis pas buka halaman tujuan (lihat NotificationCenter
 * ::dismiss()), cuma sekarang dipicu manual lewat tombol di dropdown-nya
 * sendiri, dipanggil via fetch() biar item-nya ilang seketika tanpa reload
 * halaman.
 */
class NotificationController extends Controller
{
    public function dismiss(Request $request, string $kategori): JsonResponse
    {
        NotificationCenter::dismiss($request->user(), $kategori);

        return response()->json(['ok' => true]);
    }

    public function dismissAll(Request $request): JsonResponse
    {
        foreach (NotificationCenter::forUser($request->user()) as $item) {
            NotificationCenter::dismiss($request->user(), $item['kategori']);
        }

        return response()->json(['ok' => true]);
    }
}
