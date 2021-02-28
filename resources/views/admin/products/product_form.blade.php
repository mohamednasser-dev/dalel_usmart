@extends('admin.app')
@section('title' , __('messages.add_new_product'))
@section('content')
    <div class="col-lg-12 col-12 layout-spacing">
        <div class="statbox widget box box-shadow">
            <div class="widget-header">
                <div class="row">
                    <div class="col-xl-12 col-md-12 col-sm-12 col-12">
                        <h4>{{ __('messages.add_new_product') }}</h4>
                    </div>
                </div>
                @if (session('status'))
                    <div class="alert alert-danger mb-4" role="alert">
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">x</button>
                        <strong>Error!</strong> {{ session('status') }} </button>
                    </div>
                @endif
                <form method="post" enctype="multipart/form-data" action="">
                    @csrf
                    <div class="form-group row">
                        <div class="col-md-8">
                            <h4>{{ __('messages.user') }}</h4>
                            <select class="form-control" name="user_id" id="sel1">
                                <option selected disabled>{{ __('messages.select') }}</option>
                                @foreach ($data['users'] as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                        <h4>{{ __('messages.pull_ad_cost') }}</h4>
                        <label class="switch s-icons s-outline  s-outline-primary  mb-4 mr-2">
                                <input type="checkbox" name="pull_ad_balance">
                                <span class="slider round"></span>
                            </label>
                        </div>
                    </div>
{{--                    // 0--}}
                    <div class="form-group">
                        @php $cats = \App\Category::where('deleted',0)->get(); @endphp
                        <h4>{{ __('messages.category') }}</h4>
                        <select required class="form-control" name="category_id" id="cmb_cat">
                            <option selected disabled>{{ __('messages.choose_category') }}</option>
                            @foreach ($cats as $row)
                                @if( app()->getLocale() == 'en')
                                    <option value="{{ $row->id }}">{{ $row->title_en }}</option>
                                @else
                                    <option value="{{ $row->id }}">{{ $row->title_ar }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
{{--                    // 1--}}
                    <div class="form-group" id="sub_cat_cont" style="display:none;">
                        @php $sub_cats = \App\SubCategory::where('deleted',0)->get(); @endphp
                        <h4>{{ __('messages.sub_category_first') }}</h4>
                        <select required class="form-control" name="sub_category_id" id="cmb_sub_cat">
                            <option selected disabled>{{ __('messages.choose_sub_category') }}</option>
                            @foreach ($sub_cats as $row)
                                @if( app()->getLocale() == 'en')
                                    <option value="{{ $row->id }}">{{ $row->title_en }}</option>
                                @else
                                    <option value="{{ $row->id }}">{{ $row->title_ar }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
{{--                    // 2--}}
                    <div class="form-group" id="sub_two_cat_cont" style="display:none;">
                        @php $sub_two_cats = \App\SubTwoCategory::where('deleted',0)->get(); @endphp
                        <h4>{{ __('messages.sub_category_second') }}</h4>
                        <select class="form-control" name="sub_category_two_id" id="cmb_sub_two_cat">
                            <option selected>{{ __('messages.choose_sub_two_category') }}</option>
                            @foreach ($sub_two_cats as $row)
                                @if( app()->getLocale() == 'en')
                                    <option value="{{ $row->id }}">{{ $row->title_en }}</option>
                                @else
                                    <option value="{{ $row->id }}">{{ $row->title_ar }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
{{--                    // 3--}}
                    <div class="form-group" id="sub_three_cat_cont" style="display:none;">
                        @php $sub_three_cats = \App\SubThreeCategory::where('deleted',0)->get(); @endphp
                        <h4>{{ __('messages.sub_category_third') }}</h4>
                        <select class="form-control" name="sub_category_three_id" id="cmb_sub_three_cat">
                            <option selected>{{ __('messages.choose_sub_three_category') }}</option>
                            @foreach ($sub_three_cats as $row)
                                @if( app()->getLocale() == 'en')
                                    <option value="{{ $row->id }}">{{ $row->title_en }}</option>
                                @else
                                    <option value="{{ $row->id }}">{{ $row->title_ar }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
{{--                    // 4--}}
                    <div class="form-group" id="sub_four_cat_cont" style="display:none;" >
                        @php $sub_four_cats = \App\SubFourCategory::where('deleted',0)->get(); @endphp
                        <h4>{{ __('messages.sub_category_fourth') }}</h4>
                        <select class="form-control" name="sub_category_four_id" id="cmb_sub_four_cat">
                            <option selected>{{ __('messages.choose_sub_four_category') }}</option>
                            @foreach ($sub_four_cats as $row)
                                @if( app()->getLocale() == 'en')
                                    <option value="{{ $row->id }}">{{ $row->title_en }}</option>
                                @else
                                    <option value="{{ $row->id }}">{{ $row->title_ar }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
{{--                    // 5--}}
                    <div class="form-group" id="sub_five_cat_cont" style="display:none;" >
                        @php $sub_five_cats = \App\SubFiveCategory::where('deleted','0')->get(); @endphp
                        <h4>{{ __('messages.sub_category_fifth') }}</h4>
                        <select class="form-control" name="sub_category_five_id" id="cmb_sub_five_cat">
                            <option selected>{{ __('messages.choose_sub_five_category') }}</option>
                            @foreach ($sub_five_cats as $row)
                                @if( app()->getLocale() == 'en')
                                    <option value="{{ $row->id }}">{{ $row->title_en }}</option>
                                @else
                                    <option value="{{ $row->id }}">{{ $row->title_ar }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group mb-4">
                        <h4>{{ __('messages.product_name') }}</h4>
                        <input required type="text" name="title" class="form-control" id="title"
                               placeholder="{{ __('messages.product_name') }}" value="">
                    </div>
                    <div class="form-group mb-4">
                        <h4>{{ __('messages.product_price') }}</h4>
                        <input required type="number" class="form-control" step="any" min="0" id="price" name="price"
                               placeholder="{{ __('messages.product_price') }}" value="">
                    </div>
                    <div class="form-group mb-4 arabic-direction">
                        <h4>{{ __('messages.product_description') }}</h4>
                        <textarea required name="description" placeholder="{{ __('messages.product_description') }}"
                                  class="form-control" id="description" rows="5"></textarea>
                    </div>
                    <h4>{{ __('messages.city') }}</h4>
                    <div class="form-group" id="city_cont">
                        @php $cities = \App\City::where('deleted','0')->get(); @endphp
                        <select required class="form-control" name="city_id" id="cmb_city_id">
                            <option selected>{{ __('messages.choose_city') }}</option>
                            @foreach ($cities as $row)
                                @if( app()->getLocale() == 'en')
                                    <option value="{{ $row->id }}">{{ $row->title_en }}</option>
                                @else
                                    <option value="{{ $row->id }}">{{ $row->title_ar }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group" id="area_cont" style="display:none;" >
                        @php $areas = \App\Area::where('deleted','0')->get(); @endphp
                        <select required class="form-control" name="area_id" id="cmb_area_id">
                            <option selected>{{ __('messages.choose_area') }}</option>
                            @foreach ($areas as $row)
                                @if( app()->getLocale() == 'en')
                                    <option value="{{ $row->id }}">{{ $row->title_en }}</option>
                                @else
                                    <option value="{{ $row->id }}">{{ $row->title_ar }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                    <h4>{{ __('messages.map_location') }}</h4>
                    <div class="form-group row">
                        <div class="col-lg-2 col-md-3 col-sm-4 col-6">
                        <label for="title">{{ __('messages.share_location') }}</label>
                        </div>
                        <div class="col-lg-2 col-md-3 col-sm-4 col-6">
                            <label class="switch s-icons s-outline  s-outline-primary  mb-4 mr-2">
                                <input type="checkbox" name="share_location" checked>
                                <span class="slider round"></span>
                            </label>
                        </div>
                    </div>
                    <div class="form-group row">
                        <div class="col-md-6">
                            <input required type="text" name="longitude" class="form-control" placeholder="{{ __('messages.longitude') }}">
                        </div>
                        <div class="col-md-6">
                            <input required type="text" name="latitude" class="form-control" placeholder="{{ __('messages.latitude') }}">
                        </div>
                    </div>
                    <div class="form-group mb-4 mt-3">
                        <h4>{{ __('messages.main_image') }}</h4>

                        <div class="custom-file-container" data-upload-id="mySecondImage">
                            <label>{{ __('messages.upload') }} ({{ __('messages.single_image') }}) <a href="javascript:void(0)" class="custom-file-container__image-clear" title="Clear Image">x</a></label>
                            <label class="custom-file-container__custom-file" >
                                <input type="file" required name="main_image" class="custom-file-container__custom-file__custom-file-input" accept="image/*">
                                <input type="hidden" name="MAX_FILE_SIZE" value="10485760" />
                                <span class="custom-file-container__custom-file__custom-file-control"></span>
                            </label>
                            <div class="custom-file-container__image-preview">

                            </div>
                        </div>
                    </div>
                    <h4>{{ __('messages.ad_images') }}</h4>
                    <div class="custom-file-container" data-upload-id="myFirstImage">
                        <label>{{ __('messages.upload') }} ({{ __('messages.multiple_images') }}) <a
                                href="javascript:void(0)" class="custom-file-container__image-clear"
                                title="Clear Image">x</a></label>
                        <label class="custom-file-container__custom-file">
                            <input type="file" required name="images[]" multiple
                                   class="custom-file-container__custom-file__custom-file-input" accept="image/*">
                            <input type="hidden" name="MAX_FILE_SIZE" value="10485760"/>
                            <span class="custom-file-container__custom-file__custom-file-control"></span>
                        </label>
                        <div class="custom-file-container__image-preview"></div>
                    </div>
                    <input type="submit" value="{{ __('messages.add') }}" class="btn btn-primary">
                </form>
            </div>

@endsection
@section('scripts')
   <script src="/admin/assets/js/generate_categories.js"></script>
@endsection
