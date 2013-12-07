#!/usr/bin/octave --norc

global opt;
source("monda.lib.m");

opt.pause=1;

global cm;
global hdata;

function net=trainvalues(inputs,outputs)
% inputs: [y1;y2;..yn]
% outputs: [o1;o2;..on]
    [P,pmean,pstd,T,tmean,tstd]=prestd(inputs,outputs);
    net=newff(min_max(P),[rows(inputs),10,rows(outputs)]);  
    VV.P=P;
    VV.T=T;
    net.trainParam.goal = 0.01;
    net=train(net,P,T,[],[],VV);
% [Pout,Tout]=poststd(P,pmean,pstd,T,tmean,tstd);
end

start1=time();

arg_list=getrestopts();
if (length(arg_list)<1)
  fprintf(stderr,"Error in arguments!\n som.m src.az [src2.az] ...\n");
  exit;
end

function i=getiindex(item)
   global indexes;
   
    scmp=strcmp(item,indexes.items);
    if (max(scmp)==1)
        i=(find(scmp==1));
    else
        indexes.items{indexes.lastitem}=item;
        i=indexes.lastitem++;
    end
end

function i=gethindex(host)
   global indexes;
   
    scmp=strcmp(host,indexes.hosts);
    if (max(scmp)==1)
        i=(find(scmp==1));
    else
        indexes.hosts{indexes.lasthost}=host;
        i=indexes.lasthost++;
    end
end

function i=gettindex(tme)
   global indexes;
   
    scmp=strcmp(tme,indexes.tmes);
    if (max(scmp)==1)
        i=(find(scmp==1));
    else
        indexes.tmes{indexes.lasttme}=tme;
        i=indexes.lasttme++;
    end
end

global indexes;

indexes.lastitem=1;
indexes.items={};
indexes.lasthost=1;
indexes.hosts={};
indexes.lasttme=1;
indexes.tmes={};

for i=1:length(arg_list)
  loaddata(arg_list{i},1);
  for [host,hkey] = hdata
      if (ishost(host))
        dkey=sprintf("%s_%s",hkey,xdate2(hdata.time_from));
        hdata.(dkey)=host;
        hdata=rmfield(hdata,hkey);
      end
  end
end

hostsinfo();
exit;

D=som_data_struct(Dh,'comp_names',indexes.hosts);
M=som_make(D);
som_show(M);

mexit(0);

#i1=finditem('joanes:net.if.in[eth0]');
#i2=finditem('joanes:net.if.out[eth0]');
#i3=finditem('jovado:net.if.in[eth0]');
#i4=finditem('jovado:net.if.out[eth0]');
#D=[i1.yn(s:e);i2.yn(s:e);i3.yn(s:e);i4.yn(s:e)]';
#size(cm.joanes)
#size(cm.jovado)


j=1;
for [host, hkey]=hdata
    if (ishost(host))
        D(j++,:)=host.cmvec;
    end
end


exit

sm=som_make(D);

#som_lininit(D); 
#som_normalize(D);
som_show(sm);

pause();

exit;




