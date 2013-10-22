
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

function [out]=snip(in,arg1,varargin)

%% SNIP FUNCTION  %2nd Version, 13.Sept.2013
% Snip something out of a vector or matrix. 
%
% (C) for snip.m function implementation Nicolas Ummen, under BSD2 open source copyright license.
% Comments etc. to NicolasUmmen@web.de
%
% The function snip takes the following arguments:
%
% snip(input,argument_1,argument_2)
%
% %arg_1 = what to snip, arg_2 = for matrices, to define 'r'ow or 'c'olumn
%
% It then removes either the specified kinds of elements from the input, or
% removes the element at the stated position, then compresses the input by as
% much as was deleted out of it.
% 
% LIST OF POSSIBLE ARGUMENT_1s:
% 'x'   -> remove all x's from the input vector, compress it afterwards
% '1'   -> remove all 1's from the input vector, compress it afterwards
% [all other comparable items to the above are possible]
% 1     -> remove THE FIRST element, move all one up, shorten vector by 1
% i     -> remove COMPLEX elements from input, compress it afterwards
% j     -> [same as above] - note that 'i' and 'j' would remove the characters i or j.
% nan   -> remove all nan's, compress it
% inf   -> remove all infs from the input vector, compress it afterwards
%
% etc.
%
% The rest of the possible arguments follows matlab notation, such as:
% last  -> snip off the last element
% :     -> snip from:to
%
% Note that 'partial' deletions, for example getting rid of elements from
% (2,3) to (2,6) in a 7x7 matrix = A, is much better accomplished by overwriting
% them directly as A(2,3:6) = 0; or similar. A compression of the data is
% not possible, as elements remain in e.g. (2,1:2)
%
% ALLOWED DATA TYPES: Everything except self-defined structures/objects.
%
% EXAMPLES:
%
% in = [2 1 3 NaN NaN 1]; snip(in,2)   = [2 3 NaN NaN 1]
% in = [2 1 3 NaN NaN 1]; snip(in,'1') = [2 3 NaN NaN]
% in = [2 1 3 NaN NaN 1]; snip(in,nan) = [2 1 3 1]
% in = [2 1 3 inf inf 1]; snip(in,inf) = [2 1 3 1]
% compl = complex(1,2); in = [2 1 3 compl compl 1]; snip(in,i) = [2 1 3 1]
% in = [2 1 3 NaN NaN 1]; snip(in,'last') = [2 1 3 NaN NaN]
% in = ['abcdefg']; snip(in,'c')   = abdefg
% in = ['abcdefg']; snip(in,'last') = abcdef
% in = ['abcdefg']; snip(in,2)     = acdefg
%
% Whether the input is a row or column vector doesn't matter, and the output
% is returned accordingly.
%
% Advanced vector, snip out a certain, continuous length:
%
% in = [2 1 3 NaN NaN 1]; snip(in,2:4) = [2 NaN 1]
%
% MATRIX EXAMPLES: either snip out completely rows/columns, or identify rows
% and columns that have the same elements throughout and delete these.
% Note: deleting all e.g. 3's therefore leaves 3's in mixed entry columns
% or rows. In some cases using snip.m column or row-wise on the matrix
% might help.
%
% in = [1 2 3; 3 3 3; 4 3 3]; snip(in,2,'r') = [1 2 3; 4 3 3]
% in = [1 2 3; 3 3 3; 4 3 3]; snip(in,2,'c') = [1 3; 3 3; 4 3]
% in = [1 2 3; 3 3 3; 4 3 3; 5 3 3; 6 3 3]; snip(in,2:4,'r') = [1 2 3; 6 3 3]
% in = [1 2 3; 3 3 3; 4 3 3]; snip(in,'1')     = [1 2 3; 3 3 3; 4 3 3]
% Note the above doesn't detect a complete row/column full of 1's...
% 
% in = [1 2 3; 3 3 3; 4 3 3]; snip(in,'3') = [1 2; 4 3]
% While this one detected a row and a column of solely 3's. To overwrite
% all e.g. 3s in the above matrix, rather use find - or logical indexing.
% E.g. in(in == 3) = 0 for the result [1 2 0; 0 0 0; 4 0 0].
%
% in = [1 2 nan; nan nan nan; 4 nan nan]; snip(in,nan) = [1 2; 4 nan]
% in = ['abc'; 'acb'; 'abc']; snip(in,'a') = ['bc','cb','bc']
%
% etc as above for vectors
% 
% For more complicated choices from a matrix; like the first, third and
% seventh column; use the notation A = A(:,[1,3,7])

