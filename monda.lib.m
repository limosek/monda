
warning("off");

function retval=xdate(x)
  retval=strftime("%Y-%m-%d %H:%M:%S",localtime(x));
endfunction;

function savedata(fle)
  global cm;
  global hdata;
  fprintf(stderr,"Saving file %s ",fle)
  save("-binary", fle);
  fprintf(stderr,"\n");
endfunction;

function loaddata(fle)
  global cm;
  global hdata;
  
  fprintf(stderr,"Loading file %s ",fle)
  load(fle);
  fprintf(stderr,"\n");
endfunction;

function ret=datetoseconds(dte)
  [tme,n]=strptime(dte,"@%s");
  if (n==0)
    [tme,n]=strptime(dte,"%Y-%m-%d %k:%M");
    if (n==0)
      [tme,n]=strptime(dte,"%Y-%m-%d");
    else
      ret=strftime("%s",tme);
    endif
    if (n==0) 
      ret=-1;
    endif
  else
    ret=strftime("%s",tme);
  endif
endfunction

function normalize()
    global hdata;
    delay=60;

    for [host, hkey] = hdata
     if (isstruct(host))
      for [item, key] = host
       if (isstruct(item))
         hdata.minx=min([hdata.minx,hdata.(hkey).(key).x]);
	 hdata.maxx=max([hdata.maxx,hdata.(hkey).(key).x]);
	 hdata.minx2=min([hdata.minx2,hdata.(hkey).(key).x(2:end)]);
	 hdata.maxx2=max([hdata.maxx2,hdata.(hkey).(key).x(1:end-1)]);
       end;
      end;
     end;
    end;
    startx=(round(hdata.minx2/delay)+1)*delay;
    endx=(round(hdata.maxx2/delay)-1)*delay;
    
    for [host, hkey] = hdata
     if (isstruct(host))
      fprintf(stderr,"\nNormalize %s (start=%s(%i),stop=%s(%i),values=%i):\n",hkey,xdate(startx),startx,xdate(endx),endx,round((endx-startx)/delay));
      for [item, key] = host
       if (isstruct(item))
        if (isfield(item,"xn")) 
          # Already normalized
          continue;
        end
	cols=columns(item.x);
	cols2=columns(item.y);
	hdata.(hkey).(key).xn=[startx:delay:endx];
	cols3=columns(hdata.(hkey).(key).xn);
	fprintf(stderr,"%s(%i,%i)>%i\n",item.key,cols,cols2,cols3);
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
       end;
      end;
    end;
    end;
    fprintf(stderr,"\n\n");
endfunction;

function hostinfo(host) 
  for [item, key] = host
	  if (isstruct(item))
	    fprintf(stdout,"Item %s: minx=%i,maxx=%i,miny=%i,maxy=%i,size=(%i=>%i)\n",item.key,min(item.x),max(item.x),min(item.y),max(item.y),columns(item.x),columns(item.xn));
	  end;
  end;
endfunction;

function hostsinfo(h) 
  for [host, hkey] = h
	  if (isstruct(host))
	    fprintf(stdout,"Host %s: minx=%s,maxx=%s,minx2=%s,maxx2=%s,\n",hkey,xdate(h.minx),xdate(h.maxx),xdate(h.minx2),xdate(h.maxx2));
	  end;
  end;
endfunction;


function cminfo(cm)
  for [host, hkey] = cm
    fprintf(stdout,"CM %s: %i/%i\n",hkey,columns(host),rows(host));
  end;
endfunction;

# Remove bad items (small change, ...)
function remove_bad(minchange) 
  global hdata;
      for [host, hkey] = hdata
       if (isstruct(host))
	fprintf(stderr,"%s ",hkey);
	for [item, key] = host
	  if (isstruct(item))
	    if (range(item.y)/max(item.y)<minchange)
	      fprintf(stderr,"%s:%s change less than %f, removing (range=%f,min=%f,max=%f)\n",hkey,item.key,minchange,range(item.y),min(item.y),max(item.y));
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
      fprintf(stderr,"Statistics: ");
      for [host, hkey] = hdata
       if (isstruct(host))
	fprintf(stderr,"%s ",hkey);
	for [item, key] = host
	  if (isstruct(item))
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
      fprintf(stderr,"\n");
endfunction;

function cmatrix()
      global hdata;
      global cm;
      fprintf(stderr,"Correlation:\n");
      for [host, hkey] = hdata
        if (isfield(cm,hkey))
	  # Corelation matrix already computed
          continue;
        end
       if (isstruct(host))
	fprintf(stderr,"%s\n",hkey);
	col1=1; 
	for [item1, key1] = host
	 if (isstruct(item1))
	  col2=1;
	  for [item2, key2] = host
	   if (isstruct(item2))
	    #fprintf(stderr,"%s(%s)<=>%s(%s)\n",item1.key,key1,item2.key,key2);
	    cols=min([columns(item1.xn),columns(item2.xn)]);
	    cm.(hkey)(col1,col2)=corr(item1.yn(1:cols),item2.yn(1:cols));
	    col2++;
	   end;
	  end;
	  col1++;
	 end;
	end;
       end;
      end;
      fprintf(stderr,"\n");
endfunction;

function hoststoany(varargin) 
  global hdata;
  
  for [host, hkey] = hdata
    if (isstruct(host) && !strcmp(hkey,"any") && (find(strcmp(varargin,hkey)>0) || length(varargin)==0))
	for [item, key] = host
	  if (isstruct(item))
	    hdata.any.(key)=item;
	    hdata.any.(key).key=[hkey,":",item.key];
	  end
	end
    end
  end;
endfunction

function hostplot(host)
      items=1;
      i=1;
      for [item, key] = host
       if (isstruct(item))
	items++;
       end;
      end;
      for [item, key] = host
       if (isstruct(item))
	subplot(items,2,i++);
	plot(item.xn,item.yn);
	subplot(items,2,i++);
	plot(item.xn,item.yn);
       end;
      end;
endfunction;

function cmplot(cm)
	x=1:rows(cm)+1;
	y=1:rows(cm)+1;
	cm(:,end+1)=zeros();
	cm(end+1,:)=zeros();
	surface(x,y,cm);
	title('Korelace dat monitorovaciho systemu');
	xlabel('Item');
	ylabel('Item');
	colorbar();
endfunction;
