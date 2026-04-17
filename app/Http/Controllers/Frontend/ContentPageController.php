<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Services\Commerce\StoreSettingsService;

class ContentPageController extends Controller
{
    public function __construct(protected StoreSettingsService $settingsService)
    {
    }

    public function contact()
    {
        $settings = $this->settingsService->all();

        return view('frontend.pages.contact', compact('settings'));
    }

    public function privacy()
    {
        return $this->renderLegalPage('privacy');
    }

    public function terms()
    {
        return $this->renderLegalPage('terms');
    }

    public function refund()
    {
        return $this->renderLegalPage('refund');
    }

    public function shipping()
    {
        return $this->renderLegalPage('shipping');
    }

    protected function renderLegalPage(string $page)
    {
        $settings = $this->settingsService->all();

        return view('frontend.pages.legal', [
            'pageKey' => $page,
            'title' => $settings["legal_{$page}_title"] ?? '',
            'intro' => $settings["legal_{$page}_intro"] ?? '',
            'body' => $settings["legal_{$page}_body"] ?? '',
            'settings' => $settings,
        ]);
    }
}
