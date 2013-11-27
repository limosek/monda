
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
	dbg2(sprintf("%s(%i,%i)>%i ",item.key,cols,cols2,cols3));
        hdata.(hkey).(key).yn=interp1(item.x,item.y,xn);
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

function cmatrix()
      global hdata;

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
	col1=1;
	for [item1, key1] = host
	if (isitem(item1))
	  col2=1;
          dbg2(sprintf("%s(%i/%i secs),",item1.key,time()-timestart,maxtime));
 	  for [item2, key2] = host
	   if (isitem(item2))
             if ((time()-timestart)>maxtime)
                warn(sprintf("Overtime!Ignoring %s "));
                continue;
             end
            c=corr(item1.yn(1:100),item2.yn(1:100));
	    tmpcm(item1.index,item2.index)=c;
	    col2++;
	   end;
	  end;
	  col1++;
	 end;
	end;
       end;
      end;
      hdata.cm=tmpcm;
      dbg("\n");
endfunction;

function cmtovector()
  global hdata;

  cm=hdata.cm;
  limit=getopt("cmin");
  
  i2=getopt("cmitrations");
  dbg(sprintf("Correlation2 (limit=%f): ",limit));
  for [host, hkey] = hdata
    if (ishost(host))
      k=1;
      tmp=cm;
      tmpvec=[];
      sortvec=[];
      maxri=1;
      maxci=1; # Index of maximum value in column
      iterations1=0;
      iterations2=0;
      if (!isopt("citerations1"))
        i1=columns(tmp)*10;
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
            dbg2(sprintf("%i/%i(corr=1) ",iterations1/i1));
          end
          if (maxri!=maxci)
            dbg2(sprintf("%s and %s are same data??\n",hdata.itemindex{maxri},hdata.itemindex{maxci}))
          end
          tmp(maxri,maxci)=0;
       else
          if (!mod(iterations2,100))
            dbg(sprintf("%i/%i(corr=%f) ",iterations2,i2,maxv));
          end
          iterations2++;
       end
       tmpvec(maxri,maxci)=maxv;
       sortvec(k++,:)=[maxri,maxci];
       if (maxri!=maxci)
          dbg2(sprintf(stdout,"%i: %s(%i)<>%s(%i): %f\n",k,hdata.itemindex{maxri},maxri,hdata.itemindex{maxci},maxci,maxv));
       end
       tmp(maxri,maxci)=0;
      end
      if (iterations1>=i1 || iterations2>=i2)
        warn(sprintf("More results available, all iterations(%i of %i, %i of %i) looped!\n",iterations1,i1,iterations2,i2));
      end
      dbg2(sprintf("\nmaxv=%f,minv=%f\n",abs(max(max(tmp))),abs(min(min(tmp)))));
      hdata.(hkey).cmvec=tmpvec;
      hdata.(hkey).sortvec=sortvec;
    end
  end
  dbg("\n");
endfunction
