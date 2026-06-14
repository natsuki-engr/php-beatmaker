// Headless boot script for php-beatmaker
// Usage: ./boot.sh  (or sclang boot-headless.scd)
// sclang stays running and listens for OSC on 127.0.0.1:57120,
// and sends replies back to the PHP server on 127.0.0.1:57121.

s.options.numInputBusChannels = 0;
s.options.outDevice = "MacBook Proのス";
s.options.inDevice = "MacBook Proの";

// where to send replies back to the PHP server
~phpAddr = NetAddr("127.0.0.1", 57121);

~samples = IdentityDictionary.new;
~samplePaths = IdentityDictionary.new;

// (re)register the SynthDef and reload every buffer.
// Runs after each (re)boot so switching the output device keeps samples alive.
~setup = {
	Routine({
		SynthDef(\samplePlayer, { |buf, amp = 1|
			var sig = PlayBuf.ar(
				numChannels: 1,
				bufnum: buf,
				rate: BufRateScale.kr(buf),
				doneAction: 2
			);
			Out.ar(0, sig * amp);
		}).add;
		s.sync;

		~samplePaths.keysValuesDo { |name, path|
			~samples[name] = Buffer.read(s, path);
		};
		s.sync;

		("=== php-beatmaker ready (out: " ++ s.options.outDevice ++ ") ===").postln;
	}).play;
};

OSCdef(\loadSample, { |msg|
	var name = msg[1];
	var path = msg[2].asString;

	("/load " ++ name ++ " <- " ++ path).postln;

	~samplePaths[name] = path;
	~samples[name].free;

	Buffer.read(s, path, action: { |buffer|
		~samples[name] = buffer;
		("  loaded: bufnum=" ++ buffer.bufnum ++ " frames=" ++ buffer.numFrames).postln;
	});
}, '/load');

OSCdef(\playSample, { |msg|
	var name = msg[1];
	var buffer = ~samples[name];

	if(buffer.notNil) {
		Synth(\samplePlayer, [\buf, buffer.bufnum]);
	} {
		("sample not found: " ++ name).postln;
	};
}, '/play');

// reply with the list of available audio devices
OSCdef(\listDevices, { |msg|
	var devices = ServerOptions.devices ? [];
	("/listDevices -> " ++ devices).postln;
	~phpAddr.sendMsg('/devices', *devices);
}, '/listDevices');

// switch the output device (requires a server reboot)
OSCdef(\setOutDevice, { |msg|
	var dev = msg[1].asString;
	("/setOutDevice -> " ++ dev).postln;
	s.options.outDevice = dev;
	s.reboot;
}, '/setOutDevice');

// run setup on every (re)boot
ServerBoot.add(~setup);

s.boot;
