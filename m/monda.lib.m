global opt;

warning('off');
output_precision(10);
addpath("./m/somtoolbox");
addpath("./m/jsonlab/");
source(file_in_loadpath("opts.lib.m"));
source(file_in_loadpath("an.lib.m"));

cache.start=1;

parseopts();

if (isopt("debug"))
    debug_on_interrupt(1);
    debug_on_warning(1);
    debug_on_error(1);
end

if (getopt("v")<2)
    warning('off');
end

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

function retval=xdate2(x)
  retval=strftime("%Y_%m_%d_%H_%M_%S",localtime(x));
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

function r=scmp(s1,s2)
     [s, e, te, m, t, nm] =regexp(s1,s2);
     r=0;
     for i=1:length(s)
        if (s{i}==1)
            r=1;
            return
        end
     end
end

function r=ishost(h)
  global hdata;

  r=0;
  if (!isfield(h,"ishost"))
    return
  end
  if (isfield(h,"hostname"))
    if (isopt("excludehosts"))
        if (scmp(h.hostname,getopt("excludehosts")))
            return
        end
    end
    if (isopt("hosts"))
        if (!scmp(h.hostname,getopt("hosts")))
            return
        end
    end
  end
  r=1;
end

function r=isitem(i)
  r=0;
  if (!isfield(i,"isitem"))
    return
  end
  if (isfield(i,"key"))
    if (isopt("excludeitems"))
        if (scmp(i.key,getopt("excludeitems")))
            return
        end
    end
    if (isopt("items"))
        if (!scmp(i.key,getopt("items")))
            return
        end
    end
  end
  r=(isfield(i,"x") && (!isfield(i,"isbad")||isopt("baditems")));
end

# Test if item is good or bad
function r=isbitem(i)
  r=0;
  if (!isfield(i,"isitem"))
    return
  end
  if (isfield(i,"key"))
    if (isopt("excludeitems"))
        if (scmp(i.key,getopt("excludeitems")))
            return
        end
    end
    if (isopt("items"))
        if (!scmp(i.key,getopt("items")))
            return
        end
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
  fprintf(stdout,"Saving file %s ",fle);
  if (exist(fle,"file") && !isopt("f"))
    err(sprintf("Not overwriting. Use -f!\n",fle));
    mexit(1);
  end
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

function jsonsave(fle,data)
    dbg(sprintf("Saving json file %s ",fle));
    savejson("",data,'ExcludeNames',{'xn','yn','x','y'},'FileName',fle);
    dbg("\n");
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
  warn(sprintf("Item %s not found!\n",key));
end

function r=gethostsforitem(key)
    global hdata;
    r={};
    idx=1;
    for [host,hkey] = hdata
        if (ishost(host))
            found=0;
            for [item,ikey] = host
                if (isitem(item))
                    if (strcmp(item.key,key))
                        found=1;
                        r{idx}=hkey;
                    end
                end
            end
            if (found)
                idx++;
            end
        end
    end
end

function disableitems(keys,ids)
    global hdata;
    for [host,hkey] = hdata
        if (ishost(host))
            for [item,ikey] = host
                if (isitem(item))
                    if (max(strcmp(item.key,keys))>0)
                        hdata.(hkey).(ikey).isbad=1;
                    end
                end
            end
        end
    end
    hdata.itemdindex=keys;
end

function tocache(item,value)
    global cache;
    cache.(item)=value;
end

function value=fromcache(item)
    global cache;
    if (isfield(cache,item))
        value=cache.(item);
    else
        value=[];
    end
end

function i=findhitem(hi)
  global hdata;
  global cache;

  #if (i=fromcache(hi))
  #  return
  #end
  s=strsplit(hi,":");
  h=s(1);
  itm=s(2);
  for [host, hkey] = hdata
    if (!strcmp(hkey,h) || !ishost(host))
        continue;
    end
    for [item,k] = host
        if (isitem(item))
            if (strcmp(item.key,itm))
                i=item;
                #tocache(hi,i);
                return;
            end
        end
     end
  end
  i=[];
  #tocache(hi,i);
  #warn(sprintf("Item %s not found!\n",hi));
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
                warn(sprintf("  Item %i(%s:%s) minx=%i,maxx=%i,miny=%i,maxy=%i,size=(%i=>%i),seconds=%i,cv=%f\n",item.index,hkey,item.key,min(item.x),max(item.x),min(item.y),max(item.y),columns(item.x),columns(item.xn),max(item.x)-min(item.x),coefvar(item.y)));
            else
                warn(sprintf("  Item %i(%s:%s) minx=%i,maxx=%i,miny=%i,maxy=%i,size=%i,seconds=%i,cv=%f\n",item.index,hkey,item.key,min(item.x),max(item.x),min(item.y),max(item.y),columns(item.x),max(item.x)-min(item.x),coefvar(item.y)));
            end
	  end;
  end;
end

function triggersinfo()
   global hdata;
   for [host, hkey] = hdata
      if (istrigger(host))
	 dbg(sprintf("Trigger '%s': '%s'\n",host.description,host.expression));
      end;
  end;
