<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::with(['children' => function($query) {
            $query->active()->orderBy('order');
        }])
        ->whereNull('parent_id')
        ->active()
        ->orderBy('order')
        ->get();

        return response()->json($categories);
    }

    public function show($slug)
    {
        $category = Category::with(['parent', 'children'])
            ->where('slug', $slug)
            ->active()
            ->firstOrFail();

        $products = Product::with(['images', 'category', 'brand'])
            ->where('category_id', $category->id)
            ->orWhereHas('category', function($query) use ($category) {
                $query->where('parent_id', $category->id);
            })
            ->active()
            ->paginate(20);

        return response()->json([
            'category' => $category,
            'products' => $products,
            'breadcrumb' => $category->breadcrumb
        ]);
    }

    public function featured()
    {
        $categories = Category::withCount(['products' => function($query) {
            $query->active();
        }])
        ->active()
        ->featured()
        ->orderBy('order')
        ->limit(8)
        ->get();

        return response()->json($categories);
    }
}