
warning("off");
output_precision(10);
more off;

global opt;

addpath("./somtoolbox/");
addpath("./jsonlab/");
source("opts.lib.m");
source("an.lib.m");

aversion=10; # Analyze version
global aversion;

function dbg2(m)
    if (getopt("v")>2)
        fprintf(stdout,m);
    end
end

function dbg(m)
    if (getopt("v")>1)
        fprintf(stdout,m);
    end
end

function warn(m)
    if (getopt("v")>0)
        fprintf(stdout,m);
    end
end

function err(m)
    fprintf(stderr,m);
end

function retval=xdate(x)
  retval=strftime("%Y-%m-%d %H:%M:%S",localtime(x));
end

function mexit(code)  
  if (isopt("pause") && isopt("interactive"))
    fprintf(stdout,"Press any key\n");
    pause();
  end;
  if (isopt("profiling"))
    profshow(profile("info"),20);
  end
  exit(code);
end

function r=ishost(h)
  global hdata;

  if (isopt("hosts") && isfield(h,"hostname"))
    if (max(strcmp(h.hostname,getopt("hosts")))==0)
        r=0;
        return
    end
  end
  r=isfield(h,"ishost");
end

function r=isitem(i)
  if (isopt("excludeitems"))
    if (max(strcmp(i.key,getopt("excludeitems")))==0)
        r=0;
        return
    end
  end
  r=(isfield(i,"isitem") && isfield(i,"x"));
end

function r=istrigger(i)
  r=isfield(i,"istrigger");
end

function r=isnormalized(i)
  r=isfield(i,"isnormalized");
end

function r=iseventdata(e)
  r=isfield(e,"iseventdata");
end

function r=hasevents(i)
  r=isfield(i,"events");
end

function ev=geteventsdata(i)
  j=1;
  for [e, key] = i
    if (iseventdata(e))
      ev(j++,:)=e.yn;
    end
  end
end

function r=checkhversion(h,ver)
  if (isfield(h,"version"))
    r=(h.version>=ver);
  else
    r=(0>=ver);
  end
end

function r=checkaversion(h,ver)
  if (isfield(h,"aversion"))
    r=(h.aversion>=ver);
  else
    r=(0>=ver);
  end
end

function savedata(fle)
  global cm;
  global hdata;
  global aversion;

  hdata.aversion=aversion;
  fprintf(stdout,"Saving file %s ",fle)
  save("-binary", fle);
  fprintf(stdout,"\n");
end

function loaddata(fle,version)
  global cm;
  global hdata;

  dbg(sprintf("Loading file %s ",fle));
  load(fle);
  if (exist("version", "var") == 1)
    if (!checkaversion(hdata,version))
       warn(sprintf("Data does not have required analyze version (needed %i)!",version));
    end;
  end
  preprocess();
  dbg("\n");
end

function loadsrc(fle)
  global cm;
  global hdata;

  dbg(sprintf("Loading file %s ",fle))
  source(fle);
  dbg("\n");
end

function jsonsave(fle)
    global hdata;
    global cm;

    tmp=hdata;
    tmp.cm=cm;
    fprintf(stdout,"Saving json file %s ",fle)
    savejson("",tmp,'ExcludeNames',{'xn','yn','x','y'},'FileName',fle);
    fprintf(stdout,"\n");
end

function ret = datetoseconds(dte)
    [tme, n] = strptime(dte, "@%s");
    if (n == 0)
        [tme, n] = strptime(dte, "%Y-%m-%d %k:%M");
    if (n == 0)
        [tme, n] = strptime(dte, "%Y-%m-%d");
    else
        ret = strftime("%s", tme);
    endif
    if (n == 0)
        ret = -1;
    endif
    else
        ret = strftime("%s", tme);
    endif
end

function h=findhost(host)
  global hdata;
  for [hst,hname] = hdata
    if (ishost(hst))
      if (strcmp(hname,host))
        h=hst;
        return;
      end
    end
  end
  fprintf(stderr,"Host %s not found!\n",host);
end

% Finditem 
% 'host:item'
% 'host','item'
% hdata.host,'item'
function i=finditem(varargin)
  if (length(varargin)==1)
    i=findhitem(varargin{1});
    return
  end
  host=varargin{1};
  key=varargin{2};
  if (!ishost(host))
    host=findhost(host);
  end
  for [item,k] = host
    if (isitem(item))
      if (strcmp(item.key,key))
        i=item;
        return;
      end
    end
  end
  fprintf(stderr,"Item %s not found!\n",key);
end

function i=findhitem(hi)
  global hdata;

  s=strsplit(hi,":");
  h=s(1);
  hi=s(2);
  for [host, hkey] = hdata
    if (!ishost(host) || !strcmp(hkey,h))
        continue;
    end
    for [item,k] = host
        if (isitem(item))
            if (strcmp(item.key,hi))
                i=item;
                return;
            end
        end
     end
  end
  fprintf(stderr,"Item %s not found!\n",hi);
end

function idx=findcmindex(host,item)
    item=finditem(host,item);
    idx=item.index;
end

