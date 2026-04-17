import './bootstrap.js';
import './styles/app.css';

function initOfflineDetection() {
    const body = document.body;
    if (!body || body.dataset.offlineDetectionReady === '1') {
        return;
    }

    body.dataset.offlineDetectionReady = '1';

    const banner = document.createElement('div');
    banner.className = 'tbn-network-banner';
    banner.setAttribute('role', 'status');
    banner.setAttribute('aria-live', 'polite');
    banner.innerHTML = `
        <span class="tbn-network-banner__dot" aria-hidden="true"></span>
        <span class="tbn-network-banner__text"></span>
    `;
    body.prepend(banner);

    const bannerText = banner.querySelector('.tbn-network-banner__text');

    const getProtectedForms = () => Array.from(document.querySelectorAll('form[method="post"], form[method="POST"]'));

    const syncNetworkState = () => {
        const offline = !window.navigator.onLine;
        body.classList.toggle('tbn-is-offline', offline);
        body.classList.toggle('tbn-is-online', !offline);

        if (bannerText) {
            bannerText.textContent = offline
                ? 'Mode hors ligne: les actions d’envoi sont temporairement désactivées.'
                : 'Connexion rétablie: vous pouvez envoyer vos actions normalement.';
        }

        getProtectedForms().forEach((form) => {
            form.dataset.offlineProtected = '1';

            form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach((button) => {
                if (!(button instanceof HTMLButtonElement || button instanceof HTMLInputElement)) {
                    return;
                }

                if (offline) {
                    button.dataset.offlineDisabled = button.disabled ? '1' : '0';
                    button.disabled = true;
                    button.setAttribute('aria-disabled', 'true');
                    button.title = 'Action indisponible hors ligne';
                } else {
                    const wasDisabledBeforeOffline = button.dataset.offlineDisabled === '1';
                    if (!wasDisabledBeforeOffline) {
                        button.disabled = false;
                    }
                    button.removeAttribute('aria-disabled');
                    button.removeAttribute('title');
                    delete button.dataset.offlineDisabled;
                }
            });
        });
    };

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        const isProtected = form.dataset.offlineProtected === '1'
            || form.getAttribute('method')?.toLowerCase() === 'post';

        if (isProtected && !window.navigator.onLine) {
            event.preventDefault();
            syncNetworkState();
        }
    });

    window.addEventListener('offline', syncNetworkState);
    window.addEventListener('online', syncNetworkState);
    syncNetworkState();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initOfflineDetection, { once: true });
} else {
    initOfflineDetection();
}

function initNotificationAlerts() {
    const body = document.body;
    if (!body || body.dataset.notificationAlertsReady === '1') {
        return;
    }
    body.dataset.notificationAlertsReady = '1';

    const notifLink = document.querySelector('.tbn-notif-link');
    if (!(notifLink instanceof HTMLAnchorElement)) {
        return;
    }

    const unreadEndpoint = '/connect/notifications/unread-count';
    let currentCount = Number.parseInt(body.dataset.tbnUnreadInitial || '0', 10);
    if (Number.isNaN(currentCount) || currentCount < 0) {
        currentCount = 0;
    }

    const toast = document.createElement('div');
    toast.className = 'tbn-notif-toast';
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'assertive');
    toast.hidden = true;
    toast.innerHTML = `
        <span class="tbn-notif-toast__icon" aria-hidden="true">🔔</span>
        <span class="tbn-notif-toast__text"></span>
        <a class="tbn-notif-toast__link" href="/connect/notifications">Voir</a>
    `;
    body.appendChild(toast);

    const toastText = toast.querySelector('.tbn-notif-toast__text');
    let toastTimer = null;

    const showToast = (count) => {
        if (!(toastText instanceof HTMLElement)) {
            return;
        }
        toastText.textContent = count > 1
            ? `Vous avez ${count} notifications non lues.`
            : 'Vous avez une nouvelle notification.';
        toast.hidden = false;
        toast.classList.add('is-visible');
        window.clearTimeout(toastTimer);
        toastTimer = window.setTimeout(() => {
            toast.classList.remove('is-visible');
            window.setTimeout(() => {
                toast.hidden = true;
            }, 180);
        }, 4200);
    };

    const syncBadge = (count) => {
        let badge = notifLink.querySelector('.tbn-notif-link__badge');
        let state = notifLink.querySelector('.tbn-notif-link__state');
        if (count > 0) {
            notifLink.classList.add('is-unread');
            if (!(state instanceof HTMLElement)) {
                state = document.createElement('span');
                state.className = 'tbn-notif-link__state';
                state.textContent = 'Non lu';
                const label = notifLink.querySelector('.tbn-notif-link__label');
                if (label instanceof HTMLElement && label.nextSibling) {
                    notifLink.insertBefore(state, label.nextSibling);
                } else {
                    notifLink.appendChild(state);
                }
            }
            if (!(badge instanceof HTMLElement)) {
                badge = document.createElement('span');
                badge.className = 'tbn-notif-link__badge';
                notifLink.appendChild(badge);
            }
            badge.textContent = String(count);
            badge.classList.add('tbn-notif-link__badge--pulse');
            window.setTimeout(() => badge.classList.remove('tbn-notif-link__badge--pulse'), 600);
        } else {
            notifLink.classList.remove('is-unread');
            if (badge instanceof HTMLElement) {
                badge.remove();
            }
            if (state instanceof HTMLElement) {
                state.remove();
            }
        }
    };

    notifLink.addEventListener('click', () => {
        currentCount = 0;
        syncBadge(0);
    });

    const checkUnread = async () => {
        try {
            const response = await fetch(unreadEndpoint, {
                method: 'GET',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (!response.ok) {
                return;
            }
            const payload = await response.json();
            const nextCount = Number.parseInt(String(payload.count ?? 0), 10);
            if (Number.isNaN(nextCount) || nextCount < 0) {
                return;
            }

            if (nextCount > currentCount) {
                showToast(nextCount);
            }

            currentCount = nextCount;
            syncBadge(nextCount);
        } catch (error) {
        }
    };

    syncBadge(currentCount);
    window.setInterval(checkUnread, 20000);
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            checkUnread();
        }
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initNotificationAlerts, { once: true });
} else {
    initNotificationAlerts();
}

