(() => {
    const dialog = document.getElementById('refundDialog');
    const openButtons = document.querySelectorAll('[data-open-refund]');
    const closeButtons = document.querySelectorAll('[data-close-refund]');
    const form = document.getElementById('refundForm');
    const orderId = document.getElementById('refundOrderId');
    const receiptNo = document.getElementById('refundReceiptNo');
    const dialogTitle = document.getElementById('refundDialogTitle');
    const dialogTotal = document.getElementById('refundDialogTotal');

    if (!dialog || openButtons.length === 0) {
        return;
    }

    openButtons.forEach((button) => {
        button.addEventListener('click', () => {
            orderId.value = button.dataset.orderId;
            receiptNo.value = button.dataset.receiptNo;
            dialogTitle.textContent = `Refund ${button.dataset.receiptNo}`;
            dialogTotal.textContent = button.dataset.refundTotal;
            dialog.showModal();
            dialog.querySelector('[name="reason_note"]').focus();
        });
    });

    closeButtons.forEach((button) => {
        button.addEventListener('click', () => dialog.close());
    });

    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) {
            dialog.close();
        }
    });

    if (form) {
        form.addEventListener('submit', (event) => {
            const submitButton = event.submitter;

            if (!submitButton || !window.confirm(
                'Authorize this full refund? This action is recorded and cannot be undone.'
            )) {
                event.preventDefault();
                return;
            }

            submitButton.disabled = true;
            submitButton.textContent = 'Authorizing refund…';
        });
    }
})();
