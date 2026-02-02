<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'user_id',
        'order_id',
        'rating',
        'title',
        'comment',
        'pros',
        'cons',
        'verified_purchase',
        'status',
        'helpful_count',
        'not_helpful_count',
        'images'
    ];

    protected $casts = [
        'rating' => 'integer',
        'verified_purchase' => 'boolean',
        'helpful_count' => 'integer',
        'not_helpful_count' => 'integer',
        'images' => 'array'
    ];

    // Relationships
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function helpfulVotes()
    {
        return $this->hasMany(ReviewHelpfulVote::class);
    }

    // Scopes
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    public function scopeVerified($query)
    {
        return $query->where('verified_purchase', true);
    }

    public function scopeWithHighRating($query, $minRating = 4)
    {
        return $query->where('rating', '>=', $minRating);
    }

    public function scopeWithLowRating($query, $maxRating = 2)
    {
        return $query->where('rating', '<=', $maxRating);
    }

    public function scopeRecent($query, $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    // Methods
    public function getRatingStarsAttribute()
    {
        $stars = '';
        for ($i = 1; $i <= 5; $i++) {
            if ($i <= $this->rating) {
                $stars .= '★';
            } else {
                $stars .= '☆';
            }
        }
        return $stars;
    }

    public function getFormattedDateAttribute()
    {
        return $this->created_at->format('F j, Y');
    }

    public function getHelpfulPercentageAttribute()
    {
        $total = $this->helpful_count + $this->not_helpful_count;
        return $total > 0 ? round(($this->helpful_count / $total) * 100) : 0;
    }

    public function markAsHelpful($userId)
    {
        $existingVote = $this->helpfulVotes()->where('user_id', $userId)->first();
        
        if ($existingVote) {
            if ($existingVote->is_helpful) {
                // Already marked as helpful, remove vote
                $existingVote->delete();
                $this->decrement('helpful_count');
            } else {
                // Change from not helpful to helpful
                $existingVote->update(['is_helpful' => true]);
                $this->increment('helpful_count');
                $this->decrement('not_helpful_count');
            }
        } else {
            // New helpful vote
            $this->helpfulVotes()->create([
                'user_id' => $userId,
                'is_helpful' => true
            ]);
            $this->increment('helpful_count');
        }
    }

    public function markAsNotHelpful($userId)
    {
        $existingVote = $this->helpfulVotes()->where('user_id', $userId)->first();
        
        if ($existingVote) {
            if (!$existingVote->is_helpful) {
                // Already marked as not helpful, remove vote
                $existingVote->delete();
                $this->decrement('not_helpful_count');
            } else {
                // Change from helpful to not helpful
                $existingVote->update(['is_helpful' => false]);
                $this->increment('not_helpful_count');
                $this->decrement('helpful_count');
            }
        } else {
            // New not helpful vote
            $this->helpfulVotes()->create([
                'user_id' => $userId,
                'is_helpful' => false
            ]);
            $this->increment('not_helpful_count');
        }
    }

    public function approve()
    {
        $this->update(['status' => 'approved']);
        $this->product->updateRating();
    }

    public function reject()
    {
        $this->update(['status' => 'rejected']);
    }

    public function isHelpfulByUser($userId)
    {
        $vote = $this->helpfulVotes()->where('user_id', $userId)->first();
        return $vote ? $vote->is_helpful : null;
    }

    public function getImageUrlsAttribute()
    {
        if (empty($this->images)) {
            return [];
        }

        return array_map(function($image) {
            return asset('storage/reviews/' . $image);
        }, $this->images);
    }

    // Validation
    public static function validationRules()
    {
        return [
            'rating' => 'required|integer|min:1|max:5',
            'title' => 'required|string|max:255',
            'comment' => 'required|string|min:10|max:2000',
            'pros' => 'nullable|string|max:500',
            'cons' => 'nullable|string|max:500',
            'images' => 'nullable|array',
            'images.*' => 'image|max:2048'
        ];
    }
}