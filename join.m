#!/usr/bin/octave -qf

source "monda.lib.m";

function ndata=joindata(ndata,fle)
  global hdata;
  global cm;
  
  ncm=cm;
  loaddata(fle);
  ndata.samehosts=0;
  
  for [host, hkey] = hdata
     if (isstruct(host))
       ndata.minx=hdata.minx;
       ndata.maxx=hdata.maxx;
       ndata.minx2=hdata.minx2;
       ndata.maxx2=hdata.maxx2;
       if (isfield(ndata,hkey))
	 ndata.samehosts=1;
       else
	 ndata.(hkey)=host;
       end
       for [item, ikey] = host
         if (!isfield(ndata.(hkey),ikey))
           ndata.(hkey).(ikey)=item;
         endif
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
       ncm.(hkey)=cm.(hkey);
     else
       ndata.(hkey)=hdata.(hkey);
     end;
  end;
  cm=ncm;
endfunction;

global hdata;
global cm;

arg_list=argv();
dst=arg_list{1};
ndata=[];

for i = 2:nargin
  ndata=joindata(ndata,arg_list{i});
end

hostsinfo(ndata);
cminfo(cm);

hdata=ndata;
ndata=[];

hoststoany();

normalize();
smatrix;
cmatrix;

savedata(dst);

exit;
