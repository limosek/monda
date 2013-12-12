#!/usr/bin/octave -qf

global opt;
source("monda.lib.m");

parseopts();
arg_list=getrestopts();

global hdata;

if (length(arg_list)<2)
    err("export source.az destination.json\n");
    mexit(1);
end

src=arg_list{1};
dst=arg_list{2};

loaddata(src);
jsonsave(dst,hdata);

mexit(0);
