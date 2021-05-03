@extends('admin.app')

@section('title' , __('messages.show_products'))

@section('content')
    <div id="tableSimple" class="col-lg-12 col-12 layout-spacing">
        <div class="statbox widget box box-shadow">
            <div class="widget-header">
            <div class="row">

                <div class="col-xl-12 col-md-12 col-sm-12 col-12">
                    <h4>{{ __('messages.show_products') }} {{ isset($data['user']) ? '( ' . $data['user'] . ' )' : '' }} {{ isset($data['category']) ? '( ' . $data['category'] . ' )' : '' }}</h4>
                </div>
            </div>
        </div>
        <div class="widget-content widget-content-area">
            <div class="table-responsive">
                <table id="html5-extension" class="table table-hover non-hover" style="width:100%">
                    <thead>
                        <tr>
                            <th class="text-center">Id</th>
                            <th class="text-center">{{ __('messages.publication_date') }}</th>
                            <th class="text-center">{{ __('messages.product_name') }}</th>
                            <th class="text-center">{{ __('messages.category_title') }}</th>
                            <th class="text-center">{{ __('messages.user') }}</th>
                            <th class="text-center">{{ __('messages.archived_or_not') }}</th>
                            <th class="text-center">{{ __('messages.pin_it_now') }}</th>
                            <th class="text-center">{{ __('messages.details') }}</th>
                            @if(Auth::user()->update_data)
                                <th class="text-center">{{ __('messages.edit') }}</th>
                            @endif
                            @if(Auth::user()->delete_data)
                            <th class="text-center" >{{ __('messages.delete') }}</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; ?>
                            @foreach ($data['products'] as $product)
                                <tr >
                                    <td class="text-center"><?=$i;?></td>
                                    <td class="text-center">
                                        @if( $product->publication_date != null)
                                            {{date('Y-m-d', strtotime($product->publication_date))}}
                                        @else
                                            {{ __('messages.not_publish_yet') }}
                                        @endif</td>
                                    <td class="text-center">{{ $product->title }}</td>
                                    <td class="text-center">{{ app()->getLocale() == 'en' ? $product->category->title_en : $product->category->title_ar }}</td>
                                    <td class="text-center">
                                        <a href="{{ route('users.details', $product->user->id) }}" target="_blank">
                                            {{ $product->user->name }}
                                        </a>
                                    </td>
                                    <td class="text-center">{{ $product->status == 1 ? __('messages.published') : __('messages.archived') }}</td>
                                    <td class="text-center">
                                        @if($product->pin == 0)
                                            <a  id="btn_pin" data-toggle="modal" data-id="{{$product->id}}" data-pin="{{$product->pin}}" @if($product->expire_pin_date != null) data-expire="{{date('Y-m-d', strtotime($product->expire_pin_date)) }}" @endif
                                                data-target="#save_model" class="btn btn-dark  mb-2 mr-2 rounded-circle" title="{{ __('messages.pin_it') }}"
                                               data-original-title="Tooltip using BUTTON tag">
                                                <div class="icon-container">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-paperclip"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"></path></svg><span class="icon-name"></span>
                                                </div>
                                            </a>
                                        @else
                                            <a  id="btn_pin" data-toggle="modal" data-id="{{$product->id}}" data-pin="{{$product->pin}}" @if($product->expire_pin_date != null) data-expire="{{date('Y-m-d', strtotime($product->expire_pin_date)) }}" @endif
                                                data-target="#save_model" class="btn btn-info  mb-2 mr-2 rounded-circle" title="{{ __('messages.pin_it') }}"
                                                data-original-title="Tooltip using BUTTON tag">
                                                <div class="icon-container">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-paperclip"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"></path></svg><span class="icon-name"></span>
                                                </div>
                                            </a>
                                        @endif
                                    </td>
                                    <td class="text-center blue-color"><a href="{{ route('products.details', $product->id) }}" ><i class="far fa-eye"></i></a></td>
                                    @if(Auth::user()->update_data)
                                        <td class="text-center blue-color" ><a href="{{ route('products.edit', $product->id) }}" ><i class="far fa-edit"></i></a></td>
                                    @endif
                                    @if(Auth::user()->delete_data)
                                        <td class="text-center blue-color" ><a onclick="return confirm('{{ __('messages.are_you_sure') }}');" href="{{ route('delete.product', $product->id) }}" ><i class="far fa-trash-alt"></i></a></td>
                                    @endif
                                    <?php $i++; ?>
                                </tr>
                            @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div id="save_model" class="modal animated zoomInUp custo-zoomInUp" role="dialog">
            <div class="modal-dialog">
                <!-- Modal content-->
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ __('messages.pin_it_now') }}</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                 viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                 stroke-linecap="round" stroke-linejoin="round" class="feather feather-x">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                        </button>
                    </div>
                    <form action="{{route('product.make_pin')}}" method="post"
                          enctype="multipart/form-data">
                        @csrf
                        <input required type="hidden" id="txt_ad_id" name="ad_id" class="form-control">
                        <div class="modal-body">
                            <div class="form-group mb-4">
                                <label for="plan_price">{{ __('messages.expire_date') }}</label>
                                <input type="text" readonly id="txt_expire" name="expire" class="form-control">
                            </div>
                            <div class="form-group mb-4">
                                <label for="plan_price">{{ __('messages.day_num') }}</label>
                                <input required type="number" min="0" name="day_num" class="form-control">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button class="btn" data-dismiss="modal">
                                <i class="flaticon-cancel-12"></i> {{ __('messages.cancel') }}
                            </button>
                            <button type="submit" class="btn btn-primary">{{ __('messages.save') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
@section('scripts')
    <script>
        $(document).ready(function () {
            $(document).on('click', '#btn_pin', function () {
                ad_id = $(this).data('id');
                expire = $(this).data('expire');
                $("#txt_ad_id").val(ad_id);
                $("#txt_expire").val(expire);
            });
        });
    </script>
@endsection


