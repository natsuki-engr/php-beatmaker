<script setup lang="ts">
import { ref, nextTick, onUnmounted } from "vue"
import { ws, connected, onPreset } from "./socket.ts"
import { startRecording, stopRecording, computePeaks, PEAK_BUCKETS } from "./recorder.ts"

const PAD_COUNT = 8

const recording = ref(false)
const loadedPads = ref<Set<string>>(new Set())
const padPeaks = ref<Map<string, Float32Array>>(new Map())
const elapsed = ref(0)

let timer: number | null = null
let recordStart = 0

let decodeContext: AudioContext | null = null
function getDecodeContext(): AudioContext {
  if (!decodeContext) decodeContext = new AudioContext()
  return decodeContext
}

onPreset(async (pad, wav) => {
  const buffer = await getDecodeContext().decodeAudioData(wav)
  const peaks = computePeaks(buffer.getChannelData(0), PEAK_BUCKETS)
  padPeaks.value.set(pad, peaks)
  loadedPads.value.add(pad)
  await nextTick()
  redrawAll()
})

const canvasMap = new Map<string, HTMLCanvasElement>()
const resizeObserver = new ResizeObserver((entries) => {
  for (const entry of entries) {
    const pad = (entry.target as HTMLCanvasElement).dataset.pad
    if (pad) drawWave(pad)
  }
})

function bindCanvas(pad: string, el: HTMLCanvasElement | null) {
  const prev = canvasMap.get(pad)
  if (prev && prev !== el) resizeObserver.unobserve(prev)
  if (!el) {
    canvasMap.delete(pad)
    return
  }
  el.dataset.pad = pad
  canvasMap.set(pad, el)
  resizeObserver.observe(el)
  drawWave(pad)
}

function computeGlobalScale(): number {
  const abs: number[] = []
  for (const peaks of padPeaks.value.values()) {
    for (let i = 0; i < peaks.length; i++) {
      abs.push(Math.abs(peaks[i]))
    }
  }
  if (abs.length === 0) return 1
  abs.sort((a, b) => a - b)
  const p95 = abs[Math.min(abs.length - 1, Math.floor(abs.length * 0.95))]
  return p95 > 0 ? 1 / p95 : 1
}

function redrawAll() {
  for (const pad of canvasMap.keys()) drawWave(pad)
}

function drawWave(pad: string) {
  const canvas = canvasMap.get(pad)
  const peaks = padPeaks.value.get(pad)
  if (!canvas || !peaks) return

  const dpr = window.devicePixelRatio || 1
  const cssW = canvas.clientWidth
  const cssH = canvas.clientHeight
  if (cssW === 0 || cssH === 0) return

  canvas.width = Math.round(cssW * dpr)
  canvas.height = Math.round(cssH * dpr)

  const ctx = canvas.getContext("2d")
  if (!ctx) return
  ctx.scale(dpr, dpr)
  ctx.clearRect(0, 0, cssW, cssH)

  const buckets = peaks.length / 2
  const mid = cssH / 2
  const barW = cssW / buckets
  const scale = computeGlobalScale()

  ctx.fillStyle = "rgba(140, 170, 255, 0.85)"
  for (let i = 0; i < buckets; i++) {
    const min = Math.max(-1, Math.min(1, peaks[i * 2] * scale))
    const max = Math.max(-1, Math.min(1, peaks[i * 2 + 1] * scale))
    const y1 = mid - max * mid
    const y2 = mid - min * mid
    const h = Math.max(1, y2 - y1)
    ctx.fillRect(i * barW, y1, Math.max(1, barW - 0.5), h)
  }
}

function play(pad: string) {
  ws.send(JSON.stringify({ type: "play", pad }))
}

function nextPad(): string {
  for (let i = 1; i <= PAD_COUNT; i++) {
    const p = String(i)
    if (!loadedPads.value.has(p)) return p
  }
  return "1"
}

async function toggleRecord() {
  if (!recording.value) {
    await startRecording()
    recording.value = true
    recordStart = performance.now()
    elapsed.value = 0
    timer = window.setInterval(() => {
      elapsed.value = (performance.now() - recordStart) / 1000
    }, 100)
    return
  }

  const { blob, peaks } = await stopRecording()
  recording.value = false
  if (timer !== null) {
    clearInterval(timer)
    timer = null
  }

  const pad = nextPad()
  ws.send(JSON.stringify({ type: "upload", pad, size: blob.size }))
  ws.send(await blob.arrayBuffer())
  padPeaks.value.set(pad, peaks)
  loadedPads.value.add(pad)
  await nextTick()
  redrawAll()

  console.log(`Sent ${blob.size} bytes → pad ${pad}`)
}

onUnmounted(() => {
  if (timer !== null) clearInterval(timer)
  resizeObserver.disconnect()
})
</script>

<template>
  <header class="status" :class="{ ok: connected }">
    {{ connected ? "● connected" : "○ disconnected" }}
  </header>

  <section class="grid">
    <button
      v-for="n in PAD_COUNT"
      :key="n"
      class="pad"
      :class="{ loaded: loadedPads.has(String(n)) }"
      :disabled="!loadedPads.has(String(n))"
      @click="play(String(n))"
    >
      <canvas
        v-if="padPeaks.has(String(n))"
        :ref="(el) => bindCanvas(String(n), el as HTMLCanvasElement | null)"
        class="pad-waveform"
      />
      <span class="pad-label">{{ n }}</span>
    </button>
  </section>

  <section class="controls">
    <button class="rec" :class="{ active: recording }" @click="toggleRecord">
      {{ recording ? `STOP ${elapsed.toFixed(1)}s` : "REC" }}
    </button>
  </section>
</template>

<style scoped>
.status {
  padding: 0.75rem 1rem;
  background: #444;
  color: #aaa;
  text-align: center;
  font-family: monospace;
  font-size: 1rem;
}
.status.ok {
  background: #1a5;
  color: #fff;
}

.grid {
  flex: 1;
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  grid-template-rows: repeat(4, 1fr);
  gap: 0.75rem;
  padding: 0.75rem;
  min-height: 0;
}

.pad {
  position: relative;
  overflow: hidden;
  font-size: 2.5rem;
  font-weight: bold;
  border: 3px solid #555;
  border-radius: 0.75rem;
  background: #222;
  color: #555;
  cursor: pointer;
  touch-action: manipulation;
}
.pad.loaded {
  background: #1e2a4a;
  color: #fff;
  border-color: #66f;
}
.pad-waveform {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  pointer-events: none;
}
.pad-label {
  position: absolute;
  top: 0.4rem;
  left: 0.6rem;
  font-size: 1.1rem;
  opacity: 0.85;
}
.pad:active:not(:disabled) {
  background: #335;
  transform: scale(0.97);
}
.pad:disabled {
  cursor: default;
}

.controls {
  display: flex;
  justify-content: center;
  padding: 1rem 1rem calc(1rem + env(safe-area-inset-bottom));
}
.rec {
  width: 100%;
  max-width: 480px;
  padding: 1.25rem;
  font-size: 1.5rem;
  font-weight: bold;
  background: #555;
  color: #fff;
  border: none;
  border-radius: 999px;
  cursor: pointer;
  letter-spacing: 0.15em;
}
.rec.active {
  background: #c33;
  animation: pulse 1s infinite;
}

@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.65; }
}
</style>
