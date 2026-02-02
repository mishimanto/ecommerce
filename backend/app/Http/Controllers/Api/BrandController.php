<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use Illuminate\Http\Request;

class BrandController extends Controller
{
    public function index(Request $request)
    {
        $brands = Brand::withCount(['products' => function($query) {
            $query->active();
        }])
        ->active()
        ->orderBy('name')
        ->get();

        return response()->json($brands);
    }

    public function show($slug)
    {
        $brand = Brand::where('slug', $slug)
            ->active()
            ->firstOrFail();

        $products = $brand->products()
            ->with(['images', 'category'])
            ->active()
            ->paginate(20);

        return response()->json([
            'brand' => $brand,
            'products' => $products
        ]);
    }

    public function featured()
    {
        $brands = Brand::withCount(['products' => function($query) {
            $query->active();
        }])
        ->active()
        ->featured()
        ->orderBy('order')
        ->limit(10)
        ->get();

        return response()->json($brands);
    }
}