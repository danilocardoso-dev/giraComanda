import { BrowserQRCodeReader } from '@zxing/browser';
import { setupMarketingPage } from './marketing';

const onlyDigits = (value, limit) => value.replace(/\D/g, '').slice(0, limit);

const formatCpf = (value) => {
    const digits = onlyDigits(value, 11);

    return digits
        .replace(/^(\d{3})(\d)/, '$1.$2')
        .replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3')
        .replace(/\.(\d{3})(\d)/, '.$1-$2');
};

const formatPhone = (value) => {
    const digits = onlyDigits(value, 11);

    if (digits.length <= 10) {
        return digits
            .replace(/^(\d{2})(\d)/, '($1) $2')
            .replace(/(\d{4})(\d)/, '$1-$2');
    }

    return digits
        .replace(/^(\d{2})(\d)/, '($1) $2')
        .replace(/(\d{5})(\d)/, '$1-$2');
};

const formatCurrency = (value) => {
    const digits = value.replace(/\D/g, '').replace(/^0+(?=\d)/, '');

    if (digits.length === 0) {
        return '';
    }

    const padded = digits.padStart(3, '0');
    const integer = padded.slice(0, -2).replace(/^0+(?=\d)/, '');
    const decimal = padded.slice(-2);

    return `${integer.replace(/\B(?=(\d{3})+(?!\d))/g, '.')},${decimal}`;
};

const isValidCpf = (value) => {
    const cpf = onlyDigits(value, 11);

    if (cpf.length !== 11 || /^(\d)\1{10}$/.test(cpf)) {
        return false;
    }

    const calculateDigit = (length) => {
        let sum = 0;

        for (let index = 0; index < length; index += 1) {
            sum += Number(cpf[index]) * (length + 1 - index);
        }

        const remainder = (sum * 10) % 11;

        return remainder === 10 ? 0 : remainder;
    };

    return calculateDigit(9) === Number(cpf[9]) && calculateDigit(10) === Number(cpf[10]);
};

