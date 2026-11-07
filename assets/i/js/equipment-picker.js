// Native operating-system option menus cannot reliably render downloaded fonts.
// Keep the select as the form value and enhance it with an in-page select-only combobox.
(() => {
    const initialize = () => {
        document.querySelectorAll('.final-fantasy .ff-item-picker select').forEach(select => {
            if (select.dataset.enhanced) {
                return;
            }
            const container = document.createElement('span');
            container.className = 'ff-combobox';
            const button = document.createElement('button');
            button.type = 'button';
            button.id = `${select.id}-picker`;
            button.className = 'ff-choice';
            button.setAttribute('role', 'combobox');
            button.setAttribute('aria-haspopup', 'listbox');
            button.setAttribute('aria-expanded', 'false');
            const list = document.createElement('span');
            list.id = `${select.id}-options`;
            list.className = 'ff-options';
            list.setAttribute('role', 'listbox');
            list.hidden = true;
            button.setAttribute('aria-controls', list.id);
            const label = select.labels[0];
            const labelText = label?.textContent.trim() || 'Equipment';
            list.setAttribute('aria-label', labelText);
            const options = Array.from(select.options);
            const entries = options.map((option, index) => {
                const entry = document.createElement('span');
                entry.id = `${list.id}-${index}`;
                entry.className = option.value === '0' ? 'ff-option' : 'ff-option ff-text';
                entry.setAttribute('role', 'option');
                entry.setAttribute('aria-label', option.label);
                entry.textContent = option.dataset.fontLabel || option.label;
                list.append(entry);
                entry.addEventListener('click', () => {
                    active = index;
                    close(true);
                    button.focus();
                });
                return entry;
            });
            let active = select.selectedIndex;
            let prefix = '';
            let lastKey = 0;
            const sync = () => {
                const option = options[select.selectedIndex];
                button.textContent = option.dataset.fontLabel || option.label;
                button.classList.toggle('ff-text', option.value !== '0');
                button.setAttribute('aria-label', `${labelText}: ${option.label}`);
                entries.forEach((entry, index) => entry.setAttribute('aria-selected', String(index === select.selectedIndex)));
            };
            const highlight = index => {
                active = Math.max(0, Math.min(options.length - 1, index));
                entries.forEach((entry, i) => entry.classList.toggle('ff-option-active', i === active));
                button.setAttribute('aria-activedescendant', entries[active].id);
                entries[active].scrollIntoView({block: 'nearest'});
            };
            const open = () => {
                list.hidden = false;
                button.setAttribute('aria-expanded', 'true');
                const rect = button.getBoundingClientRect();
                const below = window.innerHeight - rect.bottom;
                const above = rect.top;
                const upwards = below < Math.min(280, above);
                list.classList.toggle('ff-options-above', upwards);
                list.style.maxHeight = `${Math.max(80, Math.min(280, (upwards ? above : below) - 8))}px`;
                prefix = '';
                highlight(select.selectedIndex);
            };
            const close = commit => {
                if (list.hidden) {
                    return;
                }
                if (commit && active !== select.selectedIndex) {
                    select.selectedIndex = active;
                    select.dispatchEvent(new Event('input', {bubbles: true}));
                    select.dispatchEvent(new Event('change', {bubbles: true}));
                }
                list.hidden = true;
                button.setAttribute('aria-expanded', 'false');
                button.removeAttribute('aria-activedescendant');
            };
            button.addEventListener('click', () => list.hidden ? open() : close(false));
            button.addEventListener('keydown', event => {
                const wasOpen = !list.hidden;
                if (event.key === 'Escape') {
                    if (wasOpen) {
                        event.preventDefault();
                        close(false);
                    }
                    return;
                }
                if (event.key === 'Tab') {
                    close(true);
                    return;
                }
                if (['Enter', ' '].includes(event.key)) {
                    event.preventDefault();
                    wasOpen ? close(true) : open();
                    return;
                }
                const navigation = ['ArrowDown', 'ArrowUp', 'Home', 'End', 'PageDown', 'PageUp'];
                const printable = event.key.length === 1 && !event.ctrlKey && !event.metaKey && !event.altKey;
                if (!navigation.includes(event.key) && !printable) {
                    return;
                }
                event.preventDefault();
                if (!wasOpen) {
                    open();
                }
                switch (event.key) {
                    case 'ArrowDown':
                        highlight(active + (wasOpen ? 1 : 0));
                        break;
                    case 'ArrowUp':
                        highlight(active - (wasOpen ? 1 : 0));
                        break;
                    case 'Home':
                        highlight(0);
                        break;
                    case 'End':
                        highlight(options.length - 1);
                        break;
                    case 'PageDown':
                        highlight(active + 8);
                        break;
                    case 'PageUp':
                        highlight(active - 8);
                        break;
                    default: {
                        const now = Date.now();
                        prefix = now - lastKey > 700 ? event.key : prefix + event.key;
                        lastKey = now;
                        const repeated = [...prefix].every(char => char.toLowerCase() === prefix[0].toLowerCase());
                        const search = (repeated ? prefix[0] : prefix).toLowerCase();
                        for (let step = repeated ? 1 : 0; step < options.length + (repeated ? 1 : 0); step++) {
                            const index = (active + step) % options.length;
                            if (options[index].label.toLowerCase().startsWith(search)) {
                                highlight(index);
                                break;
                            }
                        }
                    }
                }
            });
            // Keep keyboard focus on the combobox while clicking options or its scrollbar.
            list.addEventListener('mousedown', event => event.preventDefault());
            container.addEventListener('focusout', event => {
                if (!container.contains(event.relatedTarget)) {
                    close(true);
                }
            });
            document.addEventListener('pointerdown', event => {
                if (!container.contains(event.target)) {
                    close(true);
                }
            });
            select.addEventListener('change', sync);
            select.form?.addEventListener('reset', () => {
                close(false);
                setTimeout(() => select.dispatchEvent(new Event('change', {bubbles: true})), 0);
            });
            container.append(button, list);
            select.after(container);
            select.hidden = true;
            select.dataset.enhanced = 'true';
            if (label) {
                label.htmlFor = button.id;
            }
            sync();
        });
    };
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize, {once: true});
    } else {
        initialize();
    }
})();
