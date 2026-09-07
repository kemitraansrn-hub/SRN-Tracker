{{--
    Scroll-reveal ringan: card/section dengan class "reveal-on-scroll" fade-in
    + naik sedikit begitu masuk viewport (IntersectionObserver, one-time,
    unobserve setelah muncul). Hormatin prefers-reduced-motion.
    Include sekali di akhir @section('content') tiap halaman yang mau pakai.
--}}
<style>
    .reveal-on-scroll {
        opacity: 0; transform: translateY(18px);
        transition: opacity 0.5s ease, transform 0.5s ease;
    }
    .reveal-on-scroll.is-visible { opacity: 1; transform: translateY(0); }
    @media (prefers-reduced-motion: reduce) {
        .reveal-on-scroll { opacity: 1; transform: none; transition: none; }
    }
</style>
<script>
    (() => {
        const targets = document.querySelectorAll('.reveal-on-scroll:not(.reveal-observed)');
        if (!targets.length) return;
        targets.forEach((el) => el.classList.add('reveal-observed'));

        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            targets.forEach((el) => el.classList.add('is-visible'));
            return;
        }

        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            });
        }, { threshold: 0.15, rootMargin: '0px 0px -40px 0px' });

        targets.forEach((el) => observer.observe(el));
    })();
</script>
