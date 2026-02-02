<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Category;
use Illuminate\Support\Facades\DB;

class SearchService
{
    public function search($query, $filters = [], $sort = 'relevance', $perPage = 20)
    {
        $productQuery = Product::with(['category', 'brand', 'images'])
            ->where('status', 'active');

        // Apply search query
        if (!empty($query)) {
            $productQuery->where(function($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('description', 'like', "%{$query}%")
                  ->orWhere('sku', 'like', "%{$query}%")
                  ->orWhere('tags', 'like', "%{$query}%");
            });
        }

        // Apply filters
        $this->applyFilters($productQuery, $filters);

        // Apply sorting
        $this->applySorting($productQuery, $sort);

        // Get results
        $products = $productQuery->paginate($perPage);

        // Get available filters for the results
        $availableFilters = $this->getAvailableFilters($productQuery, $filters);

        return [
            'products' => $products,
            'filters' => $availableFilters,
            'total' => $products->total(),
            'suggestions' => $this->getSearchSuggestions($query)
        ];
    }

    private function applyFilters($query, $filters)
    {
        // Category filter
        if (!empty($filters['category'])) {
            $query->where('category_id', $filters['category']);
        }

        // Brand filter
        if (!empty($filters['brand'])) {
            $query->whereIn('brand_id', $filters['brand']);
        }

        // Price range filter
        if (!empty($filters['min_price']) || !empty($filters['max_price'])) {
            $min = $filters['min_price'] ?? 0;
            $max = $filters['max_price'] ?? PHP_FLOAT_MAX;
            $query->whereBetween('price', [$min, $max]);
        }

        // Rating filter
        if (!empty($filters['min_rating'])) {
            $query->where('average_rating', '>=', $filters['min_rating']);
        }

        // Stock filter
        if (isset($filters['in_stock'])) {
            if ($filters['in_stock']) {
                $query->where('stock', '>', 0);
            } else {
                $query->where('stock', 0);
            }
        }

        // Attributes filter
        if (!empty($filters['attributes'])) {
            foreach ($filters['attributes'] as $key => $value) {
                $query->whereJsonContains('attributes->' . $key, $value);
            }
        }

        // Featured filter
        if (isset($filters['featured'])) {
            $query->where('is_featured', $filters['featured']);
        }

        // Trending filter
        if (isset($filters['trending'])) {
            $query->where('is_trending', $filters['trending']);
        }
    }

    private function applySorting($query, $sort)
    {
        switch ($sort) {
            case 'price_low':
                $query->orderBy('price', 'asc');
                break;
            case 'price_high':
                $query->orderBy('price', 'desc');
                break;
            case 'popularity':
                $query->orderBy('views', 'desc');
                break;
            case 'rating':
                $query->orderBy('average_rating', 'desc');
                break;
            case 'newest':
                $query->orderBy('created_at', 'desc');
                break;
            case 'name_asc':
                $query->orderBy('name', 'asc');
                break;
            case 'name_desc':
                $query->orderBy('name', 'desc');
                break;
            default:
                $query->orderBy('created_at', 'desc');
        }
    }

    private function getAvailableFilters($baseQuery, $currentFilters = [])
    {
        $cloneQuery = clone $baseQuery;

        // Remove existing filters to get all possible values
        $this->removeFilters($cloneQuery, $currentFilters);

        $filters = [
            'price_range' => [
                'min' => (int) $cloneQuery->min('price') ?? 0,
                'max' => (int) $cloneQuery->max('price') ?? 1000,
                'current_min' => $currentFilters['min_price'] ?? 0,
                'current_max' => $currentFilters['max_price'] ?? 1000
            ],
            'categories' => Category::whereIn('id', $cloneQuery->pluck('category_id'))
                ->select('id', 'name', 'slug')
                ->get(),
            'brands' => \App\Models\Brand::whereIn('id', $cloneQuery->pluck('brand_id'))
                ->select('id', 'name', 'slug')
                ->get(),
            'ratings' => [
                '5' => $cloneQuery->where('average_rating', '>=', 4.5)->count(),
                '4' => $cloneQuery->whereBetween('average_rating', [3.5, 4.49])->count(),
                '3' => $cloneQuery->whereBetween('average_rating', [2.5, 3.49])->count(),
                '2' => $cloneQuery->whereBetween('average_rating', [1.5, 2.49])->count(),
                '1' => $cloneQuery->where('average_rating', '<', 1.5)->count()
            ]
        ];

        // Get available attributes
        $attributes = $cloneQuery->get()
            ->flatMap(function($product) {
                return $product->attributes ?? [];
            })
            ->toArray();

        $filters['attributes'] = $this->processAttributes($attributes);

        return $filters;
    }

