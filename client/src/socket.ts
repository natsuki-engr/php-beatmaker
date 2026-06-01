import { ref } from "vue";

const ws = new WebSocket(`ws://${location.hostname}:8080`);
ws.binaryType = "arraybuffer";
const connected = ref(false);

type PresetHandler = (pad: string, wav: ArrayBuffer) => void;
let presetHandler: PresetHandler | null = null;
let pendingPreset: string | null = null;

export function onPreset(h: PresetHandler): void {
  presetHandler = h;
}

ws.onopen = () => {
  connected.value = true;
  console.log("Connected");
};

ws.onmessage = (event) => {
  if (typeof event.data === "string") {
    try {
      const m = JSON.parse(event.data);
      if (m.type === "preset" && typeof m.pad === "string") {
        pendingPreset = m.pad;
        return;
      }
    } catch {
      // not JSON, fall through
    }
    console.log("Received:", event.data);
    return;
  }
  if (pendingPreset && event.data instanceof ArrayBuffer) {
    const pad = pendingPreset;
    pendingPreset = null;
    presetHandler?.(pad, event.data);
  }
};

ws.onerror = (error) => {
  console.error("WebSocket error:", error);
};

ws.onclose = (event) => {
  connected.value = false;
  console.log(`Closed: ${event.code} ${event.reason}`);
};

export { ws, connected };
