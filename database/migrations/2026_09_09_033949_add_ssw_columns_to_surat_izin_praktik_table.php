<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kolom pendukung integrasi SSW.
     *
     * `id_ijin` & `jenis_permohonan` adalah bagian payload MPPD yang tidak
     * punya padanan di data SIP, jadi harus disimpan per record.
     * Kolom `ssw_*` menyimpan bukti sinkronisasi supaya satu permohonan tidak
     * terkirim dua kali dan kegagalan terakhir bisa ditampilkan ke petugas.
     */
    public function up(): void
    {
        Schema::table('surat_izin_praktik', function (Blueprint $table) {
            $table->unsignedInteger('id_ijin')->nullable()->after('profesi');
            $table->string('jenis_permohonan')->nullable()->after('id_ijin');

            // Disimpan sebagai string: dokumentasi SSW mengembalikan angka,
            // tapi tipe di sisi mereka tidak dijamin tetap numerik.
            $table->string('ssw_id_permohonan_det', 50)->nullable();
            $table->string('ssw_id_dinkes_mppd_det', 50)->nullable();
            $table->dateTime('ssw_dikirim_pada')->nullable();
            $table->text('ssw_error')->nullable();

            $table->index('ssw_dikirim_pada');
        });
    }

    public function down(): void
    {
        Schema::table('surat_izin_praktik', function (Blueprint $table) {
            $table->dropIndex(['ssw_dikirim_pada']);

            $table->dropColumn([
                'id_ijin',
                'jenis_permohonan',
                'ssw_id_permohonan_det',
                'ssw_id_dinkes_mppd_det',
                'ssw_dikirim_pada',
                'ssw_error',
            ]);
        });
    }
};
