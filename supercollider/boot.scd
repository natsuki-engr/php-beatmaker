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

    "--- /load received ---".postln;
    ("  msg:    " ++ msg).postln;
    ("  name:   " ++ name ++ "  (" ++ name.class ++ ")").postln;
    ("  path:   " ++ path).postln;
    ("  exists: " ++ File.exists(path)).postln;

    ~samples[name].free;

    Buffer.read(
        s,
        path,
        action: { |buffer|
            ~samples[name] = buffer;
            ("  loaded: bufnum=" ++ buffer.bufnum
                ++ " frames=" ++ buffer.numFrames
                ++ " ch=" ++ buffer.numChannels
                ++ " sr=" ++ buffer.sampleRate).postln;
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

s.sampleRate.postln;

~test = Buffer.read(s, "/Users/natsuki/Development/php-beatmaker/samples/346.wav");
{ PlayBuf.ar(1, ~test.bufnum, doneAction: 2) ! 2 }.play;
{ PlayBuf.ar(1, ~test, BufRateScale.kr(~test.bufnum), doneAction: 2) }.play;
{ PlayBuf.ar(2, ~test.bufnum, 48000/44100, doneAction: 2) }.play;
{ PlayBuf.ar(2, ~test, BufRateScale.kr(~test)\*5, 1, 0, 0, 2)}.play;
{ PlayBuf.ar(2, ~test, 6)}.play;

// 比較対照: rate=1 (8.8% 遅く、低く)
{ PlayBuf.ar(2, ~test.bufnum, 1, doneAction: 2) }.play;

a = Buffer.read(s, "/Users/natsuki/Development/php-beatmaker/samples/rythm01.wav");
a.play;
{PlayBuf.ar(2, a, BufRateScale.kr(1)\*2, 1, 0, 0, 2)}.play;
a.sampleRate;
