<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PageController extends Controller
{
    public function index(Request $request)
    {
        $query = Page::query();

        // Search
        if ($request->has('search')) {
            $query->where('title', 'like', "%{$request->search}%")
                  ->orWhere('slug', 'like', "%{$request->search}%");
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $pages = $query->orderBy('order')->paginate(20);

        return response()->json($pages);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'required|string|unique:pages,slug|max:255',
            'content' => 'required|string',
            'excerpt' => 'nullable|string|max:500',
            'featured_image' => 'nullable|image|max:5120',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string',
            'meta_keywords' => 'nullable|string',
            'template' => 'required|in:default,fullwidth,sidebar_left,sidebar_right',
            'order' => 'integer',
            'status' => 'required|in:published,draft',
            'show_in_menu' => 'boolean',
            'menu_position' => 'nullable|in:header,footer,both',
            'parent_id' => 'nullable|exists:pages,id'
        ]);

        $page = Page::create([
            'title' => $request->title,
            'slug' => $request->slug,
            'content' => $request->content,
            'excerpt' => $request->excerpt,
            'meta_title' => $request->meta_title,
            'meta_description' => $request->meta_description,
            'meta_keywords' => $request->meta_keywords,
            'template' => $request->template,
            'order' => $request->order ?? 0,
            'status' => $request->status,
            'show_in_menu' => $request->show_in_menu ?? false,
            'menu_position' => $request->menu_position,
            'parent_id' => $request->parent_id
        ]);

        // Handle featured image upload
        if ($request->hasFile('featured_image')) {
            $path = $request->file('featured_image')->store('pages', 'public');
            $page->update(['featured_image' => $path]);
        }

        return response()->json([
            'page' => $page,
            'message' => 'Page created successfully'
        ], 201);
    }

    public function show($id)
    {
        $page = Page::with(['parent', 'children'])->findOrFail($id);
        return response()->json($page);
    }

    public function update(Request $request, $id)
    {
        $page = Page::findOrFail($id);

        $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'required|string|unique:pages,slug,' . $id . '|max:255',
            'content' => 'required|string',
            'excerpt' => 'nullable|string|max:500',
            'featured_image' => 'nullable|image|max:5120',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string',
            'meta_keywords' => 'nullable|string',
            'template' => 'required|in:default,fullwidth,sidebar_left,sidebar_right',
            'order' => 'integer',
            'status' => 'required|in:published,draft',
            'show_in_menu' => 'boolean',
            'menu_position' => 'nullable|in:header,footer,both',
            'parent_id' => 'nullable|exists:pages,id'
        ]);

        // Prevent circular reference
        if ($request->parent_id == $id) {
            return response()->json([
                'message' => 'Page cannot be its own parent'
            ], 400);
        }

        $page->update([
            'title' => $request->title,
            'slug' => $request->slug,
            'content' => $request->content,
            'excerpt' => $request->excerpt,
            'meta_title' => $request->meta_title,
            'meta_description' => $request->meta_description,
            'meta_keywords' => $request->meta_keywords,
            'template' => $request->template,
            'order' => $request->order ?? $page->order,
            'status' => $request->status,
            'show_in_menu' => $request->show_in_menu ?? $page->show_in_menu,
            'menu_position' => $request->menu_position,
            'parent_id' => $request->parent_id
        ]);

        // Handle featured image upload
        if ($request->hasFile('featured_image')) {
            // Delete old image
            if ($page->featured_image) {
                Storage::disk('public')->delete($page->featured_image);
            }
            $path = $request->file('featured_image')->store('pages', 'public');
            $page->update(['featured_image' => $path]);
        }

        return response()->json([
            'page' => $page->fresh(['parent', 'children']),
            'message' => 'Page updated successfully'
        ]);
    }

    public function destroy($id)
    {
        $page = Page::findOrFail($id);

        // Check if page has children
        if ($page->children()->exists()) {
            return response()->json([
                'message' => 'Cannot delete page with sub-pages'
            ], 400);
        }

        // Delete featured image
        if ($page->featured_image) {
            Storage::disk('public')->delete($page->featured_image);
        }

        $page->delete();

        return response()->json([
            'message' => 'Page deleted successfully'
        ]);
    }

    public function getMenuPages()
    {
        $pages = Page::where('status', 'published')
            ->where('show_in_menu', true)
            ->whereNull('parent_id')
            ->with(['children' => function($query) {
                $query->where('status', 'published')
                    ->where('show_in_menu', true)
                    ->orderBy('order');
            }])
            ->orderBy('order')
            ->get();

        return response()->json($pages);
    }

    public function getBySlug($slug)
    {
        $page = Page::where('slug', $slug)
            ->where('status', 'published')
            ->firstOrFail();

        return response()->json($page);
    }

    public function updateOrder(Request $request)
    {
        $request->validate([
            'pages' => 'required|array',
            'pages.*.id' => 'required|exists:pages,id',
            'pages.*.order' => 'required|integer',
            'pages.*.parent_id' => 'nullable|exists:pages,id'
        ]);

        foreach ($request->pages as $item) {
            Page::where('id', $item['id'])
                ->update([
                    'order' => $item['order'],
                    'parent_id' => $item['parent_id'] ?? null
                ]);
        }

        return response()->json([
            'message' => 'Page order updated successfully'
        ]);
    }
}