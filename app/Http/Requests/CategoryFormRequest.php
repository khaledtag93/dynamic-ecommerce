<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CategoryFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'mimes:jpg,jpeg,png,webp'],
            'meta_title' => ['required', 'string', 'max:255'],
            'meta_description' => ['required', 'string'],
            'meta_keyword' => ['required', 'string'],
        ];
    }
}
