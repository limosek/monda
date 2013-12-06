#!/usr/bin/octave

global opt;
opt.pause=1;
source("monda.lib.m");

function ret=newfigure()
  if (!strcmp(getopt("imgformat"),"")) 
    ret=figure("visible","off");
  else
    ret=figure();
  end
  set(ret,'papertype', 'a4');
endfunction

function printplot(h,id)
    global file;
    [dir, name, ext, ver] = fileparts(file);
    
    if (!strcmp(getopt("imgformat"),""))
      dir=sprintf("%s/%s",dir,name);
      mkdir(dir);
      fle=sprintf("%s/%s.%s",dir,id,getopt("imgformat"));
      print(fle,["-r",getopt("imgdpi")],["-S",getopt("imgsizex"),"x",getopt("imgsizey")]);
      dbg(sprintf("Saving %s\n",fle));
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
        ylabel(sprintf("min=%f,max=%f,cv=%f",min(item.y),max(item.y),coeffvar(item.y)));
        printplot(h,sprintf("item-%i",item.id));
      else
        warn(sprintf("Ignoring %s:%s (delta==0)\n",hostname,item.key));
      end;
endfunction;

function hostplot(hostname)
      global fig;
      global hdata;

      plots=0;
      maxplots=getopt("maxplots")
      for [item, key] = hdata.(hostname)
       if (isitem(item) && plots<maxplots)
         dbg(sprintf("Ploting %s:%s\n",hostname,item.key));
	 itemplot(hostname,item);
         plots++
       end;
      end;
endfunction;

function correlplot(hostname)
      global fig;
      global hdata;
      
      maxplots=getopt("maxplots");
      cmin=getopt("cmin");
      cmvec=hdata.(hostname).cmvec;
      cm=hdata.cm;
      sortvec=hdata.(hostname).sortvec;
      plots=0;
      for i=1:rows(sortvec)
          if (plots>=maxplots)
            return;
          end
          [rc]=sortvec(i,:);
          row=rc(1);
          col=rc(2);
          c=cm(row,col);
          if (c>cmin && row!=col)
            item1hkey=hdata.itemhindex{row};
            item1ikey=hdata.itemkindex{row};
            item2hkey=hdata.itemhindex{col};
            item2ikey=hdata.itemkindex{col};
            item1=hdata.(item1hkey).(item1ikey);
            item2=hdata.(item2hkey).(item2ikey);
            if (!isitem(item1) || !isitem(item2))
                continue;
            end
            newfigure();
            set(gcf,"name",sprintf("Correlation of %s and %s (%f)",hdata.itemindex{row},hdata.itemindex{col},c));
            subplot(2,1,1);
            h1=plot(item1.xn-hdata.minx2,item1.yn,"g");
            title(sprintf("%s",hdata.itemindex{row}));
            xlabel(sprintf("t[S] (start %s, end %s)",xdate(hdata.minx),xdate(hdata.maxx)));
	    legend(hdata.itemindex{row});
	    printplot(h1,sprintf("cm-%i_%i",item1.id,item2.id));
            subplot(2,1,2);
            h2=plot(item2.xn-hdata.minx2,item2.yn,"b");
            title(sprintf("%s",hdata.itemindex{col}));
            xlabel(sprintf("t[S] (start %s, end %s)",xdate(hdata.minx),xdate(hdata.maxx)));
	    legend(hdata.itemindex{col});
	    printplot(h2,sprintf("cm-%i_%i",item2.id,item1.id));
            plots++;
          end
      end
endfunction

function cmplot(hostname)
	global hdata;
	
	cmhost=hdata.cm;
	newfigure();
        mini=(hdata.(hostname).minindex);
        maxi=(hdata.(hostname).maxindex);
        x=[mini:maxi];
        y=x;
	h=surface(x,y,cmhost(x,y));
	title(sprintf("Correlation of items on host %s",hostname));
	xlabel('Item');
	ylabel('Item');
	colorbar();
	printplot(h,sprintf("cm-%s",hostname));
        for i=mini:maxi
            item=finditem(hdata.itemindex{i});
            if (!isfield(item,"isbad"))
                warn(sprintf("Item %i => %s (cv=%f)\n",i,hdata.itemindex{i},coeffvar(item.y)));
            else
                warn(sprintf("Item %i => %s (deleted)\n",i,hdata.itemindex{i}));
            end
        end
endfunction;

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

mexit(0);