% Change log:
% 2nd Version: Added infinity and complex number option on request.

%% (C) for snip.m function implementation Nicolas Ummen, under BSD2 open soure copyright license.
% Comments etc. to NicolasUmmen@web.de

% main function body

%scrutinize what the user wants, operate on most likely first
flagflipped = 0; 
if isstruct(in)
   out = in;
   warning('snip function: structures cannot be snipped!')
   return
end

[z s] = size(in);
if z == 0
    out = in;
    warning('snip function: 0x0 input detected!')
    return
end

if z > s
    in = in';
    flagflipped = 1;
    [z s] = size(in);
end

%operate on numerical things first

%if it is a vector
if z == 1 && strcmpi(arg1,'last') %snip end command
    out = in(1,1:end-1);
    if flagflipped == 1
        out = out';
    end
    return
end

if z == 1 && isnan(arg1(1)) %sort nans out
    out = in(~isnan(in));
    if flagflipped == 1
        out = out';
    end
    return
end

if z == 1 && isinf(arg1(1)) %sort infs out
    out = in(~isinf(in));
    if flagflipped == 1
        out = out';
    end
    return
end

if z == 1 && ~isreal(arg1(1)) %sort complex numbers out
    out = in(~imag(in));
    if flagflipped == 1
        out = out';
    end
    return
end

if z == 1 && isnumeric(arg1) %snip out a specific place/length from vector
    if arg1(end) > s
        out = in;
        if flagflipped == 1
            out = out';
        end
        warning('snip function: the vector is too short for that snip!')
        return
    end
    out = [in(1:arg1(1)-1) in(arg1(end)+1:end)];
    if flagflipped == 1
        out = out';
    end
    return
end

if z == 1 && iscell(in) %if it is a cell vector
    if strcmpi(arg1,'last') %snip end command
        out = in{1,1:end-1};
        if flagflipped == 1
            out = out';
        end
        return
    end
    arg1 = str2num(arg1);
    if arg1(end) > s
        out = in;
        if flagflipped == 1
            out = out';
        end
        warning('snip function: the vector is too short for that snip!')
        return
    end
    out = [in{1:arg1(1)-1} in{arg1(end)+1:end}];
    if flagflipped == 1
        out = out';
    end
    return
end

if z == 1 %snip out all objects, like 'a', or '1' from a vector
    if length(str2num(arg1)) == 1
        arg1 = str2num(arg1);
    end
    out = in(in~=arg1);
    if flagflipped == 1
        out = out';
    end
    return
end
%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%

%if it is a matrix

if z >= 1 && isnan(arg1(1)) %snip out all nans
    in = in(:,all(isnan(in),1) ~= 1);
    out = in(all(isnan(in),2) ~= 1,:);
    if flagflipped == 1
        out = out';
    end
    return
end

if z >= 1 && isinf(arg1(1)) %snip out all infs
    in = in(:,all(isinf(in),1) ~= 1);
    out = in(all(isinf(in),2) ~= 1,:);
    if flagflipped == 1
        out = out';
    end
    return
end

if z >= 1 && ~isreal(arg1(1)) %snip out complex numbers
    in = in(:,all(imag(in),1) ~= 1);
    out = in(all(imag(in),2) ~= 1,:);
    if flagflipped == 1
        out = out';
    end
    return
end

if z >= 1 && isempty(varargin) == 1 && ischar(arg1) && ischar(in(1,1)) %snip out all equal elements
    in = in(:,all(in==arg1,1) ~= 1);
    out = in(all(in==arg1,2) ~= 1,:);
    if flagflipped == 1
        out = out';
    end
    return
