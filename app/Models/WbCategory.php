<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['subject_id', 'subject_name', 'parent_name', 'full_path', 'embedding'])]
class WbCategory extends Model
{
    protected $hidden = ['embedding'];

    protected function casts(): array
    {
        return [
            'subject_id' => 'integer',
            'embedding' => 'array',
        ];
    }
}
