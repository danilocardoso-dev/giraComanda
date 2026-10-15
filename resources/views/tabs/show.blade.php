@extends('layouts.app')

@section('title', 'Comanda '.$tab->card->code)

@section('content')
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <a href="{{ route('tabs.index') }}" class="text-sm font-bold text-brand-700 hover:text-brand-900">← Voltar para comandas</a>
            <div class="mt-3 flex items-center gap-3">
                <h2 class="text-3xl font-black text-slate-950">{{ $tab->card->code }}</h2>
                <span @class([
                    'rounded-full px-3 py-1 text-xs font-bold',
                    'bg-amber-100 text-amber-800' => $tab->status === \App\TabStatus::Open,
                    'bg-slate-200 text-slate-700' => $tab->status === \App\TabStatus::Closed,
                ])>{{ $tab->status->label() }}</span>
            </div>
            <p class="mt-2 text-sm text-slate-500">Aberta em {{ $tab->opened_at->format('d/m/Y H:i') }} por {{ $tab->opener->name }}</p>
        </div>
        <div class="rounded-2xl bg-brand-900 px-6 py-4 text-right text-white">
            <p class="text-xs font-bold uppercase tracking-[0.18em] text-brand-200">Total da comanda</p>
            <p class="mt-1 text-3xl font-black">R$ {{ number_format($tab->total_cents / 100, 2, ',', '.') }}</p>
        </div>
    </div>

    @if ($errors->has('tab'))
        <div class="mt-5 rounded-xl bg-red-50 px-4 py-3 text-sm font-semibold text-red-800">{{ $errors->first('tab') }}</div>
    @endif

    @if ($tab->status === \App\TabStatus::Open)
        <section class="mt-7 rounded-2xl border border-brand-100 bg-white p-5 shadow-sm sm:p-6">
            <h3 class="text-lg font-bold text-slate-950">Adicionar consumo</h3>
            <form method="POST" action="{{ route('tabs.items.store', $tab) }}" class="mt-4 grid gap-4 md:grid-cols-[minmax(0,1fr)_180px_auto] md:items-end">
                @csrf
                <div>
                    <label for="product_id" class="text-sm font-semibold text-slate-700">Produto</label>
                    <select id="product_id" name="product_id" required class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 outline-none focus:border-brand-500 focus:ring-3 focus:ring-brand-500/20">
                        <option value="">Selecione</option>
                        @foreach ($products as $product)
                            <option value="{{ $product->id }}" @selected((string) old('product_id') === (string) $product->id)>
                                {{ $product->name }} — R$ {{ number_format($product->price_cents / 100, 2, ',', '.') }}/{{ $product->sale_type->quantityLabel() }}
                            </option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('product_id')" />
                </div>
                <div>
                    <label for="quantity" class="text-sm font-semibold text-slate-700">Quantidade / peso em kg</label>
                    <input id="quantity" name="quantity" type="number" min="0.001" step="0.001" value="{{ old('quantity', '1') }}" required
                        class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 outline-none focus:border-brand-500 focus:ring-3 focus:ring-brand-500/20">
                    <x-input-error :messages="$errors->get('quantity')" />
                </div>
                <button type="submit" @disabled($products->isEmpty()) class="rounded-xl bg-brand-700 px-5 py-3 text-sm font-bold text-white hover:bg-brand-800 disabled:bg-slate-300">Adicionar</button>
            </form>
            @if ($products->isEmpty())
                <p class="mt-3 text-sm text-amber-800">Cadastre um produto ativo antes de lançar consumos.</p>
            @endif
        </section>
    @endif

    <section class="mt-7">
        <div class="flex items-center justify-between gap-4">
            <h3 class="text-xl font-bold text-slate-950">Itens consumidos</h3>
            <p class="text-sm text-slate-500">{{ $tab->items->count() }} {{ $tab->items->count() === 1 ? 'lançamento' : 'lançamentos' }}</p>
        </div>

        <div class="mt-4 grid gap-4">
            @forelse ($tab->items as $item)
                <article class="rounded-2xl border border-brand-100 bg-white p-5 shadow-sm">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h4 class="text-lg font-bold text-slate-950">{{ $item->product_name }}</h4>
                            <p class="mt-1 text-sm text-slate-500">
                                @if ($item->sale_type === \App\ProductSaleType::Weight)
                                    {{ number_format($item->quantity_milliunits / 1000, 3, ',', '.') }} kg
                                @else
                                    {{ intdiv($item->quantity_milliunits, 1000) }} un.
                                @endif
                                × R$ {{ number_format($item->unit_price_cents / 100, 2, ',', '.') }}
                            </p>
                        </div>
                        <div class="flex items-center gap-3">
                            <p class="text-xl font-black text-brand-900">R$ {{ number_format($item->total_cents / 100, 2, ',', '.') }}</p>
                            @if ($tab->status === \App\TabStatus::Open)
                                <form method="POST" action="{{ route('tabs.items.destroy', [$tab, $item]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="rounded-lg bg-red-50 px-3 py-2 text-xs font-bold text-red-700 hover:bg-red-100">Remover</button>
                                </form>
                            @endif
                        </div>
                    </div>

                    @if ($item->shares->isNotEmpty())
                        <div class="mt-4 rounded-xl bg-warm-50 p-4">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <p class="text-sm font-bold text-brand-900">Rateado entre {{ $item->shares->count() }} clientes</p>
                                @if ($tab->status === \App\TabStatus::Open)
                                    <form method="POST" action="{{ route('tabs.items.shares.destroy', [$tab, $item]) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs font-bold text-red-700 hover:text-red-900">Remover rateio</button>
                                    </form>
                                @endif
                            </div>
                            <div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                                @foreach ($item->shares as $share)
                                    <div class="rounded-lg bg-white px-3 py-2 text-sm">
                                        <p class="truncate font-semibold text-slate-800">{{ $share->customer->name }}</p>
                                        <p class="mt-1 font-bold text-brand-700">R$ {{ number_format($share->amount_cents / 100, 2, ',', '.') }}</p>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @elseif ($tab->status === \App\TabStatus::Open && $customers->count() >= 2)
                        <details class="mt-4 rounded-xl border border-dashed border-brand-200 bg-brand-50/60 p-4">
                            <summary class="cursor-pointer text-sm font-bold text-brand-800">Dividir este item entre clientes</summary>
                            <form method="POST" action="{{ route('tabs.items.shares.store', [$tab, $item]) }}" class="mt-4">
                                @csrf
                                @method('PUT')
                                <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                    @foreach ($customers as $customer)
                                        <label class="flex items-center gap-2 rounded-lg bg-white px-3 py-2 text-sm text-slate-700">
                                            <input type="checkbox" name="customer_ids[]" value="{{ $customer->id }}" class="rounded border-slate-300 text-brand-700 focus:ring-brand-500">
                                            <span class="truncate">{{ $customer->name }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                <x-input-error :messages="$errors->get('customer_ids')" />
                                <button type="submit" class="mt-4 rounded-xl bg-warm-500 px-4 py-2.5 text-sm font-bold text-brand-950 hover:bg-warm-400">Aplicar divisão igual</button>
                            </form>
                        </details>
                    @endif
                </article>
            @empty
                <div class="rounded-2xl border border-dashed border-brand-300 bg-brand-50 p-10 text-center text-slate-600">Nenhum consumo lançado nesta comanda.</div>
            @endforelse
        </div>
    </section>

    @if ($tab->status === \App\TabStatus::Open && auth()->user()->hasRole(\App\UserRole::Admin, \App\UserRole::Cashier))
        <section class="mt-7 rounded-2xl border border-brand-200 bg-brand-50 p-5 sm:p-6">
            <h3 class="text-lg font-bold text-brand-950">Fechar comanda</h3>
            <p class="mt-2 text-sm leading-6 text-brand-800">Selecione como o valor total foi recebido. Na anotação, o cliente escolhido recebe os itens não rateados e as divisões existentes são preservadas.</p>
            <form method="POST" action="{{ route('tabs.close', $tab) }}" class="mt-4 grid gap-3 lg:grid-cols-[260px_minmax(0,1fr)_auto] lg:items-start">
                @csrf
                <div>
                    <label for="payment_method" class="text-sm font-semibold text-brand-900">Forma de fechamento</label>
                    <select id="payment_method" name="payment_method" data-payment-method required class="mt-2 w-full rounded-xl border border-brand-200 bg-white px-4 py-3 outline-none focus:border-brand-500 focus:ring-3 focus:ring-brand-500/20">
                        <option value="">Selecione</option>
                        @foreach ($paymentMethods as $paymentMethod)
                            <option value="{{ $paymentMethod->value }}" @selected(old('payment_method') === $paymentMethod->value)>{{ $paymentMethod->label() }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('payment_method')" />
                </div>
                <div>
                    <label for="customer_id" class="text-sm font-semibold text-brand-900">Cliente <span class="font-normal text-brand-700">(obrigatório somente na anotação)</span></label>
                    <select id="customer_id" name="customer_id" class="mt-2 w-full rounded-xl border border-brand-200 bg-white px-4 py-3 outline-none focus:border-brand-500 focus:ring-3 focus:ring-brand-500/20">
                        <option value="">Sem cliente identificado</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}" @selected((string) old('customer_id') === (string) $customer->id)>{{ $customer->name }} — {{ $customer->formatted_cpf }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('customer_id')" />
                </div>
                <button type="submit" class="rounded-xl bg-brand-800 px-6 py-3 text-sm font-bold text-white hover:bg-brand-900">Fechar comanda</button>
                <div data-mixed-payments class="lg:col-span-3" hidden>
                    <div class="rounded-xl border border-brand-200 bg-white p-4">
                        <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                            <p class="text-sm font-bold text-brand-950">Divida o total entre pelo menos duas formas</p>
                            <p class="text-sm font-semibold text-brand-700">Total da comanda: R$ {{ number_format($tab->total_cents / 100, 2, ',', '.') }}</p>
                        </div>
                        <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                            @foreach (\App\PaymentMethod::immediateCases() as $method)
                                <div>
                                    <label for="payment_{{ $method->value }}" class="text-xs font-bold uppercase tracking-wide text-slate-600">{{ $method->label() }}</label>
                                    <div class="mt-2 flex rounded-xl border border-slate-300 bg-white focus-within:border-brand-500 focus-within:ring-3 focus-within:ring-brand-500/20">
                                        <span class="border-r border-slate-200 px-3 py-2.5 text-sm text-slate-500">R$</span>
                                        <input id="payment_{{ $method->value }}" name="payments[{{ $method->value }}]" value="{{ old('payments.'.$method->value) }}" data-mixed-payment-input data-mask="currency" inputmode="numeric" placeholder="0,00" class="min-w-0 flex-1 rounded-r-xl px-3 py-2.5 outline-none">
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <x-input-error :messages="$errors->get('payments')" />
                        <x-input-error :messages="$errors->get('payments.*')" />
                    </div>
                </div>
            </form>
        </section>
    @elseif ($tab->status === \App\TabStatus::Closed)
        <section class="mt-7 rounded-2xl bg-slate-900 p-6 text-white">
            <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-300">Fechamento</p>
            @if ($tab->receivables->isNotEmpty())
                <p class="mt-2 text-xl font-bold">Valores enviados para o contas a receber</p>
                <div class="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($tab->receivables as $receivable)
                        <a href="{{ route('receivables.show', $receivable->customer) }}" class="rounded-xl bg-white/10 px-4 py-3 hover:bg-white/15">
                            <span class="block text-sm text-slate-300">{{ $receivable->customer->name }}</span>
                            <span class="mt-1 block font-bold">R$ {{ number_format($receivable->amount_cents / 100, 2, ',', '.') }}</span>
                        </a>
                    @endforeach
                </div>
            @elseif ($tab->payments->isNotEmpty())
                <p class="mt-2 text-xl font-bold">Pagamento recebido</p>
                <div class="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($tab->payments as $payment)
                        <div class="rounded-xl bg-white/10 px-4 py-3">
                            <span class="block text-sm text-slate-300">{{ $payment->method->label() }}</span>
                            <span class="mt-1 block font-bold">R$ {{ number_format($payment->amount_cents / 100, 2, ',', '.') }}</span>
                        </div>
                    @endforeach
                </div>
                <p class="mt-3 text-sm text-slate-300">Registrado por {{ $tab->payments->first()->recorder->name }}.</p>
            @endif
            <p class="mt-2 text-sm text-slate-300">Fechada em {{ $tab->closed_at->format('d/m/Y H:i') }} por {{ $tab->closer->name }}.</p>
            @if (auth()->user()->hasRole(\App\UserRole::Admin, \App\UserRole::Cashier))
                <a href="{{ route('tabs.receipt', $tab) }}" target="_blank" class="mt-5 inline-flex rounded-xl bg-white px-5 py-2.5 text-sm font-bold text-slate-900 hover:bg-slate-100">Imprimir comprovante não fiscal</a>
            @endif
        </section>
        @if (auth()->user()->hasRole(\App\UserRole::Admin))
            <section class="mt-5 rounded-2xl border border-red-200 bg-red-50 p-6">
                <h3 class="text-lg font-bold text-red-950">Cancelar fechamento</h3>
                <p class="mt-1 text-sm leading-6 text-red-800">Use apenas para corrigir uma venda lançada incorretamente. O estoque e os valores serão estornados e o motivo ficará na auditoria.</p>
                <form method="POST" action="{{ route('tabs.cancellations.store', $tab) }}" class="mt-4 grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto]">
                    @csrf
                    <div>
                        <label for="reason" class="sr-only">Motivo do cancelamento</label>
                        <input id="reason" name="reason" value="{{ old('reason') }}" required minlength="5" maxlength="500" placeholder="Motivo do cancelamento" class="w-full rounded-xl border border-red-300 bg-white px-4 py-3 outline-none focus:border-red-500 focus:ring-3 focus:ring-red-500/20">
                        <x-input-error :messages="$errors->get('reason')" />
                        <x-input-error :messages="$errors->get('tab')" />
                    </div>
                    <button type="submit" class="rounded-xl bg-red-700 px-5 py-3 text-sm font-bold text-white hover:bg-red-800">Cancelar comanda</button>
                </form>
            </section>
        @endif
    @elseif ($tab->status === \App\TabStatus::Cancelled)
        <section class="mt-7 rounded-2xl border border-red-200 bg-red-50 p-6">
            <p class="text-xs font-bold uppercase tracking-[0.18em] text-red-700">Comanda cancelada</p>
            <p class="mt-2 text-lg font-bold text-red-950">{{ $tab->cancellation_reason }}</p>
            <p class="mt-2 text-sm text-red-800">Cancelada em {{ $tab->cancelled_at->format('d/m/Y H:i') }} por {{ $tab->canceller->name }}.</p>
        </section>
    @endif
@endsection
