(() => {
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // Spotlight that follows the cursor across cards
    document.querySelectorAll('.card, .podium__card').forEach((el) => {
        el.addEventListener('pointermove', (event) => {
            const rect = el.getBoundingClientRect();
            el.style.setProperty('--mx', `${event.clientX - rect.left}px`);
            el.style.setProperty('--my', `${event.clientY - rect.top}px`);
        });
    });

    const forms = Array.from(document.querySelectorAll('.contender'));
    if (forms.length !== 2) return;

    let locked = false;

    const reset = () => {
        locked = false;
        document.body.classList.remove('is-voting');
        forms.forEach((f) => f.classList.remove('is-chosen', 'is-defeated'));
    };

    // Play a short "chosen" animation, then submit the vote
    forms.forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (locked) {
                event.preventDefault();
                return;
            }
            locked = true;
            if (reduceMotion) return;

            event.preventDefault();
            document.body.classList.add('is-voting');
            form.classList.add('is-chosen');
            forms.filter((f) => f !== form).forEach((f) => f.classList.add('is-defeated'));
            window.setTimeout(() => form.submit(), 450);
        });
    });

    // Restored from the back/forward cache: make the page usable again
    window.addEventListener('pageshow', (event) => {
        if (event.persisted) reset();
    });

    document.addEventListener('keydown', (event) => {
        if (locked || event.metaKey || event.ctrlKey || event.altKey) return;
        if (event.target.closest('input, textarea, select, [contenteditable]')) return;

        const key = event.key.toLowerCase();
        if (key === 'arrowleft' || key === '1') {
            event.preventDefault();
            forms[0].requestSubmit();
        } else if (key === 'arrowright' || key === '2') {
            event.preventDefault();
            forms[1].requestSubmit();
        } else if (key === 'r') {
            locked = true;
            window.location.href = 'index.php';
        }
    });
})();
