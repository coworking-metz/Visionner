document.addEventListener('DOMContentLoaded', () => {

    const slides = Array.from(document.querySelectorAll('.slide'));
    if (!slides.length) return;

    const container = document.getElementById('screen');

    // ---- Iframe load handling (loading spinner + retry on error) -----------
    const IFRAME_LOAD_TIMEOUT = 10000; // ms before considering a load stuck
    const IFRAME_RETRY_DELAY  = 5000;  // ms between failed attempts

    const iframeStates = new WeakMap();

    function setOverlay(state, mode) {
        const msg = state.overlay.querySelector('.iframe-overlay-message');
        if (mode === 'hidden') {
            state.overlay.classList.add('hidden');
            return;
        }
        state.overlay.classList.remove('hidden');
        msg.textContent = mode === 'error'
            ? 'Chargement en cours…'
            : 'Chargement en cours';
    }

    async function probeUrl(url) {
        // Server-side HEAD via probe.php — bypasses browser CORS so we can
        // read the real HTTP status. Returns 'ok' for 2xx/3xx, 'bad' for
        // 4xx/5xx or unreachable, 'unknown' if the probe itself fails.
        try {
            const res = await fetch('probe.php?url=' + encodeURIComponent(url), {
                cache: 'no-store',
            });
            if (!res.ok) return 'unknown';
            const data = await res.json();
            console.log('[probe]', url, data);
            if (data.status === 0) return 'bad';
            return data.ok ? 'ok' : 'bad';
        } catch (err) {
            console.warn('[probe] failed', url, err);
            return 'unknown';
        }
    }

    function clearIframeTimers(state) {
        if (state.loadTimer) {
            clearTimeout(state.loadTimer);
            state.loadTimer = null;
        }
        if (state.retryTimer) {
            clearTimeout(state.retryTimer);
            state.retryTimer = null;
        }
        if (state.loadHandler) {
            state.iframe.removeEventListener('load', state.loadHandler);
            state.loadHandler = null;
        }
        if (state.errorHandler) {
            state.iframe.removeEventListener('error', state.errorHandler);
            state.errorHandler = null;
        }
        state.loadToken = (state.loadToken || 0) + 1;
    }

    function failIframe(state) {
        clearIframeTimers(state);
        setOverlay(state, 'error');
        state.retryTimer = setTimeout(() => loadIframe(state), IFRAME_RETRY_DELAY);
    }

    function loadIframe(state) {
        clearIframeTimers(state);
        setOverlay(state, 'loading');

        // Token captures this attempt; if a new attempt starts (or we retry)
        // before the async probe resolves, its result must be ignored.
        const token = state.loadToken;

        const onLoad = async () => {
            console.log('[iframe] load', state.url);
            state.iframe.removeEventListener('load', onLoad);
            state.iframe.removeEventListener('error', onError);
            state.loadHandler = null;
            state.errorHandler = null;
            if (state.loadTimer) {
                clearTimeout(state.loadTimer);
                state.loadTimer = null;
            }

            const probe = await probeUrl(state.url);
            if (token !== state.loadToken) return; // superseded
            if (probe === 'bad') {
                failIframe(state);
            } else {
                setOverlay(state, 'hidden');
            }
        };
        const onError = () => {
            console.warn('[iframe] error', state.url);
            failIframe(state);
        };

        state.loadHandler = onLoad;
        state.errorHandler = onError;
        state.iframe.addEventListener('load', onLoad);
        state.iframe.addEventListener('error', onError);

        state.loadTimer = setTimeout(() => {
            console.warn('[iframe] load timeout', state.url);
            failIframe(state);
        }, IFRAME_LOAD_TIMEOUT);

        // Forcing about:blank first guarantees the load event fires even when
        // the URL is identical to the previously loaded one.
        state.iframe.src = 'about:blank';
        state.iframe.src = state.url;
    }

    function setupIframeLoader(slide) {
        const iframe = slide.querySelector('iframe');
        if (!iframe) return;

        const url = iframe.getAttribute('src');
        if (!url) return;
        iframe.removeAttribute('src');

        const overlay = document.createElement('div');
        overlay.className = 'iframe-overlay';
        overlay.innerHTML = `
            <div class="iframe-overlay-spinner" aria-hidden="true"></div>
            <div class="iframe-overlay-message">Chargement en cours…</div>
        `;
        slide.appendChild(overlay);

        const state = {
            slide, iframe, overlay, url,
            loadTimer: null, retryTimer: null, loadHandler: null,
        };
        iframeStates.set(slide, state);
        loadIframe(state);
    }

    slides.forEach(setupIframeLoader);
    // ------------------------------------------------------------------------

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
    let preloadTimer = null;
    let rafId = null;
    let slideDuration = 0;
    let startTime = 0;

    function stopTimers() {
        if (timer) clearTimeout(timer);
        timer = null;

        if (preloadTimer) clearTimeout(preloadTimer);
        preloadTimer = null;

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

		preloadTimer = setTimeout(() => {
			const nextSlide = slides[nextIndex];
			const state = iframeStates.get(nextSlide);
			if (state) {
				console.log('preloading iframe', state.iframe);
				loadIframe(state);
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

    function handleKey(e) {
        console.log('[keyup]', {
            code: e.code,
            key: e.key,
            target: e.target,
            activeElement: document.activeElement,
        });
        if (e.code === 'ArrowLeft') {
            console.log('[keyup] -> prev()');
            prev();
        } else {
            console.log('[keyup] -> next()');
            next();
        }
    }

    document.addEventListener('keyup', handleKey);
    window.addEventListener('keyup', handleKey);
    console.log('[slides] keyup listeners attached on document and window');

    renderSlide(index);
});
