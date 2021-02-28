
@if(app()->getLocale() == 'ar')
    <option value="">{{ __('messages.choose_area') }}</option>
    @forelse($data as $row)
        <option value="{{$row->id}}">{{$row->title_ar}}</option>
    @empty
        <option disabled selected=""> لا يوجد مناطق حتى الأن </option>
    @endforelse
@else
    <option value="">{{ __('messages.choose_area') }}</option>
    @forelse($data as $row)
        <option value="{{$row->id}}">{{$row->title_en}}</option>
    @empty
        <option disabled selected=""> no areas until now</option>
    @endforelse
@endif
