(() => {
    const authPage = document.querySelector('[data-auth-page]');

    if (!authPage) {
        return;
    }

    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const pointerTarget = authPage.querySelector('[data-auth-visual]');
    const submitButton = authPage.querySelector('[data-auth-submit]');
    const authForm = authPage.querySelector('[data-auth-form]');
    const switchLinks = authPage.querySelectorAll('[data-auth-switch]');
    const fields = authPage.querySelectorAll('.auth-field');
    const passwordFields = authPage.querySelectorAll('.password-field');
    const loginModal = authPage.querySelector('[data-auth-modal]');
    const modalInput = authPage.querySelector('[data-auth-modal-input]');
    const modalOpenButton = authPage.querySelector('[data-auth-modal-open]');
    const modalCloseButtons = authPage.querySelectorAll('[data-auth-modal-close]');
    const modalCancelButton = authPage.querySelector('[data-auth-modal-cancel]');
    const modalConfirmButton = authPage.querySelector('[data-auth-modal-confirm]');
    const loginCheckUrl = authPage.dataset.totpCheckUrl || '';
    const loginRequiresTotp = authPage.dataset.requiresTotp === '1';
    const loginHiddenTotp = authPage.querySelector('#totp_code');
    const loginIdentifier = authPage.querySelector('#identifier');
    const loginPassword = authPage.querySelector('#password');
    const loginForm = authPage.querySelector('form[data-auth-login-form]');
    const loginState = {
        requiresTotp: loginRequiresTotp,
        pendingCheck: null,
        checkTimer: null,
    };

    requestAnimationFrame(() => {
        authPage.classList.add('is-mounted');
        document.body.classList.add('auth-active');
    });

    const refreshFieldState = (field) => {
        const input = field.querySelector('input, select, textarea');
        if (!input) {
            return;
        }

        const update = () => {
            const hasValue = String(input.value || '').trim().length > 0;
            field.classList.toggle('has-value', hasValue);
        };

        update();
        input.addEventListener('input', update);
        input.addEventListener('change', update);
        input.addEventListener('focus', () => field.classList.add('is-focused'));
        input.addEventListener('blur', () => field.classList.remove('is-focused'));

        if (input.autocomplete === 'one-time-code') {
            input.addEventListener('animationstart', update);
        }
    };

    fields.forEach(refreshFieldState);

    const ensurePasswordToggle = () => {
        const eyeOpenIcon = `
            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M1.5 12s3.9-7.5 10.5-7.5S22.5 12 22.5 12 18.6 19.5 12 19.5 1.5 12 1.5 12Z" stroke="currentColor" stroke-width="1.8"/>
                <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.8"/>
            </svg>
        `;
        const eyeOffIcon = `
            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M3 3l18 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                <path d="M10.58 10.58a2 2 0 0 0 2.84 2.84" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                <path d="M9.9 5.2A11.5 11.5 0 0 1 12 4.5C18.6 4.5 22.5 12 22.5 12a21.7 21.7 0 0 1-3.2 4.3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                <path d="M6.3 7.2A21.4 21.4 0 0 0 1.5 12s3.9 7.5 10.5 7.5c1.5 0 2.9-.4 4.2-1" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
            </svg>
        `;

        passwordFields.forEach((container) => {
            if (container.querySelector('.password-toggle')) {
                return;
            }

            const input = container.querySelector('input[type="password"], input[type="text"]');
            if (!input) {
                return;
            }

            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'password-toggle';
            button.setAttribute('aria-label', 'Afficher le mot de passe');
            button.innerHTML = `
                <span class="toggle-icon icon-open">${eyeOpenIcon}</span>
                <span class="toggle-icon icon-closed">${eyeOffIcon}</span>
            `;

            const syncToggleState = (isVisible) => {
                button.classList.toggle('is-visible', isVisible);
                button.setAttribute('aria-pressed', String(isVisible));
                button.setAttribute('aria-label', isVisible ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
            };

            syncToggleState(input.type === 'text');

            button.addEventListener('click', (event) => {
                event.preventDefault();
                const isPassword = input.type === 'password';
                input.type = isPassword ? 'text' : 'password';
                syncToggleState(isPassword);
            });

            container.appendChild(button);
        });
    };

    ensurePasswordToggle();

    switchLinks.forEach((link) => {
        link.addEventListener('click', (event) => {
            if (prefersReducedMotion) {
                return;
            }

            const target = link.getAttribute('href');
            if (!target || target.startsWith('#')) {
                return;
            }

            event.preventDefault();
            authPage.classList.add('is-leaving');
            setTimeout(() => {
                window.location.href = target;
            }, 180);
        });
    });

    if (pointerTarget && !prefersReducedMotion) {
        const moveBg = (event) => {
            const rect = pointerTarget.getBoundingClientRect();
            const x = ((event.clientX - rect.left) / rect.width - 0.5) * 20;
            const y = ((event.clientY - rect.top) / rect.height - 0.5) * 20;
            pointerTarget.style.setProperty('--pointer-x', `${x}px`);
            pointerTarget.style.setProperty('--pointer-y', `${y}px`);
        };

        authPage.addEventListener('pointermove', moveBg, { passive: true });
        authPage.addEventListener('pointerleave', () => {
            pointerTarget.style.setProperty('--pointer-x', '0px');
            pointerTarget.style.setProperty('--pointer-y', '0px');
        });
    }

    if (authForm && submitButton) {
        authForm.addEventListener('submit', () => {
            if (loginForm && loginState.requiresTotp && loginHiddenTotp && !loginHiddenTotp.value) {
                return;
            }

            submitButton.classList.add('is-loading');
            submitButton.setAttribute('aria-busy', 'true');
            submitButton.disabled = true;
        });
    }

    if (loginForm && loginIdentifier && loginCheckUrl) {
        const setRequiresTotp = (value) => {
            loginState.requiresTotp = value;
            if (!value && loginHiddenTotp) {
                loginHiddenTotp.value = '';
            }
        };

        const performCheck = () => {
            const identifier = loginIdentifier.value.trim();
            if (identifier.length < 2) {
                setRequiresTotp(loginRequiresTotp);
                return;
            }

            const formData = new FormData();
            formData.append('identifier', identifier);

            if (loginState.pendingCheck) {
                loginState.pendingCheck.abort();
            }

            const controller = new AbortController();
            loginState.pendingCheck = controller;

            fetch(loginCheckUrl, {
                method: 'POST',
                body: formData,
                signal: controller.signal,
            })
                .then((response) => response.json())
                .then((data) => setRequiresTotp(Boolean(data.requiresTotp)))
                .catch(() => setRequiresTotp(loginRequiresTotp))
                .finally(() => {
                    if (loginState.pendingCheck === controller) {
                        loginState.pendingCheck = null;
                    }
                });
        };

        loginIdentifier.addEventListener('input', () => {
            clearTimeout(loginState.checkTimer);
            loginState.checkTimer = setTimeout(performCheck, 220);
        });

        loginForm.addEventListener('submit', (event) => {
            if (loginState.requiresTotp && loginHiddenTotp && !loginHiddenTotp.value) {
                event.preventDefault();
                if (loginModal) {
                    loginModal.classList.add('is-open');
                    loginModal.setAttribute('aria-hidden', 'false');
                    if (modalInput) {
                        modalInput.value = '';
                        modalInput.focus();
                    }
                }
            }
        });

        const closeLoginModal = () => {
            if (!loginModal) {
                return;
            }
            loginModal.classList.remove('is-open');
            loginModal.setAttribute('aria-hidden', 'true');
        };

        modalOpenButton?.addEventListener('click', () => {
            if (!loginModal) {
                return;
            }
            loginModal.classList.add('is-open');
            loginModal.setAttribute('aria-hidden', 'false');
            modalInput?.focus();
        });

        modalCloseButtons.forEach((button) => button.addEventListener('click', closeLoginModal));
        modalCancelButton?.addEventListener('click', closeLoginModal);

        modalConfirmButton?.addEventListener('click', () => {
            if (loginHiddenTotp && modalInput) {
                loginHiddenTotp.value = modalInput.value.trim();
            }
            closeLoginModal();
            if (loginHiddenTotp?.value) {
                loginForm.submit();
            }
        });

        loginModal?.addEventListener('click', (event) => {
            if (event.target === loginModal) {
                closeLoginModal();
            }
        });
    }
})();
