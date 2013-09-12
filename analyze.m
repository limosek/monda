#!/usr/bin/octave -qf

function normalize(delay)
    global history;
    minx=0; maxx=0;
    for [host, hkey] = history
      for [item, key] = host
	if (minx==0) minx=min(item.x); end;
	if (maxx==0) maxx=max(item.x); end;
        if (minx>min(item.x)) minx=min(item.x); end;
        if (maxx<max(item.x)) maxx=max(item.x); end;
      end;
    end;
    startx=round(minx/delay)*delay;
    endx=round(maxx/delay)*delay;
    for [host, hkey] = history
      printf("\nNormalize %s (start=%s(%i),stop=%s(%i),values=%i): ",hkey,strftime("%Y-%m-%d %H:%M:%S",localtime(startx)),startx,strftime("%Y-%m-%d %H:%M:%S",localtime(endx)),endx,round((endx-startx)/delay));
      for [item, key] = host
	cols=columns(item.x);
	cols2=columns(item.y);
	printf("%s(%i,%i) ",item.key,cols,cols2);
	p(1,:)=[0,item.y(1)];
	for col=1:round(cols/2)-1
	    p(col+1,:)=polyfit(item.x(col*2:col*2+1),item.y(col*2:col*2+1),1);
	    #printf("%i %i %i %i\n",item.x(col),item.x(col+2),startx,endx);
	end;
	p(end+1,:)=[0,item.y(end)];
	p(end+1,:)=[0,item.y(end)];
	col=1;
	history.(hkey).(key).xn=[startx:delay:endx];
	for x=history.(hkey).(key).xn
	    history.(hkey).(key).yn(end+1)=x*p(round(col),1)+p(round(col),2);
	    col+=0.5;
	end;
     end;
    end;
    printf("\n");
endfunction;

function smatrix()
      global history;
      printf("Statistics: ");
      for [host, hkey] = history
        printf("%s ",hkey);
	for [item, key] = host
		history.(hkey).(key).std=std(item.y);
		history.(hkey).(key).stdn=std(item.yn);
		history.(hkey).(key).max=max(item.y);
		history.(hkey).(key).maxn=max(item.yn);
		history.(hkey).(key).min=min(item.y);
		history.(hkey).(key).minn=min(item.yn);
		history.(hkey).(key).var=var(item.y);
		history.(hkey).(key).varn=var(item.yn);
		history.(hkey).(key).delta=max(item.y)-min(item.y);
		history.(hkey).(key).deltan=max(item.yn)-min(item.yn);
		history.(hkey).(key).range=range(item.y);
		history.(hkey).(key).rangen=range(item.yn);
		history.(hkey).(key).avg=mean(item.y);
		history.(hkey).(key).avgn=mean(item.yn);
		history.(hkey).(key).median=median(item.y);
		history.(hkey).(key).mediann=median(item.yn);
		history.(hkey).(key).mode=mode(item.y);
		history.(hkey).(key).moden=mode(item.yn);
	end;
      end;
      printf("\n");
endfunction;

function cmatrix()
      global history;
      global cm;
      printf("Correlation: ");
      for [host, hkey] = history
        printf("%s ",hkey);
        col1=1; 
	for [item1, key1] = host
	  col2=1;
	  for [item2, key2] = host
	    cols=columns(item1.xn)-2;
	    cm.(hkey)(col1,col2)=corr(item1.yn(1:cols),item2.yn(1:cols));
	    col2++;
	  end;
	  col1++;
        end;
      end;
      printf("\n");
endfunction;

function hostplot(host)
      items=1;
      i=1;
      for [item, key] = host
	items++;
      end;
      for [item, key] = host
	subplot(items,2,i++);
	plot(item.xn,item.yn);
	subplot(items,2,i++);
	plot(item.xn,item.yn);
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

global cm;

normalize(60);
smatrix;
cmatrix;
save("-binary", "-");
exit;

fig=1;
for [ host, hkey ] = history
  figure(fig++);
  hostplot(host);
  figure(fig++);
  cmplot(cm.(hkey));
end;

pause();





