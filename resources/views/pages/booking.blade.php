@extends('layouts.app')

@section('content')
    @livewire('booking-calendar', ['tenantId' => $tenant->id])
@endsection