end

function hostsinfo(h)
  allitems=0;
  for [host, hkey] = h
      items=0;
      if (ishost(host))
       for [item, key] = host
        if (isitem(item))
          items++;
          allitems++;
        end
       end;
      end;
      if (ishost(host))
	 err(sprintf("Host %s: items=%i,minindex=%i,maxindex=%i,minx=%s(%i),maxx=%s(%i),minx2=%s,maxx2=%s\n",hkey,items,host.minindex,host.maxindex,xdate(h.minx),h.minx,xdate(h.maxx),h.maxx,xdate(h.minx2),xdate(h.maxx2)));
         hostinfo(host,hkey);
      end;
  end;
  triggersinfo();
end

function cminfo()
  global hdata;
  for [host, hkey] = hdata
    if (ishost(host))
        warn(sprintf("Host %s: cm=(%u,%u), sortvec=(%u), cmvec=(%u,%u)\n",hkey,rows(host.cm),columns(host.cm),rows(host.sortvec),rows(host.cmvec),columns(host.cmvec)));
        sortvec=host.sortvec;
        for i=1:rows(sortvec)
            item1id=sortvec(i,1);
            item2id=sortvec(i,2);
            c=sortvec(i,3);
            if (min([item1id,item2id])<host.minindex)
                item1id=item1id+host.minindex-1;
                item2id=item2id+host.minindex-1;
            end
            if (item1id!=item2id && item1id<length(hdata.itemindex) && item2id<length(hdata.itemindex))
                dbg(sprintf("  Corr %f: %s(%i)<>%s(%i)\n",c,hdata.itemindex{item1id},item1id,hdata.itemindex{item2id},item2id));
            end
        end
    end
  end
  if (isfield(hdata,"sortvec"))
    sortvec=hdata.sortvec;
    warn(sprintf("Host all: cm=(%u,%u), sortvec=(%u), cmvec=(%u,%u)\n",rows(hdata.cm),columns(hdata.cm),rows(hdata.sortvec),rows(hdata.cmvec),columns(hdata.cmvec)));
    for i=1:rows(sortvec)
        item1id=sortvec(i,1);
        item2id=sortvec(i,2);
        c=sortvec(i,3);
        if (item1id!=item2id && item1id<length(hdata.itemindex) && item2id<length(hdata.itemindex))
            if (!strcmp(hdata.itemhindex{item1id},hdata.itemhindex{item2id}))
                dbg(sprintf("  XCorr %f: %s(%i)<>%s(%i)\n",c,hdata.itemindex{item1id},item1id,hdata.itemindex{item2id},item2id));
            end
        end
     end
  end
end

function e=coefvar(y)
    e=std(y)/mean(y);
end

# Remove bad items (small change, ...)
function remove_bad()
  global hdata;
    gcv=getopt("cv");
    dbg(sprintf("Remove bad (cv<%f):\n",gcv));
      for [host, hkey] = hdata
       if (ishost(host))
	for [item, key] = host
	  if (isitem(item))
            r=range(item.y);
            mx=max(item.y);
            mn=min(item.y);
            cv=coefvar(item.y);
            #dbg2(sprintf("(%s:%s range=%f,min=%f,max=%f,cv=%f)\n",hkey,item.key,r,mn,mx,cv));
	    if (isnan(cv) || cv<gcv)
	      dbg(sprintf("!%s:%s\n",hkey,item.key));
	      hdata.(hkey).(key).isbad=1;
            endif
	  end
	end
       end
      end
      
      if (isopt("shareditems"))
        dbg(sprintf("Remove nonshared...\n"));
        rmidx=1;
        shidx=1;
        if (!isfield(hdata,"itemdindex"))
            hdata.itemdindex={};
        end
        for i=1:length(hdata.itemindex)
            item=hdata.itemindex{i};
            ikey=hdata.itemkindex{i};
            hkey=hdata.itemhindex{i};
            s=strsplit(item,":");
            ikey=s(2){:};
            if (max(strcmp(item,hdata.itemdindex))>0)
                dbg2(sprintf("!%s (not shared, available only on %u hosts)\n",ikey,length(h)));
                rmkeys{rmidx}=ikey;
                rmids(rmidx++)=i;
                continue;
            end
            h=gethostsforitem(ikey);
            if (length(h)!=length(hdata.hostindex))
                    dbg2(sprintf("!%s (not shared, available only on %u hosts)\n",ikey,length(h)));
                    rmkeys{rmidx}=ikey;
                    rmids(rmidx++)=i;
                    continue;
            else
                    hdata.sharedindex{shidx++}=ikey;
            end
        end
        if (rmidx>1)
            disableitems(rmkeys,rmids);
        end
      end
end

