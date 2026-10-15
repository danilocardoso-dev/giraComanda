@extends('layouts.app')

@section('title', 'Novo cliente')

@section('content')
    <div class="mx-auto max-w-4xl">
        <div class="mb-6">
            <h2 class="text-2xl font-bold text-slate-950">Cadastrar cliente</h2>
            <p class="mt-1 text-sm text-slate-600">Cadastre a pessoa física que poderá receber consumos anotados.</p>
        </div>
        <form method="POST" action="{{ route('customers.store') }}" class="rounded-2xl border border-brand-100 bg-white p-6 shadow-sm sm:p-8">
            @csrf
            @include('customers._form')
        </form>
    </div>
@endsection
