<?php

namespace hexa_app_publish\Discovery\Sources\Models;

use Illuminate\Database\Eloquent\Model;

class BannedSource extends Model
{
    protected $table = 'publish_banned_sources';

    protected $fillable = ['domain', 'reason', 'banned_by'];
}
