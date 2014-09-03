#!/bin/sh

SQLDIR=$(dirname $0)/../sql
unset LANG

psqlc () {
  psql $@ <<EOF
\\conninfo
EOF
}

#You are connected to database "postgres" as user "postgres" via socket in "/var/run/postgresql" at port "5432".
psqladmin () {
  psqlc $@ | grep -q 'user "postgres"'
}

psqlmonda () {
  psqlc $@ | grep -q 'database "monda"'
}

if [ -z "$1" ]; then
  echo "Use $0 init|drop [psql parameters]"
  echo "All data in monda database will be lost!!"
  exit 2
fi

cmd="$1"
shift

if ! psqladmin $@; then
  echo "You are not postgresql admin?"
  exit 2
fi

if [ "$cmd" = "init" ]; then
    psql <$SQLDIR/init_db.sql && psql monda <$SQLDIR/init_schema.sql
    exit
fi

if [ "$cmd" = "drop" ]; then
    psql <$SQLDIR/drop.sql
    exit
fi
