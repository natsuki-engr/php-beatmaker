import { ref } from "vue";

const ws = new WebSocket(`ws://${location.hostname}:8080`);
const connected = ref(false);

ws.onopen = () => {
  connected.value = true;
  console.log("Connected");
};

ws.onmessage = (event) => {
  console.log("Received:", event.data);
};

ws.onerror = (error) => {
  console.error("WebSocket error:", error);
};

ws.onclose = (event) => {
  connected.value = false;
  console.log(`Closed: ${event.code} ${event.reason}`);
};

export { ws, connected };
