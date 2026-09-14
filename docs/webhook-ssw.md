# Webhook Hasil Verifikasi Teknis — P-MPPD ⇄ SSW

Spesifikasi endpoint yang disediakan **P-MPPD (DPMPTSP Kota Surabaya)** untuk menerima keputusan verifikasi teknis dari **SSW (kantorku.surabaya.go.id)** atas permohonan Surat Izin Praktik (SIP) yang sebelumnya dikirim P-MPPD lewat `POST /integrasi/ssw-mppd`.

- Versi: 1.0 — 14 September 2026
- Base URL produksi: `https://pmppd.dpmptsp-surabaya.my.id/api`
- Format: JSON (`Content-Type: application/json`, `Accept: application/json`)
- Sumber kode: `routes/api.php`, `app/Http/Controllers/Api/`, `app/Http/Requests/SswCallbackRequest.php`, `app/Services/Ssw/SswCallbackService.php`

---

## 1. Alur singkat

```
P-MPPD ──POST /integrasi/ssw-mppd (id_layanan 36)──▶ SSW
        ◀── id_t_permohonan_det ────────────────────
        ... berkas menunggu keputusan dinas teknis ...
SSW    ──POST /api/login ───────────────────────────▶ P-MPPD   (dapat Bearer token)
SSW    ──POST /api/ssw/callback + Bearer ───────────▶ P-MPPD   (status disetujui / ditolak)
```

Kunci yang dipakai kedua sisi:

| Kunci | Asal | Dipakai untuk |
|---|---|---|
| `nomor_register` | Dikirim P-MPPD di payload MPPD | **Wajib** di callback — mengenali berkas |
| `id_t_permohonan_det` | Dikembalikan SSW saat insert MPPD | Opsional di callback — pengaman agar callback tidak nyasar |

---

## 2. Autentikasi

Semua endpoint stateless (tanpa sesi, tanpa CSRF). SSW login dengan **akun sistem P-MPPD** yang kami sediakan, lalu memakai token hasilnya sebagai `Authorization: Bearer <token>`.

### 2.1 `POST /api/login`

Rate limit: **10 request/menit** per IP.

**Request**

```json
{
  "email": "ssw-integrasi@dpmptsp.surabaya.go.id",
  "password": "********",
  "device_name": "ssw-produksi"
}
```

| Field | Wajib | Keterangan |
|---|---|---|
| `email` | ya* | Email akun sistem. `username` diterima sebagai alias. |
| `password` | ya | |
| `device_name` | tidak | Nama token, memudahkan pencabutan per konsumen. Default `api-<ip>`. |

**Response 200**

```json
{
  "success": true,
  "token": "12|Kq8f...c1Z",
  "token_name": "ssw-produksi",
  "expired": null,
  "user": { "id": 3, "name": "Integrasi SSW", "email": "ssw-integrasi@dpmptsp.surabaya.go.id" }
}
```

Token **tidak memiliki masa berlaku** (`expired: null`) — simpan dan pakai ulang; tidak perlu login setiap kali mengirim callback. Jika suatu saat menerima `401`, login ulang sekali lalu ulangi request.

**Response 422** — kredensial salah

```json
{ "message": "Kredensial tidak cocok.", "errors": { "email": ["Kredensial tidak cocok."] } }
```

### 2.2 `GET /api/me`

Memastikan token masih hidup. Response `200 { "success": true, "data": { id, name, email } }`, atau `401` bila token tidak valid.

### 2.3 `POST /api/logout`

Mencabut token yang sedang dipakai. Response `200 { "success": true, "message": "Token dicabut." }`.

---

## 3. `POST /api/ssw/callback`

Mengirim keputusan verifikasi teknis satu berkas. Rate limit **60 request/menit** per token.

**Header**

```
Authorization: Bearer <token>
Content-Type: application/json
Accept: application/json
```

### 3.1 Field

