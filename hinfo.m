#!/usr/bin/octave -qf

global opt;
addpath("m");
source(file_in_loadpath("monda.lib.m"));

parseopts();
arg_list=getrestopts();

global hdata;

for i=1:length(arg_list)
 hdata=[];
 src=arg_list{i};
 if (index(src, ".m") > 0)
    loadsrc(src);
 else
    loaddata(src);
 end
 hostsinfo(hdata);
 cminfo(hdata.cm);
end

mexit(0);
