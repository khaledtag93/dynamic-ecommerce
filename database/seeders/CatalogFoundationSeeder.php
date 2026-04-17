<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductTranslation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CatalogFoundationSeeder extends Seeder
{
    public function run(): void
    {
        $brand = Brand::query()->firstOrCreate(
            ['slug' => 'tag-basics'],
            ['name' => 'Tag Basics', 'status' => true]
        );

        $catalog = [
            [
                'category' => [
                    'name' => 'Baking Tools',
                    'slug' => 'baking-tools',
                    'description' => 'Core tools used daily in the kitchen.',
                    'meta_title' => 'Baking Tools',
                    'meta_keyword' => 'baking tools, kitchen tools',
                    'meta_description' => 'Essential baking tools for home and business kitchens.',
                    'translations' => [
                        'ar' => [
                            'name' => 'أدوات الخَبز',
                            'slug' => 'adwat-alkhabz',
                            'description' => 'أدوات أساسية تُستخدم يوميًا داخل المطبخ.',
                            'meta_title' => 'أدوات الخَبز',
                            'meta_keyword' => 'أدوات خبز، أدوات مطبخ',
                            'meta_description' => 'مجموعة أدوات خبز أساسية للمطبخ المنزلي أو التجاري.',
                        ],
                    ],
                ],
                'products' => [
                    [
                        'name' => 'Offset Spatula',
                        'slug' => 'offset-spatula',
                        'description' => 'Stainless spatula for frosting and finishing cakes.',
                        'base_price' => 95,
                        'sale_price' => 79,
                        'quantity' => 30,
                        'translations' => [
                            'ar' => [
                                'name' => 'اسباتيولا ستانلس',
                                'slug' => 'asbatyola-stanls',
                                'description' => 'اسباتيولا مناسبة لفرد الكريمة وإنهاء تزيين الكيك.',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'category' => [
                    'name' => 'Baking Ingredients',
                    'slug' => 'baking-ingredients',
                    'description' => 'Ingredients used in cakes, desserts, and pastry prep.',
                    'meta_title' => 'Baking Ingredients',
                    'meta_keyword' => 'baking ingredients, cake ingredients',
                    'meta_description' => 'Reliable baking ingredients for cakes and desserts.',
                    'translations' => [
                        'ar' => [
                            'name' => 'مكونات الخَبز',
                            'slug' => 'mukawnat-alkhabz',
                            'description' => 'مكونات تستخدم في إعداد الكيك والحلويات والمعجنات.',
                            'meta_title' => 'مكونات الخَبز',
                            'meta_keyword' => 'مكونات خبز، مكونات حلويات',
                            'meta_description' => 'مكونات موثوقة لإعداد الكيك والحلويات.',
                        ],
                    ],
                ],
                'products' => [
                    [
                        'name' => 'Premium Cocoa Powder',
                        'slug' => 'premium-cocoa-powder',
                        'description' => 'Rich cocoa powder suitable for cakes, ganache, and cookies.',
                        'base_price' => 180,
                        'sale_price' => null,
                        'quantity' => 20,
                        'translations' => [
                            'ar' => [
                                'name' => 'كاكاو خام فاخر',
                                'slug' => 'kakao-kham-fakher',
                                'description' => 'كاكاو غني مناسب للكيك والجاناش والبسكويت.',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'category' => [
                    'name' => 'Packaging',
                    'slug' => 'packaging',
                    'description' => 'Boxes and packaging essentials for customer-ready orders.',
                    'meta_title' => 'Packaging',
                    'meta_keyword' => 'packaging, cake boxes',
                    'meta_description' => 'Practical packaging for delivery and display.',
                    'translations' => [
                        'ar' => [
                            'name' => 'التغليف',
                            'slug' => 'altaghlif',
                            'description' => 'علب وتجهيزات تغليف مناسبة للطلبات الجاهزة للعملاء.',
                            'meta_title' => 'التغليف',
                            'meta_keyword' => 'تغليف، علب كيك',
                            'meta_description' => 'حلول تغليف عملية للتوصيل والعرض.',
                        ],
                    ],
                ],
                'products' => [
                    [
                        'name' => 'Window Cake Box',
                        'slug' => 'window-cake-box',
                        'description' => 'Sturdy cake box with clear window for presentation.',
                        'base_price' => 35,
                        'sale_price' => 29,
                        'quantity' => 100,
                        'translations' => [
                            'ar' => [
                                'name' => 'علبة كيك بنافذة شفافة',
                                'slug' => 'elbat-cake-binafitha-shafafa',
                                'description' => 'علبة قوية بنافذة شفافة مناسبة لعرض الكيك.',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        foreach ($catalog as $entry) {
            $categoryData = $entry['category'];
            $translations = $categoryData['translations'] ?? [];
            unset($categoryData['translations']);
            $categoryData['status'] = 0;

            $category = Category::query()->updateOrCreate(
                ['slug' => $categoryData['slug']],
                $categoryData
            );

            foreach ($translations as $locale => $translation) {
                $category->translations()->updateOrCreate(
                    ['locale' => $locale],
                    $translation
                );
            }

            foreach ($entry['products'] as $productData) {
                $productTranslations = $productData['translations'] ?? [];
                unset($productData['translations']);

                $product = Product::query()->updateOrCreate(
                    ['slug' => $productData['slug']],
                    array_merge($productData, [
                        'category_id' => $category->id,
                        'brand_id' => $brand->id,
                        'status' => true,
                        'is_featured' => true,
                        'has_variants' => false,
                        'stock_status' => ($productData['quantity'] ?? 0) > 0 ? 'in_stock' : 'out_of_stock',
                        'meta_title' => $productData['name'],
                        'meta_description' => Str::limit($productData['description'] ?? '', 150),
                    ])
                );

                foreach ($productTranslations as $locale => $translation) {
                    ProductTranslation::query()->updateOrCreate(
                        ['product_id' => $product->id, 'locale' => $locale],
                        $translation
                    );
                }
            }
        }
    }
}
