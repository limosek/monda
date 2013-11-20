#!/usr/bin/octave -qf

source("monda.lib.m");

arg_list=argv();
src=arg_list{1};

global hdata;

if (index(src, ".m") > 0)
    loadsrc(src);
else
    loaddata(src);
end

hostsinfo(hdata);

for [host, hkey] = hdata
  if (ishost(host) && nargin>1)
    hostinfo(host,hkey);
  end
end
