#!/usr/bin/octave -qf

source "monda.lib.m";

function ndata=joindata(ndata,fle)
  fprintf(stderr,"Reading file %s ",fle);
  loaddata(fle);
  if (!isstruct(ndata))
    ndata=hdata;
  end;
  fprintf(stderr,"(minx=%s,maxx=%s) ",xdate(hdata.minx),xdate(hdata.maxx));
  for [host, hkey] = hdata
     if (isstruct(host))
       for [item, ikey] = host
         xy=sort([item.x;item.y],2);
         ndata.(hkey).(ikey).x=xy(1,:);
         ndata.(hkey).(ikey).y=xy(2,:);
         ndata.minx=min([hdata.minx,ndata.minx]);
         ndata.maxx=max([hdata.maxx,ndata.maxx]);
         ndata.minx2=min([hdata.minx2,ndata.minx2]);
         ndata.maxx2=max([hdata.maxx2,ndata.maxx2]);
         ndata.date_from=xdate(ndata.minx);
         ndata.date_to=xdate(ndata.maxx);
         ndata.time_from=ndata.minx;
         ndata.time_to=ndata.maxx;
       end;
     end;
  end;
  fprintf(stderr,"(newminx=%s,newmaxx=%s,newminx2=%s,newmaxx2=%s)\n",xdate(ndata.minx),xdate(ndata.maxx),xdate(ndata.minx2),xdate(ndata.maxx2));
endfunction;

arg_list=argv();
dst=arg_list{1};
ndata=[];

for i = 2:nargin
  ndata=joindata(ndata,arg_list{i});
end

global hdata;
global cm;

hdata=ndata;
ndata=[];

normalize();
smatrix;
cmatrix;

savedata(dst);

exit;
