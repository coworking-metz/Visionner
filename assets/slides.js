document.addEventListener('DOMContentLoaded', () => {

    const templates = Array.from(document.querySelectorAll('template[id^="slide-"]'));
    if (templates.length === 0) return;

    const container = document.getElementById('screen');
    let index = 0;
    let timer = null;

    function renderSlide(i) {
        container.innerHTML = "";

        const tpl = templates[i];
        console.log('Displaying slide:', tpl);
        const duration = parseInt(tpl.dataset.duration || 10, 10) * 1000;

        const node = tpl.content.cloneNode(true);
        container.appendChild(node);

        timer = setTimeout(() => {
            index = (index + 1) % templates.length;
            renderSlide(index);
        }, duration);
    }

    function next() {
        clearTimeout(timer);
        index = (index + 1) % templates.length;
        renderSlide(index);
    }

    function prev() {
        clearTimeout(timer);
        index = (index - 1 + templates.length) % templates.length;
        renderSlide(index);
    }

    document.addEventListener('keyup', e => {
        if (e.code === 'ArrowRight') next();
        if (e.code === 'ArrowLeft')  prev();
    });

    renderSlide(index);
});
