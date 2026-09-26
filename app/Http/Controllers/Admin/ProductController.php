<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $products = Product::query()
            ->with(['translations', 'category.translations', 'brand', 'mainImage', 'defaultVariant'])
            ->latest('id')
            ->paginate(10);

        return view('admin.products.index', compact('products'));
    }

    public function create()
    {
        $categories = Category::where('status', 0)->get();
        $brands = Brand::where('status', 0)->get();

        return view('admin.products.create', compact('categories', 'brands'));
    }

    public function edit(Product $product)
    {
        $categories = Category::where('status', 0)->get();
        $brands = Brand::where('status', 0)->get();

        return view('admin.products.edit', compact('product', 'categories', 'brands'));
    }

}