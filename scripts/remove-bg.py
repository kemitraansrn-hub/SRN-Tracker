r"""
Background removal buat foto mitra di kartu member (efek "pop" di atas
background bentuk organik) — dipanggil dari
App\Http\Controllers\GrowthSpecialistController::update() tiap kali ada
foto baru di-upload. Sengaja pakai GrabCut (OpenCV) bukan model ML besar
(rembg dkk) biar gak butuh download model & tetap ringan buat dijalankan
per-request.

Gagal (exception apapun / OpenCV gak ada) -> ditangkep di PHP (proses
exit code != 0), fallback ke foto asli tanpa background dihapus.

Usage: python remove-bg.py <input_path> <output_path>
"""
import sys

def main():
    if len(sys.argv) != 3:
        print('usage: remove-bg.py <input> <output>', file=sys.stderr)
        sys.exit(1)

    input_path, output_path = sys.argv[1], sys.argv[2]

    import cv2
    import numpy as np

    img = cv2.imread(input_path)
    if img is None:
        print('could not read image', file=sys.stderr)
        sys.exit(1)

    h, w = img.shape[:2]
    mask = np.zeros((h, w), np.uint8)
    bgd_model = np.zeros((1, 65), np.float64)
    fgd_model = np.zeros((1, 65), np.float64)
    rect = (int(w * 0.04), int(h * 0.02), int(w * 0.92), int(h * 0.96))

    cv2.grabCut(img, mask, rect, bgd_model, fgd_model, 8, cv2.GC_INIT_WITH_RECT)

    fg_mask = np.where((mask == 2) | (mask == 0), 0, 255).astype('uint8')

    # Isi lubang kecil (mis. mata gelap sempat kebaca "background") tanpa
    # ngikis detail halus — cuma CLOSE, sengaja gak pakai OPEN.
    kernel = np.ones((15, 15), np.uint8)
    fg_mask = cv2.morphologyEx(fg_mask, cv2.MORPH_CLOSE, kernel)

    # Buang bercak kecil yang gak nempel ke badan utama.
    num_labels, labels, stats, _ = cv2.connectedComponentsWithStats(fg_mask, connectivity=8)
    if num_labels > 1:
        largest = 1 + int(np.argmax(stats[1:, cv2.CC_STAT_AREA]))
        fg_mask = np.where(labels == largest, 255, 0).astype('uint8')

    # Kalau foreground yang kedeteksi kekecilan/kegedean banget, kemungkinan
    # GrabCut salah baca foto ini (background gak seragam, dst) -- lebih
    # aman nyerah (biar PHP fallback ke foto asli) daripada hasil pas-pasan.
    fg_ratio = (fg_mask > 0).mean()
    if fg_ratio < 0.05 or fg_ratio > 0.85:
        print(f'foreground ratio {fg_ratio:.2f} out of sane range, aborting', file=sys.stderr)
        sys.exit(1)

    fg_mask = cv2.GaussianBlur(fg_mask, (7, 7), 0)

    img_rgba = cv2.cvtColor(img, cv2.COLOR_BGR2BGRA)
    img_rgba[:, :, 3] = fg_mask

    cv2.imwrite(output_path, img_rgba)
    print('ok')


if __name__ == '__main__':
    main()
