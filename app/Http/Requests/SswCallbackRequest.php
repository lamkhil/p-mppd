<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * Validasi payload callback SSW.
 *
 * Kontraknya sengaja longgar di bagian yang tidak kita butuhkan (field asing
 * diabaikan, bukan ditolak) dan ketat di bagian yang menentukan keputusan.
 */
class SswCallbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // penjaganya middleware ssw.webhook
    }

    protected function prepareForValidation(): void
    {
        // SSW boleh mengirim 'Disetujui', 'DITOLAK', dsb.
        if (is_string($this->input('status'))) {
            $this->merge(['status' => mb_strtolower(trim($this->input('status')))]);
        }
    }

    public function rules(): array
    {
        return [
            'nomor_register' => ['required', 'string', 'max:255'],
            'status' => ['required', 'string', Rule::in(array_keys(config('ssw.webhook.peta_status', [])))],

            'id_t_permohonan_det' => ['nullable'],
            'keterangan' => ['nullable', 'string', 'max:2000'],
            'verifikator' => ['nullable', 'string', 'max:255'],
            'tanggal_verifikasi' => ['nullable', 'date'],
            'event_id' => ['nullable', 'string', 'max:255'],

            // Hanya dipakai kalau hasilnya disetujui.
            'nomor_sip' => ['nullable', 'string', 'max:255'],
            'tanggal_terbit_sip' => ['nullable', 'date'],
            'tanggal_akhir_sip' => ['nullable', 'date', 'after_or_equal:tanggal_terbit_sip'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $hasil = config('ssw.webhook.peta_status')[$this->input('status')] ?? null;

            // Penolakan tanpa alasan tidak bisa ditindaklanjuti petugas.
            if ($hasil === 'ditolak' && blank($this->input('keterangan'))) {
                $validator->errors()->add('keterangan', 'Keterangan wajib diisi untuk permohonan yang ditolak.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'status.in' => 'Nilai status tidak dikenali. Gunakan "disetujui" atau "ditolak".',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Payload tidak valid.',
            'errors' => $validator->errors(),
        ], 422));
    }
}
