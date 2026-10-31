@extends($layout['view'])

@section($layout['title'], 'Visitor Log')

@if ($layout['header'])
    @section($layout['header'])
        @include('visitor-counter::partials.header')
    @endsection
@endif

@section($layout['content'])
    @unless ($layout['header'])
        @include('visitor-counter::partials.header')
    @endunless
    @include('visitor-counter::partials.content')
@endsection
