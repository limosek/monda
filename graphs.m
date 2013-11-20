#!/usr/bin/octave -qf 

global opt;
source("monda.lib.m");

function ret=newfigure()
  if (!strcmp(getopt("imgformat"),"")) 
    ret=figure("visible","off");
  else
    ret=figure();
  end
endfunction

function printplot(h,id)
    global file;
    [dir, name, ext, ver] = fileparts(file);
    
    if (!strcmp(getopt("imgformat"),""))
      dir=sprintf("%s/%s",dir,name);
      mkdir(dir);
      fle=sprintf("%s/%s.%s",dir,id,getopt("imgformat"));
      print(fle,["-r",getopt("imgdpi")],["-S",getopt("imgsizex"),"x",getopt("imgsizey")]);
    end
endfunction

function h=itemplot(hostname,item)
      global fig;
      global hdata;

      if (item.delta>0)
        newfigure();
        h=plot(item.xn-hdata.minx2,item.yn,"g",item.x-hdata.minx,item.y,"b");
        title(sprintf("%s:%s",hostname,item.key));
        xlabel(sprintf("t[S] (start %s, end %s)",xdate(hdata.minx),xdate(hdata.maxx)));
        legend(sprintf("Raw (%i values)",columns(item.x)),sprintf("Normalized (%i values)",columns(item.xn)));
        printplot(h,sprintf("item-%i",item.id));
      end;
endfunction;

function hostplot(hostname)
      global fig;
      global hdata;

      plots=0;
      maxplots=getopt("maxplots");
      for [item, key] = hdata.(hostname)
       if (isitem(item) && plots<maxplots)
	 itemplot(hostname,item);
         plots++;
       end;
      end;
endfunction;

function correlplot(hostname)
      global fig;
      global hdata;
      
      maxplots=getopt("maxplots");
      cmvec=hdata.(hostname).cmvec;
      plots=0;
      for i=1:rows(cmvec)
        for j=1:columns(cmvec)
          if (cmvec(i,j)>0.6 && plots<maxplots)
            item1hkey=hdata.itemhindex{i};
            item1ikey=hdata.itemkindex{i};
            item2hkey=hdata.itemhindex{j};
            item2ikey=hdata.itemkindex{j};
            item1=hdata.(item1hkey).(item1ikey);
            item2=hdata.(item2hkey).(item2ikey);
            newfigure();
            c=corr(item1.yn,item2.yn);
            set(gcf,"name",sprintf("Correlation of %s and %s (%f,%f)",hdata.itemindex{i},hdata.itemindex{j},cmvec(i,j),c));
            subplot(2,1,1);
            h1=plot(item1.xn-hdata.minx2,item1.yn,"g");
            title(sprintf("%s",hdata.itemindex{i}));
            xlabel(sprintf("t[S] (start %s, end %s)",xdate(hdata.minx),xdate(hdata.maxx)));
	    legend(hdata.itemindex{i});
	    printplot(h1,sprintf("cm-%i_%i",item1.id,item2.id));
            subplot(2,1,2);
            h2=plot(item2.xn-hdata.minx2,item2.yn,"b");
            title(sprintf("%s",hdata.itemindex{j}));
            xlabel(sprintf("t[S] (start %s, end %s)",xdate(hdata.minx),xdate(hdata.maxx)));
	    legend(hdata.itemindex{j});
	    printplot(h2,sprintf("cm-%i_%i",item2.id,item1.id));
            plots++;
          end
        end
      end
endfunction

function cmplot(hostname)
	global cm;
	global hdata;
	
	cmhost=cm.(hostname);
	newfigure();
        [nzx,nzy]=find(cmhost!=0);
	x=[min(nzx):max(nzx)];
	y=x;
	h=surface(x,y,cmhost(x,y));
	title(sprintf("Correlation of items on host %s",hostname));
	xlabel('Item');
	ylabel('Item');
	printplot(h,sprintf("cm-%s",hostname));
	colorbar();
endfunction;

global cm;
global hdata;
global fig;
global file;

parseopts({"cmplot","hostplot","corrplot","maxplots"});

arg_list=getrestopts();
items=[];
file=arg_list{1};
loaddata(file);

graphics_toolkit(getopt("gtoolkit"));

if (isopt("cmplot"))
    for [host,hkey] = hdata
        if (ishost(host))
            cmplot(hkey);
        end
    end
end

if (isopt("hostplot"))
    for [host,hkey] = hdata
        if (ishost(host))
            hostplot(hkey);
        end
    end
end

if (isopt("corrplot"))
    for [host,hkey] = hdata
        if (ishost(host))
            correlplot(hkey);
        end
    end
end

if (!isopt("corrplot") && !isopt("hostplot") && !isopt("cmplot"))
    for [host,hkey] = hdata
        if (ishost(host))
            cmplot(hkey);
        end
    end
end

if (!isopt("imgformat"))
  pause();
end;
