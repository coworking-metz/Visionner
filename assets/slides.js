document.addEventListener('DOMContentLoaded', () => {

    const slides = Array.from(document.querySelectorAll('.slide'));
    if (!slides.length) return;

    const container = document.getElementById('screen');

    // ---- Progress bar DOM --------------------------------------------------
    const progressRoot = document.createElement('div');
    progressRoot.id = 'slide-progress';

    progressRoot.innerHTML = `
        <div class="slide-progress-counter"></div>
        <div class="slide-progress-bar">
            <span class="slide-progress-fill"></span>
        </div>
    `;

    container.appendChild(progressRoot);

    const progressFill = progressRoot.querySelector('.slide-progress-fill');
    const progressCounter = progressRoot.querySelector('.slide-progress-counter');
    // ------------------------------------------------------------------------

    let index = 0;
    let timer = null;
    let rafId = null;
    let slideDuration = 0;
    let startTime = 0;

    function stopTimers() {
        if (timer) clearTimeout(timer);
        timer = null;

        if (rafId) cancelAnimationFrame(rafId);
        rafId = null;
    }

    function startProgressAnimation() {
        progressFill.style.width = '0%';

        function step(ts) {
            if (!startTime) startTime = ts;
            const elapsed = ts - startTime;
            const ratio = Math.min(elapsed / slideDuration, 1);
            progressFill.style.width = `${ratio * 100}%`;

            if (ratio < 1) {
                rafId = requestAnimationFrame(step);
                return;
            }

            rafId = null;
        }

        startTime = 0;
        rafId = requestAnimationFrame(step);
    }

	function renderSlide(i) {

		stopTimers();

		const slide = slides[i];
		document.querySelectorAll('.slide.selected').forEach(item => {
			item.classList.remove('selected');
		});

		slide.classList.add('selected');

		slideDuration = parseInt(slide.dataset.duration || 10, 10) * 1000;

		progressCounter.textContent = `${i + 1} / ${slides.length}`;
		startProgressAnimation();

		// ---- PRELOAD NEXT IFRAME 3 s BEFORE SWITCH --------------------
		const nextIndex = (i + 1) % slides.length;
		const preloadDelay = Math.max(slideDuration - 3000, 0);

		timer = setTimeout(() => {
			const nextSlide = slides[nextIndex];
			const iframe = nextSlide.querySelector('iframe');
			if (iframe) {
				console.log('preloading iframe', iframe);
				iframe.src = iframe.src;             // reload only now
			}
		}, preloadDelay);
		// ----------------------------------------------------------------

		timer = setTimeout(() => {
			index = nextIndex;
			renderSlide(index);
		}, slideDuration);
	}
    
    function next() {
        stopTimers();
        index = (index + 1) % slides.length;
        renderSlide(index);
    }

    document.addEventListener('next-slide', () => next());

    function prev() {
        stopTimers();
        index = (index - 1 + slides.length) % slides.length;
        renderSlide(index);
    }

    document.addEventListener('keyup', e => {
        if (e.code === 'ArrowRight') next();
        if (e.code === 'ArrowLeft') prev();
    });

    renderSlide(index);
});
