#!/usr/bin/octave -qf

source "monda.lib.m";

function ndata=joindata(ndata,fle)
  global hdata;
  global cm;
  
  ncm=cm;
  loaddata(fle);
  ndata.samehosts=0;
  for [host, hkey] = hdata
     if (ishost(host))
       if (!isfield(ndata,'minx'))
         ndata.minx=hdata.minx;
         ndata.maxx=hdata.maxx;
         ndata.minx2=hdata.minx2;
         ndata.maxx2=hdata.maxx2;
       end
       for [item, ikey] = host
         if (!isitem(item))
           continue;
         end
         if (!isfield(ndata,hkey) || !isfield(ndata.(hkey),ikey))
           ndata.(hkey).(ikey)=item;
         endif
         #[ndata.(hkey).(ikey).x;ndata.(hkey).(ikey).y]
         xy=cat(2,[item.x;item.y],[ndata.(hkey).(ikey).x;ndata.(hkey).(ikey).y]);
         xy=transpose(sortrows(transpose(xy)));
         ndata.(hkey).(ikey).x=xy(1,:);
         ndata.(hkey).(ikey).y=xy(2,:);
         ndata.(hkey).(ikey).minx=min(ndata.(hkey).(ikey).x);
         ndata.(hkey).(ikey).maxx=max(ndata.(hkey).(ikey).x);
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
     end;
  end;
  cm=ncm;
  hdata=ndata;
endfunction;

global hdata;
global cm;

arg_list=argv();
dst=arg_list{1};
ndata=[];

start1=time();

for i = 2:nargin
  ndata=joindata(ndata,arg_list{i});
end

hostsinfo(ndata);
cminfo(cm);

remove_bad(0.001);
start3=time();

normalize(60);
hoststoany("all");
start4=time();
smatrix();
start5=time();
cmatrix();
start6=time();
cmtovector(0.4);
start7=time();

savedata(dst);
start8=time();

fprintf(stderr,"Analyze took %i seconds\n",start8-start1);


exit;
