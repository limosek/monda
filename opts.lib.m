
global globalopts;
globalopts = {"h", "help", "v", "profiling", \
        "delay:","hosts:","items:", "excludeitems:", \
        "cv:","imgformat:","gtoolkit:","interactive", "preprocess", \
        "citerations1:","citerations2:","cmin:","cmaxtime1:","cmaxtime2:"
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
opt.preprocess=3;
# bit1 - removebad
# bit2 - indexes
# bit3 - cm move 
#opt.profiling=0

global opt;

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
    tmpopt=opt;
    while (i<=length(args))
        option = args{i};
        for j=1:length(opts)
           o=strsplit(opts{j},":");
           a=strsplit(args{i},"=");
           aopt=o(1);   # Actual opt parsing without :
           aarg=a(1);  # Actual argument without =
           if (strcmp(strcat("--",aopt),aarg) || strcmp(strcat("-",aopt),aarg))
                if (length(o)>1)
                    if (length(a)>1)
                        tmpopt.(aopt)=strcat(a(2){:});
                        args{i}="_-_";
                    else
                      if (length(args)>i)
                        tmpopt.(aopt)=args{i+1};
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
    if (isfield(tmpopt,"imgformat") && !isfield(tmpopt,"interactive"))
        tmpopt.interactive=0;
    else
        tmpopt.interactive=1;
    end
    if (!tmpopt.interactive)
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
    o=opt;
end

function r=isopt(o)
    global opt
    r=(isfield(opt,o));
end

function r=getopt(o)
    global opt
    if (isfield(opt,o))
        if (!isempty(opt.(o)) && isdigit(opt.(o)(1)))
            r=str2double(opt.(o));
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
