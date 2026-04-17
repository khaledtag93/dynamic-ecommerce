<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\App;

trait TranslatableModel
{
    abstract public function translations(): HasMany;

    public function getTranslation(?string $locale = null)
    {
        $locale ??= App::getLocale();

        if ($this->relationLoaded('translations')) {
            $translations = $this->getRelation('translations');

            return $translations->firstWhere('locale', $locale)
                ?: $translations->firstWhere('locale', config('app.fallback_locale', 'en'));
        }

        return $this->translations()
            ->whereIn('locale', array_values(array_unique([$locale, config('app.fallback_locale', 'en')])))
            ->get()
            ->sortBy(fn ($item) => $item->locale === $locale ? 0 : 1)
            ->first();
    }

    protected function translatedAttribute(string $attribute, mixed $fallback = null, ?string $locale = null): mixed
    {
        $translation = $this->getTranslation($locale);

        if ($translation && filled($translation->{$attribute} ?? null)) {
            return $translation->{$attribute};
        }

        return $fallback ?? $this->getRawOriginal($attribute);
    }
}
