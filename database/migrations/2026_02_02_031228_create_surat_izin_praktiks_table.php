<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('surat_izin_praktik', function (Blueprint $table) {
            $table->id();
            $table->string('nik', 20);
            $table->string('nama');
            $table->text('alamat')->nullable();
            $table->string('email')->nullable();
            $table->string('nomor_telepon', 20)->nullable();

            $table->string('nomor_str')->nullable();
            $table->string('masa_berlaku_str')->nullable();

            $table->string('nomor_register')->unique();

            $table->string('profesi')->nullable();

            $table->string('tempat_praktik')->nullable();
            $table->text('alamat_tempat_praktik')->nullable();

            $table->string('nomor_sip')->nullable();
            $table->date('tanggal_terbit_sip')->nullable();
            $table->date('tanggal_akhir_sip')->nullable();

            $table->string('keterangan')->nullable();

            // Optional index (berguna kalau sering dicari)
            $table->index('nik');
            $table->index('nomor_register');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('surat_izin_praktik');
    }
};
