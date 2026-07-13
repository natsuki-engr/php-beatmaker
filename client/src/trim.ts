import { reactive, watch } from "vue";

// Per-pad playback trim as normalized fractions [start, end] in 0..1.
// [0, 1] means the whole sample. Persisted to localStorage.
export type Trim = [number, number];

const STORAGE_KEY = "pad-trims";
const FULL: Trim = [0, 1];

function load(): Record<string, Trim> {
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    return raw ? JSON.parse(raw) : {};
  } catch {
    return {};
  }
}

const trims = reactive<Record<string, Trim>>(load());

watch(
  trims,
  (t) => localStorage.setItem(STORAGE_KEY, JSON.stringify(t)),
  { deep: true },
);

export function getTrim(pad: string): Trim {
  return trims[pad] ?? FULL;
}

export function setTrim(pad: string, trim: Trim): void {
  trims[pad] = trim;
}

export function clearTrim(pad: string): void {
  delete trims[pad];
}