| Field | Tipe | Wajib | Keterangan |
|---|---|---|---|
| `nomor_register` | string ≤255 | **ya** | Nomor register SIP, persis seperti yang dikirim P-MPPD. |
| `status` | string | **ya** | Keputusan. Tidak peka huruf besar/kecil. Nilai yang dikenali — **disetujui**: `disetujui`, `setuju`, `diterima`, `approved`, `verified`; **ditolak**: `ditolak`, `tolak`, `rejected`. |
| `keterangan` | string ≤2000 | **ya jika ditolak** | Alasan / catatan verifikator. Ditampilkan ke petugas MPPD. |
| `id_t_permohonan_det` | string/angka | tidak | ID permohonan dari SSW. Jika dikirim dan P-MPPD punya nilainya, keduanya harus cocok (lihat `409`). Sangat disarankan diisi. |
| `verifikator` | string ≤255 | tidak | Nama petugas / dinas yang memutuskan. |
| `tanggal_verifikasi` | date | tidak | `YYYY-MM-DD` atau `YYYY-MM-DD HH:MM:SS`. Kosong = waktu callback diterima. |
| `event_id` | string ≤255 | tidak | ID unik per kejadian dari sisi SSW. Dipakai untuk menolak callback ganda — **disarankan diisi**. |
| `nomor_sip` | string ≤255 | tidak | Hanya diproses bila disetujui. |
| `tanggal_terbit_sip` | date | tidak | Hanya diproses bila disetujui. |
| `tanggal_akhir_sip` | date | tidak | Hanya diproses bila disetujui; harus ≥ `tanggal_terbit_sip`. |

Field lain yang tidak tercantum **diabaikan** (tidak menyebabkan error).

### 3.2 Contoh — disetujui

```json
{
  "nomor_register": "SIP-2026-000123",
  "id_t_permohonan_det": "98765",
  "status": "disetujui",
  "verifikator": "Dinas Kesehatan Kota Surabaya",
  "tanggal_verifikasi": "2026-09-14 10:32:00",
  "keterangan": "Berkas lengkap dan sesuai.",
  "event_id": "ssw-evt-7f3a9c",
  "nomor_sip": "503/1234/SIP-DR/436.7.2/2026",
  "tanggal_terbit_sip": "2026-09-14",
  "tanggal_akhir_sip": "2031-09-13"
}
```

### 3.3 Contoh — ditolak

```json
{
  "nomor_register": "SIP-2026-000124",
  "id_t_permohonan_det": "98766",
  "status": "ditolak",
  "verifikator": "Dinas Kesehatan Kota Surabaya",
  "tanggal_verifikasi": "2026-09-14 11:05:00",
  "keterangan": "STR sudah tidak berlaku, mohon perbarui.",
  "event_id": "ssw-evt-7f3a9d"
}
```

### 3.4 Response

**200 — diproses**

```json
{
  "success": true,
  "message": "Status permohonan berhasil diperbarui.",
  "data": {
    "nomor_register": "SIP-2026-000123",
    "status": "terverifikasi_teknis",
    "duplikat": false,
    "diproses_pada": "2026-09-14T10:32:05+07:00"
  }
}
```

**200 — duplikat (sudah pernah diproses, tidak ada perubahan)**

```json
{
  "success": true,
  "message": "Callback sudah pernah diproses, tidak ada perubahan.",
  "data": { "nomor_register": "SIP-2026-000123", "status": "terverifikasi_teknis", "duplikat": true, "diproses_pada": "..." }
}
```

**Kode error**

| HTTP | Kapan | Body |
|---|---|---|
| `401` | Token tidak ada / tidak valid / sudah dicabut | `{ "message": "Unauthenticated." }` |
| `403` | IP atau akun tidak masuk daftar yang diizinkan | `{ "success": false, "message": "Alamat IP tidak diizinkan." }` / `"Akun ini tidak diizinkan mengirim callback."` |
| `404` | `nomor_register` tidak ditemukan | `{ "success": false, "message": "Permohonan dengan nomor register X tidak ditemukan." }` |
| `409` | `id_t_permohonan_det` tidak cocok dengan yang tersimpan | `{ "success": false, "message": "id_t_permohonan_det tidak cocok untuk X (dikirim: ..., tersimpan: ...)." }` |
| `422` | Payload tidak valid | `{ "success": false, "message": "Payload tidak valid.", "errors": { "<field>": ["..."] } }` |
| `429` | Melebihi rate limit | standar Laravel `Too Many Attempts.` |
| `503` | Endpoint callback sedang dinonaktifkan oleh P-MPPD | `{ "success": false, "message": "Endpoint callback SSW sedang dinonaktifkan." }` |

