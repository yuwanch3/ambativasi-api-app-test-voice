<?php
/**
 * Loader untuk file .env
 * Mendukung baris komentar (#), nilai kosong, dan nilai yang mengandung "=".
 * Nilai disimpan ke $_ENV (bukan putenv, karena putenv bisa di-disable oleh host).
 */
if (!is_array($_ENV)) {
    $_ENV = [];
}

$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, ';')) continue;
        $pos = strpos($line, '=');
        if ($pos === false) continue;
        $key = trim(substr($line, 0, $pos));
        $value = trim(substr($line, $pos + 1));
        // Hapus tanda kutip pembungkus bila ada
        if ((str_starts_with($value, '"') && str_ends_with($value, '"'))
            || (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
            $value = substr($value, 1, -1);
        }
        if ($key !== '') {
            $_ENV[$key] = $value;
        }
    }
}

/**
 * Helper: ambil nilai env dengan fallback default.
 * Prioritas: $_ENV (dari file .env) lalu getenv() (variabel proses).
 */
function env_get(string $key, ?string $default = null): ?string {
    if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
        return $_ENV[$key];
    }
    $val = getenv($key);
    return ($val === false || $val === '') ? $default : $val;
}
