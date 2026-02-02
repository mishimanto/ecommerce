<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BannerController extends Controller
{
    public function index(Request $request)
    {
        $query = Banner::query();

        // Search
        if ($request->has('search')) {
            $query->where('title', 'like', "%{$request->search}%");
        }

        // Filter by position
        if ($request->has('position')) {
            $query->where('position', $request->position);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $banners = $query->orderBy('order')->paginate(20);

        return response()->json($banners);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'image' => 'required|image|max:5120',
            'mobile_image' => 'nullable|image|max:5120',
            'position' => 'required|in:home_top,home_middle,home_bottom,category_top,sidebar',
            'link_type' => 'required|in:product,category,brand,url,custom',
            'link_target' => 'required_if:link_type,product,category,brand,url',
            'custom_url' => 'required_if:link_type,custom|url',
            'description' => 'nullable|string',
            'button_text' => 'nullable|string|max:50',
            'order' => 'integer',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
            'status' => 'required|in:active,inactive'
        ]);

        $banner = Banner::create([
            'title' => $request->title,
            'position' => $request->position,
            'link_type' => $request->link_type,
            'link_target' => $request->link_target,
            'custom_url' => $request->custom_url,
            'description' => $request->description,
            'button_text' => $request->button_text,
            'order' => $request->order ?? 0,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'status' => $request->status
        ]);

        // Handle image upload
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('banners', 'public');
            $banner->update(['image' => $path]);
        }

        // Handle mobile image upload
        if ($request->hasFile('mobile_image')) {
            $path = $request->file('mobile_image')->store('banners/mobile', 'public');
            $banner->update(['mobile_image' => $path]);
        }

        return response()->json([
            'banner' => $banner,
            'message' => 'Banner created successfully'
        ], 201);
    }

    public function show($id)
    {
        $banner = Banner::findOrFail($id);
        return response()->json($banner);
    }

    public function update(Request $request, $id)
    {
        $banner = Banner::findOrFail($id);

        $request->validate([
            'title' => 'required|string|max:255',
            'image' => 'nullable|image|max:5120',
            'mobile_image' => 'nullable|image|max:5120',
            'position' => 'required|in:home_top,home_middle,home_bottom,category_top,sidebar',
            'link_type' => 'required|in:product,category,brand,url,custom',
            'link_target' => 'required_if:link_type,product,category,brand,url',
            'custom_url' => 'required_if:link_type,custom|url',
            'description' => 'nullable|string',
            'button_text' => 'nullable|string|max:50',
            'order' => 'integer',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
            'status' => 'required|in:active,inactive'
        ]);

        $banner->update([
            'title' => $request->title,
            'position' => $request->position,
            'link_type' => $request->link_type,
            'link_target' => $request->link_target,
            'custom_url' => $request->custom_url,
            'description' => $request->description,
            'button_text' => $request->button_text,
            'order' => $request->order ?? $banner->order,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'status' => $request->status
        ]);

        // Handle image upload
        if ($request->hasFile('image')) {
            // Delete old image
            if ($banner->image) {
                Storage::disk('public')->delete($banner->image);
            }
            $path = $request->file('image')->store('banners', 'public');
            $banner->update(['image' => $path]);
        }

        // Handle mobile image upload
        if ($request->hasFile('mobile_image')) {
            // Delete old mobile image
            if ($banner->mobile_image) {
                Storage::disk('public')->delete($banner->mobile_image);
            }
            $path = $request->file('mobile_image')->store('banners/mobile', 'public');
            $banner->update(['mobile_image' => $path]);
        }

        return response()->json([
            'banner' => $banner,
            'message' => 'Banner updated successfully'
        ]);
    }

    public function destroy($id)
    {
        $banner = Banner::findOrFail($id);

        // Delete images
        if ($banner->image) {
            Storage::disk('public')->delete($banner->image);
        }
        if ($banner->mobile_image) {
            Storage::disk('public')->delete($banner->mobile_image);
        }

        $banner->delete();

        return response()->json([
            'message' => 'Banner deleted successfully'
        ]);
    }

    public function updateOrder(Request $request)
    {
        $request->validate([
            'banners' => 'required|array',
            'banners.*.id' => 'required|exists:banners,id',
            'banners.*.order' => 'required|integer'
        ]);

        foreach ($request->banners as $item) {
            Banner::where('id', $item['id'])
                ->update(['order' => $item['order']]);
        }

        return response()->json([
            'message' => 'Banner order updated successfully'
        ]);
    }

    public function getPositions()
    {
        $positions = [
            [
                'value' => 'home_top',
                'label' => 'Home Page - Top Banner',
                'dimensions' => '1920x600'
            ],
            [
                'value' => 'home_middle',
                'label' => 'Home Page - Middle Banner',
                'dimensions' => '1920x400'
            ],
            [
                'value' => 'home_bottom',
                'label' => 'Home Page - Bottom Banner',
                'dimensions' => '1920x300'
            ],
            [
                'value' => 'category_top',
                'label' => 'Category Page - Top',
                'dimensions' => '1920x400'
            ],
            [
                'value' => 'sidebar',
                'label' => 'Sidebar Banner',
                'dimensions' => '300x600'
            ]
        ];

        return response()->json($positions);
    }

    public function getActiveBanners($position)
    {
        $banners = Banner::where('position', $position)
            ->where('status', 'active')
            ->where(function($query) {
                $query->whereNull('start_date')
                    ->orWhere('start_date', '<=', now());
            })
            ->where(function($query) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>=', now());
            })
            ->orderBy('order')
            ->get();

        return response()->json($banners);
    }
}