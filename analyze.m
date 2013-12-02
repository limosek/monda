#!/usr/bin/octave --norc

global opt;
source("monda.lib.m");

global cm;
global hdata;

parseopts();

start1 = time();

arg_list = getrestopts();
if (length(arg_list)<2)
    fprintf(stderr, "Error in arguments!\n analyze.m src dst\n");
    exit;
end

src = arg_list{1};
if (index(src, ".m") > 0)
    loadsrc(src);
else
    loaddata(src);
end
start2 = time();
dst = arg_list{2};

preprocess();
start3 = time();
normalize();
start4 = time();
smatrix();
start5 = time();
cmatrix();
start6 = time();
cmtovector();
start7 = time();
savedata(dst);
start8 = time();

fprintf(stdout, "Analyze took %i seconds (%i load,%i remove, %i normalize, %i smatrix, %i cmatrix, %i cmtovector, %i save).\n", time() - start1, start2 - start1, start3 - start2, start4 - start3, start5 - start4, start6 - start5, start7 - start6, start8 - start7);

mexit(0);




