<?php

namespace App\Services;

use App\Models\HotelSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class HotelSettingService
{
    private const DEFAULTS = [
        'show_invoice_title' => true,
        'show_logo' => true,
        'show_company_details' => true,
        'show_footer' => true,
        'show_terms' => true,
    ];

    public function get(): HotelSetting
    {
        return HotelSetting::find(1) ?? new HotelSetting(self::DEFAULTS);
    }

    // Pass validated settings, an optional uploaded logo, and the authenticated user ID.
    public function save(array $data, int $userId): HotelSetting
    {
        return Cache::lock('hotel-settings.save', 30)->block(5, function () use ($data, $userId) {
            $newPath = null;

            try {
                return DB::transaction(function () use ($data, $userId, &$newPath) {
                    $settings = HotelSetting::whereKey(1)->lockForUpdate()->first();
                    if (!$settings) {
                        $settings = new HotelSetting(self::DEFAULTS);
                        $settings->id = 1;
                    }

                    $oldPath = $settings->logo_path;
                    $fields = array_intersect_key($data, array_flip([
                        'invoice_title', 'show_invoice_title', 'show_logo',
                        'company_name', 'company_address', 'company_phone', 'company_email',
                        'show_company_details', 'footer_text', 'show_footer',
                        'terms_and_conditions', 'show_terms',
                    ]));

                    if (($data['logo'] ?? null) instanceof UploadedFile) {
                        $newPath = $data['logo']->store('hotel-settings', 'public');
                        if (!$newPath) {
                            throw new RuntimeException('Could not store the invoice logo. Please try again.');
                        }
                        $fields['logo_path'] = $newPath;
                    } elseif (filter_var($data['remove_logo'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                        $fields['logo_path'] = null;
                    }

                    $settings->fill($fields);
                    $settings->updated_by = $userId;
                    $settings->save();

                    if ($oldPath && $oldPath !== $settings->logo_path) {
                        DB::afterCommit(fn () => Storage::disk('public')->delete($oldPath));
                    }

                    return $settings->refresh();
                });
            } catch (Throwable $exception) {
                if ($newPath && !HotelSetting::whereKey(1)->where('logo_path', $newPath)->exists()) {
                    Storage::disk('public')->delete($newPath);
                }
                throw $exception;
            }
        });
    }
}