const setupQrScanner = () => {
    const form = document.querySelector('[data-qr-scan-form]');
    const scanner = form?.querySelector('[data-qr-scanner]');
    const retryButton = form?.querySelector('[data-qr-scanner-retry]');
    const video = form?.querySelector('[data-qr-video]');
    const status = form?.querySelector('[data-qr-status]');
    const tokenInput = form?.querySelector('[data-qr-token]');
    const cameraState = form?.querySelector('[data-qr-camera-state]');
    const cameraStateDot = form?.querySelector('[data-qr-camera-state-dot]');
    const cameraStateLabel = form?.querySelector('[data-qr-camera-state-label]');
    const permissionHelp = form?.querySelector('[data-qr-permission-help]');

    if (! form || ! scanner || ! retryButton || ! video || ! status || ! tokenInput) {
        return;
    }

    const codeReader = new BrowserQRCodeReader(undefined, {
        delayBetweenScanAttempts: 150,
        delayBetweenScanSuccess: 500,
    });
    let scannerControls = null;
    let scannerSession = 0;
    let scannerStarting = false;
    let submitting = false;
    let initializationRunning = false;
    let initializationTimer = null;
    const permissionStorageKey = 'giracomanda.camera.permission';

    const setStatus = (message, isError = false) => {
        status.textContent = message;
        status.classList.toggle('bg-red-50', isError);
        status.classList.toggle('text-red-800', isError);
        status.classList.toggle('bg-brand-50', ! isError);
        status.classList.toggle('text-brand-900', ! isError);
    };

    const setCameraState = (label, state) => {
        if (! cameraState || ! cameraStateDot || ! cameraStateLabel) {
            return;
        }

        const isActive = state === 'active';
        const isError = state === 'error';

        cameraStateLabel.textContent = label;
        cameraState.classList.toggle('bg-emerald-400/15', isActive);
        cameraState.classList.toggle('text-emerald-200', isActive);
        cameraState.classList.toggle('bg-red-400/15', isError);
        cameraState.classList.toggle('text-red-200', isError);
        cameraState.classList.toggle('bg-white/10', ! isActive && ! isError);
        cameraState.classList.toggle('text-brand-100', ! isActive && ! isError);
        cameraStateDot.classList.toggle('bg-emerald-300', isActive);
        cameraStateDot.classList.toggle('bg-red-300', isError);
        cameraStateDot.classList.toggle('bg-warm-300', ! isActive && ! isError);
    };

    const stopScanner = () => {
        scannerSession += 1;
        scannerControls?.stop();
        scannerControls = null;

        if (typeof MediaStream !== 'undefined' && video.srcObject instanceof MediaStream) {
            video.srcObject.getTracks().forEach((track) => track.stop());
        }

        video.srcObject = null;
    };

    const cameraPermissionState = async () => {
        if (! navigator.permissions?.query) {
            return null;
        }

        try {
            const permission = await navigator.permissions.query({ name: 'camera' });

            return permission.state;
        } catch (_error) {
            return null;
        }
    };

    const rememberedCameraPermission = () => {
        try {
            return window.localStorage.getItem(permissionStorageKey) === 'granted';
        } catch (_error) {
            return false;
        }
    };

    const rememberCameraPermission = (granted) => {
        try {
            if (granted) {
                window.localStorage.setItem(permissionStorageKey, 'granted');
            } else {
                window.localStorage.removeItem(permissionStorageKey);
            }
        } catch (_error) {
            // The browser permission remains authoritative when storage is unavailable.
        }
    };

    const showPermissionRequest = () => {
        setStatus('Toque em “Ativar câmera” para liberar o leitor neste aparelho.');
        setCameraState('Aguardando permissão', 'starting');
        permissionHelp?.setAttribute('hidden', '');
        retryButton.textContent = 'Ativar câmera';
        retryButton.removeAttribute('hidden');
    };

    const setPermissionHelp = (message) => {
        if (! permissionHelp) {
            return;
        }

        permissionHelp.textContent = message;
        permissionHelp.removeAttribute('hidden');
    };

    const showCameraError = async (error) => {
        const denied = ['NotAllowedError', 'PermissionDeniedError'].includes(error?.name);
        const unavailable = ['NotFoundError', 'DevicesNotFoundError', 'OverconstrainedError'].includes(error?.name);
        const busy = ['NotReadableError', 'AbortError', 'TrackStartError'].includes(error?.name);
        const permissionState = denied ? await cameraPermissionState() : null;

        console.warn(`Falha na câmera: nome=${error?.name ?? 'desconhecido'}; mensagem=${error?.message ?? 'sem mensagem'}; permissão=${permissionState ?? 'indisponível'}`);

        if (denied && permissionState === 'denied') {
            rememberCameraPermission(false);
            setStatus('A câmera está bloqueada para este endereço.', true);
            setPermissionHelp('No navegador, clique no ícone ao lado do endereço, permita o uso da câmera e volte para esta página. O leitor tentará novamente automaticamente.');
            retryButton.textContent = 'Verificar novamente';
        } else if (denied && permissionState === 'granted') {
            setStatus('O Chrome permitiu o acesso, mas não conseguiu iniciar a câmera.', true);
            setPermissionHelp('Feche outros aplicativos que possam estar usando a câmera e tente novamente. Se continuar, reinicie o Chrome.');
            retryButton.textContent = 'Tentar novamente';
        } else if (denied) {
            setStatus('Toque em “Ativar câmera” para autorizar o leitor.', true);
            retryButton.textContent = 'Ativar câmera';
        } else if (unavailable) {
            setStatus('Nenhuma câmera compatível foi encontrada neste aparelho.', true);
            retryButton.textContent = 'Verificar novamente';
        } else if (busy) {
            setStatus('A câmera está ocupada por outro aplicativo.', true);
            setPermissionHelp('Feche aplicativos de reunião, gravação ou outras abas que estejam usando a câmera e tente novamente.');
            retryButton.textContent = 'Tentar novamente';
        } else {
            setStatus('Não foi possível iniciar a câmera. Você ainda pode selecionar o cartão manualmente.', true);
            retryButton.textContent = 'Tentar novamente';
        }

        setCameraState('Indisponível', 'error');
        retryButton.removeAttribute('hidden');
    };

    const startScanner = async () => {
        if (scannerControls || scannerStarting || submitting) {
            return;
        }

        scannerStarting = true;
        stopScanner();
        const session = scannerSession;
        submitting = false;
        tokenInput.value = '';
        retryButton.setAttribute('hidden', '');
        permissionHelp?.setAttribute('hidden', '');
        setCameraState('Iniciando', 'starting');

        if (! window.isSecureContext) {
            setStatus('Para usar a câmera no tablet, abra o sistema por uma conexão HTTPS segura.', true);
            setCameraState('Requer HTTPS', 'error');
            scannerStarting = false;

            return;
        }

        if (! navigator.mediaDevices?.getUserMedia) {
            setStatus('Este navegador não oferece acesso à câmera. Use Chrome, Edge ou Safari atualizado.', true);
            setCameraState('Indisponível', 'error');
            scannerStarting = false;

            return;
        }

        setStatus('Preparando a câmera...');

        try {
            const controls = await codeReader.decodeFromConstraints(
                {
                    audio: false,
                    video: {
                        facingMode: { ideal: 'environment' },
                    },
                },
                video,
                (result, _error, activeControls) => {
                    if (! result || submitting || session !== scannerSession) {
                        return;
                    }

                    const token = result.getText().trim();

                    if (token === '') {
                        return;
                    }

                    submitting = true;
                    activeControls.stop();
                    tokenInput.value = token;
                    setStatus('Cartão identificado. Abrindo a comanda...');
                    setCameraState('Cartão lido', 'active');
                    window.setTimeout(() => form.requestSubmit(), 250);
                },
            );

            if (session !== scannerSession || submitting) {
                controls.stop();

                return;
            }

            scannerControls = controls;
            rememberCameraPermission(true);
            setStatus('Câmera pronta. Centralize o QR Code do cartão no quadro.');
            setCameraState('Câmera ativa', 'active');
        } catch (error) {
            if (session === scannerSession) {
                await showCameraError(error);
            }
        } finally {
            scannerStarting = false;
        }
    };

    window.addEventListener('pagehide', stopScanner);

    const initializeScanner = async () => {
        if (scannerControls || scannerStarting || submitting || initializationRunning) {
            return;
        }

        initializationRunning = true;

        try {
            if (! window.isSecureContext || ! navigator.mediaDevices?.getUserMedia) {
                await startScanner();

                return;
            }

            const permissionState = await cameraPermissionState();

            if (permissionState === 'granted' || (permissionState === null && rememberedCameraPermission())) {
                await startScanner();

                return;
            }

            if (permissionState === 'denied') {
                stopScanner();
                await showCameraError({ name: 'NotAllowedError' });

                return;
            }

            showPermissionRequest();
        } finally {
            initializationRunning = false;
        }
    };

    const scheduleScannerInitialization = () => {
        window.clearTimeout(initializationTimer);
        initializationTimer = window.setTimeout(initializeScanner, 200);
    };

    retryButton.addEventListener('click', startScanner);
    window.addEventListener('focus', scheduleScannerInitialization);
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            scheduleScannerInitialization();
        }
    });

    if (navigator.permissions?.query) {
        navigator.permissions.query({ name: 'camera' })
            .then((permission) => permission.addEventListener('change', scheduleScannerInitialization))
            .catch(() => {});
    }

    initializeScanner();
};

