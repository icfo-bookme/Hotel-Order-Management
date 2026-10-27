<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelSetting extends Model
{
    protected $fillable = [
        'invoice_title',
        'show_invoice_title',
        'logo_path',
        'show_logo',
        'company_name',
        'company_address',
        'company_phone',
        'company_email',
        'show_company_details',
        'footer_text',
        'show_footer',
        'terms_and_conditions',
        'show_terms',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'show_invoice_title' => 'boolean',
            'show_logo' => 'boolean',
            'show_company_details' => 'boolean',
            'show_footer' => 'boolean',
            'show_terms' => 'boolean',
        ];
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
