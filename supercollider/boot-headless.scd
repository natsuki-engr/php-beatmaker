// Headless boot script for php-beatmaker
// Usage: ./boot.sh  (or sclang boot-headless.scd)
// sclang stays running and listens for OSC on 127.0.0.1:57120.

s.options.numInputBusChannels = 0;
s.options.outDevice = "MacBook Proのス";
s.options.inDevice = "MacBook Proの";

~samples = IdentityDictionary.new;

s.waitForBoot({
	"--- server booted ---".postln;
	("  sampleRate: " ++ s.sampleRate).postln;
	("  outDevice:  " ++ s.options.outDevice).postln;

	SynthDef(\samplePlayer, { |buf, amp = 1|
		var sig = PlayBuf.ar(
			numChannels: 1,
			bufnum: buf,
			rate: BufRateScale.kr(buf),
			doneAction: 2
		);
		Out.ar(0, sig * amp);
	}).add;

	// wait for the SynthDef to be sent to the server
	s.sync;

	OSCdef(\loadSample, { |msg|
		var name = msg[1];
		var path = msg[2].asString;

		"--- /load received ---".postln;
		("  name:   " ++ name).postln;
		("  path:   " ++ path).postln;
		("  exists: " ++ File.exists(path)).postln;

		~samples[name].free;

		Buffer.read(s, path, action: { |buffer|
			~samples[name] = buffer;
			("  loaded: bufnum=" ++ buffer.bufnum
				++ " frames=" ++ buffer.numFrames
				++ " ch=" ++ buffer.numChannels
				++ " sr=" ++ buffer.sampleRate).postln;
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

	"=== php-beatmaker SuperCollider ready (OSC on 57120) ===".postln;
});
