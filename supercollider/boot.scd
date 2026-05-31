ServerOptions.devices;
ServerOptions.devices.do(_.postln);
// [Nothing Ear (a), Nothing Ear (a), Background Music, Background Music (UI Sounds), MacBook Proの, MacBook Proのス]


s.options.numInputBusChannels = 0;
// s.options.numOutputBusChannels = 1;
s.options.outDevice = "Nothing Ear (a)";
s.reboot;

{ SinOsc.ar(440, 0, 0.1) }.play;
s.freeAll;

s.boot;

OSCdef(\test, { |msg, time, addr|
    "received".postln;
    msg.postln;
}, '/hello');

n = NetAddr("127.0.0.1", NetAddr.langPort);
n.sendMsg("/hello", 123);

OSCdef(\play, { |msg|
    var freq = msg[1];

    {
        SinOsc.ar(freq, 0, 0.1)
        * EnvGen.kr(
            Env.perc(0.01, 0.3),
            doneAction: 2
        )
    }.play;
}, '/play');

(
SynthDef(\kick, {
    var env = EnvGen.kr(
        Env.perc(0.001, 0.2),
        doneAction: 2
    );

    var osc = SinOsc.ar(
        XLine.kr(120, 40, 0.05)
    );

    Out.ar(0, (osc * env * 0.3) ! 2);
}).add;
)

OSCdef(\play, { |msg|
    switch(msg[1],
        \bd, { Synth(\kick); }
    );
}, '/play');