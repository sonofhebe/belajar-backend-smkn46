<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PostAttachment extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'post_attachments';

    protected $primaryKey = 'id';

    protected $fillable = [
        'id',
        'storage_path',
        'post_id',
    ];

    // Relasi dengan Post
    public function post()
    {
        return $this->belongsTo(Post::class);
    }
}
