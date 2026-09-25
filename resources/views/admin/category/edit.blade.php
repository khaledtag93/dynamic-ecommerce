@extends('layouts.admin')

@section('title', __('Edit Category'))

@section('content')
    <x-admin.page-header
        :kicker="__('Catalog management')"
        :title="__('Edit Category')"
        :description="__('Update category content, image, and search metadata.')"
    >
        <a href="{{ route('admin.categories.index') }}" class="btn btn-light admin-btn-soft admin-back-btn"><i class="mdi mdi-arrow-left"></i> {{ __('Back to Categories') }}</a>
    </x-admin.page-header>

    <form action="{{ route('admin.categories.update', $category) }}" method="POST" enctype="multipart/form-data" data-submit-loading>
        @csrf
        @method('PUT')
        @php($submitLabel = __('Update Category'))
        @include('admin.category._form', ['category' => $category, 'submitLabel' => $submitLabel])
    </form>
@endsection
