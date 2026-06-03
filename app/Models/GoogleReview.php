<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class GoogleReview extends Model
{
    protected $fillable = [
        'review_id',
        'author_name',
        'author_url',
        'profile_photo_url',
        'rating',
        'text',
        'language',
        'review_time',
        'is_it_related',
        'it_keywords_found',
        'recommendation',
        'is_ai_recommendation',
        'sentiment',
    ];

    protected $casts = [
        'review_time' => 'datetime',
        'is_it_related' => 'boolean',
        'it_keywords_found' => 'array',
        'rating' => 'integer',
        'is_ai_recommendation' => 'boolean',
    ];

    public function scopeItRelated(Builder $query): Builder
    {
        return $query->where('is_it_related', true);
    }

    public function scopeNegative(Builder $query): Builder
    {
        return $query->where('rating', '<=', 3);
    }

    public function scopeByMonth(Builder $query, int $year, int $month): Builder
    {
        return $query->whereYear('review_time', $year)
                     ->whereMonth('review_time', $month);
    }

    public function getStarsAttribute(): string
    {
        return str_repeat('★', $this->rating) . str_repeat('☆', 5 - $this->rating);
    }
}

