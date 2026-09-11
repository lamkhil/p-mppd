<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kolom hasil verifikasi teknis yang dikirim balik SSW lewat webhook.
     *
     * Dipisah dari `keterangan` (catatan petugas MPPD) supaya jejak keputusan
     * dinas teknis tidak pernah tertimpa catatan internal, dan sebaliknya.
     */
    public function up(): void
    {
        Schema::table('surat_izin_praktik', function (Blueprint $table) {
            // Nilai mentah dari SSW ('disetujui' / 'ditolak') sebelum dipetakan
            // ke status internal — berguna saat menelusuri selisih data.
            $table->string('ssw_hasil_verifikasi', 50)->nullable();
            $table->dateTime('ssw_diverifikasi_pada')->nullable();
            $table->text('ssw_catatan_verifikasi')->nullable();
            $table->string('ssw_verifikator')->nullable();

            // Dipakai untuk menolak callback ganda / callback nyasar.
            $table->string('ssw_callback_event_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('surat_izin_praktik', function (Blueprint $table) {
            $table->dropIndex(['ssw_callback_event_id']);

            $table->dropColumn([
                'ssw_hasil_verifikasi',
                'ssw_diverifikasi_pada',
                'ssw_catatan_verifikasi',
                'ssw_verifikator',
                'ssw_callback_event_id',
            ]);
        });
    }
};
