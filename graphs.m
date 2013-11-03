#!/usr/bin/octave -qf 

source "monda.lib.m";

function ret=newfigure(o)
  if (!strcmp(o,"")) 
    ret=figure("visible","off");
  else
    ret=figure();
  end
endfunction

function printplot(h,id,o)
    global file;
    [dir, name, ext, ver] = fileparts(file);
    
    if (!strcmp(o,""))
      dir=sprintf("%s/%s",dir,name);
      mkdir(dir);
      fle=sprintf("%s/%s.%s",dir,id,o)
      print(fle,"-r600","-S4000x768");
    end
endfunction

function h=itemplot(hostname,item,o)
      global fig;
      global hdata;

      if (item.delta>0)
        newfigure(o);
        h=plot(item.xn-hdata.minx2,item.yn,"g",item.x-hdata.minx,item.y,"b");
        title(sprintf("%s:%s",hostname,item.key));
        xlabel(sprintf("t[S] (start %s, end %s)",xdate(hdata.minx),xdate(hdata.maxx)));
        legend(sprintf("Raw (%i values)",columns(item.x)),sprintf("Normalized (%i values)",columns(item.xn)));
        printplot(h,sprintf("item-%i",item.id),o);
      end;
endfunction;

function hostplot(hostname,o)
      global fig;
      global hdata;
      for [item, key] = hdata.(hostname)
       if (isstruct(item))
	 itemplot(hostname,item,o);
       end;
      end;
endfunction;

function correlplot(hostname,o)
      global fig;
      global hdata;
      
      cmvec=hdata.(hostname).cmvec;
      for i=1:rows(cmvec)
        for j=1:columns(cmvec)
          if (cmvec(i,j)>0.6)
            item1hkey=hdata.itemhindex{i};
            item1ikey=hdata.itemkindex{i};
            item2hkey=hdata.itemhindex{j};
            item2ikey=hdata.itemkindex{j};
            item1=hdata.(item1hkey).(item1ikey);
            item2=hdata.(item2hkey).(item2ikey);
            newfigure(o);
            c=corr(item1.yn,item2.yn);
            set(gcf,"name",sprintf("Correlation of %s and %s (%f,%f)",hdata.itemindex{i},hdata.itemindex{j},cmvec(i,j),c));
            subplot(2,1,1);
            h1=plot(item1.xn-hdata.minx2,item1.yn,"g");
            title(sprintf("%s",hdata.itemindex{i}));
            xlabel(sprintf("t[S] (start %s, end %s)",xdate(hdata.minx),xdate(hdata.maxx)));
	    legend(hdata.itemindex{i});
	    printplot(h1,sprintf("cm-%i_%i",item1.id,item2.id),o);
            subplot(2,1,2);
            h2=plot(item2.xn-hdata.minx2,item2.yn,"b");
            title(sprintf("%s",hdata.itemindex{j}));
            xlabel(sprintf("t[S] (start %s, end %s)",xdate(hdata.minx),xdate(hdata.maxx)));
	    legend(hdata.itemindex{j});
	    printplot(h2,sprintf("cm-%i_%i",item2.id,item1.id),o);
          end
        end
      end
endfunction

function cmplot(hostname,o)
	global cm;
	global hdata;
	
	cmhost=cm.(hostname);
	newfigure(o);
	x=1:rows(cmhost);
	y=1:rows(cmhost);
	h=surface(x,y,cmhost);
	title(sprintf("Correlation of items on host %s",hostname));
	xlabel('Item');
	ylabel('Item');
	printplot(h,sprintf("cm-%s",hostname),o);
	colorbar();
endfunction;

global cm;
global hdata;
global fig;
global file;

arg_list=argv();
items=[];
file=arg_list{1};
loaddata(file);

graphics_toolkit("gnuplot");

if (nargin>1)
  outfmt=arg_list{2};
else
  outfmt="";
end

if (nargin>2)
  for i=3:nargin
    s=strsplit(arg_list{i},":");
    type=s(1);
    id=s(2);
    if (strcmp(type,"host")) 
      hostplot(id,outfmt);
    end
    if (strcmp(type,"cm")) 
      cmplot(id,outfmt);
    end
    if (strcmp(type,"corr")) 
      correlplot(id,outfmt);
    end
  end
else
  fig=1;
  for [ host, hkey ] = hdata
   if (isstruct(host))
     cmplot(hkey,outfmt);
     hkey
     correlplot(hkey,outfmt);
   end;
  end;
end;

if (strcmp(outfmt,""))
  pause();
end;