function initSmartHashtagSuggestions() {
    const host = document.getElementById('tbn-smart-hashtag-suggestions');
    if (!host) {
        return;
    }
    if (host.dataset.smartHashtagReady === '1') {
        return;
    }
    host.dataset.smartHashtagReady = '1';

    const endpoint = host.dataset.endpoint;
    const contentInputId = host.dataset.contentInputId;
    const hashtagsInputId = host.dataset.hashtagsInputId;
    if (!endpoint || !contentInputId || !hashtagsInputId) {
        return;
    }

    const contentEl = document.getElementById(contentInputId);
    const hashtagsEl = document.getElementById(hashtagsInputId);
    if (!contentEl || !hashtagsEl) {
        return;
    }

    let timer = null;
    const debounceDelayMs = 650;

    const ensureBox = () => {
        let list = host.querySelector('[data-smart-hashtag-list="1"]');
        if (!list) {
            list = document.createElement('div');
            list.dataset.smartHashtagList = '1';
            list.className = 'tbn-smart-hashtag-suggestions__list';
            host.appendChild(list);
        }
        return list;
    };

    const setStatus = (text) => {
        let status = host.querySelector('[data-smart-hashtag-status="1"]');
        if (!status) {
            status = document.createElement('div');
            status.dataset.smartHashtagStatus = '1';
            status.className = 'tbn-smart-hashtag-suggestions__status';
            host.appendChild(status);
        }
        status.textContent = text;
    };

    const clearStatus = () => {
        const status = host.querySelector('[data-smart-hashtag-status="1"]');
        if (status instanceof HTMLElement) {
            status.remove();
        }
    };

    const render = (suggestions) => {
        const list = ensureBox();
        list.innerHTML = '';

        if (!Array.isArray(suggestions) || suggestions.length === 0) {
            list.innerHTML = '<div class="tbn-smart-hashtag-suggestions__empty">Aucune suggestion pour le moment.</div>';
            return;
        }

        const frag = document.createDocumentFragment();
        suggestions.forEach((s) => {
            if (!s || !s.tag) return;

            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'tbn-smart-hashtag-suggestions__btn';
            const count = Number.isFinite(s.count) ? s.count : 0;
            btn.textContent = '#'+s.tag + (count > 0 ? ' ('+count+')' : '');

            btn.addEventListener('click', () => {
                const current = (hashtagsEl.value || '').trim();
                const already = parseCurrentTags();
                if (already.has(String(s.tag))) {
                    return;
                }

                if (!current) {
                    hashtagsEl.value = '#'+s.tag;
                } else {
                    const sep = current.endsWith(',') ? ' ' : ', ';
                    hashtagsEl.value = current + sep + '#'+s.tag;
                }

                hashtagsEl.dispatchEvent(new Event('input', { bubbles: true }));
            });

            frag.appendChild(btn);
        });

        list.appendChild(frag);
    };

    const parseCurrentTags = () => {
        const current = (hashtagsEl.value || '').toLowerCase();
        const parts = current
            .split(/[,\s]+/u)
            .map((p) => p.trim())
            .filter(Boolean)
            .map((p) => p.replace(/^#+/u, ''));

        return new Set(parts);
    };

    const update = async () => {
        timer = null;

        const content = (contentEl.value || '').trim();
        const currentHashtags = (hashtagsEl.value || '').trim();

        if (!content) {
            render([]);
            clearStatus();
            return;
        }

        setStatus('Chargement des suggestions...');

        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ content, currentHashtags }),
            });

            if (!response.ok) {
                render([]);
                setStatus('Impossible de récupérer les suggestions.');
                return;
            }

            const payload = await response.json();
            const suggestions = payload && Array.isArray(payload.suggestions) ? payload.suggestions : [];
            render(suggestions);
            clearStatus();
        } catch (e) {
            render([]);
            setStatus('Erreur réseau (suggestions indisponibles).');
        }
    };

    const scheduleUpdate = () => {
        if (timer) {
            window.clearTimeout(timer);
        }
        timer = window.setTimeout(update, debounceDelayMs);
    };

    contentEl.addEventListener('input', scheduleUpdate);
    hashtagsEl.addEventListener('input', scheduleUpdate);

    update();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initSmartHashtagSuggestions, { once: true });
} else {
    initSmartHashtagSuggestions();
}
document.addEventListener('turbo:load', initSmartHashtagSuggestions);

