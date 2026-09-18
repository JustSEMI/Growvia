let closeOpenSelect = null;
let selectId = 0;

for (const select of document.querySelectorAll('select[data-custom-select]')) {
    if (select.multiple || select.size > 1 || select.options.length === 0) continue;

    const id = select.id || `growvia-select-${++selectId}`;
    const wrapper = document.createElement('div');
    wrapper.className = 'custom-select';
    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.id = `${id}-trigger`;
    trigger.className = 'field-input select-trigger';
    trigger.dataset.selectTrigger = '';
    trigger.setAttribute('role', 'combobox');
    trigger.setAttribute('aria-haspopup', 'listbox');
    trigger.setAttribute('aria-expanded', 'false');

    const labels = [...select.labels];
    if (labels.length) {
        labels.forEach((label, index) => {
            label.id ||= `${id}-label-${index}`;
            label.htmlFor = trigger.id;
        });
        trigger.setAttribute('aria-labelledby', labels.map(label => label.id).join(' '));
    } else {
        trigger.setAttribute('aria-label', select.getAttribute('aria-label') || 'Pilihan');
    }

    const label = document.createElement('span');
    label.dataset.selectLabel = '';
    const chevron = document.createElement('span');
    chevron.className = 'select-chevron';
    chevron.setAttribute('aria-hidden', 'true');
    trigger.append(label, chevron);
    const list = document.createElement('div');
    list.id = `${id}-listbox`;
    list.className = 'select-popover';
    list.setAttribute('role', 'listbox');
    list.setAttribute('aria-label', select.getAttribute('aria-label') || labels.map(item => item.textContent.trim()).join(' ') || 'Pilihan');
    list.hidden = true;
    trigger.setAttribute('aria-controls', list.id);

    let activeIndex = -1;
    let typed = '';
    let typedAt = 0;
    const enabled = () => [...select.options].map((option, index) => ({ option, index })).filter(({ option }) => !option.disabled && !option.hidden && !option.parentElement.disabled).map(({ index }) => index);
    const activate = (index) => {
        activeIndex = index;
        options.forEach((item, position) => item.toggleAttribute('data-active', position === index));
        if (index < 0) return;
        trigger.setAttribute('aria-activedescendant', options[index].id);
        options[index].scrollIntoView({ block: 'nearest' });
    };
    const commit = () => {
        if (!enabled().includes(activeIndex) || trigger.disabled) return;
        if (select.selectedIndex !== activeIndex) {
            select.selectedIndex = activeIndex;
            select.dispatchEvent(new Event('input', { bubbles: true }));
            select.dispatchEvent(new Event('change', { bubbles: true }));
        }
    };
    const options = [...select.options].map((option, index) => {
        const item = document.createElement('div');
        item.id = `${id}-option-${index}`;
        item.className = 'select-option';
        item.setAttribute('role', 'option');
        item.dataset.value = option.value;
        item.textContent = option.textContent.trim();
        list.append(item);
        item.addEventListener('click', () => {
            if (!enabled().includes(index) || trigger.disabled) return;
            activate(index);
            commit();
            close();
            trigger.focus({ preventScroll: true });
        });
        return item;
    });

    const sync = () => {
        label.textContent = select.selectedOptions[0]?.textContent.trim() || 'Pilih opsi';
        trigger.disabled = select.matches(':disabled');
        trigger.setAttribute('aria-required', String(select.required));
        for (const attribute of ['aria-invalid', 'aria-describedby']) {
            select.hasAttribute(attribute) ? trigger.setAttribute(attribute, select.getAttribute(attribute)) : trigger.removeAttribute(attribute);
        }
        options.forEach((item, index) => {
            item.setAttribute('aria-selected', String(select.options[index].selected));
            item.setAttribute('aria-disabled', String(!enabled().includes(index)));
            item.hidden = select.options[index].hidden;
        });
    };
    const close = () => {
        list.hidden = true;
        trigger.setAttribute('aria-expanded', 'false');
        trigger.removeAttribute('aria-activedescendant');
        typed = '';
        options.forEach(item => item.removeAttribute('data-active'));
        if (closeOpenSelect === close) closeOpenSelect = null;
    };
    const open = () => {
        if (trigger.disabled) return;
        closeOpenSelect?.();
        closeOpenSelect = close;
        sync();
        const bounds = trigger.getBoundingClientRect();
        const viewport = window.visualViewport;
        const leftEdge = (viewport?.offsetLeft ?? 0) + 8;
        const topEdge = (viewport?.offsetTop ?? 0) + 8;
        const rightEdge = leftEdge + (viewport?.width ?? document.documentElement.clientWidth) - 16;
        const bottomEdge = topEdge + (viewport?.height ?? innerHeight) - 16;
        const width = Math.min(Math.max(bounds.width, 212), rightEdge - leftEdge);
        list.style.width = `${width}px`;
        list.style.left = `${Math.max(leftEdge, Math.min(bounds.left, rightEdge - width))}px`;
        list.style.maxHeight = '320px';
        list.style.overflowY = 'auto';
        list.hidden = false;
        const desiredHeight = Math.min(list.scrollHeight + 2, 320);
        const above = bounds.top - topEdge - 6;
        const below = bottomEdge - bounds.bottom - 6;
        const upwards = below < desiredHeight && above > below;
        const height = Math.max(0, Math.min(desiredHeight, upwards ? above : below));
        list.style.maxHeight = `${height}px`;
        list.style.top = `${Math.max(topEdge, upwards ? bounds.top - height - 6 : bounds.bottom + 6)}px`;
        list.dataset.side = upwards ? 'above' : 'below';
        trigger.setAttribute('aria-expanded', 'true');
        activate(enabled().includes(select.selectedIndex) ? select.selectedIndex : (enabled()[0] ?? -1));
    };

    trigger.addEventListener('click', () => list.hidden ? open() : close());
    trigger.addEventListener('keydown', (event) => {
        if (event.ctrlKey || event.metaKey || trigger.disabled) return;
        if (event.key === 'Escape') {
            if (!list.hidden) { event.preventDefault(); event.stopPropagation(); close(); }
            return;
        }
        if (event.key === 'Tab') {
            if (!list.hidden) { commit(); close(); }
            return;
        }
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            if (list.hidden) open();
            else { commit(); close(); }
            return;
        }
        const navigation = ['ArrowDown', 'ArrowUp', 'Home', 'End', 'PageDown', 'PageUp'];
        if (navigation.includes(event.key)) {
            event.preventDefault();
            const wasClosed = list.hidden;
            if (wasClosed) open();
            const indices = enabled();
            let position = indices.indexOf(activeIndex);
            if (event.key === 'Home') position = 0;
            else if (event.key === 'End') position = indices.length - 1;
            else if (event.altKey && event.key === 'ArrowUp') { commit(); close(); return; }
            else if (!wasClosed) position += event.key === 'ArrowDown' ? 1 : event.key === 'ArrowUp' ? -1 : event.key === 'PageDown' ? 10 : -10;
            activate(indices[Math.max(0, Math.min(indices.length - 1, position))] ?? -1);
            return;
        }
        if (event.key.length === 1 && !event.altKey) {
            event.preventDefault();
            if (list.hidden) open();
            const now = performance.now();
            typed = now - typedAt > 700 ? event.key : typed + event.key;
            typedAt = now;
            const query = [...typed].every(letter => letter.toLocaleLowerCase() === typed[0].toLocaleLowerCase()) ? typed[0] : typed;
            const indices = enabled();
            const start = query.length === 1 ? indices.indexOf(activeIndex) + 1 : 0;
            const ordered = [...indices.slice(start), ...indices.slice(0, start)];
            const match = ordered.find(index => select.options[index].textContent.trim().toLocaleLowerCase().startsWith(query.toLocaleLowerCase()));
            if (match !== undefined) activate(match);
        }
    });
    list.addEventListener('pointerdown', (event) => {
        if (event.pointerType === 'mouse') event.preventDefault();
    });
    document.addEventListener('pointerdown', (event) => {
        if (!list.hidden && !wrapper.contains(event.target) && !list.contains(event.target)) close();
    });
    document.addEventListener('focusin', (event) => {
        if (!list.hidden && !wrapper.contains(event.target) && !list.contains(event.target)) { commit(); close(); }
    });
    document.addEventListener('scroll', (event) => {
        if (!list.hidden && !list.contains(event.target)) close();
    }, true);
    window.addEventListener('resize', close);
    window.visualViewport?.addEventListener('resize', close);
    document.addEventListener('click', (event) => {
        const link = event.target.closest('a[href]');
        if (select.id && link?.getAttribute('href') === `#${select.id}`) {
            event.preventDefault();
            trigger.focus();
        }
    });
    const observer = new MutationObserver(() => { sync(); if (trigger.disabled) close(); });
    observer.observe(select, { attributes: true, subtree: true, attributeFilter: ['disabled', 'required', 'aria-invalid', 'aria-describedby', 'selected', 'hidden'] });
    select.addEventListener('change', sync);
    select.addEventListener('growvia:select-sync', sync);
    select.form?.addEventListener('reset', () => setTimeout(() => { close(); sync(); }, 0));
    window.addEventListener('pageshow', () => { close(); sync(); });
    select.before(wrapper);
    wrapper.append(select, trigger);
    document.body.append(list);
    sync();
    select.hidden = true;
}
