<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['description_category_id', 'type_id', 'category_name', 'type_name', 'full_path', 'embedding'])]
class OzonCategory extends Model
{
    protected $hidden = ['embedding'];

    protected function casts(): array
    {
        return [
            'description_category_id' => 'integer',
            'type_id' => 'integer',
            'embedding' => 'array',
        ];
    }
}
