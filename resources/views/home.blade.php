@extends('layouts.app')

@section('content')
    <div id="app" data-public="{{ auth()->check() ? 'auth' : 'public' }}"
         data-subscription-mode="{{ \App\Services\SubscriptionPeriod::mode() }}"
         data-fixed-period-end="{{ \App\Services\SubscriptionPeriod::fixedPeriodEnd() }}"
         data-fixed-switch-date="{{ \App\Services\SubscriptionPeriod::fixedSwitchDate() }}"></div>
@endsection
