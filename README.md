# PHP Beatmaker

A lightweight browser-controlled beatmaking system powered by PHP, SuperCollider, and WebSockets.

PHP Beatmaker is an experimental project for building a **Koala-like sampler and Strudel-like pattern engine** using PHP as a real-time audio control layer.

---

## Overview

- 🎛 Browser UI (Vite / Vue)
- 🔌 WebSocket server (PHP)
- 🎧 OSC bridge (PHP → SuperCollider)
- 🔊 Audio engine (SuperCollider)

Users can trigger samples and patterns from a browser and play them in SuperCollider in real time.

---

## Architecture

```
Browser (Vite)
↓ WebSocket
PHP Beatmaker Server
↓ OSC
SuperCollider
```

---

## Features

- Load WAV samples dynamically
- Trigger pads from browser
- Real-time WebSocket control
- OSC-based audio engine control
- Extensible message-based protocol

---

## Example Messages

### Play a sample

```json
{ "type": "play", "pad": "pad1" }
```

### Load a sample

```json
{ "type": "load", "pad": "pad1", "path": "/samples/kick.wav" }
```

## SuperCollider Interface

Communication is handled via OSC:

- /load → load sample into buffer
- /play → trigger sample playback

---

## Tech Stack

- PHP 8.1+
- ReactPHP
- Ratchet WebSocket
- OSC (UDP)
- SuperCollider
- Vite (frontend)

## Goal

The goal of PHP Beatmaker is to create a web-based beatmaking instrument:

- sample pads like Koala
- pattern sequencing like Strudel
- controlled entirely from the browser

## Status

Early prototype:

- OSC communication working
- sample loading working
- basic playback working
- WebSocket layer in progress


## License

MIT
