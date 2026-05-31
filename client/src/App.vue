<script setup lang="ts">
import { ref } from "vue"
import { ws } from "./socket.ts"
import { startRecording, stopRecording } from "./recorder.ts"

const recording = ref(false)
const lastBlobUrl = ref<string | null>(null)

function load() {
  ws.send(JSON.stringify({"type":"load","pad":"1","path":"/Users/natsuki/Development/php-beatmaker/samples/330.wav"}));
}

function play() {
  ws.send(JSON.stringify({"type":"play","pad":"1"}));
}

async function toggleRecord() {
  if (!recording.value) {
    await startRecording()
    recording.value = true
    return
  }

  const blob = await stopRecording()
  recording.value = false

  if (lastBlobUrl.value) URL.revokeObjectURL(lastBlobUrl.value)
  lastBlobUrl.value = URL.createObjectURL(blob)

  ws.send(JSON.stringify({ type: "upload", pad: "1", size: blob.size }))
  ws.send(await blob.arrayBuffer())

  console.log(`Sent WAV: ${blob.size} bytes`)
}
</script>

<template>
  <button @click="load">Load</button>
  <button @click="play">Kick</button>
  <button @click="toggleRecord">{{ recording ? "Stop" : "Record" }}</button>
  <audio v-if="lastBlobUrl" :src="lastBlobUrl" controls />
</template>
