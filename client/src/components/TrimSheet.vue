<script setup lang="ts">
import { ref, watch, computed, onMounted, onUnmounted } from "vue"
import type { Trim } from "../trim.ts"

const props = defineProps<{
  peaks: Float32Array
  trim: Trim
  duration?: number
}>()

const emit = defineEmits<{
  (e: "save", trim: Trim): void
  (e: "cancel"): void
  (e: "preview", trim: Trim): void
}>()

const MIN_GAP = 0.02

const start = ref(props.trim[0])
const end = ref(props.trim[1])

const startPct = computed(() => start.value * 100)
const endPct = computed(() => end.value * 100)

const canvas = ref<HTMLCanvasElement | null>(null)
let resizeObserver: ResizeObserver | null = null

function draw() {
  const cv = canvas.value
  if (!cv) return
  const dpr = window.devicePixelRatio || 1
  const w = cv.clientWidth
  const h = cv.clientHeight
  if (w === 0 || h === 0) return

  cv.width = Math.round(w * dpr)
  cv.height = Math.round(h * dpr)
  const ctx = cv.getContext("2d")
  if (!ctx) return
  ctx.setTransform(dpr, 0, 0, dpr, 0, 0)
  ctx.clearRect(0, 0, w, h)

  const peaks = props.peaks
  const buckets = peaks.length / 2
  const barW = w / buckets
  const mid = h / 2

  let max = 0
  for (let i = 0; i < peaks.length; i++) {
    const a = Math.abs(peaks[i])
    if (a > max) max = a
  }
  const scale = max > 0 ? 1 / max : 1

  for (let i = 0; i < buckets; i++) {
    const frac = (i + 0.5) / buckets
    const inSel = frac >= start.value && frac <= end.value
    ctx.fillStyle = inSel ? "rgba(140, 170, 255, 0.9)" : "rgba(120, 120, 120, 0.3)"
    const mn = Math.max(-1, Math.min(1, peaks[i * 2] * scale))
    const mx = Math.max(-1, Math.min(1, peaks[i * 2 + 1] * scale))
    const y1 = mid - mx * mid
    const y2 = mid - mn * mid
    ctx.fillRect(i * barW, y1, Math.max(1, barW - 0.5), Math.max(1, y2 - y1))
  }
}

watch([start, end, () => props.peaks], draw)

onMounted(() => {
  draw()
  resizeObserver = new ResizeObserver(draw)
  if (canvas.value) resizeObserver.observe(canvas.value)
})

onUnmounted(() => {
  resizeObserver?.disconnect()
})

let active: "start" | "end" | null = null

function xToFrac(clientX: number): number {
  const cv = canvas.value
  if (!cv) return 0
  const rect = cv.getBoundingClientRect()
  return Math.max(0, Math.min(1, (clientX - rect.left) / rect.width))
}

function onPointerDown(e: PointerEvent) {
  const frac = xToFrac(e.clientX)
  // grab whichever handle is nearer
  active = Math.abs(frac - start.value) <= Math.abs(frac - end.value) ? "start" : "end"
  ;(e.currentTarget as HTMLElement).setPointerCapture(e.pointerId)
  navigator.vibrate?.(10)
  onPointerMove(e)
}

function onPointerMove(e: PointerEvent) {
  if (!active) return
  const frac = xToFrac(e.clientX)
  if (active === "start") {
    start.value = Math.max(0, Math.min(frac, end.value - MIN_GAP))
  } else {
    end.value = Math.min(1, Math.max(frac, start.value + MIN_GAP))
  }
}

function onPointerUp() {
  active = null
}

// Dismiss only when a tap both starts and ends on the backdrop itself.
// This prevents the long-press that opens the sheet (whose pointerdown began
// on the pad, outside the backdrop) from closing it on release.
let backdropDown = false

function onBackdropDown() {
  backdropDown = true
}

function onBackdropUp() {
  if (backdropDown) {
    backdropDown = false
    emit("cancel")
  }
}

function fmt(frac: number): string {
  if (props.duration == null) return `${Math.round(frac * 100)}%`
  return `${(frac * props.duration).toFixed(2)}s`
}
</script>

<template>
  <div
    class="trim-backdrop"
    @pointerdown.self="onBackdropDown"
    @pointerup.self="onBackdropUp"
  >
    <div class="trim-sheet">
      <header class="trim-head">
        <button class="trim-btn" @click="emit('cancel')">✕</button>
        <span class="trim-time">{{ fmt(start) }} – {{ fmt(end) }}</span>
        <button class="trim-btn save" @click="emit('save', [start, end])">保存</button>
      </header>

      <div
        class="trim-wave"
        @pointerdown="onPointerDown"
        @pointermove="onPointerMove"
        @pointerup="onPointerUp"
        @pointercancel="onPointerUp"
      >
        <canvas ref="canvas" class="trim-canvas" />
        <div class="trim-shade left" :style="{ width: startPct + '%' }" />
        <div class="trim-shade right" :style="{ width: 100 - endPct + '%' }" />
        <div class="trim-handle" :style="{ left: startPct + '%' }" />
        <div class="trim-handle" :style="{ left: endPct + '%' }" />
      </div>

      <button class="trim-preview" @click="emit('preview', [start, end])">▶</button>
    </div>
  </div>
</template>

<style scoped>
.trim-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.6);
  display: flex;
  align-items: flex-end;
  z-index: 100;
}
.trim-sheet {
  width: 100%;
  background: #1c1c1c;
  border-radius: 1rem 1rem 0 0;
  padding: 0.75rem 0.75rem calc(0.75rem + env(safe-area-inset-bottom));
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}
.trim-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.trim-btn {
  min-width: 4rem;
  padding: 0.6rem 0.9rem;
  font-size: 1rem;
  background: #333;
  color: #eee;
  border: none;
  border-radius: 0.5rem;
  cursor: pointer;
}
.trim-btn.save {
  background: #1a5;
  color: #fff;
  font-weight: bold;
}
.trim-time {
  font-family: monospace;
  font-size: 0.95rem;
  color: #bbb;
}
.trim-wave {
  position: relative;
  height: 40vh;
  max-height: 320px;
  background: #111;
  border-radius: 0.5rem;
  overflow: hidden;
  touch-action: none;
}
.trim-canvas {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
}
.trim-shade {
  position: absolute;
  top: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.45);
  pointer-events: none;
}
.trim-shade.left {
  left: 0;
}
.trim-shade.right {
  right: 0;
}
.trim-handle {
  position: absolute;
  top: 0;
  bottom: 0;
  width: 2px;
  margin-left: -1px;
  background: #8caaff;
  pointer-events: none;
}
.trim-handle::before {
  content: "";
  position: absolute;
  top: 50%;
  left: 50%;
  width: 1.6rem;
  height: 3rem;
  transform: translate(-50%, -50%);
  background: #8caaff;
  border-radius: 0.4rem;
  opacity: 0.9;
}
.trim-preview {
  padding: 1rem;
  font-size: 1.1rem;
  font-weight: bold;
  background: #444;
  color: #fff;
  border: none;
  border-radius: 999px;
  cursor: pointer;
  letter-spacing: 0.1em;
}
</style>
