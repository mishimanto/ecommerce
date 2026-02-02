<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SearchController extends Controller
{
    public function search(Request $request)
    {
        $query = $request->get('q', '');
        
        if (empty($query) || strlen($query) < 2) {
            return response()->json([]);
        }

        // Search products with typo tolerance
        $products = Product::where(function($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('description', 'like', "%{$query}%")
                  ->orWhere('sku', 'like', "%{$query}%")
                  ->orWhere('tags', 'like', "%{$query}%");
            })
            ->where('status', 'active')
            ->limit(10)
            ->get(['id', 'name', 'sku', 'price', 'image']);

        // Search categories
        $categories = Category::where('name', 'like', "%{$query}%")
            ->where('status', 'active')
            ->limit(5)
            ->get(['id', 'name', 'slug']);

        // Save search history for logged-in users
        if (auth()->check()) {
            DB::table('search_histories')->insert([
                'user_id' => auth()->id(),
                'query' => $query,
                'results_count' => $products->count(),
                'created_at' => now()
            ]);
        }

        return response()->json([
            'products' => $products,
            'categories' => $categories,
            'suggestions' => $this->getSearchSuggestions($query)
        ]);
    }

    public function autocomplete(Request $request)
    {
        $query = $request->get('q', '');
        
        if (empty($query) || strlen($query) < 2) {
            return response()->json([]);
        }

        $products = Product::where('name', 'like', "%{$query}%")
            ->where('status', 'active')
            ->limit(8)
            ->get(['id', 'name', 'sku'])
            ->map(function($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'type' => 'product'
                ];
            });

        $categories = Category::where('name', 'like', "%{$query}%")
            ->where('status', 'active')
            ->limit(5)
            ->get(['id', 'name', 'slug'])
            ->map(function($category) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'type' => 'category'
                ];
            });

        $results = $products->merge($categories)->take(10);

        return response()->json($results);
    }

    private function getSearchSuggestions($query)
    {
        // Get popular searches related to the query
        $suggestions = DB::table('search_histories')
            ->select('query', DB::raw('COUNT(*) as count'))
            ->where('query', 'like', "%{$query}%")
            ->groupBy('query')
            ->orderBy('count', 'desc')
            ->limit(5)
            ->pluck('query')
            ->toArray();

        // If no suggestions found, provide default ones based on the query
        if (empty($suggestions)) {
            $suggestions = [
                $query . ' for men',
                $query . ' for women',
                'best ' . $query,
                $query . ' online'
            ];
        }

        return $suggestions;
    }

    public function popularSearches()
    {
        $popular = DB::table('search_histories')
            ->select('query', DB::raw('COUNT(*) as search_count'))
            ->where('created_at', '>', now()->subDays(7))
            ->groupBy('query')
            ->orderBy('search_count', 'desc')
            ->limit(10)
            ->get();

        return response()->json($popular);
    }

    public function searchHistory(Request $request)
    {
        if (!auth()->check()) {
            return response()->json([]);
        }

        $history = DB::table('search_histories')
            ->where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get(['query', 'created_at']);

        return response()->json($history);
    }

    public function clearSearchHistory()
    {
        if (!auth()->check()) {
            return response()->json(['message' => 'Not authenticated'], 401);
        }

        DB::table('search_histories')
            ->where('user_id', auth()->id())
            ->delete();

        return response()->json(['message' => 'Search history cleared']);
    }

    public function advancedSearch(Request $request)
    {
        $query = Product::with(['category', 'brand', 'images'])
            ->where('status', 'active');

        // Search term
        if ($request->has('q') && !empty($request->q)) {
            $searchTerm = $request->q;
            $query->where(function($q) use ($searchTerm) {
                $q->where('name', 'like', "%{$searchTerm}%")
                  ->orWhere('description', 'like', "%{$searchTerm}%")
                  ->orWhere('sku', 'like', "%{$searchTerm}%")
                  ->orWhere('tags', 'like', "%{$searchTerm}%");
            });
        }

        // Category filter
        if ($request->has('category')) {
            $query->where('category_id', $request->category);
        }

        // Brand filter
        if ($request->has('brand')) {
            $query->whereIn('brand_id', explode(',', $request->brand));
        }

        // Price range
        if ($request->has('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }
        if ($request->has('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

        // Rating filter
        if ($request->has('min_rating')) {
            $query->where('average_rating', '>=', $request->min_rating);
        }

        // Stock filter
        if ($request->has('in_stock')) {
            $query->where('stock', '>', 0);
        }

        // Attributes filter
        if ($request->has('attributes')) {
            $attributes = json_decode($request->attributes, true);
            foreach ($attributes as $key => $value) {
                $query->whereJsonContains('attributes->' . $key, $value);
            }
        }

        // Sorting
        $sort = $request->get('sort', 'relevance');
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
            default:
                $query->orderBy('created_at', 'desc');
        }

        $perPage = $request->get('per_page', 20);
        $results = $query->paginate($perPage);

        // Get filters for the results
        $filters = $this->getAvailableFilters($query);

        return response()->json([
            'results' => $results,
            'filters' => $filters,
            'total' => $results->total()
        ]);
    }

    private function getAvailableFilters($query)
    {
        $baseQuery = clone $query;

        $filters = [
            'price_range' => [
                'min' => (int) $baseQuery->min('price'),
                'max' => (int) $baseQuery->max('price')
            ],
            'categories' => $baseQuery->with('category')->get()
                ->pluck('category')
                ->unique('id')
                ->values(),
            'brands' => $baseQuery->with('brand')->get()
                ->pluck('brand')
                ->unique('id')
                ->values(),
            'ratings' => [
                '5' => $baseQuery->where('average_rating', '>=', 4.5)->count(),
                '4' => $baseQuery->whereBetween('average_rating', [3.5, 4.49])->count(),
                '3' => $baseQuery->whereBetween('average_rating', [2.5, 3.49])->count(),
                '2' => $baseQuery->whereBetween('average_rating', [1.5, 2.49])->count(),
                '1' => $baseQuery->where('average_rating', '<', 1.5)->count()
            ]
        ];

        return $filters;
    }
}