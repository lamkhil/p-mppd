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
        'document_upload' => 'array'
    ];

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
