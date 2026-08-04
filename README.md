# Ambativasi API — Backend

Backend PHP + MySQL untuk aplikasi Ambativasi (React Native / Expo).

## Struktur
- `db.php` — koneksi MySQL (kredensial dari `.env`, fallback XAMPP default)
- `env.php` — loader `.env`
- `.env` — konfigurasi (TIDAK di-commit). Lihat `.env.example`
- `*.php` — endpoint API
- `vendor/` — PHPMailer (composer)
- `uploads/` — folder foto profil (writable)

## Endpoint
| Endpoint | Metode | Fungsi |
|----------|--------|--------|
| `cek-status.php` | GET | Health check server + DB |
| `register.php` | POST JSON | Daftar akun |
| `login.php` | POST JSON | Login |
| `forgot-password.php` | POST JSON | Kirim email reset (SMTP Gmail) |
| `update-password.php` | POST JSON | Reset password (token) |
| `change-password.php` | POST JSON | Ganti password (dengan password lama) |
| `get-profile.php` | GET | Ambil profil user |
| `update-username.php` | POST | Ganti username |
| `update-email.php` | POST JSON | Ganti email |
| `upload-profile.php` | POST form-data | Upload/hapus foto profil |
| `get-leaderboard.php` | GET | Peringkat + XP |
| `submit-xp.php` | POST JSON | Simpan XP latihan |
| `generate-soal.php` | POST JSON | Generate soal via Gemini |
| `chat-ai.php` | POST JSON | Chat AI via Gemini |
| `reset-redirect.php` | GET | Halaman perantara deep link reset password |

## Deploy ke hosting (InfinityFree, dll)
1. Buat akun & domain di InfinityFree (gratis).
2. Di cPanel → MySQL: buat database + user, catat host (mis. `sql.xxx.infinityfree.com`).
3. Upload semua file PHP + folder `vendor/` + `uploads/` ke `htdocs/ambativasi-api/` (File Manager / FTP).
4. Buat file `.env` di `htdocs/ambativasi-api/` berisi:
   ```
   DB_HOST=<host mysql infinityfree>
   DB_USER=<user mysql infinityfree>
   DB_PASS=<password>
   DB_NAME=<nama db>
   BASE_URL=https://<domain-kamu>.infinityfreeapp.com/ambativasi-api
   GEMINI_API_KEY=<key gemini>
   SMTP_EMAIL=<gmail app password email>
   SMTP_PASSWORD=<gmail app password>
   ```
5. Import skema DB: file `db.php` otomatis membuat tabel `user_xp`, tapi tabel `users` harus di-import dari XAMPP (export phpMyAdmin).
6. Pastikan folder `uploads/` writable (chmod 755/777) untuk upload foto profil.

## Persiapan lokal (XAMPP)
- PHP `openssl`, `curl`, `mysqli`, `mbstring` aktif.
- Jalankan Apache + MySQL.
- Endpoint diakses via `http://localhost/Ambativasi-api/...` atau via ngrok.
