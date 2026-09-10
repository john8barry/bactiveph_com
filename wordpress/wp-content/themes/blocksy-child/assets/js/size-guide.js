/* B Active product size guide */
document.addEventListener('DOMContentLoaded', function() {
    var modal = document.getElementById('bactive-size-modal');

    if (!modal) {
        return;
    }

    var lastTrigger = null;
    var closeButton = modal.querySelector('.bactive-modal-close');

    document.querySelectorAll('.bactive-size-guide-link').forEach(function(trigger) {
        trigger.addEventListener('click', function(event) {
            // Keep the real Size Guide URL as a no-JavaScript/legacy-browser fallback.
            if (typeof modal.showModal !== 'function') {
                return;
            }

            event.preventDefault();
            lastTrigger = trigger;

            if (!modal.open) {
                modal.showModal();
            }
        });
    });

    if (closeButton) {
        closeButton.addEventListener('click', function() {
            modal.close();
        });
    }

    modal.addEventListener('click', function(event) {
        if (event.target === modal) {
            modal.close();
        }
    });

    modal.addEventListener('close', function() {
        if (lastTrigger) {
            lastTrigger.focus();
        }
    });
});
