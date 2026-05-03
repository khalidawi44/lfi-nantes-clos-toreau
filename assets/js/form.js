(function() {
    const form = document.getElementById('lfi-nct-form');
    if (!form) return;

    const steps = form.querySelectorAll('.lfi-step');
    const prevBtn = form.querySelector('.lfi-prev');
    const nextBtn = form.querySelector('.lfi-next');
    const submitBtn = form.querySelector('.lfi-submit');
    const progressBar = form.querySelector('.lfi-progress-bar');
    let current = 0;

    function showStep(idx) {
        steps.forEach((s, i) => s.classList.toggle('active', i === idx));
        prevBtn.disabled = idx === 0;
        const last = idx === steps.length - 1;
        nextBtn.style.display = last ? 'none' : '';
        submitBtn.style.display = last ? '' : 'none';
        progressBar.style.width = ((idx + 1) / steps.length * 100) + '%';
        window.scrollTo({ top: form.offsetTop - 20, behavior: 'smooth' });
    }

    function validateStep(stepEl) {
        const required = stepEl.querySelectorAll('[required]');
        for (const el of required) {
            if (!el.value || (el.type === 'number' && (el.value < (el.min || 0) || el.value > (el.max || Infinity)))) {
                el.focus();
                el.style.borderColor = '#c8102e';
                alert('Champ obligatoire ou valeur invalide : ' + (el.previousElementSibling?.textContent?.trim() || el.name));
                return false;
            }
        }
        return true;
    }

    nextBtn?.addEventListener('click', () => {
        if (!validateStep(steps[current])) return;
        if (current < steps.length - 1) { current++; showStep(current); }
    });
    prevBtn?.addEventListener('click', () => {
        if (current > 0) { current--; showStep(current); }
    });

    function applyConditionals() {
        document.querySelectorAll('[data-show-if]').forEach(el => {
            const [field, value] = el.dataset.showIf.split(':');
            const input = form.querySelector(`[name="${field}"]:checked`);
            const matches = input && (value.split('|').includes(input.value));
            el.style.display = matches ? '' : 'none';
        });
    }
    form.addEventListener('change', applyConditionals);
    applyConditionals();

    showStep(0);
})();