@once
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
            const short = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

            const pad = (n) => String(n).padStart(2, '0');
            const iso = (date) => date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate());
            const parse = (value) => {
                const parts = String(value || '').split('-').map(Number);
                if (parts.length === 3 && parts.every((part) => part > 0)) return new Date(parts[0], parts[1] - 1, parts[2]);
                const today = new Date();
                return new Date(today.getFullYear(), today.getMonth(), today.getDate());
            };
            const pretty = (date) => date.getDate() + ' ' + short[date.getMonth()] + ' ' + date.getFullYear();

            const closeMenus = (except) => {
                document.querySelectorAll('[data-select-menu], [data-calendar]').forEach((menu) => {
                    if (menu === except) return;
                    menu.hidden = true;
                    menu.closest('.ui-field')?.querySelector('[aria-expanded]')?.setAttribute('aria-expanded', 'false');
                });
            };

            document.querySelectorAll('[data-select]').forEach((field) => {
                const open = field.querySelector('[data-select-open]');
                const menu = field.querySelector('[data-select-menu]');
                const input = field.querySelector('[data-select-value]');
                const label = field.querySelector('[data-select-label]');
                if (!open || !menu || !input || !label) return;
                const search = menu.querySelector('[data-select-search]');
                const optionsList = menu.querySelector('.ui-select-options');
                const filterOptions = () => {
                    const query = search.value.trim().toLocaleLowerCase();
                    let matches = 0;
                    menu.querySelectorAll('[role="option"]').forEach((option) => {
                        const matchesQuery = option.textContent.toLocaleLowerCase().includes(query);
                        option.closest('li').hidden = !matchesQuery;
                        if (matchesQuery) matches++;
                    });
                    menu.querySelector('[data-select-empty]').hidden = matches > 0;
                    optionsList.scrollTop = 0;
                };
                search?.addEventListener('input', filterOptions);
                if (search) {
                    menu.addEventListener('keydown', (event) => {
                        if (event.key === 'Escape') {
                            menu.hidden = true;
                            open.setAttribute('aria-expanded', 'false');
                            open.focus();
                            return;
                        }
                        if (event.target === search && event.key === 'Enter') {
                            event.preventDefault();
                        }
                        if (event.key !== 'ArrowDown' && event.key !== 'ArrowUp') return;
                        event.preventDefault();
                        const visible = [...menu.querySelectorAll('[role="option"]')].filter((option) => !option.closest('li').hidden);
                        const index = visible.indexOf(document.activeElement);
                        const next = index < 0
                            ? (event.key === 'ArrowDown' ? 0 : visible.length - 1)
                            : (index + (event.key === 'ArrowDown' ? 1 : -1) + visible.length) % visible.length;
                        visible[next]?.focus();
                    });
                }
                const sync = () => {
                    let chosen = null;
                    menu.querySelectorAll('[role="option"]').forEach((option) => {
                        const on = option.dataset.value === input.value;
                        option.setAttribute('aria-selected', on ? 'true' : 'false');
                        if (on) chosen = option;
                    });
                    label.textContent = chosen ? chosen.textContent.trim() : 'Choose';
                };
                open.addEventListener('click', () => {
                    const show = menu.hidden;
                    closeMenus(show ? menu : null);
                    menu.hidden = !show;
                    open.setAttribute('aria-expanded', show ? 'true' : 'false');
                    if (show && search) {
                        search.value = '';
                        filterOptions();
                        search.focus({ preventScroll: true });
                    }
                });
                menu.addEventListener('click', (event) => {
                    const option = event.target.closest('[data-value]');
                    if (!option) return;
                    input.value = option.dataset.value;
                    sync();
                    menu.hidden = true;
                    open.setAttribute('aria-expanded', 'false');
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                    if (search) open.focus({ preventScroll: true });
                });
                input.addEventListener('change', sync);
            });

            document.querySelectorAll('[data-date]').forEach((field) => {
                const open = field.querySelector('[data-date-open]');
                const calendar = field.querySelector('[data-calendar]');
                const input = field.querySelector('[data-date-value]');
                const label = field.querySelector('[data-date-label]');
                const grid = field.querySelector('[data-cal-grid]');
                const title = field.querySelector('[data-cal-title]');
                if (!open || !calendar || !input || !label || !grid || !title) return;
                const dated = /^\d{4}-\d{2}-\d{2}$/.test(input.value);
                let selected = parse(input.value);
                let picked = dated;
                let view = new Date(selected.getFullYear(), selected.getMonth(), 1);
                if (dated) {
                    input.value = iso(selected);
                    label.textContent = pretty(selected);
                } else {
                    input.value = '';
                    label.textContent = label.dataset.empty || 'Any date';
                }

                const draw = () => {
                    title.textContent = months[view.getMonth()] + ' ' + view.getFullYear();
                    grid.replaceChildren();
                    const start = new Date(view.getFullYear(), view.getMonth(), 1);
                    const lead = (start.getDay() + 6) % 7;
                    const days = new Date(view.getFullYear(), view.getMonth() + 1, 0).getDate();
                    const today = iso(new Date());
                    for (let i = 0; i < lead; i += 1) grid.append(document.createElement('span'));
                    for (let day = 1; day <= days; day += 1) {
                        const date = new Date(view.getFullYear(), view.getMonth(), day);
                        const button = document.createElement('button');
                        button.type = 'button';
                        button.textContent = String(day);
                        if (picked && iso(date) === iso(selected)) button.setAttribute('aria-pressed', 'true');
                        if (iso(date) === today) button.classList.add('today');
                        button.addEventListener('click', () => {
                            selected = date;
                            picked = true;
                            input.value = iso(selected);
                            label.textContent = pretty(selected);
                            calendar.hidden = true;
                            open.setAttribute('aria-expanded', 'false');
                            input.dispatchEvent(new Event('change', { bubbles: true }));
                        });
                        grid.append(button);
                    }
                };

                open.addEventListener('click', () => {
                    const show = calendar.hidden;
                    closeMenus(show ? calendar : null);
                    view = new Date(selected.getFullYear(), selected.getMonth(), 1);
                    draw();
                    calendar.hidden = !show;
                    open.setAttribute('aria-expanded', show ? 'true' : 'false');
                });
                field.querySelector('[data-cal-prev]').addEventListener('click', () => {
                    view = new Date(view.getFullYear(), view.getMonth() - 1, 1);
                    draw();
                });
                field.querySelector('[data-cal-next]').addEventListener('click', () => {
                    view = new Date(view.getFullYear(), view.getMonth() + 1, 1);
                    draw();
                });
                input.addEventListener('change', () => {
                    if (!/^\d{4}-\d{2}-\d{2}$/.test(input.value)) {
                        picked = false;
                        label.textContent = label.dataset.empty || 'Any date';
                        return;
                    }
                    selected = parse(input.value);
                    picked = true;
                    view = new Date(selected.getFullYear(), selected.getMonth(), 1);
                    label.textContent = pretty(selected);
                });
            });

            document.querySelectorAll('[data-filter]').forEach((filter) => {
                const opener = filter.querySelector('[data-filter-open]');
                const panel = filter.querySelector('[data-filter-panel]');
                const form = filter.querySelector('form');
                if (!opener || !panel || !form) return;
                const setOpen = (open) => {
                    panel.hidden = !open;
                    opener.setAttribute('aria-expanded', open ? 'true' : 'false');
                    if (!open) closeMenus(null);
                };
                opener.addEventListener('click', () => setOpen(panel.hidden));
                filter.querySelectorAll('[data-filter-close]').forEach((button) => {
                    button.addEventListener('click', () => setOpen(false));
                });
                form.addEventListener('change', (event) => {
                    if (event.target.name !== 'from' && event.target.name !== 'to') return;
                    const period = form.querySelector('[name="period"]');
                    if (!period || period.value === 'custom') return;
                    period.value = 'custom';
                    period.dispatchEvent(new Event('change', { bubbles: true }));
                });
            });

            document.addEventListener('click', (event) => {
                if (!event.target.closest('[data-select], [data-date]')) closeMenus(null);
            });
            document.addEventListener('keydown', (event) => {
                if (event.key !== 'Escape') return;
                closeMenus(null);
                document.querySelectorAll('[data-filter-panel]').forEach((panel) => {
                    panel.hidden = true;
                    panel.closest('[data-filter]')?.querySelector('[data-filter-open]')?.setAttribute('aria-expanded', 'false');
                });
            });
        });
    </script>
@endonce
