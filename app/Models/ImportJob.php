<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImportJob extends Model
{
    use HasFactory;

    protected $fillable = ['type', 'file_name', 'status', 'rows_total', 'rows_processed', 'rows_failed', 'column_mapping', 'meta'];
    protected $casts = ['rows_total' => 'integer', 'rows_processed' => 'integer', 'rows_failed' => 'integer', 'column_mapping' => 'array', 'meta' => 'array'];
}
