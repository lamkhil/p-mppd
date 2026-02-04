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
        Schema::table('surat_izin_praktik', function (Blueprint $table) {
            $table->string('tanggal_terbit_sip')->nullable()->change();
            $table->string('tanggal_akhir_sip')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('surat_izin_praktik', function (Blueprint $table) {
            
            $table->date('tanggal_terbit_sip')->nullable()->change();
            $table->date('tanggal_akhir_sip')->nullable()->change();
        });
    }
};
