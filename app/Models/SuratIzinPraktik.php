<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class SuratIzinPraktik extends Model
{
    use LogsActivity;

    protected $table = 'surat_izin_praktik';

    protected $casts = [
        'kebutuhan_upload' => 'array',
        'document_upload' => 'array',
        'ssw_dikirim_pada' => 'datetime',
        'ssw_diverifikasi_pada' => 'datetime',
    ];

    /** Sudah pernah berhasil dikirim ke SSW? */
    public function sudahTerkirimKeSsw(): bool
    {
        return filled($this->ssw_dikirim_pada);
    }

    /** @param  \Illuminate\Database\Eloquent\Builder  $query */
    public function scopeBelumTerkirimKeSsw($query)
    {
        return $query->whereNull('ssw_dikirim_pada');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logAll();
    }

    public function getRouteKeyName(): string
    {
        return 'nomor_register';
    }
}
