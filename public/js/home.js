// SalesDock landing page JS

// Mobile menu
var navToggle = document.getElementById('nav-toggle');
var mobileMenu = document.getElementById('mobile-menu');
if (navToggle) {
    navToggle.addEventListener('click', function() {
        if (mobileMenu) mobileMenu.classList.toggle('open');
    });
}
if (mobileMenu) {
    mobileMenu.querySelectorAll('a').forEach(function(link) {
        link.addEventListener('click', function() {
            mobileMenu.classList.remove('open');
        });
    });
}

// FAQ accordion with smooth max-height transition
document.querySelectorAll('.faq-q').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var item = btn.closest('.faq-item');
        var isOpen = item.classList.contains('open');
        document.querySelectorAll('.faq-item').forEach(function(faqItem) {
            faqItem.classList.remove('open');
            faqItem.querySelector('.faq-q').setAttribute('aria-expanded', 'false');
        });
        if (!isOpen) {
            item.classList.add('open');
            btn.setAttribute('aria-expanded', 'true');
        }
    });
});

// Scroll reveal via IntersectionObserver
if ('IntersectionObserver' in window) {
    var revealObserver = new IntersectionObserver(function(entries) {
        entries.forEach(function(entry) {
            if (entry.isIntersecting) {
                entry.target.classList.add('in');
                revealObserver.unobserve(entry.target);
            }
        });
    }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });

    document.querySelectorAll('.reveal').forEach(function(el) {
        if (!el.classList.contains('in')) {
            revealObserver.observe(el);
        }
    });
}
