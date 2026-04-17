<?php

namespace App\Services\Admin;

use App\Models\Product;
use App\Models\ProductImage;
use App\Support\MediaPath;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class ProductService
{
    protected string $logChannel = 'admin_products';

    protected function logInfo(string $message, array $context = []): void
    {
        Log::channel($this->logChannel)->info($message, $context);
    }

    protected function logError(string $message, array $context = []): void
    {
        Log::channel($this->logChannel)->error($message, $context);
        Log::error($message, $context);
    }

    public function save(array $data, ?int $productId = null, array $newImages = []): Product
    {
        $this->logInfo('ProductService save started', [
            'product_id' => $productId,
            'new_images_count' => count($newImages),
            'has_name' => filled($data['name'] ?? null),
            'has_variants' => (bool) ($data['has_variants'] ?? false),
        ]);

        if (!empty($newImages)) {
            $data['images'] = $newImages;
        }

        if ($productId) {
            $product = Product::query()->findOrFail($productId);

            return $this->update($product, $data);
        }

        return $this->create($data);
    }

    public function create(array $data): Product
    {
        return DB::transaction(function () use ($data) {
            $this->logInfo('ProductService create transaction started', [
                'name' => $data['name'] ?? null,
                'images_count' => is_array($data['images'] ?? null) ? count($data['images']) : 0,
            ]);

            $product = new Product();
            $product->fill($this->prepareProductPayload($data));
            $product->slug = $this->resolveSlugForCreate($product, $data);
            $product->save();

            if (!empty($data['images']) && is_array($data['images'])) {
                $this->storeImages($product, $data['images']);
            }

            $this->logInfo('ProductService create transaction completed', [
                'product_id' => $product->id,
                'images_count' => $product->images()->count(),
            ]);

            return $product->withoutRelations();
        });
    }

    public function update(Product $product, array $data): Product
    {
        return DB::transaction(function () use ($product, $data) {
            $product->fill($this->prepareProductPayload($data));
            $product->slug = $this->resolveSlugForUpdate($product, $data);

            if ($product->isDirty()) {
                $product->save();
            }

            if (!empty($data['images']) && is_array($data['images'])) {
                $this->storeImages($product, $data['images']);
            }

            $this->logInfo('ProductService update transaction completed', [
                'product_id' => $product->id,
                'images_count' => $product->images()->count(),
                'was_dirty' => $product->wasChanged(),
            ]);

            return $product->withoutRelations();
        });
    }

    public function duplicateProduct(Product $product): Product
    {
        return DB::transaction(function () use ($product) {
            $product->loadMissing('images');

            $newProduct = $product->replicate();
            $baseName = $product->name ?: ('product-' . $product->id);

            $newProduct->name = ($product->name ?: 'Product') . ' (Copy)';
            $newProduct->slug = $this->generateUniqueSlug($baseName . '-copy');
            $newProduct->save();

            if ($product->images->isNotEmpty()) {
                foreach ($product->images as $image) {
                    $oldPath = $image->image_path ?: $image->image;
                    $newPath = $this->duplicateImageFile($oldPath);

                    $newProduct->images()->create([
                        'product_id' => $newProduct->id,
                        'image_path' => $newPath,
                        'is_main' => (bool) $image->is_main,
                        'sort_order' => (int) $image->sort_order,
                    ]);
                }

                $this->normalizeMainImage($newProduct->id);
            }

            return $newProduct->load('images');
        });
    }

    protected function prepareProductPayload(array $data): array
    {
        return [
            'name' => $data['name'] ?? null,
            'slug' => $data['slug'] ?? null,
            'sku' => $data['sku'] ?? null,
            'barcode' => $data['barcode'] ?? null,
            'category_id' => $data['category_id'] ?? null,
            'brand_id' => !empty($data['brand_id']) ? (int) $data['brand_id'] : null,
            'description' => $data['description'] ?? null,
            'base_price' => $data['base_price'] ?? null,
            'sale_price' => $data['sale_price'] ?? null,
            'quantity' => $data['quantity'] ?? 0,
            'stock_status' => $data['stock_status'] ?? 'in_stock',
            'low_stock_threshold' => $data['low_stock_threshold'] ?? null,
            'status' => (int) ($data['status'] ?? 1),
            'is_featured' => (int) ($data['is_featured'] ?? 0),
            'has_variants' => (bool) ($data['has_variants'] ?? false),
            'meta_title' => $data['meta_title'] ?? null,
            'meta_description' => $data['meta_description'] ?? null,
            'video_url' => $data['video_url'] ?? null,
        ];
    }

    protected function resolveSlugForCreate(Product $product, array $data): string
    {
        if (filled($data['slug'] ?? null)) {
            return $this->generateUniqueSlug((string) $data['slug']);
        }

        if (filled($product->slug)) {
            return $this->generateUniqueSlug((string) $product->slug);
        }

        $base = $data['name'] ?? ('product-' . now()->timestamp);

        return $this->generateUniqueSlug((string) $base);
    }

    protected function resolveSlugForUpdate(Product $product, array $data): string
    {
        if (filled($data['slug'] ?? null)) {
            return $this->generateUniqueSlug((string) $data['slug'], $product->id);
        }

        if (filled($product->slug)) {
            return (string) $product->slug;
        }

        $base = $data['name'] ?? ('product-' . now()->timestamp);

        return $this->generateUniqueSlug((string) $base, $product->id);
    }

    protected function storeImages(Product $product, array $images): void
    {
        $images = array_values(array_filter($images));

        if (empty($images)) {
            $this->logInfo('ProductService storeImages skipped: no images to store', [
                'product_id' => $product->id,
            ]);

            return;
        }

        $this->logInfo('ProductService storeImages started', [
            'product_id' => $product->id,
            'images_count' => count($images),
        ]);

        $currentMaxSort = (int) ($product->images()->max('sort_order') ?? 0);
        $rows = [];

        foreach ($images as $index => $imageFile) {
            $path = $this->storeUploadedImage($imageFile);

            $rows[] = [
                'product_id' => $product->id,
                'image_path' => $path,
                'is_main' => false,
                'sort_order' => $currentMaxSort + $index + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (!empty($rows)) {
            ProductImage::query()->insert($rows);
            $this->normalizeMainImage($product->id);

            $this->logInfo('ProductService storeImages completed', [
                'product_id' => $product->id,
                'inserted_rows' => count($rows),
            ]);
        }
    }

    public function deleteImage(int $imageId): void
    {
        DB::transaction(function () use ($imageId) {
            $image = ProductImage::query()->findOrFail($imageId);
            $productId = $image->product_id;
            $path = $image->image_path ?: $image->image;

            $this->deletePhysicalImage($path, 'products');

            $image->delete();
            $this->normalizeMainImage($productId);
        });
    }

    public function setMainImage(int $imageId): void
    {
        DB::transaction(function () use ($imageId) {
            $image = ProductImage::query()->findOrFail($imageId);
            $productId = $image->product_id;

            ProductImage::query()
                ->where('product_id', $productId)
                ->where('is_main', true)
                ->update(['is_main' => false]);

            ProductImage::query()
                ->whereKey($image->id)
                ->update(['is_main' => true]);
        });
    }

    public function reorderExistingImages(array $orderedIds): void
    {
        $orderedIds = array_values(array_filter(array_map('intval', $orderedIds)));

        if (empty($orderedIds)) {
            return;
        }

        DB::transaction(function () use ($orderedIds) {
            foreach ($orderedIds as $index => $id) {
                ProductImage::query()
                    ->whereKey($id)
                    ->update(['sort_order' => $index + 1]);
            }
        });
    }

    protected function normalizeMainImage(int $productId): void
    {
        $images = ProductImage::query()
            ->where('product_id', $productId)
            ->orderByDesc('is_main')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'is_main']);

        if ($images->isEmpty()) {
            return;
        }

        $mainImages = $images->where('is_main', true);

        if ($mainImages->count() === 1) {
            return;
        }

        $mainImage = $mainImages->first() ?: $images->first();

        ProductImage::query()
            ->where('product_id', $productId)
            ->update(['is_main' => false]);

        ProductImage::query()
            ->whereKey($mainImage->id)
            ->update(['is_main' => true]);
    }

    protected function duplicateImageFile(?string $oldPath): ?string
    {
        if (!$oldPath) {
            return null;
        }

        $source = MediaPath::publicUploadPath($oldPath, 'products');

        if (! $source || ! File::exists($source)) {
            return MediaPath::normalizeRelative($oldPath, 'products');
        }

        $destinationDirectory = MediaPath::uploadsRootPath('products');

        if (! File::exists($destinationDirectory)) {
            File::makeDirectory($destinationDirectory, 0755, true);
        }

        $extension = pathinfo($source, PATHINFO_EXTENSION);
        $newFileName = Str::uuid() . ($extension ? '.' . $extension : '');
        $destination = $destinationDirectory . DIRECTORY_SEPARATOR . $newFileName;

        File::copy($source, $destination);

        return 'products/' . $newFileName;
    }

    protected function storeUploadedImage($imageFile): string
    {
        $destinationDirectory = MediaPath::uploadsRootPath('products');

        if (! File::exists($destinationDirectory)) {
            File::makeDirectory($destinationDirectory, 0755, true);
        }

        if (! File::isDirectory($destinationDirectory)) {
            throw new RuntimeException('Product upload destination is not a valid directory: ' . $destinationDirectory);
        }

        if (! is_writable($destinationDirectory)) {
            throw new RuntimeException('Product upload destination is not writable: ' . $destinationDirectory);
        }

        $realPath = method_exists($imageFile, 'getRealPath') ? $imageFile->getRealPath() : null;
        $extension = strtolower($imageFile->getClientOriginalExtension() ?: 'jpg');
        $filename = (string) Str::uuid() . '.' . $extension;
        $destinationPath = $destinationDirectory . DIRECTORY_SEPARATOR . $filename;

        $this->logInfo('ProductService storeUploadedImage started', [
            'destination_directory' => $destinationDirectory,
            'destination_path' => $destinationPath,
            'directory_exists' => File::exists($destinationDirectory),
            'directory_is_writable' => is_writable($destinationDirectory),
            'real_path' => $realPath,
            'real_path_exists' => $realPath ? File::exists($realPath) : false,
            'original_name' => method_exists($imageFile, 'getClientOriginalName') ? $imageFile->getClientOriginalName() : null,
            'original_extension' => method_exists($imageFile, 'getClientOriginalExtension') ? $imageFile->getClientOriginalExtension() : null,
            'size' => method_exists($imageFile, 'getSize') ? $imageFile->getSize() : null,
        ]);

        if (! $realPath || ! File::exists($realPath)) {
            throw new RuntimeException('Uploaded image temporary file is missing or unreadable.');
        }

        $copied = false;

        try {
            $copied = File::copy($realPath, $destinationPath);

            if (! $copied || ! File::exists($destinationPath)) {
                $binary = @file_get_contents($realPath);

                if ($binary === false) {
                    throw new RuntimeException('Unable to read uploaded image temporary file.');
                }

                $bytes = @file_put_contents($destinationPath, $binary);

                if ($bytes === false) {
                    throw new RuntimeException('Unable to write uploaded image to destination path.');
                }
            }
        } catch (\Throwable $e) {
            $this->logError('ProductService storeUploadedImage failed', [
                'destination_directory' => $destinationDirectory,
                'destination_path' => $destinationPath,
                'directory_exists' => File::exists($destinationDirectory),
                'directory_is_writable' => is_writable($destinationDirectory),
                'real_path' => $realPath,
                'real_path_exists' => $realPath ? File::exists($realPath) : false,
                'exception_class' => get_class($e),
                'exception_message' => $e->getMessage(),
            ]);

            throw $e;
        }

        $relativePath = 'products/' . $filename;

        $this->logInfo('ProductService storeUploadedImage completed', [
            'destination_directory' => $destinationDirectory,
            'destination_path' => $destinationPath,
            'filename' => $filename,
            'relative_path' => $relativePath,
            'file_exists_after_write' => File::exists($destinationPath),
            'file_size_after_write' => File::exists($destinationPath) ? File::size($destinationPath) : null,
        ]);

        return $relativePath;
    }

    protected function deletePhysicalImage(?string $path, ?string $defaultDirectory = null): void
    {
        $fullPath = MediaPath::publicUploadPath($path, $defaultDirectory);

        if ($fullPath && File::exists($fullPath)) {
            File::delete($fullPath);
        }
    }

    protected function generateUniqueSlug(string $value, ?int $ignoreId = null): string
    {
        $slug = Str::slug($value);

        if (blank($slug)) {
            $slug = 'product';
        }

        $original = $slug;
        $counter = 1;

        while ($this->slugExists($slug, $ignoreId)) {
            $slug = $original . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    protected function slugExists(string $slug, ?int $ignoreId = null): bool
    {
        return Product::query()
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->where('slug', $slug)
            ->exists();
    }
}