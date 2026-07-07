<?php

namespace Modules\Sales\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// use Modules\Sales\Database\Factories\IngredientsFactory;

class Ingredients extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'is_allergen',
        'is_vegan',
        'is_natural',
    ];

    protected $casts = [
        'is_allergen' => 'boolean',
        'is_vegan' => 'boolean',
        'is_natural' => 'boolean',
    ];

    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_ingredients')
            ->withTimestamps();
    }
}
