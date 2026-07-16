<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Append-only Phase 3.4 category migration audit row.
 */
class CategoryMigrationAudit extends Model
{
    use HasFactory;

    protected $fillable = [
        'source_table',
        'source_primary_key',
        'source_column',
        'original_value',
        'normalized_value',
        'mapping_status',
        'matched_vendor_category_id',
        'reason_code',
        'backfill_version',
        'metadata',
    ];

    protected $casts = [
        'source_primary_key' => 'integer',
        'matched_vendor_category_id' => 'integer',
        'metadata' => 'array',
    ];
}
