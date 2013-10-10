#!/usr/bin/octave -qf

source "monda.lib.m";

function ret=splitdata(src,from,to,items)
  global hdata;
  global cm;
  
  fprintf(stderr,"Reading file %s ",src);
  loaddata(src);
  fprintf(stderr,"(minx=%s(%i),maxx=%s(%i)) ",xdate(hdata.minx),hdata.minx,xdate(hdata.maxx),hdata.maxx);
  ret=true;
  for [host, hkey] = hdata
     if (isstruct(host))
       hdata.minx=str2num(to);
       hdata.maxx=str2num(from);
       hdata.minx2=str2num(to);
       hdata.maxx2=str2num(from);
       for [item, ikey] = host
         if (columns(items)>0)
	   if (!strcmp([hkey,":",ikey],items) && !strcmp([hkey,":",item.key],items) && !strcmp([hkey],items) && !strcmp([ikey],items) && !strcmp([item.key],items))
	     fprintf(stderr,"\Filtering %s:%s(%s)\n",hkey,item.key,ikey);
	     hdata.(hkey).(ikey)=[];
	     continue;
           endif
         endif
         xy=[item.x;item.y];
         xy(:,item.x<str2num(from))=[];
         xy(:,item.x>str2num(to))=[];
         if (columns(xy)==0)
            ret=false;
         endif
         item.x=xy(1,:);
         item.y=xy(2,:);
         hdata.minx=min([item.x,hdata.minx]);
         hdata.maxx=max([item.x,hdata.maxx]);
         hdata.minx2=min([item.x(3),hdata.minx2]);
         hdata.maxx2=max([item.x(end-3),hdata.maxx2]);
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
from=datetoseconds(arg_list{3});
to=datetoseconds(arg_list{4});

if (nargin>4)
  for i=5:nargin
     items{i-4}=arg_list{i};
  endfor
else
  hosts=-1;
  items=-1;
endif;

global hdata;
global cm;

if (splitdata(src,from,to,items)==0)
  fprintf(stderr,"Error in from or to??\n");
  exit;
endif

normalize();
smatrix;
cmatrix;

savedata(dst);

exit;
