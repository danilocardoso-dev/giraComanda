@php
    $establishmentName = $settings?->name ?? $branch->company->name;
    $money = fn (int $cents): string => 'R$ '.number_format($cents / 100, 2, ',', '.');
    $orderingEnabled = $branch->menu_ordering_enabled;
    $isOrderable = fn ($product): bool => $orderingEnabled
        && $product->sale_type === \App\ProductSaleType::Unit
        && ($product->stock_milliunits === null || $product->stock_milliunits >= 1000);
    $photoUrl = fn ($product, $photo = null): string => route('menu.products.photo', [
        'qrToken' => $card->qr_token,
        'product' => $product,
        'photo' => $photo,
        'v' => ($photo ?? $product)->updated_at?->timestamp,
    ]);
    $menuProducts = $categories->flatMap(fn (array $section) => $section['products'])->unique('id');
    $productDetails = $menuProducts->mapWithKeys(fn ($product): array => [$product->id => [
        'name' => $product->name,
        'description' => $product->description,
        'price' => $money($product->price_cents).($product->sale_type === \App\ProductSaleType::Weight ? ' / kg' : ''),
        'priceCents' => $product->price_cents,
        'photos' => collect($product->photo_path ? [$photoUrl($product)] : [])
            ->merge($product->photos->map(fn ($photo): string => $photoUrl($product, $photo)))
            ->values(),
        'orderable' => $isOrderable($product),
        'note' => $product->sale_type === \App\ProductSaleType::Weight
            ? 'Vendido por quilo: peça à equipe no balcão.'
            : ($orderingEnabled && ! $isOrderable($product) ? 'Esgotado no momento.' : null),
    ]]);
    $highlights = $menuProducts->filter(fn ($product): bool => $product->photo_path !== null && $isOrderable($product))->take(8);
    $pendingOrdersCount = $orders->where('status', \App\MenuOrderStatus::Pending)->count();
    $gutter = 'px-4 sm:px-6 lg:px-8';
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#112010">
    <title>Cardápio · {{ $establishmentName }}</title>
    <x-favicon />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#faf7f2] text-slate-900">
    <div data-menu-page
        data-menu-token="{{ $card->qr_token }}"
        data-menu-status-url="{{ route('menu.orders.status', $card->qr_token) }}"
        @if (session('success')) data-menu-order-sent @endif
        class="min-h-screen pb-32">
        <script type="application/json" data-menu-product-details>@json($productDetails)</script>

        <header class="relative overflow-hidden bg-gradient-to-br from-brand-950 via-brand-900 to-brand-800 text-white">
            <div class="pointer-events-none absolute -right-24 -top-24 size-80 rounded-full bg-warm-500/15 blur-3xl"></div>
            <div class="pointer-events-none absolute -bottom-32 left-1/4 size-80 rounded-full bg-brand-500/25 blur-3xl"></div>

            <div class="relative mx-auto max-w-5xl {{ $gutter }} pb-16 pt-8 sm:pb-20 sm:pt-12">
                <div class="flex items-center gap-4 sm:gap-6">
                    @if ($settings?->logo_path)
                        <div class="grid size-[72px] shrink-0 place-items-center overflow-hidden rounded-3xl bg-white p-2 shadow-lg ring-4 ring-white/15 sm:size-24">
                            <img src="{{ route('menu.logo', ['qrToken' => $card->qr_token, 'v' => $settings->updated_at?->timestamp]) }}" alt="Logo de {{ $establishmentName }}" class="size-full object-contain">
                        </div>
                    @else
                        <div class="grid size-[72px] shrink-0 place-items-center rounded-3xl bg-warm-500 text-3xl font-black text-brand-950 shadow-lg ring-4 ring-white/15 sm:size-24 sm:text-4xl">{{ mb_substr($establishmentName, 0, 1) }}</div>
                    @endif
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-warm-300">Bem-vindo(a) ao</p>
                        <h1 class="text-[1.7rem] font-bold leading-tight tracking-tight sm:text-4xl">{{ $establishmentName }}</h1>
                        <p class="mt-0.5 text-sm text-brand-200">{{ $branch->name }}</p>
                    </div>
                </div>

                <div class="mt-6 flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                    <p class="max-w-md text-base leading-7 text-brand-100 sm:text-lg">
                        @if ($orderingEnabled)
                            Escolha com calma e peça daqui mesmo. A equipe recebe na hora e leva até você. 😋
                        @else
                            Dê uma olhada no que preparamos hoje e chame a equipe quando quiser pedir.
                        @endif
                    </p>
                    <div class="flex flex-wrap gap-2 text-xs font-bold">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-white/12 px-3 py-1.5 ring-1 ring-white/15">🎟️ Cartão {{ $card->code }}</span>
                        @if ($orderingEnabled)
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-400/15 px-3 py-1.5 text-emerald-200 ring-1 ring-emerald-300/25"><span class="size-2 animate-pulse rounded-full bg-emerald-300"></span>Pedidos pelo celular abertos</span>
                        @else
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-3 py-1.5 text-brand-100 ring-1 ring-white/15">Somente consulta</span>
                        @endif
                    </div>
                </div>
            </div>
        </header>

        <div class="relative -mt-8 rounded-t-[2rem] bg-[#faf7f2]">
            <main class="mx-auto max-w-5xl space-y-7 {{ $gutter }} pt-6">
                @if (session('success'))
                    <div role="status" class="flex items-start gap-3 rounded-3xl bg-white p-4 shadow-lg ring-1 ring-emerald-100">
                        <span class="grid size-10 shrink-0 place-items-center rounded-2xl bg-emerald-100 text-xl">✅</span>
                        <div>
                            <p class="font-bold text-slate-900">Pedido enviado!</p>
                            <p class="text-sm text-slate-600">Assim que a equipe confirmar, o status muda aqui embaixo.</p>
                        </div>
                    </div>
                @endif

                @if ($errors->any())
                    <div role="alert" class="rounded-3xl bg-white p-4 shadow-lg ring-1 ring-red-200">
                        @foreach ($errors->all() as $error)
                            <p class="text-sm font-semibold text-red-700">{{ $error }}</p>
                        @endforeach
                    </div>
                @endif

                @if ($orders->isNotEmpty() || ($openTab && $openTab->items->isNotEmpty()))
                    <section @class([
                        'grid overflow-hidden rounded-3xl bg-white shadow-lg ring-1 ring-black/5',
                        'md:grid-cols-2 md:divide-x md:divide-slate-100' => $orders->isNotEmpty() && $openTab && $openTab->items->isNotEmpty(),
                    ])>
                        @if ($orders->isNotEmpty())
                            <div class="p-4 sm:p-5">
                                <div class="flex items-center justify-between">
                                    <h2 class="font-bold text-slate-900">Seus pedidos</h2>
                                    @if ($pendingOrdersCount > 0)
                                        <span class="text-xs font-semibold text-slate-500">Atualiza sozinho</span>
                                    @endif
                                </div>
                                <ul class="mt-3 space-y-2">
                                    @foreach ($orders as $order)
                                        <li class="flex items-start gap-3 rounded-2xl bg-slate-50 p-3">
                                            <span class="grid size-9 shrink-0 place-items-center rounded-xl text-lg {{ match ($order->status) { \App\MenuOrderStatus::Pending => 'bg-amber-100', \App\MenuOrderStatus::Accepted => 'bg-emerald-100', \App\MenuOrderStatus::Rejected => 'bg-red-100' } }}">{{ match ($order->status) { \App\MenuOrderStatus::Pending => '⏳', \App\MenuOrderStatus::Accepted => '👨‍🍳', \App\MenuOrderStatus::Rejected => '⚠️' } }}</span>
                                            <div class="min-w-0 flex-1">
                                                <div class="flex items-center justify-between gap-2">
                                                    <p class="text-sm font-bold text-slate-900">{{ $order->status === \App\MenuOrderStatus::Accepted ? 'Confirmado, em preparo' : $order->status->label() }}</p>
                                                    <span class="text-xs text-slate-400">{{ $order->created_at->format('H:i') }}</span>
                                                </div>
                                                <p class="truncate text-sm text-slate-600">{{ $order->items->map(fn ($item) => $item->quantity.'× '.$item->product_name)->implode(', ') }}</p>
                                                @if ($order->rejection_reason)
                                                    <p class="mt-1 text-sm font-semibold text-red-700">{{ $order->rejection_reason }}</p>
                                                @endif
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @if ($openTab && $openTab->items->isNotEmpty())
                            <details @class(['group border-t border-slate-100 md:border-t-0', 'md:col-span-2' => $orders->isEmpty()]) open>
                                <summary class="flex cursor-pointer list-none items-center justify-between gap-3 p-4 sm:p-5">
                                    <span>
                                        <span class="block font-bold text-slate-900">Sua comanda</span>
                                        <span class="block text-xs text-slate-500">O que já foi lançado no seu cartão</span>
                                    </span>
                                    <span class="flex items-center gap-2">
                                        <span class="text-lg font-black text-brand-800">{{ $money($openTab->total_cents) }}</span>
                                        <span class="text-slate-400 transition group-open:rotate-180">⌄</span>
                                    </span>
                                </summary>
                                <ul class="divide-y divide-slate-100 px-4 text-sm sm:px-5">
                                    @foreach ($openTab->items as $item)
                                        <li class="flex justify-between gap-3 py-2.5">
                                            <span class="text-slate-700">{{ $item->sale_type === \App\ProductSaleType::Weight ? number_format($item->quantity_milliunits / 1000, 3, ',', '.').' kg' : intdiv($item->quantity_milliunits, 1000).'×' }} {{ $item->product_name }}</span>
                                            <span class="font-semibold text-slate-900">{{ $money($item->total_cents) }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                                <p class="px-4 pb-4 pt-1 text-xs text-slate-500 sm:px-5">O pagamento é feito no caixa, na saída.</p>
                            </details>
                        @endif
                    </section>
                @endif

                @if ($categories->isEmpty())
                    <div class="rounded-3xl bg-white px-6 py-14 text-center shadow-lg ring-1 ring-black/5">
                        <p class="text-4xl">🍽️</p>
                        <p class="mt-3 font-bold text-slate-900">O cardápio está sendo preparado</p>
                        <p class="mt-1 text-sm text-slate-500">Enquanto isso, a equipe pode te ajudar com o pedido.</p>
                    </div>
                @else
                    @if ($highlights->count() >= 3)
                        <section>
                            <h2 class="text-lg font-bold text-slate-950 sm:text-xl">Destaques da casa ✨</h2>
                            <div class="-mx-4 mt-3 flex snap-x snap-mandatory gap-3 overflow-x-auto px-4 pb-3 sm:-mx-6 sm:gap-4 sm:px-6 lg:-mx-8 lg:px-8 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                                @foreach ($highlights as $product)
                                    <article data-menu-detail="{{ $product->id }}" data-menu-product data-product-id="{{ $product->id }}" data-product-name="{{ $product->name }}" data-product-price="{{ $product->price_cents }}" data-product-photo="{{ $photoUrl($product) }}"
                                        class="w-44 shrink-0 cursor-pointer snap-start overflow-hidden rounded-3xl bg-white shadow-md ring-1 ring-black/5 transition hover:-translate-y-0.5 hover:shadow-lg data-[in-cart]:ring-2 data-[in-cart]:ring-brand-500 sm:w-52">
                                        <div class="relative">
                                            <img src="{{ $photoUrl($product) }}" alt="{{ $product->name }}" loading="lazy" class="aspect-square w-full object-cover">
                                            @include('menu.partials.photo-count', ['count' => $productDetails[$product->id]['photos']->count()])
                                            @include('menu.partials.quantity-controls', ['product' => $product, 'position' => 'bottom-2 right-2'])
                                        </div>
                                        <div class="p-3">
                                            <h3 class="truncate text-sm font-bold text-slate-900">{{ $product->name }}</h3>
                                            <p class="mt-0.5 text-sm font-black text-brand-700">{{ $money($product->price_cents) }}</p>
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        </section>
                    @endif

                    <nav data-menu-category-nav class="sticky top-0 z-20 -mx-4 flex gap-2 overflow-x-auto bg-[#faf7f2]/90 px-4 py-3 backdrop-blur sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden" aria-label="Categorias">
                        @foreach ($categories as $index => $section)
                            <a href="#categoria-{{ $index }}" data-menu-category-link="categoria-{{ $index }}"
                                class="shrink-0 rounded-full bg-white px-4 py-2 text-sm font-bold text-slate-700 shadow-sm ring-1 ring-black/5 transition hover:ring-brand-300 data-[active]:bg-brand-800 data-[active]:text-white data-[active]:ring-brand-800">{{ $section['name'] }}</a>
                        @endforeach
                    </nav>

                    @foreach ($categories as $index => $section)
                        <section id="categoria-{{ $index }}" data-menu-category class="scroll-mt-16">
                            <div class="flex items-baseline justify-between">
                                <h2 class="text-xl font-bold tracking-tight text-slate-950 sm:text-2xl">{{ $section['name'] }}</h2>
                                <span class="text-xs font-semibold text-slate-400">{{ $section['products']->count() }} {{ $section['products']->count() === 1 ? 'opção' : 'opções' }}</span>
                            </div>
                            <div class="mt-3 grid gap-3 md:grid-cols-2 lg:gap-4">
                                @foreach ($section['products'] as $product)
                                    @php($orderable = $isOrderable($product))
                                    <article data-menu-detail="{{ $product->id }}"
                                        @if ($orderable) data-menu-product data-product-id="{{ $product->id }}" data-product-name="{{ $product->name }}" data-product-price="{{ $product->price_cents }}" data-product-photo="{{ $product->photo_path ? $photoUrl($product) : '' }}" @endif
                                        class="flex cursor-pointer gap-4 rounded-3xl bg-white p-3 shadow-sm ring-1 ring-black/5 transition hover:shadow-md data-[in-cart]:ring-2 data-[in-cart]:ring-brand-500">
                                        <div class="flex min-w-0 flex-1 flex-col py-1 pl-1">
                                            <h3 class="font-bold leading-snug text-slate-900">{{ $product->name }}</h3>
                                            @if ($product->description)
                                                <p class="mt-1 line-clamp-2 text-sm leading-5 text-slate-500">{{ $product->description }}</p>
                                            @endif
                                            <div class="mt-auto flex flex-wrap items-center gap-2 pt-3">
                                                <span class="text-base font-black text-brand-800">{{ $productDetails[$product->id]['price'] }}</span>
                                                @if ($product->sale_type === \App\ProductSaleType::Weight)
                                                    <span class="rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-bold text-amber-700">Peça à equipe</span>
                                                @elseif ($orderingEnabled && ! $orderable)
                                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-bold text-slate-500">Esgotado</span>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="relative size-28 shrink-0 sm:size-32">
                                            @if ($product->photo_path)
                                                <img src="{{ $photoUrl($product) }}" alt="{{ $product->name }}" loading="lazy" @class(['size-full rounded-2xl object-cover', 'grayscale opacity-60' => $orderingEnabled && ! $orderable && $product->sale_type === \App\ProductSaleType::Unit])>
                                                @include('menu.partials.photo-count', ['count' => $productDetails[$product->id]['photos']->count()])
                                            @else
                                                <div class="grid size-full place-items-center rounded-2xl bg-gradient-to-br from-warm-400/40 to-brand-200 text-3xl font-black text-brand-800/70">{{ mb_substr($product->name, 0, 1) }}</div>
                                            @endif
                                            @if ($orderable)
                                                @include('menu.partials.quantity-controls', ['product' => $product, 'position' => '-bottom-2 right-2'])
                                            @endif
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        </section>
                    @endforeach
                @endif
            </main>
        </div>

        <dialog data-menu-detail-dialog class="m-0 mt-auto max-h-[94vh] w-full max-w-none overflow-hidden rounded-t-[2rem] bg-white p-0 backdrop:bg-slate-950/60 backdrop:backdrop-blur-sm sm:m-auto sm:max-w-xl sm:rounded-[2rem]">
            <div class="flex max-h-[94vh] flex-col">
                <div class="relative shrink-0">
                    <div data-menu-detail-gallery class="flex aspect-[4/3] snap-x snap-mandatory overflow-x-auto bg-slate-100 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"></div>
                    <div data-menu-detail-dots class="absolute inset-x-0 bottom-3 flex justify-center gap-1.5"></div>
                    <button type="button" data-menu-detail-previous hidden class="absolute left-3 top-1/2 hidden size-10 -translate-y-1/2 place-items-center rounded-full bg-white/90 text-xl text-slate-700 shadow sm:grid" aria-label="Foto anterior">‹</button>
                    <button type="button" data-menu-detail-next hidden class="absolute right-3 top-1/2 hidden size-10 -translate-y-1/2 place-items-center rounded-full bg-white/90 text-xl text-slate-700 shadow sm:grid" aria-label="Próxima foto">›</button>
                    <button type="button" data-menu-detail-close class="absolute right-3 top-3 grid size-10 place-items-center rounded-full bg-white/90 text-lg text-slate-700 shadow" aria-label="Fechar">✕</button>
                </div>
                <div class="overflow-y-auto px-5 pb-6 pt-5">
                    <h2 data-menu-detail-name class="text-2xl font-bold tracking-tight text-slate-950"></h2>
                    <p data-menu-detail-price class="mt-1 text-lg font-black text-brand-700"></p>
                    <p data-menu-detail-description class="mt-3 whitespace-pre-line text-base leading-7 text-slate-600"></p>
                    <p data-menu-detail-note hidden class="mt-4 rounded-2xl bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-800"></p>
                    <div data-menu-detail-actions hidden class="mt-6 flex items-center gap-3">
                        <div class="flex items-center gap-1 rounded-2xl bg-slate-100 p-1">
                            <button type="button" data-menu-detail-decrement class="grid size-11 place-items-center rounded-xl text-2xl font-bold text-brand-800 active:bg-white" aria-label="Diminuir quantidade">−</button>
                            <span data-menu-detail-quantity class="min-w-8 text-center text-lg font-black">1</span>
                            <button type="button" data-menu-detail-increment class="grid size-11 place-items-center rounded-xl text-2xl font-bold text-brand-800 active:bg-white" aria-label="Aumentar quantidade">+</button>
                        </div>
                        <button type="button" data-menu-detail-confirm class="flex flex-1 items-center justify-between gap-2 whitespace-nowrap rounded-2xl bg-brand-800 px-5 py-3.5 font-bold text-white shadow-lg shadow-brand-900/30">
                            <span data-menu-detail-confirm-label>Adicionar</span>
                            <span data-menu-detail-total class="font-black"></span>
                        </button>
                    </div>
                </div>
            </div>
        </dialog>

        @if ($orderingEnabled)
            <div data-menu-cart-bar hidden class="fixed inset-x-0 bottom-0 z-30 px-4 pb-[max(1rem,env(safe-area-inset-bottom))]">
                <button type="button" data-menu-cart-open class="mx-auto flex w-full max-w-xl items-center gap-3 rounded-2xl bg-brand-900 p-2 pr-5 text-left text-white shadow-2xl shadow-brand-950/40">
                    <span data-menu-cart-count class="grid size-11 place-items-center rounded-xl bg-warm-500 text-base font-black text-brand-950"></span>
                    <span class="flex-1 font-bold">Ver meu pedido</span>
                    <span data-menu-cart-total class="text-lg font-black"></span>
                </button>
            </div>

            <dialog data-menu-cart-dialog class="m-0 mt-auto max-h-[92vh] w-full max-w-none rounded-t-[2rem] bg-white p-0 backdrop:bg-slate-950/60 backdrop:backdrop-blur-sm sm:m-auto sm:max-w-lg sm:rounded-[2rem]">
                <form method="POST" action="{{ route('menu.orders.store', $card->qr_token) }}" data-menu-cart-form class="px-5 pb-6 pt-3">
                    @csrf
                    <div class="mx-auto h-1.5 w-12 rounded-full bg-slate-200 sm:hidden"></div>
                    <div class="mt-4 flex items-center justify-between">
                        <div>
                            <h2 class="text-xl font-bold text-slate-950">Seu pedido</h2>
                            <p class="text-xs text-slate-500">Cartão {{ $card->code }} · {{ $establishmentName }}</p>
                        </div>
                        <button type="button" data-menu-cart-close class="grid size-10 place-items-center rounded-full bg-slate-100 text-lg text-slate-600" aria-label="Fechar">✕</button>
                    </div>

                    <ul data-menu-cart-items class="mt-4 max-h-64 space-y-2 overflow-y-auto"></ul>
                    <div data-menu-cart-inputs></div>

                    <div class="mt-4 grid gap-3">
                        <label class="block">
                            <span class="text-sm font-semibold text-slate-700">Como podemos te chamar? <span class="font-normal text-slate-400">(opcional)</span></span>
                            <input name="customer_name" maxlength="80" value="{{ old('customer_name') }}" autocomplete="given-name" placeholder="Seu nome" class="mt-1 w-full rounded-2xl border-0 bg-slate-100 px-4 py-3 outline-none focus:bg-white focus:ring-2 focus:ring-brand-500">
                        </label>
                        <label class="block">
                            <span class="text-sm font-semibold text-slate-700">Alguma observação? <span class="font-normal text-slate-400">(opcional)</span></span>
                            <textarea name="notes" rows="2" maxlength="500" placeholder="Ex.: sem cebola, ponto da carne, mesa perto da janela" class="mt-1 w-full rounded-2xl border-0 bg-slate-100 px-4 py-3 outline-none focus:bg-white focus:ring-2 focus:ring-brand-500">{{ old('notes') }}</textarea>
                        </label>
                    </div>

                    <button type="submit" data-menu-cart-submit class="mt-5 flex w-full items-center justify-between rounded-2xl bg-brand-800 px-5 py-4 text-base font-bold text-white shadow-lg shadow-brand-900/30 disabled:opacity-60">
                        <span>Enviar pedido</span>
                        <span data-menu-cart-dialog-total class="font-black"></span>
                    </button>
                    <p class="mt-3 text-center text-xs text-slate-500">A equipe confirma e o pedido entra na sua comanda. Você paga no caixa, na saída.</p>
                </form>
            </dialog>
        @endif

        <footer class="px-5 pb-8 pt-12 text-center text-xs text-slate-400">Cardápio digital por <span class="font-bold text-slate-500">{{ config('app.name') }}</span></footer>
    </div>
</body>
</html>
