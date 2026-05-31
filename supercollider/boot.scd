ServerOptions.devices;
ServerOptions.devices.do(_.postln);
// [Nothing Ear (a), Nothing Ear (a), Background Music, Background Music (UI Sounds), MacBook Proの, MacBook Proのス]


s.options.numInputBusChannels = 0;
// s.options.numOutputBusChannels = 1;
s.options.outDevice = "Nothing Ear (a)";
s.reboot;

~samples = IdentityDictionary.new;

(
SynthDef(\samplePlayer, {
    |bufnum, amp = 1|

    var sig;

    sig = PlayBuf.ar(
        numChannels: 2,
        bufnum: bufnum,
        doneAction: 2
    );

    Out.ar(0, sig * amp);
}).add;
)

(
OSCdef(\loadSample, { |msg|

    var name = msg[1];
    var path = msg[2].asString;

    Buffer.read(
        s,
        path,
        action: { |buffer|

            ~samples[name] = buffer;

            ("loaded: " ++ name).postln;
        }
    );

}, '/load');
)

(
OSCdef(\playSample, { |msg|

    var name = msg[1];
    var buffer;

    name.postln;
    name.class.postln;

    buffer = ~samples[name];

    buffer.postln;

    if(buffer.notNil) {
        ("bufnum=" ++ buffer.bufnum).postln;

        Synth(
            \samplePlayer,
            [
                \bufnum,
                buffer.bufnum
            ]
        );

    } {
		("sample not found: " ++ name).postln;
    };

	buffer = ~samples[name];

	if(buffer.notNil) {
		("frames=" ++ buffer.numFrames).postln;
	};

}, '/play');
)