function item=cmindex(idx)
    global cm;
    global hdata;
    
    item=finditem(hdata.itemindex{idx});
end

function hostinfo(host,hkey)
  for [item, key] = host
	  if (isitem(item))
            if (isfield(item,"xn"))
                warn(sprintf("  Item %s:%s minx=%i,maxx=%i,miny=%i,maxy=%i,size=(%i=>%i),seconds=%i,cv=%f\n",hkey,item.key,min(item.x),max(item.x),min(item.y),max(item.y),columns(item.x),columns(item.xn),max(item.x)-min(item.x),coeffvar(item.y)));
            else
                warn(sprintf("  Item %s:%s minx=%i,maxx=%i,miny=%i,maxy=%i,size=%i,seconds=%i,cv=%f\n",hkey,item.key,min(item.x),max(item.x),min(item.y),max(item.y),columns(item.x),max(item.x)-min(item.x),coeffvar(item.y)));
            end
	  end;
  end;
end

function hostsinfo(h)
  allitems=0;
  for [host, hkey] = h
      items=0;
      if (ishost(host))
       for [item, key] = host
        if (isfield(item,'x'))
          items++;
          allitems++;
        end
       end;
      end;
	  if (ishost(host))
	    fprintf(stdout,"Host %s: items=%i,minx=%s(%i),maxx=%s(%i),minx2=%s,maxx2=%s\n",hkey,items,xdate(h.minx),h.minx,xdate(h.maxx),h.maxx,xdate(h.minx2),xdate(h.maxx2));
	  end;
  end;
end

function cminfo()
  global hdata;
  for [host, hkey] = hdata
    if (ishost(host))
        sortvec=host.sortvec;
        for i=1:rows(sortvec)
            dbg(sprintf("  Corr %f: %s<>%s\n",sortvec(i,3),hdata.itemindex{sortvec(i,1)},hdata.itemindex{sortvec(i,2)}));
        end
    end
  end;
end

function e=coeffvar(y)
    e=std(y)/mean(y);
end

# Remove bad items (small change, ...)
function remove_bad()
  global hdata;
    gcv=getopt("cv");
    dbg(sprintf("Remove bad (cv<%f): ",gcv));
      for [host, hkey] = hdata
       if (ishost(host))
	for [item, key] = host
	  if (isitem(item))
            r=range(item.y);
            mx=max(item.y);
            mn=min(item.y);
            cv=coeffvar(item.y);
            dbg2(sprintf("(%s:%s range=%f,min=%f,max=%f,cv=%f),",hkey,item.key,r,mn,mx,cv));
	    if (cv<gcv)
	      dbg(sprintf("!%s:%s,",hkey,item.key));
	      hdata.(hkey).(key)=[];
            endif
	  end
	end
       end
      end
      dbg("\n");
endfunction

function preprocess(varargin)
    global hdata;
    global cm;

    if (length(varargin)>0)
        pp=varargin{1};
    else
        pp=getopt("preprocess");
    end

    if (!isfield(hdata,"version"))
        for [host, hkey] = hdata
            if (isstruct(host) && !strcmp(hkey,"triggers"))
                hdata.(hkey).ishost=1;
                for [item, key] = host
                    if (isfield(item,"key"))
                        hdata.(hkey).(key).isitem=1;
                    end
                end
            end
        end
    end

    if (bitget(pp,1))
        dbg("Preprocess remove_bad\n");
        remove_bad();
    end
    if (bitget(pp,2))
        dbg("Preprocess indexes\n");
        indexes();
    end
    if (bitget(pp,3) && isstruct("cm"))
        hdata.cm=cm;
        clear(cm);
    end
end

function r=indexes()
    global hdata;
    itemid=1;
    hostid=1;
   
    if (isfield(hdata,"itemindex"))
        r=length(hdata.itemindex);
    else
        r=0;
    end
    for [host, hkey] = hdata
     if (ishost(host))
      if (r==0)
        hdata.hostindex{hostid++}=hkey;
      end
      hdata.(hkey).hostname=hkey;
      hdata.(hkey).minindex=itemid;
      for [item, key] = host
       if (isitem(item))
         if (r==0)
            hdata.(hkey).(key).index=itemid;
            hdata.itemhindex{itemid}=hkey;
            hdata.itemkindex{itemid}=key;
            hdata.itemindex{itemid++}=[hkey,":",item.key];
         end
       end
      end
      hdata.(hkey).maxindex=itemid;
      #dbg2(sprintf("%s(min=%i,max=%i),",hkey,hdata.(hkey).minindex,hdata.(hkey).maxindex));
     end
    end
    r=itemid;
endfunction

function hoststoany(varargin)
  global hdata;
  for [host, hkey] = hdata
    if (ishost(host) && !strcmp(hkey,"any"))
      if (find(strcmp(varargin,hkey)>0) || find(strcmp(varargin,"all")>0))
	for [item, key] = host
	  if (isitem(item))
	    hdata.any.(key)=item;
	    hdata.any.(key).key=[hkey,":",item.key];
	  end
	end
     end;
    end
  end;
endfunction

