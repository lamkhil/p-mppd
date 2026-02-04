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
            $table->string('status')->default('masuk');
            $table->json('kebutuhan_upload')->nullable();
            $table->dateTime('follow_up_whatsapp_pada')->nullable();
            $table->dateTime('follow_up_email_pada')->nullable();
            $table->json('document_upload')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('surat_izin_praktik', function (Blueprint $table) {
            $table->dropColumn([
                'status',
                'kebutuhan_upload',
                'follow_up_whatsapp_pada',
                'follow_up_email_pada',
                'document_upload',
            ]);
        });
    }
};
