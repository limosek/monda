
function normalize(delay)
    global hdata;
    minx=0; maxx=0;
    for [host, hkey] = hdata
      if (isstruct(host))
      for [item, key] = host
	if (isstruct(item))
	  if (minx==0) minx=min(item.x); end;
	  if (maxx==0) maxx=max(item.x); end;
	  if (minx>min(item.x)) minx=min(item.x); end;
	  if (maxx<max(item.x)) maxx=max(item.x); end;
	end;
      end;
      end;
    end;
    startx=round(minx/delay)*delay;
    endx=round(maxx/delay)*delay;
    for [host, hkey] = hdata
     if (isstruct(host))
      fprintf(stderr,"\nNormalize %s (start=%s(%i),stop=%s(%i),values=%i):\n",hkey,strftime("%Y-%m-%d %H:%M:%S",localtime(startx)),startx,strftime("%Y-%m-%d %H:%M:%S",localtime(endx)),endx,round((endx-startx)/delay));
      for [item, key] = host
       if (isstruct(item))
	cols=columns(item.x);
	cols2=columns(item.y);
	fprintf(stderr,"%s(%i,%i)\n",item.key,cols,cols2);
	p(1,:)=[0,item.y(1)];
	for col=1:round(cols/2)-1
	    p(col+1,:)=polyfit(item.x(col*2:col*2+1),item.y(col*2:col*2+1),1);
	end;
	p(end+1,:)=[0,item.y(end)];
	p(end+1,:)=[0,item.y(end)];
	col=1;
	hdata.(hkey).(key).xn=[startx:delay:endx];
	hdata.(hkey).(key).yn=[];
	for x=hdata.(hkey).(key).xn
	    if (col<=size(p))
	      hdata.(hkey).(key).yn(end+1)=x*p(round(col),1)+p(round(col),2);
	    else
	      hdata.(hkey).(key).yn(end+1)=hdata.(hkey).(key).yn(end);
	    end;
	    col+=0.5;
	end;
       end;
      end;
    end;
    end;
    fprintf(stderr,"\n\n");
endfunction;

function smatrix()
      global hdata;
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
       if (isstruct(host))
	fprintf(stderr,"%s\n",hkey);
	col1=1; 
	for [item1, key1] = host
	 if (isstruct(item1))
	  col2=1;
	  for [item2, key2] = host
	   if (isstruct(item2))
	    #fprintf(stderr,"%s(%s)<>%s(%s)\n",item1.key,key1,item2.key,key2);
	    cols=columns(item1.xn)-2;
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