Anjuran retry di sisi SSW: ulangi hanya untuk `429`, `503`, `5xx`, dan kegagalan koneksi (backoff eksponensial). Jangan ulangi `4xx` lainnya — perbaiki payload dulu.

---

## 4. Idempotensi

Callback yang sama boleh dikirim lebih dari sekali; P-MPPD mengenali pengulangan dengan urutan berikut:

1. Jika `event_id` dikirim dan sama dengan `event_id` yang terakhir diproses untuk berkas itu → dianggap duplikat.
2. Jika tidak ada `event_id`: bila status berkas sudah sama dengan hasil keputusan dan sudah pernah diverifikasi → dianggap duplikat.

Duplikat dijawab `200` dengan `duplikat: true` dan **tidak mengubah apa pun**. Keputusan baru yang berbeda (misalnya `ditolak` setelah `disetujui`) **akan diproses** dan menimpa status sebelumnya — kirim hanya keputusan final.

---

## 5. Efek di P-MPPD

| Keputusan | Status berkas P-MPPD | Data yang disimpan |
|---|---|---|
| disetujui | `menunggu_verifikasi_teknis` → `terverifikasi_teknis` | hasil, tanggal verifikasi, keterangan, verifikator, event_id, + `nomor_sip`/`tanggal_terbit_sip`/`tanggal_akhir_sip` bila dikirim |
| ditolak | `menunggu_verifikasi_teknis` → `ditolak_teknis` | hasil, tanggal verifikasi, keterangan, verifikator, event_id |

Setiap callback yang diproses dicatat di activity log berkas (payload lengkap tersimpan) dan dapat dilihat petugas di halaman riwayat berkas.

---

## 6. Contoh cURL

```bash
BASE=https://pmppd.dpmptsp-surabaya.my.id/api

# 1) login — simpan token
TOKEN=$(curl -s -X POST "$BASE/login" \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"email":"ssw-integrasi@dpmptsp.surabaya.go.id","password":"********","device_name":"ssw-produksi"}' \
  | jq -r .token)

# 2) kirim keputusan
curl -s -X POST "$BASE/ssw/callback" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{
    "nomor_register": "SIP-2026-000123",
    "id_t_permohonan_det": "98765",
    "status": "disetujui",
    "verifikator": "Dinas Kesehatan Kota Surabaya",
    "tanggal_verifikasi": "2026-09-14 10:32:00",
    "keterangan": "Berkas lengkap dan sesuai.",
    "event_id": "ssw-evt-7f3a9c",
    "nomor_sip": "503/1234/SIP-DR/436.7.2/2026",
    "tanggal_terbit_sip": "2026-09-14",
    "tanggal_akhir_sip": "2031-09-13"
  }'
```

---

## 7. Yang perlu disepakati / disiapkan

**Dari tim SSW ke P-MPPD**

- Daftar IP publik server SSW yang akan memanggil callback (untuk whitelist `SSW_WEBHOOK_IPS`).
- Konfirmasi kosakata `status` yang dipakai SSW jika berbeda dari daftar di §3.1 (bisa ditambah tanpa perubahan kode).
- Apakah SSW mengirim `event_id` dan `id_t_permohonan_det` (sangat disarankan keduanya).

**Dari P-MPPD ke tim SSW**

- Akun sistem (`email` + `password`) khusus integrasi — dikirim lewat jalur aman, bukan dokumen ini.
- URL staging untuk uji coba sebelum produksi.

**Kontak**: DPMPTSP Kota Surabaya — tim pengembang P-MPPD.
