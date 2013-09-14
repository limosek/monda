#!/usr/bin/octave -qf

source "monda.lib.m";

global cm;
global hdata;

arg_list=argv();
items=[];
file=arg_list{1};
load(file);

 fig=1;
 for [ host, hkey ] = hdata
  if (isstruct(host))
   figure(fig++);
   hostplot(host);
   figure(fig++);
   cmplot(cm.(hkey));
  end;
 end;

pause();
