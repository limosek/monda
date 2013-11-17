
warning("off");
output_precision(10);
more off;

addpath("./somtoolbox/");
addpath("./jsonlab/");

aversion=1; # Analyze version
global aversion;

function retval=xdate(x)
  retval=strftime("%Y-%m-%d %H:%M:%S",localtime(x));
endfunction;

function r=ishost(h)
  r=isfield(h,"ishost");
end

function r=isitem(i)
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

  fprintf(stdout,"Loading file %s ",fle)
  load(fle);
  if (exist("version", "var") == 1)
    if (!checkaversion(hdata,version))
       fprintf(stderr,"Data does not have required analyze version (needed %i)!",version);
    end;
  end
  fprintf(stdout,"\n");
end

function jsonsave(fle)
    global hdata;
    
    savejson("",hdata,'ExcludeNames',{'xn','yn','x','y'},'FileName',fle);
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

function i=finditem(host,key)
  for [item,k] = host
    if (isitem(item))
      if (strcmp(item.key,key))
        i=item;
        return;
      end
    end
  end
end

function yn=eventnormalize(e,xn)
   x1=find(xn<e(1));
   x2=find(xn>=e(1));
   yn(x1)=1-e(2);
   yn(x2)=e(2);
end

function normalize(delay)
  global hdata;

    if (isnormalized(hdata))
      return;
    end

    for [host, hkey] = hdata
     if (ishost(host))
      for [item, key] = host
       if (isitem(item))
         minx=min([hdata.minx,hdata.(hkey).(key).x]);
         maxx=max([hdata.maxx,hdata.(hkey).(key).x]);
         xy=transpose(sortrows(transpose([item.x;item.y])));
         hdata.(hkey).(key).x=xy(1,:);
         hdata.(hkey).(key).y=xy(2,:);
         hdata.minx=minx;
         hdata.maxx=maxx;
         e=columns(xy(1,:));
         hdata.minx2=min([hdata.minx2,hdata.(hkey).(key).x(2:e)]);
         hdata.maxx2=max([hdata.maxx2,hdata.(hkey).(key).x(1:e-1)]);
       end;
      end;
     end;
    end;
    startx=(round(hdata.minx2/delay)+1)*delay;
    endx=(round(hdata.maxx2/delay)-1)*delay;

    for [host, hkey] = hdata
     if (ishost(host))
      fprintf(stdout,"\nNormalize %s (start=%s(%i),stop=%s(%i),values=%i):\n",hkey,xdate(startx),startx,xdate(endx),endx,round((endx-startx)/delay));
      for [item, key] = host
      if (isitem(item))
	if (isnormalized(host))
	  # Already normalized
	  continue;
	end
	cols=columns(item.x);
	cols2=columns(item.y);
	xn=[startx:delay:endx];
	hdata.(hkey).(key).xn=xn;
	cols3=columns(xn);
	fprintf(stdout,"%s(%i,%i)>%i ",item.key,cols,cols2,cols3);
	hdata.(hkey).(key).yn=[];
	for x=hdata.(hkey).(key).xn
	    index=lookup(hdata.(hkey).(key).x,x);
	    if (index<=0)
	      index=1;
	    end;
	    if (index>(cols-1))
	      index=cols-1;
	    end;
	    y0=hdata.(hkey).(key).y(index);
	    y1=hdata.(hkey).(key).y(index+1);
	    x0=hdata.(hkey).(key).x(index);
	    x1=hdata.(hkey).(key).x(index+1);
	    hdata.(hkey).(key).yn(end+1)=y0+(y1-y0)*(x-x0)/(x1-x0);
	end;
	if (hasevents(item))
	  for ei = 1:rows(item.events)
	    e=item.events(ei,:);
	    ekey=sprintf("e%i",e(1));
	    hdata.(hkey).(ekey).yn=eventnormalize(e,xn);
	    hdata.(hkey).(ekey).iseventdata=1;
	    fprintf(stdout,"(event %i(value %i, priority %i)) ",e(1),e(2),e(3));
	  end
	end
	fprintf(stdout,"\n");
      end;
     end;
     hdata.(hkey).isnormalized=1;
   end;
  end;
  hdata.isnormalized=1;
  fprintf(stdout,"\n\n");
end

function hostinfo(host)
  for [item, key] = host
	  if (isitem(item))
	    fprintf(stdout,"Item %s: minx=%i,maxx=%i,miny=%i,maxy=%i,size=(%i=>%i),seconds=%i\n",item.key,min(item.x),max(item.x),min(item.y),max(item.y),columns(item.x),columns(item.xn),max(item.x)-min(item.x));
	  end;
  end;
  fprintf(stdout,"\n");
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
	    fprintf(stdout,"Host %s: items=%i,minx=%s(%i),maxx=%s(%i),minx2=%s,maxx2=%s,\n",hkey,items,xdate(h.minx),h.minx,xdate(h.maxx),h.maxx,xdate(h.minx2),xdate(h.maxx2));
	  end;
  end;
  fprintf(stdout,"\n\n");
end

function cminfo(cm)
  for [host, hkey] = cm
    fprintf(stdout,"CM %s: %i/%i\n",hkey,columns(host),rows(host));
  end;
end

