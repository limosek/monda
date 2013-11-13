#!/usr/bin/octave -q

source "monda.lib.m";

global cm;
global hdata;

if (nargin<2)
  fprintf(stderr,"Error in arguments!\n analyze.m src dst [normalize_interval]\n");
  exit;
end

start1=time();

arg_list=argv();
src=arg_list{1};
if (index(src,".m")>0)
  source(src);
else
  load(src);
end
start2=time();
dst=arg_list{2};

if (nargin==3)
  delay=str2num(arg_list{3});
else
  delay=60;
end

remove_bad(0.001);
start3=time();

normalize(delay);
start4=time();
smatrix();
start5=time();
cmatrix();
start6=time();
cmtovector(0.4);
start7=time();
savedata(dst);
start8=time();

fprintf(stderr,"Analyze took %i seconds (%i load,%i remove, %i normalize, %i smatrix, %i cmatrix, %i cmtovector, %i save).\n",time()-start1,start2-start1,start3-start2,start4-start3,start5-start4,start6-start5,start7-start6,start8-start7);

exit;




