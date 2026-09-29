/**
 * Feature tab switcher on the public home page.
 * Highlights the active tab; preview image stays the same.
 */
export function initHomeTabs() {
    const root = document.querySelector('[data-home-tabs]');

    if (!(root instanceof HTMLElement)) {
        return;
    }

    const tabs = Array.from(root.querySelectorAll('[data-home-tab]'));

    if (tabs.length === 0) {
        return;
    }

    function activate(id) {
        tabs.forEach((tab) => {
            const selected = tab.getAttribute('data-home-tab') === id;
            tab.classList.toggle('is-active', selected);
            tab.setAttribute('aria-selected', selected ? 'true' : 'false');
            tab.setAttribute('tabindex', selected ? '0' : '-1');
        });
    }

    tabs.forEach((tab) => {
        tab.addEventListener('click', () => {
            const id = tab.getAttribute('data-home-tab');

            if (id) {
                activate(id);
            }
        });

        tab.addEventListener('keydown', (event) => {
            const index = tabs.indexOf(tab);
            let next = -1;

            if (event.key === 'ArrowRight' || event.key === 'ArrowDown') {
                next = (index + 1) % tabs.length;
            } else if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') {
                next = (index - 1 + tabs.length) % tabs.length;
            } else if (event.key === 'Home') {
                next = 0;
            } else if (event.key === 'End') {
                next = tabs.length - 1;
            }

            if (next < 0) {
                return;
            }

            event.preventDefault();
            const target = tabs[next];
            const id = target.getAttribute('data-home-tab');

            if (id) {
                activate(id);
                target.focus();
            }
        });
    });

    const initial = tabs.find((tab) => tab.classList.contains('is-active'))
        || tabs[0];
    const initialId = initial?.getAttribute('data-home-tab');

    if (initialId) {
        activate(initialId);
    }
}
