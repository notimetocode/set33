function closeSelect(root) {
    root.classList.remove('is-open');
    root.querySelector('[data-lang-select-trigger]')?.setAttribute('aria-expanded', 'false');
}

function openSelect(root) {
    root.classList.add('is-open');
    root.querySelector('[data-lang-select-trigger]')?.setAttribute('aria-expanded', 'true');
}

export function initLangSelect() {
    const roots = document.querySelectorAll('[data-lang-select]');

    if (!roots.length) {
        return;
    }

    roots.forEach((root) => {
        const trigger = root.querySelector('[data-lang-select-trigger]');
        const options = root.querySelectorAll('[data-lang-select-option]');

        if (trigger) {
            trigger.addEventListener('click', (event) => {
                event.stopPropagation();

                if (root.classList.contains('is-open')) {
                    closeSelect(root);
                } else {
                    roots.forEach((other) => {
                        if (other !== root) {
                            closeSelect(other);
                        }
                    });
                    openSelect(root);
                }
            });
        }

        options.forEach((option) => {
            option.addEventListener('click', (event) => {
                if (option.getAttribute('aria-selected') === 'true') {
                    event.preventDefault();
                    closeSelect(root);

                    return;
                }

                // Anchor navigation handles locale switch; keep menu UX tidy.
                closeSelect(root);
            });
        });
    });

    document.addEventListener('click', () => {
        roots.forEach(closeSelect);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') {
            return;
        }

        roots.forEach(closeSelect);
    });
}
