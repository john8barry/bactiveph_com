// Custom JS for B Active
console.log('B Active custom scripts loaded.');

document.addEventListener('DOMContentLoaded', function() {
    // Phase 4: Size Guide Modal
    const modal = document.getElementById('bactive-size-modal');
    const openBtn = document.querySelector('.bactive-size-guide-link');
    const closeBtn = document.querySelector('.bactive-modal-close');

    if (modal && openBtn) {
        openBtn.addEventListener('click', function(e) {
            e.preventDefault();
            modal.showModal();
        });

        if (closeBtn) {
            closeBtn.addEventListener('click', function() {
                modal.close();
            });
        }

        // Close on backdrop click
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                modal.close();
            }
        });
    }

    // Phase 4: Sticky Add-to-Cart
    const stickyCart = document.getElementById('bactive-sticky-cart');
    const mainCartBtn = document.querySelector('.single_add_to_cart_button');
    const stickyCartBtn = document.getElementById('sticky-cart-button');
    
    if (stickyCart && mainCartBtn) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    stickyCart.classList.remove('visible');
                } else {
                    stickyCart.classList.add('visible');
                }
            });
        }, {
            threshold: 0,
            rootMargin: "0px 0px -100px 0px"
        });

        observer.observe(mainCartBtn);
        
        // Scroll back to main button when sticky button is clicked
        if (stickyCartBtn) {
            stickyCartBtn.addEventListener('click', function(e) {
                e.preventDefault();
                mainCartBtn.scrollIntoView({ behavior: 'smooth', block: 'center' });
                // If it's a simple product, we could also just trigger a click on mainCartBtn
                // but scrolling is safer if there are variations to select.
            });
        }
    }
});
