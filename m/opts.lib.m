
global globalopts;
globalopts = { \
        "h", "help", "v", "f", "debug", "profiling", "pause", "o:", \
        "asciilevels*", "mintime:", "maxtime:", \
        "delay:", "hosts*", "items*", \
        "excludehosts:", "excludeitems*", "baditems", "shareditems", \
        "cv:","imgformat:", "gtoolkit:", "interactive", "preprocess:", \
        "citerations1:", "citerations2:", "cmin:", "cmaxtime1:", "cmaxtime2:", \
        "somhosts", "somitems", "hostplot", "cmplot", "corrplot", \
        "imgsizex:", "imgsizey:", \
        "obfuscatelength:", "obfuscatechars*", "deobfuscate", "obfuscatehashes:" \
        };
opt.delay=60;
#opt.hosts=
#opt.items=
opt.cv=0.01;
#opt.imgformat="png";
opt.gtoolkit="gnuplot";
opt.imgdpi="600";
opt.imgsizex="1024";
opt.imgsizey="768";
opt.maxplots="10";
#opt.citerations1="100";
opt.citerations2="200";
opt.cmin="0.6";
opt.cmaxtime1="1000";
opt.cmaxtime2="1000";
#opt.excludeitems={"key","key2"};
opt.v=0;
opt.preprocess=15;
# bit1 - removebad
# bit2 - indexes
# bit3 - cm move
# bit4 - repair old version 
#opt.profiling=0
#opt.pause=1;
opt.sompertime=1;
opt.asciilevels={"<","\\","0","/",">"};
#opt.o="file"
#opt.somtimerange=3600*8;
opt.obfuscatelength=6;
opt.obfuscatechars={"ali","eli","ato","eti","uli","wut","kul","tul","bul","buk","res","ces","cis"};
for i=toascii("a"):toascii("z")
    opt.obfuscatechars{length(opt.obfuscatechars)+1}=sprintf("%c",i);
end

global opt;

function o=itemescape(i)
    o=strrep(i,'[','\[');
    o=strrep(o,']','\]');
end

function out=multiopt(opt,o,value)
    o=o{:};
    if (length(value)>0 && value(1)=="{")
        eval(sprintf("value=%s;",value));
    else
        value={value};
    end
    if (isfield(opt,o))
        opt.(o)=[opt.(o),value];
    else
        opt.(o)=value;
    end
    out=opt;
end

function r=strtotime(str,tme)
    r=str;
    if (ischar(str) && length(str)>1)
        if (str(1)=="+")
            r=tme+str2num(str);
        end
        if (str(1)=="-")
            r=tme-str2num(str);
        end
        if (index(str,":"))
            o=strsplit(str,":");
            ts=localtime(tme);
            ts.hour=str2num(o(1){:});
            ts.min=str2num(o(2){:});
            r=mktime(ts);
        end
    end
end

function o=parseopts(opts)
    global globalopts;
    global opt;

    i = 1;
    args=argv();
    
    if (nargin()==1)
        opts=[globalopts,opts];
    else
        opts=globalopts;
    end
    if (isfield(opt,"parsed"))
        o=opt;
        return;
    end
    tmpopt=opt;
    while (i<=length(args))
        #fprintf(stdout,"Parsing args (%i/%i)\r",i,length(args));
        if (!strcmp(args{i}(1),"-"))
            i++;
            continue;
        end
        option = args{i};
        
        for j=1:length(opts)
           o=strsplit(opts{j},":");
           om=strsplit(opts{j},"*");
           if (length(om)>1)
                o=om;
                multi=1;
           else
                multi=0;
           end
           a=strsplit(args{i},"=");
           aopt=o(1);   # Actual opt parsing without :
           aarg=a(1);  # Actual argument without =
           if (strcmp(strcat("--",aopt),aarg) || strcmp(strcat("-",aopt),aarg))
                if (length(o)>1)
                    if (length(a)>1)
                        if (multi)
                            tmpopt=multiopt(tmpopt,aopt,a(2));
                        else
                            tmpopt.(aopt)=strcat(a(2){:});
                        end
                        args{i}="_-_";
                    else
                      if (length(args)>i)
                        if (multi)
                            tmpopt=multiopt(tmpopt,aopt,args{i+1});
                        else
                            tmpopt.(aopt)=args{i+1};
                        end
                        args{i}="_-_";
                        args{i+1}="_-_";
                        i++;
                      else
                          fprintf(stderr,"Missing argument for --%s!\n",opts{j});
                          exit;
                      end
                    end
                else
                    if (!isfield(tmpopt,aopt))
                        tmpopt.(aopt)=1;
                    else
                        tmpopt.(aopt)++;
                    end
                    args{i}="_-_";
                end
           end
        end
        i++;
    endwhile
    if (!isfield(tmpopt,"imgformat") && !isfield(tmpopt,"interactive"))
        tmpopt.interactive=1;
    end
    if (!isfield(tmpopt,"interactive"))
        tmpopt.maxplots=1000;
    end
    if (isfield(tmpopt,"profiling"))
        profile("on");
    end
    tmpopt.rest={args{!strcmp("_-_",args)}};
    if (isfield(tmpopt,"h")||isfield(tmpopt,"help"))
        fprintf(stdout,"Some usefull help\n\n");
        i=1;
        while (i<=length(opts))
            k=opts{i};
            if (isfield(opt,k) && (isreal(opt.(k))||ischar(opt.(k))))
                o=num2str(opt.(k));
                fprintf(stdout,"--%s (default %s)\n",k,o);
            else
                if (ischar(k))
                fprintf(stdout,"--%s\n",strcat(k));
                end
            end
            i++;
        end
        exit;
    end
    opt=tmpopt;
    if (isopt("excludeitems"))
        opt.excludeitems=itemescape(getopt("excludeitems"));
    end
    if (isopt("items"))
        opt.items=itemescape(getopt("items"));
    end
    opt.parsed=1;
    o=opt;
end

function r=isopt(o)
    global opt
    r=(isfield(opt,o));
end

function r=getopt(o)
    global opt
    if (isfield(opt,o))
        if (!isempty(opt.(o)) && !iscell(opt.(o)) && isdigit(opt.(o)(1)))
            r=str2double(opt.(o));
            if (isnan(r))
                r=opt.(o);
            end
        else
            r=opt.(o);
        end
    else
        r="";
    end
end

function setopt(o,v)
    global opt  
    opt.(o)=v;
end

function o=getrestopts()
    global opt;
    o=opt.rest;
end
