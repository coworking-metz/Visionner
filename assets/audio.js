document.addEventListener('DOMContentLoaded', () => {
    const playlist = window.ECRAN_PLAYLIST || [];
    if (playlist.length === 0) return;

    const player = document.getElementById('player');
    player.volume = (window.ECRAN_VOLUME / 100);

    let currentIndex = 0;

    function shuffle(arr) {
        for (let i = arr.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [arr[i], arr[j]] = [arr[j], arr[i]];
        }
    }

    function playNext() {
        if (currentIndex >= playlist.length) {
            shuffle(playlist);
            currentIndex = 0;
        }
        // console.log('Playing audio:', playlist[currentIndex]);
        player.src = playlist[currentIndex];
        player.play();
        currentIndex++;
    }

    player.addEventListener('ended', playNext);
    shuffle(playlist);
    playNext();
});
