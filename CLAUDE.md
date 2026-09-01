# ANTREE — Booking Slot & Antrian Layanan

> File ini adalah instruksi kerja untuk Claude Code. Baca seluruhnya sebelum menulis
> baris kode pertama. Bangun aplikasi ini seperti seorang full-stack developer
> profesional: rapi, teruji, dan siap dipakai orang non-teknis.

## 1. Konteks Masalah (jangan dilewati)

Klinik gigi, salon, barbershop, bengkel, dan praktik dokter mandiri di Indonesia masih
mengelola antrean dengan cara yang sama sejak dulu: pelanggan menelepon, resepsionis
menulis di buku agenda, lalu pelanggan datang dan duduk menunggu tanpa tahu berapa lama
lagi. Kalau resepsionis sedang melayani orang lain, telepon tidak diangkat dan calon
pelanggan pindah ke tempat lain.

Dua kerugian yang tidak disadari pemilik usaha:
- **Waktu tunggu yang tidak diketahui** membuat pelanggan enggan datang lagi, padahal
  layanannya sendiri bagus.
- **Telepon yang tidak terangkat adalah pendapatan yang hilang diam-diam**, dan tidak
  pernah tercatat di mana pun.

Yang dijual aplikasi ini ke pemilik usaha bukan "sistem booking", tapi: pelanggan bisa
memesan jam 11 malam saat toko tutup, dan resepsionis berhenti menjawab pertanyaan
"masih lama nggak?" sepanjang hari.

## 2. Pengguna & Peran

| Peran | Siapa | Yang dia lakukan |
|---|---|---|
| `owner` | Pemilik usaha | Atur layanan, staf, jam operasional, tarif; lihat laporan |
| `staff` | Resepsionis / petugas | Kelola booking harian, panggil antrean, catat kedatangan & selesai |
| — | Pelanggan | Booking sendiri lewat link publik, tanpa akun |
| — | Layar tunggu | Papan antrean read-only untuk TV/tablet di ruang tunggu |

## 3. Ruang Lingkup MVP

### Masuk MVP
1. Profil usaha: nama, alamat, kontak, jam operasional per hari, hari libur, zona waktu.
2. Master layanan: nama, durasi (menit), harga, jeda setelah layanan (buffer),
   butuh staf tertentu atau tidak.
3. Master staf beserta jadwal kerjanya per hari dan pengecualian (cuti, izin).
4. **Booking publik**: pelanggan pilih layanan → pilih tanggal → sistem menampilkan
   slot yang benar-benar tersedia → isi nama & no. HP → dapat kode booking + QR.
5. Verifikasi no. HP sederhana lewat kode OTP yang dikirim via link WhatsApp
   (bukan API berbayar) — cukup untuk menekan booking iseng.
6. **Antrean walk-in**: pelanggan datang tanpa booking, dapat nomor antrean, masuk ke
   antrean yang sama dengan yang sudah booking.
7. Layar operasional staf: daftar hari ini, tombol "panggil", "mulai layani", "selesai",
   "tidak hadir". Estimasi waktu tunggu dihitung ulang otomatis.
8. Papan antrean publik untuk TV: nomor yang sedang dilayani, nomor berikutnya,
   perkiraan waktu tunggu. Auto-refresh.
9. Halaman status booking pelanggan: posisi antrean sekarang, perkiraan giliran,
   tombol batalkan.
10. Pengingat H-1 dan H-2 jam lewat pesan WhatsApp siap kirim (tombol di panel staf).
11. Laporan: booking per hari, tingkat kehadiran, layanan terlaris, jam tersibuk,
    jumlah pelanggan tidak hadir.

### TIDAK masuk MVP (jangan dikerjakan)
- Pembayaran online / DP.
- Rekam medis atau riwayat perawatan detail. Cukup catatan bebas per booking.
- Multi-cabang.
- Aplikasi mobile native.
- Integrasi WhatsApp Business API berbayar. Cukup `wa.me` link.

## 4. Tech Stack (sudah ditetapkan, jangan diganti)

- Laravel 11 (PHP 8.2+)
- Livewire 3 + Blade
- Tailwind CSS 3 + Alpine.js seperlunya
- MySQL 8 (SQLite untuk testing)
- Laravel Breeze (stack Livewire) untuk owner/staff; pelanggan tanpa akun
- Livewire `wire:poll` untuk pembaruan papan antrean (5 detik). Jangan pasang
  WebSocket/Reverb di MVP — polling sudah cukup dan jauh lebih sederhana di-deploy.
- `simplesoftwareio/simple-qrcode`, `maatwebsite/excel`
- Pest untuk testing

## 5. Data Model

