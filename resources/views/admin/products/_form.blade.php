<div class="row">

    <div class="col-md-6 mb-3">
        <label>{{ __('Name') }}</label>
        <input type="text" name="name" class="form-control"
               value="{{ old('name', $product->name ?? '') }}">
    </div>

    <div class="col-md-6 mb-3">
        <label>{{ __('Slug') }}</label>
        <input type="text" name="slug" class="form-control"
               value="{{ old('slug', $product->slug ?? '') }}">
    </div>

    <div class="col-md-6 mb-3">
        <label>{{ __('Category') }}</label>
        <select name="category_id" class="form-control">
            @foreach($categories as $cat)
                <option value="{{ $cat->id }}"
                    @selected(old('category_id', $product->category_id ?? '') == $cat->id)>
                    {{ $cat->name }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="col-md-6 mb-3">
        <label>{{ __('Brand') }}</label>
        <select name="brand_id" class="form-control">
            <option value="">--</option>
            @foreach($brands as $brand)
                <option value="{{ $brand->id }}"
                    @selected(old('brand_id', $product->brand_id ?? '') == $brand->id)>
                    {{ $brand->name }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="col-md-4 mb-3">
        <label>{{ __('Price') }}</label>
        <input type="number" name="base_price" class="form-control"
               value="{{ old('base_price', $product->base_price ?? '') }}">
    </div>

    <div class="col-md-4 mb-3">
        <label>{{ __('Sale Price') }}</label>
        <input type="number" name="sale_price" class="form-control"
               value="{{ old('sale_price', $product->sale_price ?? '') }}">
    </div>

    <div class="col-md-4 mb-3">
        <label>{{ __('Quantity') }}</label>
        <input type="number" name="quantity" class="form-control"
               value="{{ old('quantity', $product->quantity ?? 0) }}">
    </div>

    <div class="col-md-12 mb-3">
        <label>{{ __('Description') }}</label>
        <textarea name="description" class="form-control">{{ old('description', $product->description ?? '') }}</textarea>
    </div>

    <div class="col-md-12 mb-3">
        <label>{{ __('Images') }}</label>
        <input type="file" name="images[]" multiple class="form-control">
    </div>

</div>