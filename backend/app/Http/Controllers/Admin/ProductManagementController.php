<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;

class ProductManagementController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with(['category', 'brand', 'images']);

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by category
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Filter by brand
        if ($request->has('brand_id')) {
            $query->where('brand_id', $request->brand_id);
        }

        // Filter by stock
        if ($request->has('stock_status')) {
            switch ($request->stock_status) {
                case 'in_stock':
                    $query->where('stock', '>', 0);
                    break;
                case 'low_stock':
                    $query->whereBetween('stock', [1, 10]);
                    break;
                case 'out_of_stock':
                    $query->where('stock', 0);
                    break;
            }
        }

        // Sort
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->get('per_page', 20);
        $products = $query->paginate($perPage);

        // Get filter options
        $filterOptions = [
            'categories' => Category::all(),
            'brands' => Brand::all(),
            'statuses' => ['draft', 'active', 'inactive', 'archived']
        ];

        return response()->json([
            'products' => $products,
            'filter_options' => $filterOptions
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'required|string|unique:products,sku',
            'category_id' => 'required|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'price' => 'required|numeric|min:0',
            'compare_price' => 'nullable|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'description' => 'required|string',
            'short_description' => 'nullable|string',
            'specifications' => 'nullable|array',
            'attributes' => 'nullable|array',
            'tags' => 'nullable|string',
            'weight' => 'nullable|numeric',
            'dimensions' => 'nullable|array',
            'is_featured' => 'boolean',
            'is_trending' => 'boolean',
            'status' => 'required|in:draft,active,inactive',
            'images' => 'nullable|array',
            'images.*' => 'image|max:5120',
            'variants' => 'nullable|array'
        ]);

        DB::beginTransaction();

        try {
            // Generate SKU if not provided
            $sku = $request->sku;
            if (empty($sku)) {
                $sku = 'PROD-' . strtoupper(uniqid());
            }

            // Create product
            $product = Product::create([
                'name' => $request->name,
                'sku' => $sku,
                'category_id' => $request->category_id,
                'brand_id' => $request->brand_id,
                'price' => $request->price,
                'compare_price' => $request->compare_price,
                'cost_price' => $request->cost_price,
                'stock' => $request->stock,
                'description' => $request->description,
                'short_description' => $request->short_description,
                'specifications' => $request->specifications ? json_encode($request->specifications) : null,
                'attributes' => $request->attributes ? json_encode($request->attributes) : null,
                'tags' => $request->tags,
                'weight' => $request->weight,
                'dimensions' => $request->dimensions ? json_encode($request->dimensions) : null,
                'is_featured' => $request->is_featured ?? false,
                'is_trending' => $request->is_trending ?? false,
                'status' => $request->status,
                'meta_title' => $request->meta_title,
                'meta_description' => $request->meta_description,
                'meta_keywords' => $request->meta_keywords
            ]);

            // Handle images
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $index => $image) {
                    $this->saveProductImage($product, $image, $index === 0);
                }
            }

            // Handle variants
            if ($request->has('variants')) {
                foreach ($request->variants as $variant) {
                    ProductVariant::create([
                        'product_id' => $product->id,
                        'name' => $variant['name'],
                        'value' => $variant['value'],
                        'sku' => $variant['sku'] ?? $product->sku . '-' . strtoupper(substr(md5($variant['value']), 0, 6)),
                        'price' => $variant['price'] ?? $product->price,
                        'stock' => $variant['stock'] ?? $product->stock,
                        'image' => $variant['image'] ?? null
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'product' => $product->load(['images', 'variants', 'category', 'brand']),
                'message' => 'Product created successfully'
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create product: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        $product = Product::with(['images', 'variants', 'category', 'brand', 'reviews'])->findOrFail($id);
        return response()->json($product);
    }

    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'sku' => 'sometimes|required|string|unique:products,sku,' . $id,
            'category_id' => 'sometimes|required|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'price' => 'sometimes|required|numeric|min:0',
            'compare_price' => 'nullable|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'stock' => 'sometimes|required|integer|min:0',
            'description' => 'sometimes|required|string',
            'short_description' => 'nullable|string',
            'specifications' => 'nullable|array',
            'attributes' => 'nullable|array',
            'tags' => 'nullable|string',
            'weight' => 'nullable|numeric',
            'dimensions' => 'nullable|array',
            'is_featured' => 'boolean',
            'is_trending' => 'boolean',
            'status' => 'sometimes|required|in:draft,active,inactive,archived',
            'images' => 'nullable|array',
            'images.*' => 'image|max:5120',
            'variants' => 'nullable|array',
            'deleted_images' => 'nullable|array',
            'deleted_variants' => 'nullable|array'
        ]);

        DB::beginTransaction();

        try {
            // Update product
            $product->update($request->only([
                'name', 'sku', 'category_id', 'brand_id', 'price', 'compare_price',
                'cost_price', 'stock', 'description', 'short_description', 'tags',
                'weight', 'is_featured', 'is_trending', 'status', 'meta_title',
                'meta_description', 'meta_keywords'
            ]));

            // Handle specifications and attributes
            if ($request->has('specifications')) {
                $product->specifications = json_encode($request->specifications);
            }
            if ($request->has('attributes')) {
                $product->attributes = json_encode($request->attributes);
            }
            if ($request->has('dimensions')) {
                $product->dimensions = json_encode($request->dimensions);
            }
            $product->save();

            // Handle deleted images
            if ($request->has('deleted_images')) {
                foreach ($request->deleted_images as $imageId) {
                    $image = ProductImage::find($imageId);
                    if ($image) {
                        // Delete from storage
                        Storage::disk('public')->delete($image->image_path);
                        Storage::disk('public')->delete('products/thumbs/' . basename($image->image_path));
                        $image->delete();
                    }
                }
            }

            // Handle new images
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $index => $image) {
                    $this->saveProductImage($product, $image);
                }
            }

            // Handle deleted variants
            if ($request->has('deleted_variants')) {
                ProductVariant::whereIn('id', $request->deleted_variants)->delete();
            }

            // Handle variants update/create
            if ($request->has('variants')) {
                foreach ($request->variants as $variantData) {
                    if (isset($variantData['id'])) {
                        // Update existing variant
                        $variant = ProductVariant::find($variantData['id']);
                        if ($variant) {
                            $variant->update([
                                'name' => $variantData['name'],
                                'value' => $variantData['value'],
                                'sku' => $variantData['sku'] ?? $variant->sku,
                                'price' => $variantData['price'] ?? $variant->price,
                                'stock' => $variantData['stock'] ?? $variant->stock,
                                'image' => $variantData['image'] ?? $variant->image
                            ]);
                        }
                    } else {
                        // Create new variant
                        ProductVariant::create([
                            'product_id' => $product->id,
                            'name' => $variantData['name'],
                            'value' => $variantData['value'],
                            'sku' => $variantData['sku'] ?? $product->sku . '-' . strtoupper(substr(md5($variantData['value']), 0, 6)),
                            'price' => $variantData['price'] ?? $product->price,
                            'stock' => $variantData['stock'] ?? $product->stock,
                            'image' => $variantData['image'] ?? null
                        ]);
                    }
                }
            }

            // Update main image if needed
            if ($request->has('main_image_id')) {
                ProductImage::where('product_id', $product->id)
                    ->update(['is_main' => false]);
                
                ProductImage::where('id', $request->main_image_id)
                    ->update(['is_main' => true]);
            }

            DB::commit();

            return response()->json([
                'product' => $product->fresh(['images', 'variants', 'category', 'brand']),
                'message' => 'Product updated successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update product: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        $product = Product::findOrFail($id);

        // Soft delete or check if can be deleted
        if ($product->orders()->exists()) {
            return response()->json([
                'message' => 'Cannot delete product with existing orders. Consider archiving instead.'
            ], 400);
        }

        // Delete images from storage
        foreach ($product->images as $image) {
            Storage::disk('public')->delete($image->image_path);
            Storage::disk('public')->delete('products/thumbs/' . basename($image->image_path));
        }

        $product->delete();

        return response()->json([
            'message' => 'Product deleted successfully'
        ]);
    }

    public function bulkUpdate(Request $request)
    {
        $request->validate([
            'product_ids' => 'required|array',
            'product_ids.*' => 'exists:products,id',
            'action' => 'required|in:activate,inactivate,archive,delete,update_stock,update_price',
            'data' => 'nullable|array'
        ]);

        DB::beginTransaction();

        try {
            $products = Product::whereIn('id', $request->product_ids)->get();

            foreach ($products as $product) {
                switch ($request->action) {
                    case 'activate':
                        $product->update(['status' => 'active']);
                        break;
                    case 'inactivate':
                        $product->update(['status' => 'inactive']);
                        break;
                    case 'archive':
                        $product->update(['status' => 'archived']);
                        break;
                    case 'delete':
                        $product->delete();
                        break;
                    case 'update_stock':
                        if (isset($request->data['stock'])) {
                            $product->update(['stock' => $request->data['stock']]);
                        }
                        break;
                    case 'update_price':
                        if (isset($request->data['price'])) {
                            $product->update(['price' => $request->data['price']]);
                        }
                        break;
                }
            }

            DB::commit();

            return response()->json([
                'message' => count($products) . ' products updated successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Bulk update failed: ' . $e->getMessage()
            ], 500);
        }
    }

    public function importProducts(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,xlsx,xls',
            'import_type' => 'required|in:create,update,both'
        ]);

        // This would process the import file
        // For now, return a placeholder response
        
        return response()->json([
            'message' => 'Import initiated',
            'import_id' => uniqid(),
            'estimated_time' => '2 minutes'
        ]);
    }

    public function exportProducts(Request $request)
    {
        $request->validate([
            'format' => 'required|in:csv,excel,json',
            'include' => 'nullable|array',
            'status' => 'nullable|in:all,active,inactive,draft,archived'
        ]);

        $query = Product::with(['category', 'brand']);

        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $products = $query->get();

        // Generate export file
        // For now, return sample data
        
        return response()->json([
            'message' => 'Export file generated',
            'download_url' => '/api/admin/products/export/download/export.' . $request->format,
            'total_products' => $products->count()
        ]);
    }

    private function saveProductImage($product, $image, $isMain = false)
    {
        $filename = 'product-' . $product->id . '-' . time() . '-' . uniqid() . '.' . $image->getClientOriginalExtension();
        
        // Save original
        $path = $image->storeAs('products', $filename, 'public');
        
        // Create thumbnail
        $thumb = Image::make($image->getRealPath());
        $thumb->resize(300, 300, function ($constraint) {
            $constraint->aspectRatio();
        });
        $thumb->save(storage_path('app/public/products/thumbs/' . $filename));

        // Create product image record
        ProductImage::create([
            'product_id' => $product->id,
            'image_path' => $path,
            'is_main' => $isMain,
            'order' => ProductImage::where('product_id', $product->id)->count()
        ]);
    }

    public function updateStock(Request $request, $id)
    {
        $request->validate([
            'stock' => 'required|integer|min:0',
            'reason' => 'required|string',
            'adjustment_type' => 'required|in:set,increment,decrement'
        ]);

        $product = Product::findOrFail($id);
        $oldStock = $product->stock;

        switch ($request->adjustment_type) {
            case 'set':
                $newStock = $request->stock;
                break;
            case 'increment':
                $newStock = $product->stock + $request->stock;
                break;
            case 'decrement':
                $newStock = $product->stock - $request->stock;
                break;
        }

        if ($newStock < 0) {
            return response()->json(['message' => 'Stock cannot be negative'], 400);
        }

        $product->update(['stock' => $newStock]);

        // Log stock adjustment
        DB::table('stock_adjustments')->insert([
            'product_id' => $product->id,
            'old_stock' => $oldStock,
            'new_stock' => $newStock,
            'adjustment' => $newStock - $oldStock,
            'reason' => $request->reason,
            'adjusted_by' => auth()->id(),
            'created_at' => now()
        ]);

        return response()->json([
            'product' => $product,
            'message' => 'Stock updated successfully'
        ]);
    }

    public function getStockHistory($id)
    {
        $history = DB::table('stock_adjustments')
            ->where('product_id', $id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($history);
    }

    public function duplicateProduct($id)
    {
        $original = Product::with(['images', 'variants'])->findOrFail($id);

        DB::beginTransaction();

        try {
            // Create duplicate product
            $duplicate = $original->replicate();
            $duplicate->sku = $original->sku . '-COPY-' . time();
            $duplicate->name = $original->name . ' (Copy)';
            $duplicate->status = 'draft';
            $duplicate->save();

            // Duplicate images
            foreach ($original->images as $image) {
                // Copy image file
                $newFilename = 'product-' . $duplicate->id . '-' . time() . '-' . uniqid() . '.' . pathinfo($image->image_path, PATHINFO_EXTENSION);
                Storage::disk('public')->copy($image->image_path, 'products/' . $newFilename);
                
                // Copy thumbnail
                $thumbFilename = basename($image->image_path);
                if (Storage::disk('public')->exists('products/thumbs/' . $thumbFilename)) {
                    Storage::disk('public')->copy('products/thumbs/' . $thumbFilename, 'products/thumbs/' . $newFilename);
                }

                ProductImage::create([
                    'product_id' => $duplicate->id,
                    'image_path' => 'products/' . $newFilename,
                    'is_main' => $image->is_main,
                    'order' => $image->order
                ]);
            }

            // Duplicate variants
            foreach ($original->variants as $variant) {
                $variant->replicate()->fill([
                    'product_id' => $duplicate->id,
                    'sku' => $variant->sku . '-COPY'
                ])->save();
            }

            DB::commit();

            return response()->json([
                'product' => $duplicate->load(['images', 'variants']),
                'message' => 'Product duplicated successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to duplicate product: ' . $e->getMessage()
            ], 500);
        }
    }
}