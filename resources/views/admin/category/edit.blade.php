@extends('layouts.admin')

@section('title', __('Edit Category'))

@section('content')
    <div class="admin-page-header">
        <div>
            <h1 class="admin-page-title">{{ __('Edit Category') }}</h1>
            <p class="admin-page-description">{{ __('Update category content, image, and search metadata.') }}</p>
        </div>

        <div class="admin-page-actions">
            <a href="{{ route('admin.categories.index') }}" class="btn btn-light admin-btn-soft admin-back-btn"><i class="mdi mdi-arrow-left"></i> {{ __('Back to Categories') }}</a>
        </div>
    </div>

    <form action="{{ route('admin.categories.update', $category) }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        @php($submitLabel = __('Update Category'))
        @include('admin.category._form', ['category' => $category, 'submitLabel' => $submitLabel])
    </form>
@endsection
