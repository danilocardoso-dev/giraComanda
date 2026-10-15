@extends('layouts.app')

@section('title', 'Editar produto')

@section('content')
    <div class="mx-auto max-w-3xl">
        <div class="mb-6">
            <h2 class="text-2xl font-bold text-slate-950">{{ $product->name }}</h2>
            <p class="mt-1 text-sm text-slate-600">Atualize preço, estoque ou disponibilidade.</p>
        </div>
        <form method="POST" action="{{ route('products.update', $product) }}" class="rounded-2xl border border-brand-100 bg-white p-6 shadow-sm sm:p-8">
            @csrf
            @method('PUT')
            @include('products._form')
        </form>
    </div>
@endsection
