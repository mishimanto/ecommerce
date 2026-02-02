<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SearchHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'query',
        'results_count',
        'ip_address',
        'user_agent',
        'session_id'
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeRecent($query, $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopePopular($query, $limit = 10)
    {
        return $query->select('query', \DB::raw('COUNT(*) as search_count'))
            ->groupBy('query')
            ->orderBy('search_count', 'desc')
            ->limit($limit);
    }

    // Methods
    public static function logSearch($query, $userId = null, $resultsCount = 0)
    {
        if (empty($query) || strlen($query) < 2) {
            return;
        }

        self::create([
            'user_id' => $userId,
            'query' => $query,
            'results_count' => $resultsCount,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'session_id' => session()->getId()
        ]);
    }

    public static function getPopularSearches($limit = 10, $days = 7)
    {
        return self::select('query', \DB::raw('COUNT(*) as search_count'))
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy('query')
            ->orderBy('search_count', 'desc')
            ->limit($limit)
            ->get();
    }

    public static function getUserSearchHistory($userId, $limit = 10)
    {
        return self::where('user_id', $userId)
            ->select('query', 'created_at')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public static function clearUserHistory($userId)
    {
        return self::where('user_id', $userId)->delete();
    }

    public static function getSearchSuggestions($query, $limit = 5)
    {
        if (empty($query) || strlen($query) < 2) {
            return [];
        }

        return self::select('query')
            ->where('query', 'like', $query . '%')
            ->groupBy('query')
            ->orderByRaw('COUNT(*) DESC')
            ->limit($limit)
            ->pluck('query')
            ->toArray();
    }

    public static function getTrendingSearches($hours = 24, $limit = 5)
    {
        return self::select('query', \DB::raw('COUNT(*) as search_count'))
            ->where('created_at', '>=', now()->subHours($hours))
            ->groupBy('query')
            ->orderBy('search_count', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getFormattedDateAttribute()
    {
        return $this->created_at->format('M j, Y g:i A');
    }

    public static function cleanOldRecords($days = 30)
    {
        return self::where('created_at', '<', now()->subDays($days))->delete();
    }
}