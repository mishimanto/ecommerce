<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $query = Category::with(['parent']);

        if ($request->has('search')) {
            $query->where('name', 'like', "%{$request->search}%");
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $categories = $query->orderBy('order')->paginate(20);

        return response()->json($categories);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:categories,id',
            'description' => 'nullable|string',
            'image' => 'nullable|image|max:2048',
            'banner' => 'nullable|image|max:5120',
            'order' => 'integer',
            'featured' => 'boolean',
            'status' => 'required|in:active,inactive',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string',
            'meta_keywords' => 'nullable|string'
        ]);

        $category = Category::create([
            'name' => $request->name,
            'slug' => \Str::slug($request->name),
            'parent_id' => $request->parent_id,
            'description' => $request->description,
            'order' => $request->order ?? 0,
            'featured' => $request->featured ?? false,
            'status' => $request->status,
            'meta_title' => $request->meta_title,
            'meta_description' => $request->meta_description,
            'meta_keywords' => $request->meta_keywords
        ]);

        // Handle image upload
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('categories', 'public');
            $category->update(['image' => $path]);
        }

        // Handle banner upload
        if ($request->hasFile('banner')) {
            $path = $request->file('banner')->store('categories/banners', 'public');
            $category->update(['banner' => $path]);
        }

        return response()->json([
            'category' => $category->load('parent'),
            'message' => 'Category created successfully'
        ], 201);
    }

    public function show($id)
    {
        $category = Category::with(['parent', 'children'])->findOrFail($id);
        return response()->json($category);
    }

    public function update(Request $request, $id)
    {
        $category = Category::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:categories,id',
            'description' => 'nullable|string',
            'image' => 'nullable|image|max:2048',
            'banner' => 'nullable|image|max:5120',
            'order' => 'integer',
            'featured' => 'boolean',
            'status' => 'required|in:active,inactive',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string',
            'meta_keywords' => 'nullable|string'
        ]);

        // Prevent circular reference
        if ($request->parent_id == $id) {
            return response()->json([
                'message' => 'Category cannot be its own parent'
            ], 400);
        }

        $category->update([
            'name' => $request->name,
            'slug' => \Str::slug($request->name),
            'parent_id' => $request->parent_id,
            'description' => $request->description,
            'order' => $request->order ?? $category->order,
            'featured' => $request->featured ?? $category->featured,
            'status' => $request->status,
            'meta_title' => $request->meta_title,
            'meta_description' => $request->meta_description,
            'meta_keywords' => $request->meta_keywords
        ]);

        // Handle image upload
        if ($request->hasFile('image')) {
            // Delete old image
            if ($category->image) {
                Storage::disk('public')->delete($category->image);
            }
            $path = $request->file('image')->store('categories', 'public');
            $category->update(['image' => $path]);
        }

        // Handle banner upload
        if ($request->hasFile('banner')) {
            // Delete old banner
            if ($category->banner) {
                Storage::disk('public')->delete($category->banner);
            }
            $path = $request->file('banner')->store('categories/banners', 'public');
            $category->update(['banner' => $path]);
        }

        return response()->json([
            'category' => $category->load('parent'),
            'message' => 'Category updated successfully'
        ]);
    }

    public function destroy($id)
    {
        $category = Category::findOrFail($id);

        // Check if category has products
        if ($category->products()->exists()) {
            return response()->json([
                'message' => 'Cannot delete category with existing products'
            ], 400);
        }

        // Check if category has children
        if ($category->children()->exists()) {
            return response()->json([
                'message' => 'Cannot delete category with sub-categories'
            ], 400);
        }

        // Delete images
        if ($category->image) {
            Storage::disk('public')->delete($category->image);
        }
        if ($category->banner) {
            Storage::disk('public')->delete($category->banner);
        }

        $category->delete();

        return response()->json([
            'message' => 'Category deleted successfully'
        ]);
    }

    public function bulkUpdate(Request $request)
    {
        $request->validate([
            'category_ids' => 'required|array',
            'category_ids.*' => 'exists:categories,id',
            'action' => 'required|in:activate,inactivate,delete',
            'data' => 'nullable|array'
        ]);

        $categories = Category::whereIn('id', $request->category_ids)->get();

        foreach ($categories as $category) {
            switch ($request->action) {
                case 'activate':
                    $category->update(['status' => 'active']);
                    break;
                case 'inactivate':
                    $category->update(['status' => 'inactive']);
                    break;
                case 'delete':
                    if (!$category->products()->exists() && !$category->children()->exists()) {
                        $category->delete();
                    }
                    break;
            }
        }

        return response()->json([
            'message' => count($categories) . ' categories updated successfully'
        ]);
    }

    public function updateOrder(Request $request)
    {
        $request->validate([
            'categories' => 'required|array',
            'categories.*.id' => 'required|exists:categories,id',
            'categories.*.order' => 'required|integer'
        ]);

        DB::beginTransaction();

        try {
            foreach ($request->categories as $item) {
                Category::where('id', $item['id'])
                    ->update(['order' => $item['order']]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Category order updated successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update order'
            ], 500);
        }
    }
}