```
users            : id, name, email, password, role[owner|staff], business_id
businesses       : id, name, slug (unik, untuk URL publik), address, phone,
                   timezone (default "Asia/Jakarta"), slot_interval (menit, default 15),
                   max_days_ahead (default 30), settings (json)
services         : id, business_id, name, duration_minutes, buffer_minutes, price,
                   requires_staff, is_active, description
staff_members    : id, business_id, user_id (nullable), name, is_active
staff_service    : staff_member_id, service_id           (pivot: siapa bisa apa)
working_hours    : id, business_id, staff_member_id (nullable = jam usaha),
                   day_of_week (0-6), opens_at, closes_at, is_closed
time_off         : id, business_id, staff_member_id (nullable = tutup seluruh usaha),
                   starts_at, ends_at, reason
bookings         : id, business_id, service_id, staff_member_id (nullable),
                   customer_name, customer_phone, starts_at, ends_at,
                   source[online|walk_in|phone],
                   status[pending|confirmed|arrived|in_service|completed|no_show|cancelled],
                   queue_number, code (unik, mis. "K7F2"), public_token,
                   called_at, started_at, completed_at, note, created_by (nullable)
otp_codes        : id, phone, code_hash, expires_at, attempts, verified_at
audit_logs       : id, actor_type, actor_id, booking_id, from_status, to_status,
                   created_at
```

Aturan integritas yang wajib ditegakkan:
- **Tidak boleh ada dua booking yang tumpang tindih untuk staf yang sama.** Ini aturan
  paling penting di seluruh aplikasi. Tegakkan di dua lapis: pengecekan tumpang tindih
  di dalam `DB::transaction()` dengan penguncian baris, **dan** unique index pada
  `staff_member_id + starts_at` sebagai jaring pengaman terakhir. Validasi di UI saja
  tidak cukup — dua orang bisa menekan tombol pada detik yang sama.
- `ends_at` = `starts_at` + `duration_minutes`, dihitung sistem. `buffer_minutes`
  memblokir slot berikutnya tapi tidak masuk ke `ends_at` yang dilihat pelanggan.
- Slot yang ditawarkan wajib memperhitungkan seluruhnya: jam operasional usaha, jam
  kerja staf, cuti/izin, hari libur, booking yang sudah ada, buffer, dan waktu sekarang
  (slot yang sudah lewat tidak boleh muncul, termasuk slot yang mulai kurang dari 15
  menit lagi).
- `queue_number` diberikan per usaha per hari, berurutan, digenerate di dalam transaksi.
- Perpindahan status hanya boleh mengikuti alur yang sah:
  `pending → confirmed → arrived → in_service → completed`, dengan `cancelled` bisa
  dari `pending|confirmed|arrived`, dan `no_show` hanya dari `confirmed|arrived`.
  Tegakkan di Action, bukan di UI. Setiap perpindahan masuk `audit_logs`.
- Semua waktu disimpan UTC, dihitung dan ditampilkan dalam timezone usaha.
- Nomor HP dinormalisasi ke format `62xxx` saat disimpan, apa pun cara pengguna
  mengetiknya (`08xx`, `+62 8xx`, `8xx`).
- Harga disimpan sebagai integer rupiah.

## 6. Alur Utama

**Pelanggan booking online.** Buka `/{slug}` → pilih layanan (durasi dan harga terlihat)
→ pilih tanggal dari kalender yang menandai hari penuh dan hari tutup → sistem
menampilkan slot tersedia sebagai tombol besar → pilih slot → isi nama & no. HP →
verifikasi OTP → booking dikonfirmasi, tampil kode booking + QR + tombol simpan ke
kalender. Seluruh alur maksimal 4 layar, dan harus bisa diselesaikan di HP dengan satu
tangan.

**Perhitungan slot (bagian tersulit).** Buat satu Action `GetAvailableSlots(service,
date, staff?)` yang mengembalikan daftar slot. Langkahnya:
1. Ambil jam operasional untuk hari itu; kalau tutup, kembalikan kosong.
2. Untuk tiap staf yang bisa mengerjakan layanan itu, ambil jam kerjanya, kurangi
   dengan cuti/izin.
3. Potong menjadi kandidat slot dengan kelipatan `slot_interval`.
4. Buang kandidat yang bertabrakan dengan booking yang ada (termasuk buffer).
5. Buang kandidat yang sudah lewat atau kurang dari 15 menit dari sekarang.
6. Gabungkan lintas staf: satu jam tetap tersedia selama masih ada minimal satu staf
   yang bisa. Penentuan staf mana yang dipakai terjadi saat booking disimpan.

**Hari operasional staf.** Layar utama menampilkan daftar hari ini terurut waktu, dengan
tombol besar untuk tiap aksi. Menekan "Panggil" mengubah status, mencatat `called_at`,
dan memperbarui papan antrean beserta seluruh estimasi waktu tunggu.

**Estimasi waktu tunggu.** Perkiraan giliran seseorang = waktu sekarang + jumlah durasi
seluruh booking di depannya yang belum selesai. Kalau usaha itu sudah punya minimal 20
layanan selesai, gunakan durasi rata-rata sebenarnya per layanan, bukan durasi yang
diatur di master. Selalu tampilkan sebagai rentang ("sekitar 20–30 menit"), tidak
pernah sebagai angka pasti — janji yang terlalu presisi pasti meleset dan justru
membuat pelanggan kesal.

