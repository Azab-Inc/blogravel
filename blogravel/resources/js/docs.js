const copyText = async (text) => {
    if (navigator.clipboard?.writeText) {
        await navigator.clipboard.writeText(text);
        return;
    }

    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.setAttribute('readonly', '');
    textarea.className = 'docs-copy-textarea';
    document.body.append(textarea);
    textarea.select();

    if (!document.execCommand('copy')) {
        textarea.remove();
        throw new Error('Copy failed');
    }

    textarea.remove();
};

const setupCodeCopyButtons = () => {
    document.querySelectorAll('pre > code').forEach((code) => {
        const pre = code.parentElement;
        if (!pre || pre.querySelector('[data-copy-code]')) {
            return;
        }

        pre.classList.add('docs-code-block');
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'code-copy-button';
        button.dataset.copyCode = 'true';
        button.setAttribute('aria-label', 'Copy code');
        button.textContent = 'Copy';
        pre.prepend(button);

        button.addEventListener('click', async () => {
            try {
                await copyText(code.textContent);
                button.textContent = 'Copied';
                button.setAttribute('aria-label', 'Code copied');
            } catch {
                button.textContent = 'Copy failed';
                button.setAttribute('aria-label', 'Copy failed');
            }

            window.setTimeout(() => {
                button.textContent = 'Copy';
                button.setAttribute('aria-label', 'Copy code');
            }, 1800);
        });
    });
};

document.addEventListener('DOMContentLoaded', () => {
    setupCodeCopyButtons();

    new MutationObserver(setupCodeCopyButtons).observe(document.body, {
        childList: true,
        subtree: true,
    });
});