    private function removeFilters($query, $filters)
    {
        // Remove filters to get base results
        // This is a simplified version
        return $query;
    }

    private function processAttributes($attributes)
    {
        $processed = [];
        
        foreach ($attributes as $key => $values) {
            if (is_array($values)) {
                foreach ($values as $value) {
                    if (!isset($processed[$key])) {
                        $processed[$key] = [];
                    }
                    if (!in_array($value, $processed[$key])) {
                        $processed[$key][] = $value;
                    }
                }
            }
        }

        return $processed;
    }

    private function getSearchSuggestions($query)
    {
        if (empty($query) || strlen($query) < 2) {
            return [];
        }

        $suggestions = [];

        // Get product suggestions
        $productSuggestions = Product::where('name', 'like', "%{$query}%")
            ->where('status', 'active')
            ->limit(5)
            ->pluck('name')
            ->toArray();

        $suggestions = array_merge($suggestions, $productSuggestions);

        // Get category suggestions
        $categorySuggestions = Category::where('name', 'like', "%{$query}%")
            ->where('status', 'active')
            ->limit(3)
            ->pluck('name')
            ->toArray();

        $suggestions = array_merge($suggestions, $categorySuggestions);

        // Get popular search suggestions
        $popularSuggestions = DB::table('search_histories')
            ->select('query', DB::raw('COUNT(*) as count'))
            ->where('query', 'like', "%{$query}%")
            ->groupBy('query')
            ->orderBy('count', 'desc')
            ->limit(5)
            ->pluck('query')
            ->toArray();

        $suggestions = array_merge($suggestions, $popularSuggestions);

        return array_unique($suggestions);
    }

    public function autocomplete($query, $limit = 10)
    {
        if (empty($query) || strlen($query) < 2) {
            return [];
        }

        $results = [];

        // Search products
        $products = Product::select('id', 'name', 'sku')
            ->where('status', 'active')
            ->where(function($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('sku', 'like', "%{$query}%");
            })
            ->limit($limit)
            ->get()
            ->map(function($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'type' => 'product'
                ];
            });

        $results = array_merge($results, $products->toArray());

        // Search categories
        $categories = Category::select('id', 'name', 'slug')
            ->where('status', 'active')
            ->where('name', 'like', "%{$query}%")
            ->limit(5)
            ->get()
            ->map(function($category) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'type' => 'category'
                ];
            });

        $results = array_merge($results, $categories->toArray());

        // Search brands
        $brands = \App\Models\Brand::select('id', 'name', 'slug')
            ->where('status', 'active')
            ->where('name', 'like', "%{$query}%")
            ->limit(3)
            ->get()
            ->map(function($brand) {
                return [
                    'id' => $brand->id,
                    'name' => $brand->name,
                    'slug' => $brand->slug,
                    'type' => 'brand'
                ];
            });

        $results = array_merge($results, $brands->toArray());

        return array_slice($results, 0, $limit);
    }

    public function logSearch($query, $userId = null, $resultsCount = 0)
    {
        if (empty($query) || strlen($query) < 2) {
            return;
        }

        DB::table('search_histories')->insert([
            'query' => $query,
            'user_id' => $userId,
            'results_count' => $resultsCount,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now()
        ]);
    }

    public function getPopularSearches($limit = 10, $days = 7)
    {
        return DB::table('search_histories')
            ->select('query', DB::raw('COUNT(*) as search_count'))
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy('query')
            ->orderBy('search_count', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getSearchHistory($userId, $limit = 10)
    {
        return DB::table('search_histories')
            ->where('user_id', $userId)
            ->select('query', 'created_at')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
}