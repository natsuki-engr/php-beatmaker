let mediaStream: MediaStream | null = null;
let audioContext: AudioContext | null = null;
let source: MediaStreamAudioSourceNode | null = null;
let processor: ScriptProcessorNode | null = null;
let chunks: Float32Array[] = [];
let sampleRate = 44100;

export const PEAK_BUCKETS = 160;

export async function startRecording(): Promise<void> {
  mediaStream = await navigator.mediaDevices.getUserMedia({ audio: true });
  audioContext = new AudioContext();
  sampleRate = audioContext.sampleRate;

  source = audioContext.createMediaStreamSource(mediaStream);
  processor = audioContext.createScriptProcessor(4096, 1, 1);

  chunks = [];
  processor.onaudioprocess = (e) => {
    chunks.push(new Float32Array(e.inputBuffer.getChannelData(0)));
  };

  const mute = audioContext.createGain();
  mute.gain.value = 0;

  source.connect(processor);
  processor.connect(mute);
  mute.connect(audioContext.destination);
}

export async function stopRecording(): Promise<{ blob: Blob; peaks: Float32Array }> {
  processor?.disconnect();
  source?.disconnect();
  mediaStream?.getTracks().forEach((t) => t.stop());
  await audioContext?.close();

  const wav = encodeWav(chunks, sampleRate);
  const peaks = computePeaks(chunks, PEAK_BUCKETS);

  mediaStream = null;
  audioContext = null;
  source = null;
  processor = null;
  chunks = [];

  return { blob: new Blob([wav], { type: "audio/wav" }), peaks };
}

export function computePeaks(input: Float32Array | Float32Array[], buckets: number): Float32Array {
  const chunks = Array.isArray(input) ? input : [input];
  const out = new Float32Array(buckets * 2);
  const total = chunks.reduce((s, c) => s + c.length, 0);
  if (total === 0) return out;

  const bucketSize = total / buckets;
  let bucket = 0;
  let bucketEnd = bucketSize;
  let min = Infinity;
  let max = -Infinity;
  let idx = 0;

  for (const chunk of chunks) {
    for (let i = 0; i < chunk.length; i++) {
      const v = chunk[i];
      if (v < min) min = v;
      if (v > max) max = v;
      idx++;
      if (idx >= bucketEnd && bucket < buckets - 1) {
        out[bucket * 2] = min;
        out[bucket * 2 + 1] = max;
        bucket++;
        bucketEnd = (bucket + 1) * bucketSize;
        min = Infinity;
        max = -Infinity;
      }
    }
  }
  out[bucket * 2] = min === Infinity ? 0 : min;
  out[bucket * 2 + 1] = max === -Infinity ? 0 : max;
  return out;
}

function encodeWav(chunks: Float32Array[], sampleRate: number): ArrayBuffer {
  const total = chunks.reduce((s, c) => s + c.length, 0);
  const pcm = new Float32Array(total);
  let offset = 0;
  for (const c of chunks) {
    pcm.set(c, offset);
    offset += c.length;
  }

  const buffer = new ArrayBuffer(44 + pcm.length * 2);
  const view = new DataView(buffer);

  writeStr(view, 0, "RIFF");
  view.setUint32(4, 36 + pcm.length * 2, true);
  writeStr(view, 8, "WAVE");
  writeStr(view, 12, "fmt ");
  view.setUint32(16, 16, true);
  view.setUint16(20, 1, true);
  view.setUint16(22, 1, true);
  view.setUint32(24, sampleRate, true);
  view.setUint32(28, sampleRate * 2, true);
  view.setUint16(32, 2, true);
  view.setUint16(34, 16, true);
  writeStr(view, 36, "data");
  view.setUint32(40, pcm.length * 2, true);

  let pos = 44;
  for (let i = 0; i < pcm.length; i++) {
    const s = Math.max(-1, Math.min(1, pcm[i]));
    view.setInt16(pos, s < 0 ? s * 0x8000 : s * 0x7fff, true);
    pos += 2;
  }

  return buffer;
}

function writeStr(view: DataView, offset: number, str: string): void {
  for (let i = 0; i < str.length; i++) {
    view.setUint8(offset + i, str.charCodeAt(i));
  }
}
