<div data-menu-quantity-controls class="absolute {{ $position }} flex items-center gap-1 rounded-full bg-white p-1 shadow-lg ring-1 ring-black/5">
    <button type="button" data-menu-decrement hidden class="grid size-8 place-items-center rounded-full text-xl font-bold text-brand-800 active:bg-brand-50" aria-label="Remover um {{ $product->name }}">−</button>
    <span data-menu-quantity hidden class="min-w-5 text-center text-sm font-black text-slate-900">0</span>
    <button type="button" data-menu-increment class="grid size-8 place-items-center rounded-full bg-brand-700 text-xl font-bold text-white active:scale-95" aria-label="Adicionar {{ $product->name }}">+</button>
</div>
