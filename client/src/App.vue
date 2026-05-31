<script setup lang="ts">
import { ws } from "./socket.ts"
  
function load() {
  ws.send(JSON.stringify({"type":"load","pad":"1","path":"/Users/natsuki/Development/php-beatmaker/samples/330.wav"}));
}

function play() {
  ws.send(JSON.stringify({"type":"play","pad":"1"}));
}
</script>

<template>
  <button @click="load">Load</button>
  <button @click="play">Kick</button>
</template>
