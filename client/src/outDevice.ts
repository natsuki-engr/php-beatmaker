import { ref, watch } from "vue";
import { setDevice } from "./socket.ts";

const STORAGE_KEY = "out-device";

// Selected output device, persisted to localStorage.
// Restored on load (without re-applying), saved and applied on change.
const selectedDevice = ref(localStorage.getItem(STORAGE_KEY) ?? "");

watch(selectedDevice, (device) => {
  if (!device) return;
  localStorage.setItem(STORAGE_KEY, device);
  setDevice(device);
});

export { selectedDevice };
