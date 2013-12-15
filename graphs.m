#!/usr/bin/octave -qf

global opt;
opt.pause=1;
addpath("m");
source(file_in_loadpath("monda.lib.m"));

function ret=newfigure()
  if (!strcmp(getopt("imgformat"),"")) 
    ret=figure("visible","off",'Position',[0,0,2000,2000]);
  else
    ret=figure();
  end
  set(ret,'papertype', 'a4');
  #set(ret,"defaultaxesfontname","Helvetica");
  #set(ret,"defaultaxesfontsize",12);
  #set(ret,"defaulttextfontname","Helvetica"); 
  #set(ret,"defaulttextfontsize",12);
endfunction

function printplot(h,id)
    global file;
    [dir, name, ext, ver] = fileparts(file);
    
    if (!strcmp(getopt("imgformat"),""))
      dir=sprintf("%s/%s",dir,name);
      mkdir(dir);
      fle=sprintf("%s/%s.%s",dir,id,getopt("imgformat"));
      opts=sprintf("-r%i",300);# -S%ix%i",getopt("imgdpi"),getopt("imgsizex"),getopt("imgsizey"));
      dbg(sprintf("Saving %s\n",fle));
      print(fle,sprintf("-S%i,%i",getopt("imgsizex"),getopt("imgsizey")));
    end
endfunction

function h=itemplot(hostname,item)
      global fig;
      global hdata;

      if (item.delta>0)
        newfigure();
        minx=min(item.x);
        minxn=min(item.xn);
        maxx=max(item.x);
        maxxn=max(item.xn);
        h=plot(item.xn-minxn,item.yn,"g",item.x-minx,item.y,"b");
        title(sprintf("%s:%s",hostname,item.key));
        xlabel(sprintf("t[S] (start %s, end %s)",xdate(minx),xdate(maxx)));
        legend(sprintf("Raw (%i values)",columns(item.x)),sprintf("Normalized (%i values)",columns(item.xn)));
        ylabel(sprintf("min=%f,max=%f,cv=%f",min(item.y),max(item.y),coefvar(item.y)));
        printplot(h,sprintf("item-%i",item.id));
      else
        warn(sprintf("Ignoring %s:%s (delta==0)\n",hostname,item.key));
      end;
endfunction;

function hostplot(hostname)
      global fig;
      global hdata;

      plots=0;
      maxplots=getopt("maxplots");
      for [item, key] = hdata.(hostname)
       if (isitem(item) && plots<maxplots)
         dbg(sprintf("Ploting %s:%s\n",hostname,item.key));
	 itemplot(hostname,item);
         plots++;
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
        if (strcmp(hostname,"all"))
            mini=1;
            maxi=rows(cmhost);
        else
            mini=(hdata.(hostname).minindex);
            maxi=(hdata.(hostname).maxindex);
        end
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
            if (isitem(item) && !isfield(item,"isbad"))
                warn(sprintf("Item %i => %s (cv=%f)\n",i,hdata.itemindex{i},coefvar(item.y)));
            else
                warn(sprintf("Item %i => %s (deleted)\n",i,hdata.itemindex{i}));
            end
        end
end

function somhosts(name)
    global hdata;

    lasthost=1;
    lasttime=1;
    for [host,hkey]=hdata
        if (ishost(host))
            Dh(:,lasthost)=host.cmvec;
            hosts{lasthost++}=hkey;
        end
    end
    newfigure();
    D=som_data_struct(Dh,'comp_names',hosts);
    M=som_make(D);
    M.name=name;
    h=som_show(M);
    #som_cplane(M,M.codebook(:,1));  
#    bmus = som_bmus(M,D);
#    som_trajectory(bmus);
    printplot(h,sprintf("som"));
end

function somitems(host,name)
    global hdata

    lastitem=1;
    items={};
    for [item,key]=hdata.(host)
        if (isitem(item) && isfield(item,"yn"))
            Dh(:,lastitem)=item.yn;
            items{lastitem++}=item.key;
        end
    end
    newfigure();
    D=som_data_struct(Dh,'comp_names',items);
    M=som_make(D);
    M.name=name;
    h=som_show(M);
    #som_cplane(M,M.codebook(:,1));  
#    bmus = som_bmus(M,D);
#    som_trajectory(bmus);
    printplot(h,sprintf("som"));
end

global hdata;
global fig;
global file;

parseopts();

arg_list=getrestopts();
graphics_toolkit(getopt("gtoolkit"));

plotted=0;

for i=1:length(arg_list)

    clear("hdata");
    global hdata;
    file=arg_list{i};
    loaddata(file);

    if (isopt("cmplot"))
        for [host,hkey] = hdata
            if (ishost(host))
                cmplot(hkey);
                plotted=1;
            end
        end
    end

    if (isopt("hostplot"))
        for [host,hkey] = hdata
            if (ishost(host))
                hostplot(hkey);
                plotted=1;
            end
        end
    end

    if (isopt("corrplot"))
        for [host,hkey] = hdata
            if (ishost(host))
                correlplot(hkey);
                plotted=1;
            end
        end
    end

    if (isopt("somhosts"))
        somhosts(["Hosts (",xdate(hdata.minx),"-",xdate(hdata.maxx),")"]);
        plotted=1;
    end

    if (isopt("somitems"))
        for [host,hkey] = hdata
            if (ishost(host))
                somitems(hkey,[hkey,"(",xdate(hdata.minx),"-",xdate(hdata.maxx),")"]);
                plotted=1;
            end
        end
    end

    if (!plotted)
        for [host,hkey] = hdata
            if (ishost(host))
                cmplot(hkey);
            end
        end
        cmplot("all");
    end
end

mexit(0);