<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CategoryFormRequest;
use App\Models\Category;
use App\Support\MediaPath;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $filters = [
            'search' => trim((string) $request->string('search')),
            'visibility' => (string) $request->string('visibility'),
            'sort' => (string) $request->string('sort', 'updated_at'),
            'direction' => strtolower((string) $request->string('direction', 'desc')) === 'asc' ? 'asc' : 'desc',
        ];

        $sortMap = [
            'name' => 'name',
            'slug' => 'slug',
            'updated_at' => 'updated_at',
            'products_count' => 'products_count',
            'status' => 'status',
        ];

        $sortColumn = $sortMap[$filters['sort']] ?? 'updated_at';

        $categories = Category::query()
            ->with(['translations'])
            ->withCount('products')
            ->when($filters['search'], function ($query, $search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('translations', function ($translationQuery) use ($search) {
                            $translationQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('slug', 'like', "%{$search}%")
                                ->orWhere('description', 'like', "%{$search}%");
                        });
                });
            })
            ->when($filters['visibility'] !== '', function ($query) use ($filters) {
                $query->where('status', $filters['visibility'] === 'visible' ? 0 : 1);
            })
            ->orderBy($sortColumn, $filters['direction'])
            ->paginate(12)
            ->withQueryString();

        $stats = [
            'total' => Category::count(),
            'visible' => Category::where('status', 0)->count(),
            'hidden' => Category::where('status', 1)->count(),
            'with_products' => Category::has('products')->count(),
        ];

        return view('admin.category.index', compact('categories', 'filters', 'stats'));
    }

    public function create()
    {
        return view('admin.category.create', [
            'category' => new Category(),
            'translations' => $this->emptyTranslations(),
        ]);
    }

    public function store(CategoryFormRequest $request)
    {
        $validated = $request->validated();
        $translations = $this->sanitizeTranslations($request->input('translations', []), $validated);

        $category = new Category();
        $category->name = $validated['name'];
        $category->slug = Str::slug($validated['slug'] ?: $validated['name']);
        $category->description = $validated['description'] ?? '';
        $category->status = $request->boolean('status') ? 1 : 0;
        $category->meta_title = $validated['meta_title'] ?? null;
        $category->meta_keyword = $validated['meta_keyword'] ?? null;
        $category->meta_description = $validated['meta_description'] ?? null;

        if ($request->hasFile('image')) {
            $category->image = $this->uploadImage($request->file('image'));
        }

        $category->save();
        $this->syncTranslations($category, $translations);

        return redirect()
            ->route('admin.categories.index')
            ->with('message', __('Category added successfully.'));
    }

    public function edit(Category $category)
    {
        $category->load('translations');

        return view('admin.category.edit', [
            'category' => $category,
            'translations' => $this->existingTranslations($category),
        ]);
    }

    public function update(CategoryFormRequest $request, Category $category)
    {
        $validated = $request->validated();
        $translations = $this->sanitizeTranslations($request->input('translations', []), $validated);

        $category->name = $validated['name'];
        $category->slug = Str::slug($validated['slug'] ?: $validated['name']);
        $category->description = $validated['description'] ?? '';
        $category->status = $request->boolean('status') ? 1 : 0;
        $category->meta_title = $validated['meta_title'] ?? null;
        $category->meta_keyword = $validated['meta_keyword'] ?? null;
        $category->meta_description = $validated['meta_description'] ?? null;

        if ($request->hasFile('image')) {
            $this->deleteImage($category->image);
            $category->image = $this->uploadImage($request->file('image'));
        }

        $category->save();
        $this->syncTranslations($category, $translations);

        return redirect()
            ->route('admin.categories.edit', $category)
            ->with('message', __('Category updated successfully.'));
    }

    public function destroy(Category $category)
    {
        if ($category->products()->exists()) {
            return redirect()->route('admin.categories.index')->with('error', __('This category still has linked products. Move or delete them first.'));
        }

        $this->deleteImage($category->image);
        $category->delete();

        return redirect()
            ->route('admin.categories.index')
            ->with('message', __('Category deleted successfully.'));
    }


    private function emptyTranslations(): array
    {
        return [
            'en' => ['name' => '', 'slug' => '', 'description' => '', 'meta_title' => '', 'meta_keyword' => '', 'meta_description' => ''],
            'ar' => ['name' => '', 'slug' => '', 'description' => '', 'meta_title' => '', 'meta_keyword' => '', 'meta_description' => ''],
        ];
    }

    private function existingTranslations(Category $category): array
    {
        $translations = $this->emptyTranslations();

        foreach ($category->translations as $translation) {
            $translations[$translation->locale] = [
                'name' => $translation->getRawOriginal('name') ?? '',
                'slug' => $translation->getRawOriginal('slug') ?? '',
                'description' => $translation->getRawOriginal('description') ?? '',
                'meta_title' => $translation->getRawOriginal('meta_title') ?? '',
                'meta_keyword' => $translation->getRawOriginal('meta_keyword') ?? '',
                'meta_description' => $translation->getRawOriginal('meta_description') ?? '',
            ];
        }

        return $translations;
    }

    private function sanitizeTranslations(array $input, array $validated): array
    {
        $translations = $this->emptyTranslations();

        foreach (['en', 'ar'] as $locale) {
            $data = $input[$locale] ?? [];
            $translations[$locale] = [
                'name' => trim((string) ($data['name'] ?? '')),
                'slug' => Str::slug(trim((string) ($data['slug'] ?? $data['name'] ?? ''))),
                'description' => trim((string) ($data['description'] ?? '')),
                'meta_title' => trim((string) ($data['meta_title'] ?? '')),
                'meta_keyword' => trim((string) ($data['meta_keyword'] ?? '')),
                'meta_description' => trim((string) ($data['meta_description'] ?? '')),
            ];
        }

        if ($translations['en']['name'] === '') {
            $translations['en'] = [
                'name' => $validated['name'],
                'slug' => Str::slug($validated['slug'] ?: $validated['name']),
                'description' => $validated['description'] ?? '',
                'meta_title' => $validated['meta_title'] ?? '',
                'meta_keyword' => $validated['meta_keyword'] ?? '',
                'meta_description' => $validated['meta_description'] ?? '',
            ];
        }

        return $translations;
    }

    private function syncTranslations(Category $category, array $translations): void
    {
        foreach ($translations as $locale => $data) {
            if (($data['name'] ?? '') === '') {
                continue;
            }

            $category->translations()->updateOrCreate(
                ['locale' => $locale],
                $data
            );
        }
    }

    private function uploadImage($image): string
    {
        $extension = strtolower($image->getClientOriginalExtension() ?: 'jpg');
        $filename = time() . '_' . Str::random(8) . '.' . $extension;
        $relativePath = 'category/' . $filename;
        $destination = MediaPath::uploadsRootPath('category');

        if (! File::exists($destination)) {
            File::makeDirectory($destination, 0755, true);
        }

        $image->move($destination, $filename);

        return $relativePath;
    }

    private function deleteImage(?string $filename): void
    {
        $filePath = MediaPath::publicUploadPath($filename, 'category');

        if ($filePath && File::exists($filePath)) {
            File::delete($filePath);
        }
    }
}
