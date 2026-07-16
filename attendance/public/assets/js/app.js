document.addEventListener('click', (event) => {
    const target = event.target.closest('[data-confirm]');
    if (target && !window.confirm(target.dataset.confirm)) {
        event.preventDefault();
    }
});

setTimeout(() => {
    document.querySelectorAll('.toast-feedback').forEach((item) => item.remove());
}, 4500);
