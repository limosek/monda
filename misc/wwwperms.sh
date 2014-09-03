#!/bin/sh

TDIR=$(dirname $0)/../temp/web
LDIR=$(dirname $0)/../log/web

unset LANG

if id www-data >/dev/null 2>/dev/null; then
  WWW_USER=www-data
  WWW_GROUP=www-data
  else
    if id apache >/dev/null 2>/dev/null; then
      WWW_USER=apache
      WWW_GROUP=apache
    fi
fi

if [ -z "$WWW_USER" ] || [ -z "$WWW_GROUP" ]; then
  echo "Unknown web server user. Please specify effective user and group of your http server."
  echo "WWW_USER=someuser WWW_GROUP=somegroup $0"
fi

echo "Changing owner of $TDIR and $LDIR to $WWW_USER:$WWW_GROUP.\nYou will need sudo password."

sudo mkdir -p $TDIR $LDIR
sudo chown -R "$WWW_USER.$WWW_GROUP" $TDIR $LDIR
sudo chmod 770 $TDIR $LDIR

