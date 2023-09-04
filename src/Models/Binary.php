<?php

namespace Ryangurnick\FilesystemDatabase\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class Binary extends Model
{
    protected $fillable = [
        'hash',
        'name',
        'content',
        'size',
        'mime_type',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model)
        {
            $model->hash = str()->orderedUuid();
        });
    }

    public function sizeFormatted(): Attribute
    {
        return Attribute::get(function () {
            $precision = 2;
            $units = ['B', 'KB', 'MB', 'GB', 'TB'];

            $bytes = max($this->size, 0);
            $pow = floor(($this->size ? log($this->size) : 0) / log(1024));
            $pow = min($pow, count($units) - 1);

            // Uncomment one of the following alternatives
            $bytes /= pow(1024, $pow);
            // $bytes /= (1 << (10 * $pow));

            return round($bytes, $precision).' '.$units[$pow];
        });
    }
}
