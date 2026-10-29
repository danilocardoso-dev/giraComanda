@extends('layouts.app')

@section('title', 'Pedidos do cardápio')

@php($money = fn (int $cents): string => 'R$ '.number_format($cents / 100, 2, ',', '.'))

@section('content')
    <div data-menu-orders-page data-latest-id="{{ (int) $pendingOrders->max('id') }}" class="space-y-8">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="text-2xl font-bold text-slate-950">Pedidos aguardando confirmação</h2>
                <p class="mt-1 text-sm text-slate-600">Pedidos feitos pelo cliente no QR Code do cartão. Ao aceitar, os itens entram na comanda com baixa de estoque.</p>
            </div>
            <p class="text-xs font-semibold text-slate-500">Esta tela se atualiza sozinha e toca um aviso a cada novo pedido.</p>
        </div>

        <x-input-error :messages="$errors->get('menu_order')" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm" />
        <x-input-error :messages="$errors->get('quantity')" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm" />
        <x-input-error :messages="$errors->get('product_id')" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm" />
        <x-input-error :messages="$errors->get('card_id')" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm" />

        <div class="grid items-start gap-4 lg:grid-cols-2">
            @forelse ($pendingOrders as $order)
                <article class="rounded-2xl border-2 border-warm-400 bg-white p-5 shadow-sm">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[0.16em] text-warm-600">Pedido #{{ $order->id }} · {{ $order->created_at->format('H:i') }}</p>
                            <h3 class="mt-1 text-xl font-black text-slate-950">Cartão {{ $order->card->code }}</h3>
                            @if ($order->customer_name)
                                <p class="text-sm font-semibold text-slate-700">{{ $order->customer_name }}</p>
                            @endif
                        </div>
                        <span class="text-lg font-black text-slate-950">{{ $money($order->total_cents) }}</span>
                    </div>

                    <ul class="mt-4 divide-y divide-slate-100 rounded-xl bg-slate-50 px-4 text-sm">
                        @foreach ($order->items as $item)
                            <li class="flex justify-between gap-3 py-2">
                                <span class="font-semibold text-slate-900">{{ $item->quantity }}x {{ $item->product_name }}</span>
                                <span class="text-slate-600">{{ $money($item->total_cents) }}</span>
                            </li>
                        @endforeach
                    </ul>

                    @if ($order->notes)
                        <p class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-900"><span class="font-bold">Obs.:</span> {{ $order->notes }}</p>
                    @endif

                    <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-start">
                        <form method="POST" action="{{ route('menu-orders.accept', $order) }}">
                            @csrf
                            <button type="submit" class="w-full rounded-xl bg-brand-700 px-5 py-3 text-sm font-bold text-white hover:bg-brand-800 sm:w-auto">Aceitar e lançar</button>
                        </form>
                        <details class="flex-1">
                            <summary class="cursor-pointer list-none rounded-xl border border-red-200 px-5 py-3 text-center text-sm font-bold text-red-700 hover:bg-red-50">Recusar</summary>
                            <form method="POST" action="{{ route('menu-orders.reject', $order) }}" class="mt-3 flex flex-col gap-2">
                                @csrf
                                <input name="rejection_reason" required maxlength="255" placeholder="Motivo para o cliente. Ex.: item acabou" class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none focus:border-red-400 focus:ring-3 focus:ring-red-400/20">
                                <button type="submit" class="rounded-xl bg-red-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-red-700">Confirmar recusa</button>
                            </form>
                        </details>
                    </div>
                </article>
            @empty
                <div class="rounded-2xl border border-brand-100 bg-white px-5 py-12 text-center text-slate-500 lg:col-span-2">Nenhum pedido aguardando. Os novos pedidos aparecem aqui automaticamente.</div>
            @endforelse
        </div>

        @if ($reviewedOrders->isNotEmpty())
            <section>
                <h2 class="text-lg font-bold text-slate-950">Analisados hoje</h2>
                <div class="mt-3 overflow-hidden rounded-2xl border border-brand-100 bg-white shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-brand-50 text-left text-xs font-bold uppercase tracking-wider text-brand-800">
                                <tr>
                                    <th class="px-5 py-3">Pedido</th>
                                    <th class="px-5 py-3">Cartão</th>
                                    <th class="px-5 py-3">Itens</th>
                                    <th class="px-5 py-3">Situação</th>
                                    <th class="px-5 py-3">Por</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($reviewedOrders as $order)
                                    <tr>
                                        <td class="px-5 py-3 font-semibold text-slate-900">#{{ $order->id }} · {{ $order->created_at->format('H:i') }}</td>
                                        <td class="px-5 py-3 text-slate-700">
                                            @if ($order->tab)
                                                <a href="{{ route('tabs.show', $order->tab) }}" class="font-bold text-brand-700 hover:text-brand-900">{{ $order->card->code }}</a>
                                            @else
                                                {{ $order->card->code }}
                                            @endif
                                        </td>
                                        <td class="px-5 py-3 text-slate-600">{{ $order->items->map(fn ($item) => $item->quantity.'x '.$item->product_name)->implode(', ') }}</td>
                                        <td class="px-5 py-3">
                                            <span @class([
                                                'inline-flex rounded-full px-2.5 py-1 text-xs font-bold',
                                                'bg-emerald-100 text-emerald-800' => $order->status === \App\MenuOrderStatus::Accepted,
                                                'bg-red-100 text-red-800' => $order->status === \App\MenuOrderStatus::Rejected,
                                            ])>{{ $order->status->label() }}</span>
                                            @if ($order->rejection_reason)
                                                <span class="mt-1 block text-xs text-slate-500">{{ $order->rejection_reason }}</span>
                                            @endif
                                        </td>
                                        <td class="px-5 py-3 text-slate-600">{{ $order->reviewer?->name }} · {{ $order->reviewed_at?->format('H:i') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        @endif
    </div>
@endsection
