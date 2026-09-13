import { onBeforeUnmount, onMounted } from 'vue';

// Native disclosure keeps these links usable without a menu widget. Only
// action menus participate; settings accordions retain their expanded state.
export function useActionMenus() {
    const menus = () => [...document.querySelectorAll('details.cd-action-menu')];
    const closeOutside = event => menus().forEach(menu => {
        if (!menu.contains(event.target)) menu.open = false;
        else if (event.target.closest('a, button')) menu.open = false;
    });
    const keyboard = event => {
        const menu = event.target.closest('details.cd-action-menu');
        if (!menu) return;
        const summary = menu.querySelector('summary');
        const items = [...menu.querySelectorAll('a[href], button:not([disabled])')].filter(item => item.getClientRects().length);
        if (event.key === 'Escape' && menu.open) {
            event.preventDefault(); menu.open = false; summary.focus();
        } else if (['ArrowDown', 'ArrowUp'].includes(event.key)) {
            event.preventDefault(); menu.open = true;
            const available = [...menu.querySelectorAll('a[href], button:not([disabled])')].filter(item => item.getClientRects().length);
            const current = available.indexOf(document.activeElement);
            const next = event.key === 'ArrowDown' ? (current + 1) % available.length : (current <= 0 ? available.length - 1 : current - 1);
            available[next]?.focus();
        } else if (event.key === 'Tab' && menu.open) {
            if ((!event.shiftKey && document.activeElement === items.at(-1)) || (event.shiftKey && document.activeElement === summary)) {
                setTimeout(() => { menu.open = false; }, 0);
            }
        }
    };
    onMounted(() => { document.addEventListener('click', closeOutside); document.addEventListener('keydown', keyboard); });
    onBeforeUnmount(() => { document.removeEventListener('click', closeOutside); document.removeEventListener('keydown', keyboard); });
}
