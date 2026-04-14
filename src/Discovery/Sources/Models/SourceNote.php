<?php

namespace hexa_app_publish\Discovery\Sources\Models;

use Illuminate\Database\Eloquent\Model;

class SourceNote extends Model
{
    protected $table = 'publish_source_notes';

    protected $fillable = [
        'domain', 'notes', 'recommended_method', 'recommended_ua',
        'working_instructions', 'updated_by',
    ];
}
