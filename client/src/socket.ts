const ws = new WebSocket(`ws://${location.hostname}:8080`);

ws.onopen = () => {
  console.log("Connected");
};

ws.onmessage = (event) => {
  const data = JSON.parse(event.data);
  console.log("Received:", data);
};

ws.onerror = (error) => {
  console.error("WebSocket error:", error);
};

ws.onclose = (event) => {
  console.log(`Closed: ${event.code} ${event.reason}`);
};

export { ws };
