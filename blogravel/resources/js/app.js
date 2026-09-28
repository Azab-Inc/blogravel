const dismissToast = (toast, toastRegion) => {
    const focusIsInsideToast = toast.contains(document.activeElement);

    toast.hidden = true;
    toast.setAttribute('aria-hidden', 'true');

    if (focusIsInsideToast) {
        toastRegion?.focus();
    }
};

const getFieldErrorMessage = (field) => {
    const label = document.querySelector(`label[for="${field.id}"]`);
    const fieldName = label?.textContent?.trim() || field.name;

    if (field.type === 'email' && field.value.trim() !== '') {
        return `${fieldName} must be a valid email address.`;
    }

    return `${fieldName} is required.`;
};

const getClientError = (field) => field.parentElement?.querySelector(`[data-client-error="${field.name}"]`);

const setFieldError = (field, message) => {
    let error = getClientError(field);

    if (!error) {
        error = document.createElement('p');
        error.id = field.parentElement?.querySelector('.form-error:not([data-client-error])')
            ? `${field.id}-client-error`
            : `${field.id}-error`;
        error.className = 'form-error';
        error.setAttribute('role', 'alert');
        error.dataset.clientError = field.name;
        field.parentElement?.append(error);
    }

    error.textContent = message;
    error.hidden = false;
    field.setAttribute('aria-invalid', 'true');

    const describedBy = new Set((field.getAttribute('aria-describedby') || '').split(' ').filter(Boolean));
    describedBy.add(error.id);
    field.setAttribute('aria-describedby', [...describedBy].join(' '));
};

const clearFieldError = (field) => {
    const error = getClientError(field);

    if (error) {
        error.hidden = true;
        field.removeAttribute('aria-invalid');

        const describedBy = (field.getAttribute('aria-describedby') || '')
            .split(' ')
            .filter((id) => id && id !== error.id);

        if (describedBy.length > 0) {
            field.setAttribute('aria-describedby', describedBy.join(' '));
            if (field.parentElement?.querySelector('.form-error:not([data-client-error])')) {
                field.setAttribute('aria-invalid', 'true');
            }
        } else {
            field.removeAttribute('aria-describedby');
        }
    }
};

const validateField = (field) => {
    const isEmpty = field.required && field.value.trim() === '';
    const hasInvalidEmail = field.type === 'email' && field.value.trim() !== '' && !field.checkValidity();

    if (isEmpty || hasInvalidEmail) {
        setFieldError(field, getFieldErrorMessage(field));
        return false;
    }

    clearFieldError(field);
    return true;
};

const setupFormValidation = (form) => {
    const fields = [...form.querySelectorAll('input, textarea, select')]
        .filter((field) => field.required || field.type === 'email');

    fields.forEach((field) => field.addEventListener('blur', () => validateField(field)));
    form.addEventListener('submit', (event) => {
        const isValid = fields.map(validateField).every(Boolean);

        if (!isValid) {
            event.preventDefault();
        }
    });
};

document.addEventListener('DOMContentLoaded', () => {
    document.body.classList.add('has-js');

    document.querySelectorAll('form').forEach(setupFormValidation);

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
