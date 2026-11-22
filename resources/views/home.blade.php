@extends('layouts.app')

@section('content')
    <div id="app" data-subscription-mode="{{ \App\Services\SubscriptionPeriod::mode() }}"></div>
@endsection
