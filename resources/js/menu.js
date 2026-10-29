const formatMoney = (cents) => (cents / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

const setupCategoryNavigation = (page) => {
    const links = new Map([...page.querySelectorAll('[data-menu-category-link]')].map((link) => [link.dataset.menuCategoryLink, link]));
    const sections = page.querySelectorAll('[data-menu-category]');

    if (links.size === 0 || ! ('IntersectionObserver' in window)) {
        return;
    }

    const activate = (sectionId) => {
        links.forEach((link, id) => link.toggleAttribute('data-active', id === sectionId));
        links.get(sectionId)?.scrollIntoView({ block: 'nearest', inline: 'center', behavior: 'smooth' });
    };

    const observer = new IntersectionObserver((entries) => {
        const visible = entries.filter((entry) => entry.isIntersecting).sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top)[0];

        if (visible) {
            activate(visible.target.id);
        }
    }, { rootMargin: '-80px 0px -60% 0px' });

    sections.forEach((section) => observer.observe(section));
};

const setupProductDetails = (page, { quantityInCart, setQuantity }) => {
    const dialog = page.querySelector('[data-menu-detail-dialog]');
    const detailsSource = page.querySelector('[data-menu-product-details]');

    if (! dialog || ! detailsSource) {
        return;
    }

    const details = JSON.parse(detailsSource.textContent);
    const gallery = dialog.querySelector('[data-menu-detail-gallery]');
    const dots = dialog.querySelector('[data-menu-detail-dots]');
    const note = dialog.querySelector('[data-menu-detail-note]');
    const actions = dialog.querySelector('[data-menu-detail-actions]');
    const quantityLabel = dialog.querySelector('[data-menu-detail-quantity]');
    const confirmLabel = dialog.querySelector('[data-menu-detail-confirm-label]');
    const total = dialog.querySelector('[data-menu-detail-total]');
    let currentId = null;
    let chosenQuantity = 1;

    const renderActions = () => {
        const product = details[currentId];
        const inCart = quantityInCart(currentId);

        quantityLabel.textContent = String(chosenQuantity);
        total.textContent = chosenQuantity === 0 ? '' : formatMoney(chosenQuantity * product.priceCents);
        confirmLabel.textContent = inCart === 0 ? 'Adicionar' : (chosenQuantity === 0 ? 'Remover' : 'Atualizar');
    };

    const renderGallery = (product) => {
        gallery.replaceChildren();
        dots.replaceChildren();

        if (product.photos.length === 0) {
            const placeholder = document.createElement('div');

            placeholder.className = 'grid w-full shrink-0 place-items-center bg-gradient-to-br from-warm-400/40 to-brand-200 text-7xl font-black text-brand-800/60';
            placeholder.textContent = product.name.slice(0, 1);
            gallery.append(placeholder);

            return;
        }

        product.photos.forEach((url, index) => {
            const image = document.createElement('img');

            image.src = url;
            image.alt = `${product.name} – foto ${index + 1}`;
            image.className = 'w-full shrink-0 snap-center object-cover';
            gallery.append(image);

            if (product.photos.length > 1) {
                const dot = document.createElement('button');

                dot.type = 'button';
                dot.className = 'h-2 w-2 rounded-full bg-white/60 shadow transition-all data-[active]:w-5 data-[active]:bg-white';
                dot.setAttribute('aria-label', `Ver foto ${index + 1}`);
                dot.toggleAttribute('data-active', index === 0);
                dot.addEventListener('click', () => gallery.scrollTo({ left: index * gallery.clientWidth, behavior: 'smooth' }));
                dots.append(dot);
            }
        });

        gallery.scrollLeft = 0;
        previous.hidden = product.photos.length < 2;
        next.hidden = product.photos.length < 2;
    };

    const previous = dialog.querySelector('[data-menu-detail-previous]');
    const next = dialog.querySelector('[data-menu-detail-next]');

    [[previous, -1], [next, 1]].forEach(([button, direction]) => button.addEventListener('click', () => {
        const lastLeft = gallery.scrollWidth - gallery.clientWidth;
        const target = gallery.scrollLeft + direction * gallery.clientWidth;

        gallery.scrollTo({ left: target > lastLeft + 1 ? 0 : (target < -1 ? lastLeft : target), behavior: 'smooth' });
    }));

    gallery.addEventListener('scroll', () => {
        const activeIndex = Math.round(gallery.scrollLeft / Math.max(gallery.clientWidth, 1));

        [...dots.children].forEach((dot, index) => dot.toggleAttribute('data-active', index === activeIndex));
    }, { passive: true });

    const open = (productId) => {
        const product = details[productId];

        if (! product) {
            return;
        }

        currentId = productId;
        chosenQuantity = Math.max(quantityInCart(productId), 1);
        renderGallery(product);
        dialog.querySelector('[data-menu-detail-name]').textContent = product.name;
        dialog.querySelector('[data-menu-detail-price]').textContent = product.price;
        dialog.querySelector('[data-menu-detail-description]').textContent = product.description ?? '';
        note.textContent = product.note ?? '';
        note.hidden = ! product.note;
        actions.hidden = ! product.orderable;

        if (product.orderable) {
            renderActions();
        }

        dialog.showModal();
    };

    page.querySelectorAll('[data-menu-detail]').forEach((element) => {
        element.addEventListener('click', (event) => {
            if (event.target.closest('[data-menu-quantity-controls]')) {
                return;
            }

            open(element.dataset.menuDetail);
        });
    });

    dialog.querySelector('[data-menu-detail-decrement]').addEventListener('click', () => {
        chosenQuantity = Math.max(quantityInCart(currentId) > 0 ? 0 : 1, chosenQuantity - 1);
        renderActions();
    });
    dialog.querySelector('[data-menu-detail-increment]').addEventListener('click', () => {
        chosenQuantity = Math.min(20, chosenQuantity + 1);
        renderActions();
    });
    dialog.querySelector('[data-menu-detail-confirm]').addEventListener('click', () => {
        setQuantity(currentId, chosenQuantity);
        dialog.close();
    });
    dialog.querySelector('[data-menu-detail-close]').addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) {
            dialog.close();
        }
    });
};

