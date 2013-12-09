#!/usr/bin/octave -qf

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

src=arg_list{1};
loaddata(src,1);

hostsinfo(hdata);

lasthost=1;
lasttime=1;
if (isopt("somtimerange"))
    for i=hdata.minx:getopt("somtimerange"):hdata.maxx
        sx=i;
        ex=i+getopt("somtimerange");
        cmkey=cmatrix(sx,ex);
        times{lasttime++}=cmkey;
    end
else
    for [host,hkey]=hdata
        if (ishost(host))
            Dh(:,lasthost)=host.cmvec;
            hosts{lasthost++}=hkey;
        end
    end
end

D=som_data_struct(Dh,'comp_names',hosts)
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




