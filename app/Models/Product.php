<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'category_id',
        'brand_id',
        'sub_category_id',
        'name',
        'slug',
        'model_number',
        'condition',
        'description',
        'tags',
        'selling_price',
        'mrp_price',
        'stock_quantity',
        'low_stock_alert',
        'sku',
        'barcode',
        'cost_price',
        'tax_rate',
        'weight',
        'length',
        'width',
        'height',
        'shipping_class',
        'free_shipping',
        'status',
        'meta_title',
        'meta_description',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'tags' => 'array',
        'free_shipping' => 'boolean',
        'selling_price' => 'decimal:2',
        'mrp_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'weight' => 'decimal:2',
        'length' => 'decimal:2',
        'width' => 'decimal:2',
        'height' => 'decimal:2',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Relation name intentionally avoids conflict with legacy "brand" column.
     */
    public function brandModel(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    /**
     * Relation name intentionally avoids conflict with legacy "sub_category" column.
     */
    public function subCategoryModel(): BelongsTo
    {
        return $this->belongsTo(SubCategory::class, 'sub_category_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function specifications(): HasMany
    {
        return $this->hasMany(ProductSpecification::class);
    }

    public function warranty(): HasOne
    {
        return $this->hasOne(ProductWarranty::class);
    }

    public function coupons(): HasMany
    {
        return $this->hasMany(ProductCoupon::class);
    }
}
