#!/usr/bin/octave -qf

global opt;
opt.pause=1;
addpath("m");
source(file_in_loadpath("monda.lib.m"));
global hdata;
global hashes;

hashes.orig={};
hashes.obfuscated={};

parseopts();
arg_list=getrestopts();

function h=gethash()
    global hashes;

    h="";
    len=getopt("obfuscatelength");
    chars=getopt("obfuscatechars");
    while (length(h)<len)
        idx=round(rand()*(length(chars)-1))+1;
        h=[h,chars{idx}];
    end
end

function o=obfuscate(s)
    global hashes

    o=gethash();
    if (length(hashes.obfuscated)>0)
        while (max(strcmp(o,hashes.obfuscated))>0)
            o=gethash();
        end
    end
    last=length(hashes.orig);
    hashes.orig{last+1}=s;
    hashes.obfuscated{last+1}=o;    
end

function o=deobfuscate(s)
    global hashes

    scmp=strcmp(s,hashes.obfuscated);
    if (max(scmp)==1)
        idx=find(scmp==1);
        o=hashes.orig{idx};
    end
end

function dst=obfuscatedata(src)
    for [host,hkey]=src
        if (ishost(host))
            ohkey=obfuscate(hkey);
            host.hostname=ohkey;
            dst.(ohkey)=host;
            for [item,key]=host
                if (isitem(item))
                    item.key=obfuscate(item.key);
                    dst.(ohkey).(key)=item;
                end
            end
        end
        if (istrigger(host))
            host.expression=obfuscate(host.expression);
            host.description=obfuscate(host.description);
            dst.(hkey)=host;
        end
    end
    dst.cm=src.cm;
    dst.cmvec=src.cmvec;
    dst.sortvec=src.sortvec;
end

function dst=deobfuscatedata(src)
    for [host,hkey]=src
        if (ishost(host))
            ohkey=deobfuscate(hkey);
            host.hostname=ohkey;
            dst.(ohkey)=host;
            for [item,key]=host
                if (isitem(item))
                    item.key=deobfuscate(item.key);
                    dst.(ohkey).(key)=item;
                end
            end
        end
        if (istrigger(host))
            host.expression=deobfuscate(host.expression);
            host.description=deobfuscate(host.description);
            dst.(hkey)=host;
        end
    end
    dst.cm=src.cm;
    dst.cmvec=src.cmvec;
    dst.sortvec=src.sortvec;
end

for i=1:length(arg_list)
    clear("hdata");
    global hdata;
    loaddata(arg_list{i});
    if (isopt("deobfuscate"))
        if (!isopt("obfuscatehashes"))
            setopt("obfuscatehashes",[arg_list{i},"hash"]);
        end      
        warn(sprintf("Loading hashes: %s\n",getopt("obfuscatehashes")));
        load(getopt("obfuscatehashes"));
        hdata=deobfuscatedata(hdata);
        preprocess();
        if (isopt("o"))
            savedata(getopt("o"));
        else
            of="";
            parts=strsplit(arg_list{i},".");
            for i=1:length(parts)-1
                if (strcmp(of,""))
                    of=parts{i};
                else
                    of=[of,".",parts{i}];
                end
            end
            savedata(of);
        end
    else
        if (!isopt("obfuscatehashes"))
            setopt("obfuscatehashes",[arg_list{i},".obfhash"]);
        end      
        hdata=obfuscatedata(hdata);
        preprocess();
        warn(sprintf("Saving hashes: %s\n",getopt("obfuscatehashes")));
        save(getopt("obfuscatehashes"),"hashes");
        savedata([arg_list{i},".obf"]);
    end
end

mexit(0);