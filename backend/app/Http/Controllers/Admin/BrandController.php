<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BrandController extends Controller
{
    public function index(Request $request)
    {
        $query = Brand::withCount(['products']);

        if ($request->has('search')) {
            $query->where('name', 'like', "%{$request->search}%");
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $brands = $query->orderBy('order')->paginate(20);

        return response()->json($brands);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:brands,name',
            'logo' => 'nullable|image|max:2048',
            'banner' => 'nullable|image|max:5120',
            'description' => 'nullable|string',
            'website' => 'nullable|url',
            'order' => 'integer',
            'featured' => 'boolean',
            'status' => 'required|in:active,inactive',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string',
            'meta_keywords' => 'nullable|string'
        ]);

        $brand = Brand::create([
            'name' => $request->name,
            'slug' => \Str::slug($request->name),
            'description' => $request->description,
            'website' => $request->website,
            'order' => $request->order ?? 0,
            'featured' => $request->featured ?? false,
            'status' => $request->status,
            'meta_title' => $request->meta_title,
            'meta_description' => $request->meta_description,
            'meta_keywords' => $request->meta_keywords
        ]);

        // Handle logo upload
        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('brands', 'public');
            $brand->update(['logo' => $path]);
        }

        // Handle banner upload
        if ($request->hasFile('banner')) {
            $path = $request->file('banner')->store('brands/banners', 'public');
            $brand->update(['banner' => $path]);
        }

        return response()->json([
            'brand' => $brand,
            'message' => 'Brand created successfully'
        ], 201);
    }

    public function show($id)
    {
        $brand = Brand::withCount(['products'])->findOrFail($id);
        return response()->json($brand);
    }

    public function update(Request $request, $id)
    {
        $brand = Brand::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255|unique:brands,name,' . $id,
            'logo' => 'nullable|image|max:2048',
            'banner' => 'nullable|image|max:5120',
            'description' => 'nullable|string',
            'website' => 'nullable|url',
            'order' => 'integer',
            'featured' => 'boolean',
            'status' => 'required|in:active,inactive',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string',
            'meta_keywords' => 'nullable|string'
        ]);

        $brand->update([
            'name' => $request->name,
            'slug' => \Str::slug($request->name),
            'description' => $request->description,
            'website' => $request->website,
            'order' => $request->order ?? $brand->order,
            'featured' => $request->featured ?? $brand->featured,
            'status' => $request->status,
            'meta_title' => $request->meta_title,
            'meta_description' => $request->meta_description,
            'meta_keywords' => $request->meta_keywords
        ]);

        // Handle logo upload
        if ($request->hasFile('logo')) {
            // Delete old logo
            if ($brand->logo) {
                Storage::disk('public')->delete($brand->logo);
            }
            $path = $request->file('logo')->store('brands', 'public');
            $brand->update(['logo' => $path]);
        }

        // Handle banner upload
        if ($request->hasFile('banner')) {
            // Delete old banner
            if ($brand->banner) {
                Storage::disk('public')->delete($brand->banner);
            }
            $path = $request->file('banner')->store('brands/banners', 'public');
            $brand->update(['banner' => $path]);
        }

        return response()->json([
            'brand' => $brand,
            'message' => 'Brand updated successfully'
        ]);
    }

    public function destroy($id)
    {
        $brand = Brand::findOrFail($id);

        // Check if brand has products
        if ($brand->products()->exists()) {
            return response()->json([
                'message' => 'Cannot delete brand with existing products'
            ], 400);
        }

        // Delete images
        if ($brand->logo) {
            Storage::disk('public')->delete($brand->logo);
        }
        if ($brand->banner) {
            Storage::disk('public')->delete($brand->banner);
        }

        $brand->delete();

        return response()->json([
            'message' => 'Brand deleted successfully'
        ]);
    }
}