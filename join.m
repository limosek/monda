#!/usr/bin/octave -qf

source("monda.lib.m");

function ndata=joindata(ndata,fle)
  global hdata;
  
  loaddata(fle);
  for [host, hkey] = hdata
     if (ishost(host))
       if (!isfield(ndata,'minx'))
         ndata.minx=hdata.minx;
         ndata.maxx=hdata.maxx;
         ndata.minx2=hdata.minx2;
         ndata.maxx2=hdata.maxx2;
       end
       ndata.(hkey).ishost=1;
       for [item, ikey] = host
         if (!isitem(item))
           continue;
         end
         if (!isfield(ndata,hkey) || !isfield(ndata.(hkey),ikey))
           ndata.(hkey).(ikey)=item;
         endif
         ndata.(hkey).(ikey).isitem=1;
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
       ndata.cm=hdata.cm;
     end;
  end;
  itemindex=hdata.itemindex;
  hdata=ndata;
  indexes();
  remove_bad();
  indexes();
  #hdata.cm=reindexcm(hdata.cm,itemindex);
endfunction;

global hdata;

arg_list=getrestopts();
ndata=[];

start1=time();

pp=opt.preprocess;
opt.preprocess=2;
if (getopt("o"))
  dst=getopt("o");
  for i = 1:length(arg_list)
    ndata=joindata(ndata,arg_list{i});
  end
else
  dst=arg_list{1};
  for i = 1:length(arg_list)
    ndata=joindata(ndata,arg_list{i});
  end
end
opt.preprocess=pp;

preprocess();
start3=time();
normalize();
start4=time();
smatrix();
start5=time();
cmatrixall();
start6=time();
cmtovector();
start7=time();
savedata(dst);
start8=time();

warn(sprintf("Analyze took %i seconds\n",start8-start1));

mexit(0);