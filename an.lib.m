
function normalize()
  global hdata;

    delay=getopt("delay");
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

    xn=1;
    dbg("Normalize: ");
    for [host, hkey] = hdata
     if (ishost(host))
      dbg(sprintf("%s,",hkey));
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
        first=item.y(1);
        last=item.y(columns(item.y));
        item.y=[first,item.y,last];
        item.x=[startx,item.x,endx];
	dbg2(sprintf("%s(%i,%i)>%i ",item.key,cols,cols2,cols3));
        yn=interp1(item.x,item.y,xn);
        hdata.(hkey).(key).yn=yn;
	if (hasevents(item))
	  for ei = 1:rows(item.events)
	    e=item.events(ei,:);
	    ekey=sprintf("e%i",e(1));
	    hdata.(hkey).(ekey).yn=eventnormalize(e,xn);
	    hdata.(hkey).(ekey).iseventdata=1;
	    dbg(sprintf("(event %i(value %i, priority %i)) ",e(1),e(2),e(3)));
	  end
	end
      end;
     end;
     hdata.(hkey).isnormalized=1;
   end;
  end;
  hdata.isnormalized=1;
  if (xn==1)
    err("No usable items! Exiting!\n");
    exit;
  else
    hdata.ysize=columns(xn);
  end
  dbg("\n");
end

function yn=eventnormalize(e,xn)
   x1=find(xn<e(1));
   x2=find(xn>=e(1));
   yn(x1)=1-e(2);
   yn(x2)=e(2);
end

function smatrix()
      global hdata;
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

function c=cmhost(minindex,maxindex,time_start,time_to)
    global hdata;

    if (exist("time_start","var") && exist("time_to","var") && (time_start!=-1))
        i=1;
        do
            item=finditem(hdata.itemindex{i});
            i++;
        until (isitem(item))
        sx=lookup(item.xn,time_start);
        ex=loolup(item.yn,time_to);
        ysize=columns(item.xn(sx:ex));
    else
        ysize=hdata.ysize;
    end
    y=rand(ysize,maxindex-minindex);
    for i=minindex:maxindex
        item=finditem(hdata.itemindex{i});
        if (isitem(item))
            y(:,i-minindex+1)=item.yn;
            dbg2(sprintf("CM: %s(%i)\n",hdata.itemindex{i},i));
        else
            dbg2(sprintf("CM!: %s(%i)\n",hdata.itemindex{i},i));
        end
    end
    c=corr(y);
    if (length(find(isnan(c))>0))
        [rn,cn]=find(isnan(c));
        for i=1:rn
            for j=1:cn
                warn(sprintf("Correlation error %s(idx=%i,cv=%f)<>%s(idx=%i,cv=%f)!\n",hdata.itemindex{rn(i)},rn(i),coefvar(y(i,:)),hdata.itemindex{cn(j)},cn(j),coefvar(y(j,:))));
            end
        end
    end
end

function cmkey=cmatrix(time_start,time_to)
      global hdata;

      if (!exist("time_start","var") || !exist("time_to","var"))
        time_start=-1;
        time_to=-1;
        cmkey="cm";
      else
        cmkey=["cm_",xdate2(time_start)];
      end
      numitems=length(hdata.itemindex);
      dbg("Correlation1: ");
      if (isfield(hdata,"cm"))
        tmpcm=hdata.cm;
      else
        tmpcm=sparse(zeros(numitems,numitems));
      end
      timestart=time();
      maxtime=getopt("cmaxtime1");
      for [host, hkey] = hdata
       if (ishost(host))
        c=cmhost(host.minindex,host.maxindex,time_start,time_to);
        [row,col]=size(c);
        tmpcm(host.minindex:host.minindex+row-1,host.minindex:host.minindex+row-1)=c;
       end
      end
      hdata.(cmkey)=tmpcm;
end;

function cmatrixall(time_start,time_to)
    global hdata;

    if (!exist("time_start","var") || !exist("time_to","var"))
        time_start=-1;
        time_to=-1;
    end
    numitems=length(hdata.itemindex);
    if (isfield(hdata,"cm"))
        tmpcm=hdata.cm;
    else
        tmpcm=sparse(zeros(numitems,numitems));
    end
    hdata.cm=cmhost(1,length(hdata.itemindex));
end

function cmtovector()
  global hdata;

  cm=hdata.cm;
  limit=getopt("cmin");
  
  i2=getopt("cmitrations");
  dbg(sprintf("Correlation2 (limit=%f): ",limit));
  for [host, hkey] = hdata
    if (ishost(host))
      si=host.minindex;
      ei=host.maxindex-1;
      tmp=cm(si:ei,si:ei);
      k=1;
      tmpvec=[];
      sortvec=[];
      maxri=1;
      maxci=1; # Index of maximum value in column
      iterations1=0;
      iterations2=0;
      if (!isopt("citerations1"))
        i1=(ei-si)*10;
      else
        i1=getopt("citerations1");
      end
      i2=getopt("citerations2");
      limit=getopt("cmin");
      limitsec=getopt("cmaxtime2");
      timestart=time();
      while (abs(max(max(tmp)))>limit && iterations1<i1 && iterations2<i2 && (time()-timestart)<limitsec)
       iterations1++;
       maxv=max(max(abs(abs(tmp()))));
       [maxri,maxci]=find(abs(tmp)==maxv);
       maxri=maxri(1);
       maxci=maxci(1);
       if (maxv==1)
          if (!mod(iterations1,100))
            dbg2(sprintf("%i/%i(corr=1) ",iterations1,i1));
          end
          if (maxri!=maxci)
            dbg2(sprintf("%s and %s are same data??\n",hdata.itemindex{maxri},hdata.itemindex{maxci}))
          end
          tmp(maxri,maxci)=0;
          tmp(maxci,maxri)=0;
       else
          if (!mod(iterations2,100))
            dbg(sprintf("%i/%i(corr=%f) ",iterations2,i2,maxv));
          end
          iterations2++;
       end
       tmpvec(maxri,maxci)=maxv;
       sortvec(k++,:)=[maxri,maxci,maxv];
       if (maxri!=maxci)
          dbg2(sprintf("%i: %s(%i)<>%s(%i): %f\n",k,hdata.itemindex{maxri},maxri,hdata.itemindex{maxci},maxci,maxv));
       end
       tmp(maxri,maxci)=0;
       tmp(maxci,maxri)=0;
      end
      if (iterations1>=i1 || iterations2>=i2)
        warn(sprintf("More results available, all iterations(%i of %i, %i of %i) looped!\n",iterations1,i1,iterations2,i2));
      end
      dbg2(sprintf("\nmaxv=%f,minv=%f\n",abs(max(max(tmp))),abs(min(min(tmp)))));
      hdata.(hkey).cm=tmpvec;
      hdata.(hkey).sortvec=sortvec;
      k=1;
      tmp=[];
      for i=1:columns(tmpvec)
        for j=1:columns(tmpvec)
            tmp(k++)=tmpvec(i,j);
        end
      end
      hdata.(hkey).cmvec=tmp;
    end
  end
  dbg("\n");
endfunction
