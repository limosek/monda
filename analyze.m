#!/usr/bin/octave -qf

source "monda.lib.m";

global cm;
global hdata;

arg_list=argv();
src=arg_list{1};
if (index(src,".m")>0)
  source(src);
else
  load(src);
end
dst=arg_list{2};

normalize();
smatrix;
cmatrix;
savedata(dst);

exit;