function preprocess(varargin)
    global hdata;
    global cm;

    if (length(varargin)>0)
        pp=varargin{1};
    else
        pp=getopt("preprocess");
    end

    if (!isfield(hdata,"version") || bitget(pp,4))
        for [host, hkey] = hdata
            if (isstruct(host) && !isfield(host,"istrigger") && !strcmp(hkey,"triggers"))
                hdata.(hkey).ishost=1;
                for [item, key] = host
                    if (isfield(item,"key"))
                        hdata.(hkey).(key).isitem=1;
                    end
                end
            end
        end
    end

    if (bitget(pp,4))
        dbg("Preprocess minmax\n");
        minmax();
    end
    if (bitget(pp,2))
        dbg("Preprocess indexes\n");
        indexes();
    end
    if (bitget(pp,1))
        dbg("Preprocess remove_bad\n");
        remove_bad();
    end
    if (bitget(pp,3) && isstruct(cm))
        hdata.cm=cm;
        clear("cm");
    end
end

# Reindex correlation matrix and correlation vectors 
# old indexes itemindex
# new idnexes in hdata
function ocm=reindexcm(cm,itemindex)
    global hdata;
    
    for i=1:length(itemindex)
       ditem1=findhitem(itemindex{i});
       sitem1=itemindex{i};
       for j=1:length(itemindex)
            sitem2=itemindex{j};
            if (!(ditem2=fromcache(j)))
                ditem2=findhitem(itemindex{j});
                tocache(j,ditem2);
            end
            #dbg(sprintf("%i,%i\n",i,j));
            if (isitem(ditem1) && isitem(ditem2))
                #dbg(sprintf("%i>%i \n",ditem1.index,ditem2.index));
                ocm(ditem1.index,ditem2.index)=cm(i,j);
            end
        end
    end
end

# Find minimum and maximums for sets
# If time is specified in commandline, remove time not in range
function minmax(varargin)
    global hdata;

    minx=time();
    maxx=0;
    minx2=minx;
    maxx2=minx;
    mintime=0;
    maxtime=0;
    
    if (length(varargin)==0 && !isfield(hdata,"minx") && (isopt("mintime")||isopt("maxtime")))
        minmax(1);   # Run minmax to get minx and maxx first
    end
    if (isopt("mintime") && isfield(hdata,"minx"))
        mintime=strtotime(getopt("mintime"),hdata.minx);
    end
    if (isopt("maxtime") && isfield(hdata,"maxx"))
        maxtime=strtotime(getopt("maxtime"),hdata.minx);
    end
    for [host, hkey] = hdata
     if (ishost(host))
      for [item, key] = host
       if (isitem(item))
            if (mintime>0)
                rmcols1=find(item.x<mintime);
                item.x(rmcols1)=[];
                item.y(rmcols1)=[];
                if (isfield(item,"xn"))
                    rmcols2=find(item.xn<mintime);
                    item.xn(rmcols2)=[];
                    item.yn(rmcols2)=[];
                else
                    rmcols2=0;
                end
                hdata.(hkey).(key)=item;
                dbg2(sprintf("Removed %i,%i values from %s due to mintime.\n",columns(rmcols1),columns(rmcols2),item.key));
            end
            if (maxtime>0)
                rmcols1=find(item.x>maxtime);
                item.x(rmcols1)=[];
                item.y(rmcols1)=[];
                if (isfield(item,"xn"))
                    rmcols2=find(item.xn>maxtime);
                    item.xn(rmcols2)=[];
                    item.yn(rmcols2)=[];
                else
                    rmcols2=0;
                end
                hdata.(hkey).(key)=item;
                dbg2(sprintf("Removed %i,%i values from %s due to maxtime.\n",columns(rmcols1),columns(rmcols2),item.key));
            end
            if (columns(item.x)<10)
                hdata.(hkey).(key).isbad=1;
                continue;
            end
            minx=min([item.x,minx]);
            maxx=max([item.x,maxx]);
            minx2=max([item.x(2),minx2,minx]);
            maxx2=min([item.x(columns(item.x)-1),maxx2,maxx]);
       end
      end
      hdata.minx=minx;
      hdata.maxx=maxx;
      hdata.minx2=minx2;
      hdata.maxx2=maxx2;
     end
    end
    dbg2(sprintf("(minx=%i,minx2=%i,maxx=%i,maxx2=%i)\n",minx,minx2,maxx,maxx2));
end

function r=indexes()
    global hdata;
    itemid=1;
    hostid=1;
   
    hdata.itemhindex={};
    hdata.itemkindex={};
    hdata.itemindex={};
    for [host, hkey] = hdata
     if (ishost(host))
      hdata.hostindex{hostid++}=hkey;
      hdata.(hkey).hostname=hkey;
      hdata.(hkey).minindex=itemid;
      for [item, key] = host
       if (isitem(item))
         hdata.(hkey).(key).index=itemid;
         hdata.itemhindex{itemid}=hkey;
         hdata.itemkindex{itemid}=key;
         hdata.itemindex{itemid++}=[hkey,":",item.key];
         dbg2(sprintf("%s(%i)\n",[hkey,":",item.key],itemid-1));
       end
      end
      hdata.(hkey).maxindex=itemid-1;
      dbg2(sprintf("%s(min=%i,max=%i),",hkey,hdata.(hkey).minindex,hdata.(hkey).maxindex));
     end
    end
    dbg(sprintf("Max index: %i\n",itemid-1));
    r=itemid;
end