end

if z >= 1 && isempty(varargin) == 1 %snip out all equal elements
    arg1 = str2num(arg1);
    in = in(:,all(in==arg1,1) ~= 1);
    out = in(all(in==arg1,2) ~= 1,:);
    if flagflipped == 1
        out = out';
    end
    return
end

if z >= 1 && ischar(varargin{1}) %snip out specific row(s) or column(s)
    arg2 = varargin{1};
    if flagflipped == 1
        if strcmp(arg2,'c')
            arg2 = 'r';
        else
            if strcmp(arg2,'r')
            arg2 = 'c';
            end
        end
    end
    switch arg2
        case 'c'
            if arg1(end) > s
                out = in;
                if flagflipped == 1
                    out = out';
                end
                warning('snip function: the matrix is not large enough for this snip!')
                return
            end
            out = [in(:,1:arg1(1)-1) in(:,arg1(end)+1:end)];
        case 'r'
            if arg1(end) > z
                out = in;
                if flagflipped == 1
                    out = out';
                end
                warning('snip function: the matrix is not large enough for this snip!')
                return
            end
            out = [in(1:arg1(1)-1,:); in(arg1(end)+1:end,:)];
    end
    if flagflipped == 1
        out = out';
        return
    end
    return
end

%catch things that didn't go in the above cases

out = in;

if flagflipped == 1
    out = out';
end
warning('snip function: function completed, but no relevant case to execute occured!')
return

end


function normalize()
    global hdata;
    delay=60;

    force_normalize=0;
    for [host, hkey] = hdata
     if (isstruct(host))
      for [item, key] = host
       if (isstruct(item))
         minx=min([hdata.minx,hdata.(hkey).(key).x]);
         maxx=max([hdata.maxx,hdata.(hkey).(key).x]);
         if (hdata.minx!=minx || hdata.maxx!=maxx)
           force_normalize=1;
         end
         hdata.minx=minx;
	 hdata.maxx=maxx;
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
        if (isfield(item,"xn") && !force_normalize) 
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

function itemindex()
    global hdata;
    itemid=1;
    
    for [host, hkey] = hdata
     if (isstruct(host))
      for [item, key] = host
       if (isstruct(item))
         hdata.(hkey).(key).index=itemid;
         hdata.itemhindex{itemid}=hkey;
         hdata.itemkindex{itemid}=key;
         hdata.itemindex{itemid++}=[hkey,":",item.key];
       end
      end
     end
    end
endfunction

function cmatrix()
      global hdata;
      global cm;
      
      itemindex();
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
	    cols=min([columns(item1.xn),columns(item2.xn)]);
	    cm.(hkey)(item1.index,item2.index)=corr(item1.yn(1:cols),item2.yn(1:cols));
	    col2++;
	   end;
	  end;
	  col1++;
	 end;
	end;
	#cm.(hkey)=snip(cm.(hkey),nan);
       end;
      end;
      fprintf(stderr,"\n");
      cmtovector(0.4);
endfunction;

function cmtovector(limit)
  global hdata;
  global cm;
  
  for [host, hkey] = hdata
    if (isstruct(host))
      tmp=cm.(hkey);
      tmpvec=[];
      while abs(max(max(tmp)))>limit
       maxc=0;
       maxi=0;
       for i=1:rows(tmp)
        tmp(i,i)=0;
        [val,idx]=max(abs(tmp(i,:)));
        if (val>maxc)
          maxc=val;
          maxi=i;
          maxidx=idx;
        end
       end
       val=tmp(maxi,maxidx);
       tmpvec(maxi,maxidx)=val;
       fprintf(stdout,"%s(%i)<>%s(%i): %f\n",hdata.itemindex{maxi},maxi,hdata.itemindex{maxidx},maxidx,val);
       tmp(maxi,maxidx)=0;
      end
      hdata.(hkey).cmvec=tmpvec;
    end
  end
endfunction

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
