document.addEventListener('DOMContentLoaded', () => {
    const wsUrl = "wss://websocket.coworking-metz.fr/ws";
    const screenId = window.ECRAN_ID;
	let ws;
    function connectWS() {
		if (ws) ws.close(); // prevent duplication
		ws = new WebSocket(wsUrl);

        ws.addEventListener("open", () => {
            console.log("[WS] connected "+screenId);

            ws.send(JSON.stringify({
                action: "ecran",
//                type: "ecran",
                id: screenId,
            }));
        });

        ws.addEventListener("message", (event) => {

            try {
                const obj = JSON.parse(event.data);
	            console.log("[WS message]", obj);
				console.log(obj.payload.id,screenId)
				if(obj.payload.id == screenId) {
					
					if(obj.payload.name=="refresh-ecran") {
						document.location.reload(true)
					} else if(obj.payload.name=="avancer-ecran") {
					    document.dispatchEvent(new Event("next-slide"));
					}
				}
            } catch {
                console.warn(event);
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




    connectWS();
});
