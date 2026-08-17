<?php

namespace App\Models;

use App\Observers\ArticleObserver;
use Cviebrock\EloquentSluggable\Sluggable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Storage;

#[ObservedBy([ArticleObserver::class])]
class Article extends Model
{
    use HasFactory;
    use Sluggable;

    protected $fillable = [
        'user_id', 'title', 'slug', 'content', 'excerpt', 'topic', 'thumbnail',
        'seo_title', 'seo_description', 'seo_keywords', 'active', 'status',
        'content_owner', 'published_at', 'materially_updated_at', 'reviewed_at',
    ];

    /**
     * Return the sluggable configuration array for this model.
     */
    public function sluggable(): array
    {
        return [
            'slug' => [
                'source' => 'title',
            ],
        ];
    }

    protected $casts = [
        'active' => 'boolean',
        'published_at' => 'immutable_datetime',
        'materially_updated_at' => 'immutable_datetime',
        'reviewed_at' => 'immutable_datetime',
    ];

    protected $appends = ['icon'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getIconAttribute(): string
    {
        return Storage::url($this->attributes['thumbnail']);
    }

    public function scopePublishable(Builder $query): Builder
    {
        return $query->where('active', true)->where('status', 'published')
            ->whereNotNull('published_at')->where('published_at', '<=', now())
            ->whereNotNull('reviewed_at')->whereNotNull('content_owner')
            ->whereNotNull('excerpt')->whereNotNull('seo_title')
            ->whereNotNull('seo_description')->whereNotNull('thumbnail');
    }
}
