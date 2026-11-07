document.addEventListener('change', event => {
    const select = event.target;
    if (!(select instanceof HTMLSelectElement) || !select.matches('.final-fantasy .ff-item-picker select')) {
        return;
    }
    const icon = select.closest('.ff-item-picker').querySelector('.ff-selected-icon');
    const option = select.selectedOptions[0];
    const glyph = Number(option?.dataset.icon);
    const label = option?.dataset.iconLabel ?? '';
    icon.hidden = !Number.isInteger(glyph) || glyph < 212 || glyph > 225;
    icon.dataset.glyph = icon.hidden ? '' : String(glyph);
    icon.style.backgroundPosition = icon.hidden ? '' : `${-16 * (glyph - 212)}px 0`;
    icon.title = label;
    icon.setAttribute('aria-label', label);
});
