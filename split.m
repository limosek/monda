#!/usr/bin/octave -qf

source "monda.lib.m";

function splitdata(from,to)
  fprintf(stderr,"Reading file %s ",src);
  loaddata(src);
  fprintf(stderr,"(minx=%s,maxx=%s) ",xdate(hdata.minx),xdate(hdata.maxx));
  for [host, hkey] = hdata
     if (isstruct(host))
       for [item, ikey] = host
         xy=[item.x;item.y];
         xy(:,item.x<from)=[];
         xy(:,item.x>to)=[];
         hdata.(hkey).(ikey).x=xy(1,:);
         hdata.(hkey).(ikey).y=xy(2,:);
         hdata.minx=min([hdata.minx,hdata.minx]);
         hdata.maxx=max([hdata.maxx,hdata.maxx]);
         hdata.minx2=min([hdata.minx2,hdata.minx2]);
         hdata.maxx2=max([hdata.maxx2,hdata.maxx2]);
         hdata.date_from=xdate(hdata.minx);
         hdata.date_to=xdate(hdata.maxx);
         hdata.time_from=hdata.minx;
         hdata.time_to=hdata.maxx;
       end;
     end;
  end;
  fprintf(stderr,"(newminx=%s,newmaxx=%s,newminx2=%s,newmaxx2=%s)\n",xdate(hdata.minx),xdate(hdata.maxx),xdate(hdata.minx2),xdate(hdata.maxx2));
endfunction;

arg_list=argv();
src=arg_list{1};
dst=arg_list{2};
from=arg_list{3};
to=arg_list{4};

global hdata;
global cm;

loaddata(src);
splitdata(from,to);

normalize();
smatrix;
cmatrix;

savedata(dst);

exit;
