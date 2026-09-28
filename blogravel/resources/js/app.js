const dismissToast = (toast, toastRegion) => {
    const focusIsInsideToast = toast.contains(document.activeElement);

    toast.hidden = true;
    toast.setAttribute('aria-hidden', 'true');

    if (focusIsInsideToast) {
        toastRegion?.focus();
    }
};

document.addEventListener('DOMContentLoaded', () => {
    document.body.classList.add('has-js');

    const toastRegion = document.querySelector('#toast-region');

    document.querySelectorAll('[data-toast-template]').forEach((template) => {
        const toast = document.createElement('div');
        toast.className = 'toast';
        toast.setAttribute('role', 'status');
        toast.setAttribute('aria-live', 'polite');
        toast.setAttribute('aria-atomic', 'true');
        toast.append(template.content.cloneNode(true));
        toastRegion?.append(toast);

        const dismissButton = toast.querySelector('[data-toast-dismiss]');

        dismissButton?.focus();
        dismissButton?.addEventListener('click', () => dismissToast(toast, toastRegion));
        window.setTimeout(() => dismissToast(toast, toastRegion), 6000);
    });
});
