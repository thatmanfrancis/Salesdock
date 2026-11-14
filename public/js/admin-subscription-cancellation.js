(() => {
    document.querySelectorAll('[data-subscription-cancellation]').forEach((container) => {
        const credentials = container.querySelector('[data-cancel-credentials]');
        const review = container.querySelector('[data-cancel-review]');
        const form = container.querySelector('[data-cancel-form]');
        const phrase = form.elements.confirmation;
        const finalConfirmation = form.elements.final_confirmation;
        const confirmButton = container.querySelector('[data-cancel-confirm]');
        let stage = 'idle';
        const reset = () => {
            form.reset();
            phrase.setCustomValidity('');
            finalConfirmation.value = '0';
            confirmButton.disabled = false;
            confirmButton.textContent = 'Confirm cancellation';
            stage = 'idle';
        };
        container.querySelector('[data-cancel-open]').addEventListener('click', () => {
            reset();
            stage = 'credentials';
            credentials.showModal();
        });
        phrase.addEventListener('input', () => phrase.setCustomValidity(''));
        form.addEventListener('submit', (event) => {
            if (stage === 'submitting' && finalConfirmation.value === '1') return;
            event.preventDefault();
            event.stopImmediatePropagation();
            phrase.setCustomValidity(phrase.value.trim() === phrase.dataset.expectedConfirmation
                ? '' : 'Type DELETE followed by the exact merchant name.');
            if (!form.reportValidity()) return;
            stage = 'review';
            credentials.close();
            review.showModal();
        }, true);
        container.querySelector('[data-cancel-back]').addEventListener('click', () => {
            stage = 'credentials';
            review.close();
            credentials.showModal();
        });
        container.querySelectorAll('[data-cancel-dismiss]').forEach((button) => {
            button.addEventListener('click', () => {
                stage = 'idle';
                if (credentials.open) credentials.close();
                if (review.open) review.close();
                reset();
            });
        });
        [credentials, review].forEach((dialog) => {
            dialog.addEventListener('cancel', () => reset());
        });
        confirmButton.addEventListener('click', () => {
            if (stage !== 'review') return;
            stage = 'submitting';
            finalConfirmation.value = '1';
            confirmButton.disabled = true;
            confirmButton.textContent = 'Cancelling…';
            form.requestSubmit();
        });
    });
    const result = document.querySelector('[data-subscription-cancelled]');
    if (result) {
        result.querySelector('[data-cancel-result-close]').addEventListener('click', () => result.close());
        result.showModal();
    }
})();
