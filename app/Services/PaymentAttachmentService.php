<?php

namespace App\Services;

use App\Models\HotelBookingPayment;
use App\Models\HotelPayout;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Throwable;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentAttachmentService
{
    public function transaction(callable $callback): mixed
    {
        $paths = [];
        try {
            return DB::transaction(function () use ($callback, &$paths) {
                return $callback(function (array $data) use (&$paths) {
                    if (($data['attachment'] ?? null) instanceof UploadedFile) {
                        $path = $this->store($data['attachment']);
                        $paths[] = $path;
                        $data['attachment_path'] = $path;
                    }
                    unset($data['attachment']);

                    return $data;
                });
            });
        } catch (Throwable $exception) {
            foreach ($paths as $path) {
                $this->delete($path);
            }
            throw $exception;
        }
    }

    public function store(UploadedFile $file): string
    {
        $path = $file->store('payment-attachments', 'local');
        if (!$path) {
            throw new RuntimeException('Could not store payment screenshot. Please try again.');
        }

        return $path;
    }

    public function delete(?string $path): void
    {
        if ($path) {
            Storage::disk('local')->delete($path);
        }
    }

    public function view(HotelBookingPayment|HotelPayout $payment): StreamedResponse
    {
        abort_unless($payment->attachment_path && Storage::disk('local')->exists($payment->attachment_path), 404);

        $prefix = $payment instanceof HotelPayout ? 'payout-' : 'payment-';

        return Storage::disk('local')->response($payment->attachment_path, $prefix.$payment->id.'.'.pathinfo($payment->attachment_path, PATHINFO_EXTENSION), [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
