<?php

namespace hexa_app_publish\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string $prompt
 * @property string $category
 * @property int $sort_order
 * @property bool $is_active
 */
class AiSmartEditTemplate extends Model
{
    protected $table = 'ai_smart_edit_templates';

    protected $fillable = [
        'name',
        'prompt',
        'category',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];
}