## 7. Standar Engineering

1. Business logic di Action class: `GetAvailableSlots`, `CreateBooking`,
   `TransitionBookingStatus`, `AssignQueueNumber`, `EstimateWaitTime`. Komponen
   Livewire hanya validasi + panggil Action.
2. `CreateBooking` wajib dibungkus `DB::transaction()` dengan `lockForUpdate()` pada
   booking staf terkait di rentang waktu itu, lalu cek ulang ketersediaan **di dalam**
   transaksi. Jangan pernah percaya hasil pengecekan yang dilakukan sebelum transaksi.
3. `GetAvailableSlots` harus murni dan mudah diuji: terima parameter, kembalikan array
   slot, tanpa menyentuh session atau request.
4. Timezone: simpan UTC, hitung dan tampilkan di timezone usaha. Uji dengan usaha
   berzona Asia/Jakarta dan Asia/Makassar sekaligus.
5. Rate limit endpoint booking publik dan pengiriman OTP per no. HP dan per IP.
6. Otorisasi lewat Policy: staf tidak boleh mengubah tarif, jam operasional, atau
   melihat laporan pendapatan.
7. Papan antrean pakai `wire:poll.5s` dan hanya mengirim data yang benar-benar
   ditampilkan. Layar ini menyala sepanjang hari — jangan bikin berat.
8. Cegah N+1, `Model::preventLazyLoading()` saat lokal.
9. Seeder realistis: 1 klinik gigi, 6 layanan dengan durasi berbeda, 3 staf dengan
   jadwal berbeda, 1 staf sedang cuti, dan booking 30 hari ke belakang plus 7 hari ke
   depan dengan campuran selesai / tidak hadir / batal.
10. Semua label UI dalam Bahasa Indonesia. Papan antrean berhuruf sangat besar dan
    berkontras tinggi — dibaca dari seberang ruangan.

## 8. Testing (minimum yang harus lulus)

- `GetAvailableSlots` mengembalikan kosong di hari tutup dan di hari libur.
- Slot yang bertabrakan dengan booking yang ada tidak muncul.
- Buffer setelah layanan benar-benar memblokir slot berikutnya.
- Slot di masa lalu dan slot kurang dari 15 menit dari sekarang tidak muncul.
- Cuti staf menghapus slot staf itu saja, bukan slot staf lain.
- **Dua booking bersamaan untuk slot dan staf yang sama: tepat satu berhasil, satu
  ditolak dengan pesan yang jelas.** Uji dengan transaksi paralel, bukan berurutan.
- Nomor antrean berurutan per hari dan mulai dari 1 lagi di hari berikutnya.
- Perpindahan status yang tidak sah ditolak (mis. `completed` → `arrived`).
- `no_show` tidak bisa dari status `pending`.
- Estimasi waktu tunggu berkurang saat booking di depan diselesaikan.
- Nomor HP `08123`, `+628123`, dan `628123` tersimpan sebagai nilai yang sama.
- OTP kedaluwarsa ditolak, dan percobaan berlebihan diblokir.
- Halaman status booking hanya menampilkan data booking itu sendiri.

## 9. Urutan Pengerjaan

1. Setup Laravel + Breeze + Tailwind, konfigurasi database, commit awal.
2. Migration + model + factory + seeder seluruh tabel.
3. CRUD usaha, layanan, staf, jam operasional, cuti.
4. **`GetAvailableSlots` beserta seluruh testnya — sebelum menyentuh UI booking.**
   Kalau bagian ini salah, seluruh aplikasi salah.
5. `CreateBooking` dengan penguncian transaksi + test booking bersamaan.
6. Halaman booking publik (4 layar) + kode booking + QR.
7. OTP verifikasi no. HP + rate limiting.
8. Layar operasional staf + `TransitionBookingStatus` + `audit_logs`.
9. Antrean walk-in + `AssignQueueNumber`.
10. `EstimateWaitTime` + papan antrean TV + halaman status pelanggan.
11. Pengingat WhatsApp + laporan + export Excel.
12. Polish + README dengan langkah instalasi, kredensial demo, screenshot papan antrean.

Selesaikan satu langkah sampai benar-benar jalan sebelum lanjut. Commit di setiap
langkah dengan pesan yang jelas.

## 10. Definisi Selesai

Seorang pelanggan bisa membuka link klinik jam 11 malam, memesan slot untuk lusa dalam
waktu kurang dari satu menit, dan mendapat kode booking. Keesokan harinya resepsionis
menjalankan seluruh antrean hanya dari satu layar tanpa menyentuh buku agenda. Papan di
ruang tunggu menunjukkan nomor yang sedang dilayani dan perkiraan waktu tunggu, dan
tidak ada lagi yang bertanya "masih lama nggak?". Dan tidak pernah, dalam kondisi apa
pun, dua pelanggan mendapat slot yang sama.
