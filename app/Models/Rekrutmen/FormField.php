<?php

namespace App\Models\Rekrutmen;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormField extends Model
{
    protected $connection = 'recruitment';

    protected $fillable = ['form_section_id', 'name', 'label', 'type', 'options', 'is_required', 'sort_order'];

    protected $casts = [
        'options' => 'array',
        'is_required' => 'boolean',
    ];

    public function section(): BelongsTo
    {
        return $this->belongsTo(FormSection::class, 'form_section_id');
    }
}
