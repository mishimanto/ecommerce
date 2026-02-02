<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with(['category', 'brand', 'images', 'variants', 'reviews'])
            ->where('status', 'active');

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        // Filter by category
        if ($request->has('category')) {
            $query->whereHas('category', function($q) use ($request) {
                $q->where('slug', $request->category);
            });
        }

        // Filter by brand
        if ($request->has('brand')) {
            $query->whereIn('brand_id', explode(',', $request->brand));
        }

        // Price range filter
        if ($request->has('min_price') && $request->has('max_price')) {
            $query->whereBetween('price', [$request->min_price, $request->max_price]);
        }

        // Sort
        $sort = $request->get('sort', 'newest');
        switch ($sort) {
            case 'price_low':
                $query->orderBy('price', 'asc');
                break;
            case 'price_high':
                $query->orderBy('price', 'desc');
                break;
            case 'popular':
                $query->orderBy('views', 'desc');
                break;
            case 'rating':
                $query->orderBy('average_rating', 'desc');
                break;
            default:
                $query->orderBy('created_at', 'desc');
        }

        $perPage = $request->get('per_page', 20);
        $products = $query->paginate($perPage);

        return response()->json($products);
    }

    public function show($id)
    {
        $product = Product::with(['category', 'brand', 'images', 'variants', 'reviews.user'])
            ->where('status', 'active')
            ->findOrFail($id);

        // Increment view count
        $product->increment('views');

        return response()->json($product);
    }

    public function featured()
    {
        $products = Product::with(['category', 'brand', 'images'])
            ->where('status', 'active')
            ->where('is_featured', true)
            ->limit(10)
            ->get();

        return response()->json($products);
    }

    public function newArrivals()
    {
        $products = Product::with(['category', 'brand', 'images'])
            ->where('status', 'active')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return response()->json($products);
    }

    public function trending()
    {
        $products = Product::with(['category', 'brand', 'images'])
            ->where('status', 'active')
            ->orderBy('views', 'desc')
            ->limit(10)
            ->get();

        return response()->json($products);
    }

    public function compare(Request $request)
    {
        $productIds = $request->get('ids');
        
        if (empty($productIds) || count($productIds) > 4) {
            return response()->json(['message' => 'Select 2-4 products to compare'], 400);
        }

        $products = Product::with(['category', 'brand', 'images', 'variants'])
            ->whereIn('id', $productIds)
            ->get();

        return response()->json($products);
    }
}