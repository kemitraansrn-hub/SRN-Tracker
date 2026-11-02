<?php

namespace App\Services\AkuisisiLeadImport;

use RuntimeException;

/** Masalah pada file secara keseluruhan (format, judul kolom, jumlah baris), bukan per baris. */
class AkuisisiImportFileException extends RuntimeException {}
