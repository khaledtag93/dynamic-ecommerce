<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WebsiteSetting;
use App\Services\Commerce\StoreSettingsService;
use App\Support\MediaPath;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class SettingController extends Controller
{
    public function __construct(protected StoreSettingsService $settingsService)
    {
    }

    private function themeFields(): array
    {
        return [
            'theme_preset',
            'brand_primary_color',
            'brand_secondary_color',
            'brand_accent_color',
            'brand_background_color',
            'brand_surface_color',
            'brand_soft_color',
            'brand_border_color',
            'brand_muted_bg_color',
            'brand_table_head_color',
            'brand_row_hover_color',
            'brand_button_text_color',
            'admin_sidebar_color',
            'admin_header_color',
            'admin_surface_color',
            'admin_card_border_color',
            'admin_accent_soft_color',
            'admin_primary_soft_color',
            'customer_card_radius',
            'customer_badge_style',
        ];
    }

    private function themePresets(): array
    {
        return [
            'sunset_bakery' => [
                'theme_preset' => 'sunset_bakery',
                'brand_primary_color' => '#f97316',
                'brand_secondary_color' => '#ec4899',
                'brand_accent_color' => '#fb923c',
                'brand_background_color' => '#fffaf5',
                'brand_surface_color' => '#ffffff',
                'brand_soft_color' => '#fff7ed',
                'brand_border_color' => '#fed7aa',
                'brand_muted_bg_color' => '#fff1f2',
                'brand_table_head_color' => '#fff4ec',
                'brand_row_hover_color' => '#fffaf6',
                'brand_button_text_color' => '#ffffff',
                'admin_sidebar_color' => '#0f172a',
                'admin_header_color' => '#fff2e7',
                'admin_surface_color' => '#ffffff',
                'admin_card_border_color' => '#f2dac8',
                'admin_accent_soft_color' => '#fff1f2',
                'admin_primary_soft_color' => '#ffedd5',
                'customer_card_radius' => '20',
                'customer_badge_style' => 'pill',
            ],
            'midnight_luxury' => [
                'theme_preset' => 'midnight_luxury',
                'brand_primary_color' => '#7c3aed',
                'brand_secondary_color' => '#0f172a',
                'brand_accent_color' => '#c084fc',
                'brand_background_color' => '#f8fafc',
                'brand_surface_color' => '#ffffff',
                'brand_soft_color' => '#f5f3ff',
                'brand_border_color' => '#ddd6fe',
                'brand_muted_bg_color' => '#ede9fe',
                'brand_table_head_color' => '#f5f3ff',
                'brand_row_hover_color' => '#faf5ff',
                'brand_button_text_color' => '#ffffff',
                'admin_sidebar_color' => '#111827',
                'admin_header_color' => '#ede9fe',
                'admin_surface_color' => '#ffffff',
                'admin_card_border_color' => '#ddd6fe',
                'admin_accent_soft_color' => '#f5f3ff',
                'admin_primary_soft_color' => '#ede9fe',
                'customer_card_radius' => '24',
                'customer_badge_style' => 'soft',
            ],
            'fresh_market' => [
                'theme_preset' => 'fresh_market',
                'brand_primary_color' => '#16a34a',
                'brand_secondary_color' => '#0f766e',
                'brand_accent_color' => '#22c55e',
                'brand_background_color' => '#f0fdf4',
                'brand_surface_color' => '#ffffff',
                'brand_soft_color' => '#dcfce7',
                'brand_border_color' => '#86efac',
                'brand_muted_bg_color' => '#ecfdf5',
                'brand_table_head_color' => '#dcfce7',
                'brand_row_hover_color' => '#f7fee7',
                'brand_button_text_color' => '#ffffff',
                'admin_sidebar_color' => '#14532d',
                'admin_header_color' => '#dcfce7',
                'admin_surface_color' => '#ffffff',
                'admin_card_border_color' => '#bbf7d0',
                'admin_accent_soft_color' => '#ecfdf5',
                'admin_primary_soft_color' => '#dcfce7',
                'customer_card_radius' => '18',
                'customer_badge_style' => 'outline',
            ],
            'ocean_breeze' => [
                'theme_preset' => 'ocean_breeze',
                'brand_primary_color' => '#0ea5e9',
                'brand_secondary_color' => '#0f766e',
                'brand_accent_color' => '#38bdf8',
                'brand_background_color' => '#f0f9ff',
                'brand_surface_color' => '#ffffff',
                'brand_soft_color' => '#e0f2fe',
                'brand_border_color' => '#bae6fd',
                'brand_muted_bg_color' => '#ecfeff',
                'brand_table_head_color' => '#e0f2fe',
                'brand_row_hover_color' => '#f0fdfa',
                'brand_button_text_color' => '#ffffff',
                'admin_sidebar_color' => '#082f49',
                'admin_header_color' => '#e0f2fe',
                'admin_surface_color' => '#ffffff',
                'admin_card_border_color' => '#bae6fd',
                'admin_accent_soft_color' => '#ecfeff',
                'admin_primary_soft_color' => '#e0f2fe',
                'customer_card_radius' => '22',
                'customer_badge_style' => 'soft',
            ],
            'rose_boutique' => [
                'theme_preset' => 'rose_boutique',
                'brand_primary_color' => '#e11d48',
                'brand_secondary_color' => '#9d174d',
                'brand_accent_color' => '#fb7185',
                'brand_background_color' => '#fff1f2',
                'brand_surface_color' => '#ffffff',
                'brand_soft_color' => '#ffe4e6',
                'brand_border_color' => '#fecdd3',
                'brand_muted_bg_color' => '#fff7ed',
                'brand_table_head_color' => '#ffe4e6',
                'brand_row_hover_color' => '#fffafc',
                'brand_button_text_color' => '#ffffff',
                'admin_sidebar_color' => '#4a044e',
                'admin_header_color' => '#ffe4e6',
                'admin_surface_color' => '#ffffff',
                'admin_card_border_color' => '#fecdd3',
                'admin_accent_soft_color' => '#fff1f2',
                'admin_primary_soft_color' => '#ffe4e6',
                'customer_card_radius' => '26',
                'customer_badge_style' => 'pill',
            ],
            'desert_gold' => [
                'theme_preset' => 'desert_gold',
                'brand_primary_color' => '#d97706',
                'brand_secondary_color' => '#92400e',
                'brand_accent_color' => '#f59e0b',
                'brand_background_color' => '#fffbeb',
                'brand_surface_color' => '#ffffff',
                'brand_soft_color' => '#fef3c7',
                'brand_border_color' => '#fcd34d',
                'brand_muted_bg_color' => '#fff7ed',
                'brand_table_head_color' => '#fef3c7',
                'brand_row_hover_color' => '#fffbeb',
                'brand_button_text_color' => '#ffffff',
                'admin_sidebar_color' => '#451a03',
                'admin_header_color' => '#fef3c7',
                'admin_surface_color' => '#ffffff',
                'admin_card_border_color' => '#fde68a',
                'admin_accent_soft_color' => '#fff7ed',
                'admin_primary_soft_color' => '#fef3c7',
                'customer_card_radius' => '20',
                'customer_badge_style' => 'outline',
            ],
            'graphite_modern' => [
                'theme_preset' => 'graphite_modern',
                'brand_primary_color' => '#111827',
                'brand_secondary_color' => '#374151',
                'brand_accent_color' => '#f59e0b',
                'brand_background_color' => '#f9fafb',
                'brand_surface_color' => '#ffffff',
                'brand_soft_color' => '#f3f4f6',
                'brand_border_color' => '#d1d5db',
                'brand_muted_bg_color' => '#f5f5f4',
                'brand_table_head_color' => '#f3f4f6',
                'brand_row_hover_color' => '#f9fafb',
                'brand_button_text_color' => '#ffffff',
                'admin_sidebar_color' => '#030712',
                'admin_header_color' => '#e5e7eb',
                'admin_surface_color' => '#ffffff',
                'admin_card_border_color' => '#d1d5db',
                'admin_accent_soft_color' => '#f3f4f6',
                'admin_primary_soft_color' => '#e5e7eb',
                'customer_card_radius' => '16',
                'customer_badge_style' => 'soft',
            ],
        ];
    }

    private function customThemes(): array
    {
        $raw = WebsiteSetting::getValue('custom_themes', '[]');
        $decoded = json_decode((string) $raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function allThemes(): array
    {
        return $this->themePresets() + $this->customThemes();
    }

    public function edit()
    {
        $settings = $this->settingsService->all();
        $presets = $this->allThemes();
        $customThemes = $this->customThemes();

        return view('admin.settings.branding', compact('settings', 'presets', 'customThemes'));
    }

    public function update(Request $request)
    {
        $data = $request->validate($this->rules());

        foreach (['logo', 'admin_logo', 'hero_banner', 'favicon'] as $baseField) {
            $this->handleBrandingUpload($request, $data, $baseField, $baseField . '_file', $baseField . '_path');
        }

        foreach (range(1, 3) as $index) {
            $this->handleBrandingUpload(
                $request,
                $data,
                "promo_banner_{$index}",
                "promo_banner_{$index}_file",
                "promo_banner_{$index}_image_path"
            );
        }

        foreach ($this->booleanFields() as $booleanKey) {
            $data[$booleanKey] = $request->boolean($booleanKey);
        }

        if (! in_array($data['home_featured_categories_source'] ?? 'manual', ['manual', 'latest'], true)) {
            $data['home_featured_categories_source'] = 'manual';
        }

        $themes = $this->allThemes();
        if (! empty($data['theme_preset']) && isset($themes[$data['theme_preset']])) {
            $data = array_merge($themes[$data['theme_preset']], $data);
        }

        if (! empty($data['save_as_custom_theme']) && ! empty($data['custom_theme_name'])) {
            $customThemes = $this->customThemes();
            $themeKey = 'custom_' . Str::slug($data['custom_theme_name'], '_');
            $customThemes[$themeKey] = array_merge(
                ['theme_preset' => $themeKey, 'theme_label' => trim((string) $data['custom_theme_name'])],
                collect($data)->only($this->themeFields())->toArray()
            );
            WebsiteSetting::setValue('custom_themes', json_encode($customThemes, JSON_UNESCAPED_UNICODE), 'branding', 'json');
            $data['theme_preset'] = $themeKey;
        }

        $transientKeys = ['logo_file', 'admin_logo_file', 'hero_banner_file', 'favicon_file', 'save_as_custom_theme', 'custom_theme_name'];
        foreach (range(1, 3) as $index) {
            $transientKeys[] = "promo_banner_{$index}_file";
        }
        unset($data[array_shift($transientKeys)]);
        foreach ($transientKeys as $key) {
            unset($data[$key]);
        }

        $this->settingsService->save($data);

        return back()->with('success', __('Brand settings updated successfully.'));
    }

    private function rules(): array
    {
        $rules = [
            'theme_preset' => ['nullable', 'string', 'max:100'],
            'default_locale' => ['nullable', 'string', 'max:5'],
            'project_name' => ['required', 'string', 'max:255'],
            'store_name' => ['required', 'string', 'max:255'],
            'store_tagline' => ['nullable', 'string', 'max:255'],
            'footer_about' => ['nullable', 'string', 'max:600'],
            'footer_copyright' => ['nullable', 'string', 'max:255'],
            'brand_primary_color' => ['nullable', 'regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/'],
            'brand_secondary_color' => ['nullable', 'regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/'],
            'brand_accent_color' => ['nullable', 'regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/'],
            'brand_background_color' => ['nullable', 'regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/'],
            'brand_surface_color' => ['nullable', 'regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/'],
            'brand_soft_color' => ['nullable', 'regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/'],
            'brand_border_color' => ['nullable', 'regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/'],
            'brand_muted_bg_color' => ['nullable', 'regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/'],
            'brand_table_head_color' => ['nullable', 'regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/'],
            'brand_row_hover_color' => ['nullable', 'regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/'],
            'brand_button_text_color' => ['nullable', 'regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/'],
            'admin_sidebar_color' => ['nullable', 'regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/'],
            'admin_header_color' => ['nullable', 'regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/'],
            'admin_surface_color' => ['nullable', 'regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/'],
            'admin_card_border_color' => ['nullable', 'regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/'],
            'admin_accent_soft_color' => ['nullable', 'regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/'],
            'admin_primary_soft_color' => ['nullable', 'regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/'],
            'hero_title' => ['nullable', 'string', 'max:255'],
            'hero_subtitle' => ['nullable', 'string', 'max:1000'],
            'hero_badge_text' => ['nullable', 'string', 'max:120'],
            'hero_primary_button_text' => ['nullable', 'string', 'max:100'],
            'hero_primary_button_link' => ['nullable', 'string', 'max:255'],
            'hero_secondary_button_text' => ['nullable', 'string', 'max:100'],
            'hero_secondary_button_link' => ['nullable', 'string', 'max:255'],
            'logo_path' => ['nullable', 'string', 'max:255'],
            'favicon_path' => ['nullable', 'string', 'max:255'],
            'admin_logo_path' => ['nullable', 'string', 'max:255'],
            'hero_banner_path' => ['nullable', 'string', 'max:255'],
            'customer_card_radius' => ['nullable', 'integer', 'min:8', 'max:40'],
            'customer_badge_style' => ['nullable', 'string', 'max:50'],
            'homepage_sections_order' => ['nullable', 'string', 'max:255'],

            'show_home_hero' => ['nullable', 'boolean'],
            'show_home_categories' => ['nullable', 'boolean'],
            'show_home_featured_categories' => ['nullable', 'boolean'],
            'show_home_featured_products' => ['nullable', 'boolean'],
            'show_home_manual_featured_products' => ['nullable', 'boolean'],
            'show_home_latest_products' => ['nullable', 'boolean'],
            'show_home_best_sellers' => ['nullable', 'boolean'],
            'show_home_on_sale_products' => ['nullable', 'boolean'],
            'show_home_promo_banners' => ['nullable', 'boolean'],
            'show_home_promo_banner' => ['nullable', 'boolean'],

            'home_featured_products_title' => ['nullable', 'string', 'max:120'],
            'home_featured_products_subtitle' => ['nullable', 'string', 'max:120'],
            'home_featured_products_limit' => ['nullable', 'integer', 'min:1', 'max:24'],
            'home_manual_featured_products_title' => ['nullable', 'string', 'max:120'],
            'home_manual_featured_products_subtitle' => ['nullable', 'string', 'max:120'],
            'home_manual_featured_products_limit' => ['nullable', 'integer', 'min:1', 'max:24'],
            'home_manual_featured_products_ids' => ['nullable', 'string', 'max:1000'],
            'home_manual_featured_products_action_text' => ['nullable', 'string', 'max:100'],
            'home_manual_featured_products_action_link' => ['nullable', 'string', 'max:255'],
            'home_categories_title' => ['nullable', 'string', 'max:120'],
            'home_categories_subtitle' => ['nullable', 'string', 'max:120'],
            'home_categories_limit' => ['nullable', 'integer', 'min:1', 'max:24'],
            'home_featured_categories_title' => ['nullable', 'string', 'max:120'],
            'home_featured_categories_subtitle' => ['nullable', 'string', 'max:120'],
            'home_featured_categories_limit' => ['nullable', 'integer', 'min:1', 'max:24'],
            'home_featured_categories_ids' => ['nullable', 'string', 'max:1000'],
            'home_featured_categories_source' => ['nullable', 'string', 'max:20'],
            'home_latest_products_title' => ['nullable', 'string', 'max:120'],
            'home_latest_products_subtitle' => ['nullable', 'string', 'max:120'],
            'home_latest_products_limit' => ['nullable', 'integer', 'min:1', 'max:24'],
            'home_best_sellers_title' => ['nullable', 'string', 'max:120'],
            'home_best_sellers_subtitle' => ['nullable', 'string', 'max:120'],
            'home_best_sellers_limit' => ['nullable', 'integer', 'min:1', 'max:24'],
            'home_on_sale_products_title' => ['nullable', 'string', 'max:120'],
            'home_on_sale_products_subtitle' => ['nullable', 'string', 'max:120'],
            'home_on_sale_products_limit' => ['nullable', 'integer', 'min:1', 'max:24'],
            'home_trust_blocks_title' => ['nullable', 'string', 'max:120'],
            'home_trust_blocks_subtitle' => ['nullable', 'string', 'max:160'],
            'home_promo_title' => ['nullable', 'string', 'max:160'],
            'home_promo_subtitle' => ['nullable', 'string', 'max:500'],
            'home_promo_button_text' => ['nullable', 'string', 'max:100'],
            'home_promo_button_link' => ['nullable', 'string', 'max:255'],
            'home_promo_secondary_button_text' => ['nullable', 'string', 'max:100'],
            'home_promo_secondary_button_link' => ['nullable', 'string', 'max:255'],

            'trust_block_1_icon' => ['nullable', 'string', 'max:100'],
            'trust_block_1_title' => ['nullable', 'string', 'max:120'],
            'trust_block_1_subtitle' => ['nullable', 'string', 'max:255'],
            'trust_block_1_active' => ['nullable', 'boolean'],
            'trust_block_1_sort_order' => ['nullable', 'integer', 'min:1', 'max:99'],
            'trust_block_2_icon' => ['nullable', 'string', 'max:100'],
            'trust_block_2_title' => ['nullable', 'string', 'max:120'],
            'trust_block_2_subtitle' => ['nullable', 'string', 'max:255'],
            'trust_block_2_active' => ['nullable', 'boolean'],
            'trust_block_2_sort_order' => ['nullable', 'integer', 'min:1', 'max:99'],
            'trust_block_3_icon' => ['nullable', 'string', 'max:100'],
            'trust_block_3_title' => ['nullable', 'string', 'max:120'],
            'trust_block_3_subtitle' => ['nullable', 'string', 'max:255'],
            'trust_block_3_active' => ['nullable', 'boolean'],
            'trust_block_3_sort_order' => ['nullable', 'integer', 'min:1', 'max:99'],
            'trust_block_4_icon' => ['nullable', 'string', 'max:100'],
            'trust_block_4_title' => ['nullable', 'string', 'max:120'],
            'trust_block_4_subtitle' => ['nullable', 'string', 'max:255'],
            'trust_block_4_active' => ['nullable', 'boolean'],
            'trust_block_4_sort_order' => ['nullable', 'integer', 'min:1', 'max:99'],
            'logo_file' => ['nullable', 'image', 'max:4096'],
            'admin_logo_file' => ['nullable', 'image', 'max:4096'],
            'hero_banner_file' => ['nullable', 'image', 'max:8192'],
            'favicon_file' => ['nullable', 'file', 'mimes:ico,png,svg,webp,jpg,jpeg', 'max:2048'],
            'save_as_custom_theme' => ['nullable', 'boolean'],
            'custom_theme_name' => ['nullable', 'string', 'max:80'],
        ];

        foreach (range(1, 3) as $index) {
            $rules["promo_banner_{$index}_title"] = ['nullable', 'string', 'max:160'];
            $rules["promo_banner_{$index}_subtitle"] = ['nullable', 'string', 'max:500'];
            $rules["promo_banner_{$index}_button_text"] = ['nullable', 'string', 'max:100'];
            $rules["promo_banner_{$index}_button_link"] = ['nullable', 'string', 'max:255'];
            $rules["promo_banner_{$index}_image_path"] = ['nullable', 'string', 'max:255'];
            $rules["promo_banner_{$index}_active"] = ['nullable', 'boolean'];
            $rules["promo_banner_{$index}_sort_order"] = ['nullable', 'integer', 'min:1', 'max:99'];
            $rules["promo_banner_{$index}_file"] = ['nullable', 'image', 'max:8192'];
        }

        return $rules;
    }

    private function booleanFields(): array
    {
        return [
            'show_home_hero',
            'show_home_categories',
            'show_home_featured_products',
            'show_home_latest_products',
            'show_home_best_sellers',
            'show_home_on_sale_products',
            'show_home_promo_banners',
            'show_home_promo_banner',
            'save_as_custom_theme',
            'promo_banner_1_active',
            'promo_banner_2_active',
            'promo_banner_3_active',
        ];
    }

    private function handleBrandingUpload(Request $request, array &$data, string $settingKeyBase, string $fileField, string $pathField): void
    {
        if (! $request->hasFile($fileField)) {
            return;
        }

        $oldPath = WebsiteSetting::getValue($pathField) ?: WebsiteSetting::getValue($settingKeyBase);

        $extension = strtolower($request->file($fileField)->getClientOriginalExtension() ?: 'jpg');
        $filename = Str::slug($settingKeyBase) . '-' . now()->format('YmdHis') . '-' . Str::random(6) . '.' . $extension;
        $destination = MediaPath::uploadsRootPath('branding');

        if (! File::exists($destination)) {
            File::makeDirectory($destination, 0755, true);
        }

        $request->file($fileField)->move($destination, $filename);
        $data[$pathField] = 'branding/' . $filename;

        if (in_array($settingKeyBase, ['logo', 'admin_logo', 'hero_banner'], true)) {
            $data[$settingKeyBase] = $data[$pathField];
        }

        $this->deleteObsoleteBrandingFile($oldPath, $data[$pathField]);
    }

    private function deleteObsoleteBrandingFile(?string $oldPath, ?string $newPath = null): void
    {
        if (blank($oldPath)) {
            return;
        }

        $normalized = MediaPath::normalizeRelative($oldPath, 'branding');

        if (blank($normalized) || $normalized === $newPath || ! str_starts_with($normalized, 'branding/')) {
            return;
        }

        $fullPath = MediaPath::uploadsRootPath($normalized);

        if (File::exists($fullPath)) {
            File::delete($fullPath);
        }
    }
}