let tbnConfirmPendingForm = null;

function initTbnConfirmModal() {
    if (window.__tbnConfirmModalBound) {
        return;
    }
    window.__tbnConfirmModalBound = true;

    const getRoot = () => document.getElementById('tbn-confirm-modal');

    const close = () => {
        const root = getRoot();
        if (!root) {
            tbnConfirmPendingForm = null;
            return;
        }
        root.classList.remove('is-open');
        document.body.classList.remove('tbn-confirm-modal-open');
        window.setTimeout(() => {
            root.hidden = true;
            tbnConfirmPendingForm = null;
        }, 200);
    };

    const open = (message, form) => {
        const root = getRoot();
        const msgEl = document.getElementById('tbn-confirm-modal-message');
        if (!root || !(msgEl instanceof HTMLElement)) {
            return;
        }
        msgEl.textContent = message;
        tbnConfirmPendingForm = form;
        root.hidden = false;
        document.body.classList.add('tbn-confirm-modal-open');
        window.requestAnimationFrame(() => {
            root.classList.add('is-open');
            const ok = root.querySelector('[data-tbn-confirm-ok]');
            if (ok instanceof HTMLElement) {
                ok.focus();
            }
        });
    };

    document.addEventListener(
        'submit',
        (event) => {
            const form = event.target;
            if (!(form instanceof HTMLFormElement)) {
                return;
            }
            const message = form.getAttribute('data-tbn-confirm');
            if (!message) {
                return;
            }
            if (form.dataset.tbnConfirmPass === '1') {
                delete form.dataset.tbnConfirmPass;
                return;
            }
            event.preventDefault();
            event.stopPropagation();
            open(message, form);
        },
        true
    );

    document.addEventListener('click', (e) => {
        const t = e.target;
        if (!(t instanceof HTMLElement)) {
            return;
        }
        const inModal = t.closest('#tbn-confirm-modal');
        if (!inModal) {
            return;
        }
        if (t.matches('[data-tbn-confirm-ok]')) {
            const form = tbnConfirmPendingForm;
            close();
            if (form instanceof HTMLFormElement) {
                form.dataset.tbnConfirmPass = '1';
                form.requestSubmit();
            }
            return;
        }
        if (t.matches('[data-tbn-confirm-cancel]') || t.matches('[data-tbn-confirm-close]')) {
            close();
        }
    });

    document.addEventListener('keydown', (e) => {
        const root = getRoot();
        if (e.key === 'Escape' && root && !root.hidden && root.classList.contains('is-open')) {
            close();
        }
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initTbnConfirmModal, { once: true });
} else {
    initTbnConfirmModal();
}
document.addEventListener('turbo:load', initTbnConfirmModal);
