<script setup lang="ts">
import { ref, onUnmounted } from "vue"
import { ws, connected } from "./socket.ts"
import { startRecording, stopRecording } from "./recorder.ts"

const PAD_COUNT = 8

const recording = ref(false)
const loadedPads = ref<Set<string>>(new Set())
const elapsed = ref(0)

let timer: number | null = null
let recordStart = 0

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

  const blob = await stopRecording()
  recording.value = false
  if (timer !== null) {
    clearInterval(timer)
    timer = null
  }

  const pad = nextPad()
  ws.send(JSON.stringify({ type: "upload", pad, size: blob.size }))
  ws.send(await blob.arrayBuffer())
  loadedPads.value.add(pad)

  console.log(`Sent ${blob.size} bytes → pad ${pad}`)
}

onUnmounted(() => {
  if (timer !== null) clearInterval(timer)
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
      {{ n }}
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