# Remove bad items (small change, ...)
function remove_bad(minchange)
  global hdata;
      for [host, hkey] = hdata
       if (ishost(host))
	fprintf(stdout,"%s ",hkey);
	for [item, key] = host
	  if (isitem(item))
	    if (range(item.y)/max(item.y)<minchange)
	      fprintf(stdout,"%s:%s change less than %f, removing (range=%f,min=%f,max=%f)\n",hkey,item.key,minchange,range(item.y),min(item.y),max(item.y));
	      hdata.(hkey).(key)=[];
            endif
	  end
	end
       end
      end
endfunction

function smatrix()
      global hdata;
      global minchange;
      fprintf(stdout,"Statistics: ");
      for [host, hkey] = hdata
       if (ishost(host))
	fprintf(stdout,"%s ",hkey);
	for [item, key] = host
	  if (isitem(item))
		hdata.(hkey).(key).std=std(item.y);
		hdata.(hkey).(key).stdn=std(item.yn);
		hdata.(hkey).(key).max=max(item.y);
		hdata.(hkey).(key).maxn=max(item.yn);
		hdata.(hkey).(key).min=min(item.y);
		hdata.(hkey).(key).minn=min(item.yn);
		hdata.(hkey).(key).var=var(item.y);
		hdata.(hkey).(key).varn=var(item.yn);
		hdata.(hkey).(key).delta=max(item.y)-min(item.y);
		hdata.(hkey).(key).deltan=max(item.yn)-min(item.yn);
		hdata.(hkey).(key).range=range(item.y);
		hdata.(hkey).(key).rangen=range(item.yn);
		hdata.(hkey).(key).chg=hdata.(hkey).(key).range/hdata.(hkey).(key).max;
		hdata.(hkey).(key).chgn=hdata.(hkey).(key).rangen/hdata.(hkey).(key).maxn;
		hdata.(hkey).(key).chgn=range(item.yn);
		hdata.(hkey).(key).avg=mean(item.y);
		hdata.(hkey).(key).avgn=mean(item.yn);
		hdata.(hkey).(key).median=median(item.y);
		hdata.(hkey).(key).mediann=median(item.yn);
		hdata.(hkey).(key).mode=mode(item.y);
		hdata.(hkey).(key).moden=mode(item.yn);
	  end;
	end;
       end;
      end;
      fprintf(stdout,"\n");
end

function itemindex()
    global hdata;
    itemid=1;

    for [host, hkey] = hdata
     if (ishost(host))
      for [item, key] = host
       if (isitem(item))
         hdata.(hkey).(key).index=itemid;
         hdata.itemhindex{itemid}=hkey;
         hdata.itemkindex{itemid}=key;
         hdata.itemindex{itemid++}=[hkey,":",item.key];
       end
      end
     end
    end
endfunction

function cmatrix()
      global hdata;
      global cm;

      itemindex();
      fprintf(stdout,"Correlation:\n");
      for [host, hkey] = hdata
	if (isfield(cm,hkey))
	  # Corelation matrix already computed
	  continue;
	end
       if (ishost(host))
	fprintf(stdout,"%s\n",hkey);
	col1=1;
	for [item1, key1] = host
	if (isitem(item1))
	  col2=1;
	  for [item2, key2] = host
	   if (isitem(item2))
	    cols=min([columns(item1.xn),columns(item2.xn)]);
	    cm.(hkey)(item1.index,item2.index)=corr(item1.yn(1:cols),item2.yn(1:cols));
	    col2++;
	   end;
	  end;
	  col1++;
	 end;
	end;
	#cm.(hkey)=snip(cm.(hkey),nan);
       end;
      end;
      fprintf(stdout,"\n");
endfunction;

function cmtovector(limit,i1,i2)
  global hdata;
  global cm;

  for [host, hkey] = hdata
    if (ishost(host))
      k=1;
      tmp=cm.(hkey);
      #tmp(isnan(tmp))=0;
      tmpvec=[];
      sortvec=[];
      maxri=1;
      maxci=1; # Index of maximum value in column
      iterations1=0;
      iterations2=0;
      if (!exist("i1", "var") == 1)
        i1=columns(tmp)*10;
      end
      if (!exist("i2", "var") == 1)
        i2=100;
      end
      while (abs(max(max(tmp)))>limit && iterations1<i1 && iterations2<i2)
       iterations1++;
       maxv=max(max(abs(abs(tmp()))));
       [maxri,maxci]=find(abs(tmp)==maxv);
       maxri=maxri(1);
       maxci=maxci(1);
       if (maxv==1)
          if (maxri!=maxci)
            fprintf(stderr,"%s and %s are same data??\n",hdata.itemindex{maxri},hdata.itemindex{maxci})
          end
          tmp(maxri,maxci)=0;
       else
          iterations2++;
       end
       tmpvec(maxri,maxci)=maxv;
       sortvec(k++,:)=[maxri,maxci];
       if (maxri!=maxci)
          fprintf(stdout,"%i: %s(%i)<>%s(%i): %f\n",k,hdata.itemindex{maxri},maxri,hdata.itemindex{maxci},maxci,maxv);
       end
       tmp(maxri,maxci)=0;
      end
      if (iterations1>=i1 || iterations2>=i2)
        fprintf(stderr,"More results available, all iterations(%i of %i, %i of %i) looped!\n",iterations1,i1,iterations2,i2);
      end
      hdata.(hkey).cmvec=tmpvec;
      hdata.(hkey).sortvec=sortvec;
    end
  end
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
