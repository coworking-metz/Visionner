document.addEventListener('DOMContentLoaded', () => {
    const wsUrl = "wss://websocket.coworking-metz.fr/ws";

    function connectWS() {
        const ws = new WebSocket(wsUrl);

        ws.addEventListener("open", () => {
            console.log("[WS] connected");
        });

        ws.addEventListener("message", (event) => {
            // raw text
            console.log("[WS message]", event.data);

            // Try JSON if possible
            try {
                const data = JSON.parse(event.data);
                displayMessage(data);
            } catch (e) {
                displayMessage(event.data);
            }
        });

        ws.addEventListener("close", () => {
            console.warn("[WS] closed — retry in 3s");
            setTimeout(connectWS, 3000);
        });

        ws.addEventListener("error", (err) => {
            console.error("[WS error]", err);
            ws.close();
        });
    }

    function displayMessage(content) {
        const box = document.getElementById("ws-output");
        if (!box) return;

        if (typeof content === "object") {
            box.textContent = JSON.stringify(content, null, 2);
        } else {
            box.textContent = content;
        }
    }

    connectWS();
});