export const setupMenuPage = () => {
    const page = document.querySelector('[data-menu-page]');

    if (! page) {
        return;
    }

    const storageKey = `giracomanda.menu.cart.${page.dataset.menuToken}`;
    const products = new Map();
    const cart = new Map();

    page.querySelectorAll('[data-menu-product]').forEach((element) => {
        const productId = element.dataset.productId;
        const product = products.get(productId) ?? {
            elements: [],
            name: element.dataset.productName,
            priceCents: Number(element.dataset.productPrice),
            photo: element.dataset.productPhoto || null,
        };

        product.elements.push(element);
        products.set(productId, product);
    });

    const saveCart = () => {
        try {
            window.sessionStorage.setItem(storageKey, JSON.stringify(Object.fromEntries(cart)));
        } catch (_error) {
            // The cart still works for this page view when storage is unavailable.
        }
    };

    const restoreCart = () => {
        if (page.hasAttribute('data-menu-order-sent')) {
            saveCart();

            return;
        }

        try {
            const stored = JSON.parse(window.sessionStorage.getItem(storageKey) ?? '{}');

            Object.entries(stored).forEach(([productId, quantity]) => {
                if (products.has(productId) && Number(quantity) > 0) {
                    cart.set(productId, Math.min(Number(quantity), 20));
                }
            });
        } catch (_error) {
            cart.clear();
        }
    };

    const cartBar = page.querySelector('[data-menu-cart-bar]');
    const dialog = page.querySelector('[data-menu-cart-dialog]');
    const form = page.querySelector('[data-menu-cart-form]');
    const itemsList = page.querySelector('[data-menu-cart-items]');
    const inputs = page.querySelector('[data-menu-cart-inputs]');

    const changeQuantity = (productId, delta) => setQuantity(productId, (cart.get(productId) ?? 0) + delta);

    const setQuantity = (productId, requestedQuantity) => {
        const quantity = Math.max(0, Math.min(20, requestedQuantity));

        if (quantity === 0) {
            cart.delete(productId);
        } else {
            cart.set(productId, quantity);
        }

        saveCart();
        render();
    };

    const buildCartRow = (productId, quantity) => {
        const product = products.get(productId);
        const row = document.createElement('li');
        const thumbnail = product.photo ? document.createElement('img') : document.createElement('span');
        const details = document.createElement('span');
        const name = document.createElement('span');
        const subtotal = document.createElement('span');
        const controls = document.createElement('span');
        const amount = document.createElement('span');

        row.className = 'flex items-center gap-3 rounded-2xl bg-slate-50 p-2';
        thumbnail.className = 'size-14 shrink-0 rounded-xl object-cover';

        if (product.photo) {
            thumbnail.src = product.photo;
            thumbnail.alt = '';
        } else {
            thumbnail.className += ' grid place-items-center bg-brand-100 font-black text-brand-800';
            thumbnail.textContent = product.name.slice(0, 1);
        }

        details.className = 'min-w-0 flex-1';
        name.className = 'block truncate text-sm font-bold text-slate-900';
        name.textContent = product.name;
        subtotal.className = 'block text-sm font-semibold text-brand-700';
        subtotal.textContent = formatMoney(quantity * product.priceCents);
        details.append(name, subtotal);

        controls.className = 'flex items-center gap-1 rounded-full bg-white p-1 shadow-sm ring-1 ring-black/5';
        amount.className = 'min-w-5 text-center text-sm font-black';
        amount.textContent = String(quantity);

        [['−', -1], ['+', 1]].forEach(([text, delta]) => {
            const button = document.createElement('button');

            button.type = 'button';
            button.textContent = text;
            button.className = delta > 0
                ? 'grid size-8 place-items-center rounded-full bg-brand-700 text-lg font-bold text-white'
                : 'grid size-8 place-items-center rounded-full text-lg font-bold text-brand-800';
            button.addEventListener('click', () => changeQuantity(productId, delta));
            controls.append(button);

            if (delta < 0) {
                controls.append(amount);
            }
        });

        row.append(thumbnail, details, controls);

        return row;
    };

    const render = () => {
        let totalCents = 0;
        let totalItems = 0;

        products.forEach((product, productId) => {
            const quantity = cart.get(productId) ?? 0;

            product.elements.forEach((element) => {
                element.toggleAttribute('data-in-cart', quantity > 0);
                element.querySelector('[data-menu-quantity]').textContent = String(quantity);
                element.querySelector('[data-menu-quantity]').hidden = quantity === 0;
                element.querySelector('[data-menu-decrement]').hidden = quantity === 0;
            });

            totalCents += quantity * product.priceCents;
            totalItems += quantity;
        });

        if (! cartBar || ! itemsList || ! inputs) {
            return;
        }

        cartBar.hidden = totalItems === 0;
        cartBar.querySelector('[data-menu-cart-count]').textContent = String(totalItems);
        cartBar.querySelector('[data-menu-cart-total]').textContent = formatMoney(totalCents);
        page.querySelector('[data-menu-cart-dialog-total]').textContent = formatMoney(totalCents);

        itemsList.replaceChildren(...[...cart.entries()].map(([productId, quantity]) => buildCartRow(productId, quantity)));
        inputs.replaceChildren();

        [...cart.entries()].forEach(([productId, quantity], index) => {
            [['product_id', productId], ['quantity', quantity]].forEach(([field, value]) => {
                const input = document.createElement('input');

                input.type = 'hidden';
                input.name = `items[${index}][${field}]`;
                input.value = String(value);
                inputs.append(input);
            });
        });

        if (totalItems === 0 && dialog?.open) {
            dialog.close();
        }
    };

    products.forEach((product, productId) => {
        product.elements.forEach((element) => {
            element.querySelector('[data-menu-increment]').addEventListener('click', () => changeQuantity(productId, 1));
            element.querySelector('[data-menu-decrement]').addEventListener('click', () => changeQuantity(productId, -1));
        });
    });

    page.querySelector('[data-menu-cart-open]')?.addEventListener('click', () => dialog?.showModal());
    page.querySelector('[data-menu-cart-close]')?.addEventListener('click', () => dialog?.close());
    dialog?.addEventListener('click', (event) => {
        if (event.target === dialog) {
            dialog.close();
        }
    });
    form?.addEventListener('submit', () => {
        form.querySelector('[data-menu-cart-submit]').disabled = true;
    });

    restoreCart();
    render();
    setupCategoryNavigation(page);
    setupProductDetails(page, {
        quantityInCart: (productId) => cart.get(productId) ?? 0,
        setQuantity,
    });

    const statusUrl = page.dataset.menuStatusUrl;
    let lastSignature = null;

    const checkStatus = async () => {
        if (document.hidden) {
            return;
        }

        try {
            const response = await fetch(statusUrl, { headers: { Accept: 'application/json' } });

            if (! response.ok) {
                return;
            }

            const { signature } = await response.json();

            if (lastSignature !== null && signature !== lastSignature && cart.size === 0 && ! dialog?.open) {
                window.location.reload();

                return;
            }

            lastSignature = signature;
        } catch (_error) {
            // A temporary network failure only delays the next status refresh.
        }
    };

    checkStatus();
    window.setInterval(checkStatus, 15000);
};