document.addEventListener('DOMContentLoaded', () => {
    setupQrScanner();
    setupMarketingPage();

    document.querySelectorAll('[data-copy-text]').forEach((button) => {
        button.addEventListener('click', async () => {
            const text = button.dataset.copyText;

            if (! text) {
                return;
            }

            try {
                await navigator.clipboard.writeText(text);
                const feedback = button.parentElement?.querySelector('[data-copy-feedback]');

                feedback?.classList.remove('hidden');
                window.setTimeout(() => feedback?.classList.add('hidden'), 3000);
            } catch (_error) {
                window.prompt('Copie o código PIX:', text);
            }
        });
    });

    const customerSummaryModal = document.querySelector('[data-customer-summary-modal]');
    const customerSummaryContent = customerSummaryModal?.querySelector('[data-customer-summary-content]');

    const closeCustomerSummary = () => customerSummaryModal?.close();
    const openCustomerSummary = async (url) => {
        if (! customerSummaryModal || ! customerSummaryContent || ! url) {
            return;
        }

        customerSummaryContent.innerHTML = '<p class="py-12 text-center text-sm font-semibold text-slate-500">Carregando resumo...</p>';
        customerSummaryModal.showModal();

        try {
            const response = await fetch(url, {
                headers: {
                    Accept: 'text/html',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (! response.ok) {
                throw new Error('Não foi possível carregar o resumo.');
            }

            customerSummaryContent.innerHTML = await response.text();
        } catch (error) {
            customerSummaryContent.innerHTML = '<div class="py-12 text-center"><p class="font-bold text-red-800">Não foi possível carregar o resumo.</p><button type="button" data-customer-summary-close class="mt-4 rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-bold text-white">Fechar</button></div>';
        }
    };

    document.addEventListener('click', (event) => {
        const closeButton = event.target.closest('[data-customer-summary-close]');

        if (closeButton) {
            closeCustomerSummary();

            return;
        }

        const openButton = event.target.closest('[data-customer-summary-open]');

        if (openButton) {
            openCustomerSummary(openButton.dataset.customerSummaryOpen);

            return;
        }

        const row = event.target.closest('[data-customer-summary-url]');

        if (row && ! event.target.closest('a, button')) {
            openCustomerSummary(row.dataset.customerSummaryUrl);
        }
    });

    document.addEventListener('keydown', (event) => {
        const row = event.target.closest('[data-customer-summary-url]');

        if (row && ['Enter', ' '].includes(event.key)) {
            event.preventDefault();
            openCustomerSummary(row.dataset.customerSummaryUrl);
        }
    });

    customerSummaryModal?.addEventListener('click', (event) => {
        if (event.target === customerSummaryModal) {
            closeCustomerSummary();
        }
    });

    const cashPaymentDetailModal = document.querySelector('[data-cash-payment-detail-modal]');
    const cashPaymentDetailContent = cashPaymentDetailModal?.querySelector('[data-cash-payment-detail-content]');

    const closeCashPaymentDetail = () => cashPaymentDetailModal?.close();
    const openCashPaymentDetail = async (url) => {
        if (! cashPaymentDetailModal || ! cashPaymentDetailContent || ! url) {
            return;
        }

        cashPaymentDetailContent.innerHTML = '<p class="py-12 text-center text-sm font-semibold text-slate-500">Carregando detalhes...</p>';
        cashPaymentDetailModal.showModal();

        try {
            const response = await fetch(url, {
                headers: {
                    Accept: 'text/html',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (! response.ok) {
                throw new Error('Não foi possível carregar os detalhes.');
            }

            cashPaymentDetailContent.innerHTML = await response.text();
        } catch (error) {
            cashPaymentDetailContent.innerHTML = '<div class="py-12 text-center"><p class="font-bold text-red-800">Não foi possível carregar os detalhes.</p><button type="button" data-cash-payment-detail-close class="mt-4 rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-bold text-white">Fechar</button></div>';
        }
    };

    document.addEventListener('click', (event) => {
        const closeButton = event.target.closest('[data-cash-payment-detail-close]');

        if (closeButton) {
            closeCashPaymentDetail();

            return;
        }

        const openButton = event.target.closest('[data-cash-payment-detail-open]');

        if (openButton) {
            openCashPaymentDetail(openButton.dataset.cashPaymentDetailOpen);

            return;
        }

        const row = event.target.closest('[data-cash-payment-detail-url]');

        if (row && ! event.target.closest('a, button')) {
            openCashPaymentDetail(row.dataset.cashPaymentDetailUrl);
        }
    });

    document.addEventListener('keydown', (event) => {
        const row = event.target.closest('[data-cash-payment-detail-url]');

        if (row && ['Enter', ' '].includes(event.key)) {
            event.preventDefault();
            openCashPaymentDetail(row.dataset.cashPaymentDetailUrl);
        }
    });

    cashPaymentDetailModal?.addEventListener('click', (event) => {
        if (event.target === cashPaymentDetailModal) {
            closeCashPaymentDetail();
        }
    });

    const companyLogoInput = document.querySelector('[data-company-logo-input]');
    const companyLogoPreview = document.querySelector('[data-company-logo-preview]');
    const companyLogoFilename = document.querySelector('[data-company-logo-filename]');
    let companyLogoPreviewUrl = null;

    companyLogoInput?.addEventListener('change', () => {
        const file = companyLogoInput.files?.[0];

        if (! file || ! companyLogoPreview) {
            return;
        }

        if (companyLogoPreviewUrl) {
            URL.revokeObjectURL(companyLogoPreviewUrl);
        }

        companyLogoPreviewUrl = URL.createObjectURL(file);
        companyLogoPreview.querySelector('[data-company-logo-preview-fallback]')?.remove();
        let image = companyLogoPreview.querySelector('[data-company-logo-preview-image]');

        if (! image) {
            image = document.createElement('img');
            image.dataset.companyLogoPreviewImage = '';
            image.className = 'max-h-36 max-w-full object-contain drop-shadow-sm';
            companyLogoPreview.append(image);
        }

        image.src = companyLogoPreviewUrl;
        image.alt = `Prévia de ${file.name}`;

        if (companyLogoFilename) {
            companyLogoFilename.textContent = `${file.name} · a nova logo será aplicada ao salvar.`;
        }
    });

    const bulkCardPrintForm = document.querySelector('[data-bulk-card-print-form]');

    if (bulkCardPrintForm) {
        const selectAllCards = bulkCardPrintForm.querySelector('[data-card-select-all]');
        const selectedCardsCount = bulkCardPrintForm.querySelector('[data-card-selection-count]');
        const bulkPrintButton = bulkCardPrintForm.querySelector('[data-bulk-card-print-button]');
        const cardCheckboxes = [...document.querySelectorAll('[data-card-select]')];

        const updateCardSelection = () => {
            const selectedCount = cardCheckboxes.filter((checkbox) => checkbox.checked).length;

            if (selectedCardsCount) {
                selectedCardsCount.textContent = selectedCount === 0
                    ? 'Nenhum cartão selecionado'
                    : `${selectedCount} ${selectedCount === 1 ? 'cartão selecionado' : 'cartões selecionados'}`;
            }

            if (bulkPrintButton) {
                bulkPrintButton.disabled = selectedCount === 0;
                bulkPrintButton.textContent = selectedCount === 0
                    ? 'Imprimir selecionados'
                    : `Imprimir ${selectedCount} ${selectedCount === 1 ? 'cartão' : 'cartões'}`;
            }

            if (selectAllCards) {
                selectAllCards.checked = selectedCount === cardCheckboxes.length;
                selectAllCards.indeterminate = selectedCount > 0 && selectedCount < cardCheckboxes.length;
            }
        };

        selectAllCards?.addEventListener('change', () => {
            cardCheckboxes.forEach((checkbox) => {
                checkbox.checked = selectAllCards.checked;
            });
            updateCardSelection();
        });

        cardCheckboxes.forEach((checkbox) => checkbox.addEventListener('change', updateCardSelection));
        updateCardSelection();
    }

    document.querySelectorAll('[data-mask="cpf"]').forEach((input) => {
        const feedback = document.querySelector('[data-cpf-feedback]');

        const updateCpf = () => {
            input.value = formatCpf(input.value);
            const digits = onlyDigits(input.value, 11);
            const valid = isValidCpf(input.value);

            input.setCustomValidity(digits.length === 0 || valid ? '' : 'Informe um CPF válido.');

            if (feedback && digits.length === 11) {
                feedback.textContent = valid ? 'CPF válido.' : 'CPF inválido. Confira os números.';
                feedback.classList.toggle('text-emerald-700', valid);
                feedback.classList.toggle('text-red-700', ! valid);
                feedback.classList.toggle('text-slate-500', false);
            } else if (feedback) {
                feedback.textContent = 'Obrigatório, exclusivo e validado automaticamente.';
                feedback.classList.remove('text-emerald-700', 'text-red-700');
                feedback.classList.add('text-slate-500');
            }
        };

        input.addEventListener('input', updateCpf);
        updateCpf();
    });

    document.querySelectorAll('[data-mask="phone"]').forEach((input) => {
        const updatePhone = () => {
            input.value = formatPhone(input.value);
        };

        input.addEventListener('input', updatePhone);
        updatePhone();
    });

    document.querySelectorAll('[data-mask="currency"]').forEach((input) => {
        const updateCurrency = () => {
            input.value = formatCurrency(input.value);
        };

        input.addEventListener('input', updateCurrency);
        updateCurrency();
    });

    document.querySelectorAll('[data-clear-form]').forEach((button) => {
        button.addEventListener('click', () => {
            const form = button.closest('form');

            if (! form) {
                return;
            }

            form.querySelectorAll('input:not([type="hidden"]), select, textarea').forEach((field) => {
                if (field instanceof HTMLInputElement && ['checkbox', 'radio'].includes(field.type)) {
                    field.checked = false;
                } else if (field instanceof HTMLSelectElement) {
                    field.selectedIndex = 0;
                } else {
                    field.value = '';
                }

                field.setCustomValidity?.('');
                field.dispatchEvent(new Event('input', { bubbles: true }));
                field.dispatchEvent(new Event('change', { bubbles: true }));
            });

            form.querySelector('input:not([type="hidden"]):not([type="checkbox"]):not([type="radio"])')?.focus();
        });
    });

    document.querySelectorAll('[data-payment-method]').forEach((select) => {
        const form = select.closest('form');
        const mixedPayments = form?.querySelector('[data-mixed-payments]');
        const mixedInputs = mixedPayments?.querySelectorAll('[data-mixed-payment-input]') ?? [];

        const updateMixedPayments = () => {
            const isMixed = select.value === 'mixed';

            if (mixedPayments) {
                mixedPayments.hidden = ! isMixed;
            }

            mixedInputs.forEach((input) => {
                input.disabled = ! isMixed;
            });
        };

        select.addEventListener('change', updateMixedPayments);
        updateMixedPayments();
    });
});
