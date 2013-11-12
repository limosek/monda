#!/usr/bin/octave -q

source "monda.lib.m";

global cm;
global hdata;

if (nargin<2)
  fprintf(stderr,"Error in arguments!\n analyze.m src dst [normalize_interval]\n");
  exit;
end


arg_list=argv();
src=arg_list{1};
if (index(src,".m")>0)
  source(src);
else
  load(src);
end
dst=arg_list{2};

if (nargin==3)
  delay=str2num(arg_list{3});
else
  delay=60;
end

remove_bad(0.001);

normalize(delay);
smatrix();
cmatrix();
cmtovector(0.4);
savedata(dst);

exit;




