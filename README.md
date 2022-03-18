# mcseed.php

This is a simple script for pre-populating a memcache instance from another.

I use it along with [mcrouter](https://github.com/facebook/mcrouter) 
(replicates writes to all nodes) to provide
a highly available memcache service.

## Why?

Looking on the internet you'll find lots of recommendations to use 
memcached-tool for this. However when I tested this, I found  
t only read a portion of the data. This varied by run from 50-100%

I don't seem to be the only one who sees this. Netflix sponsored a
[change to memcache](https://github.com/memcached/memcached/wiki/ReleaseNotes1431)
which implements a new mechanism for retrieving (most of) the keys.

In testing I get 99.5-100% of the data returned.

## How ?

I wrote a simple shell script and use it in place og memcache-wrapper
in my unit file. It does this....
```bash
  # ensure we are starting from a clean state....
  iptables -D INPUT -p tcp -m tcp ! -d 127.0.0.1 --dport 11211 -j REJECT 2>&1 >>/dev/null
  killall -TERM memcached

  echo "Blocking incoming connections..."
  iptables -I INPUT  -p tcp -m tcp ! -d 127.0.0.1 --dport 11211 -j REJECT

  echo "Starting memcached..."
  /usr/share/memcached/scripts/systemd-memcached-wrapper /etc/memcache/memcached.conf &
  # Give memcached time to start up
  echo "sleeping...."
  sleep 3 

  echo "Replicating data..."
  nc -z -w 1 $REPLICATEHOST $REPLICATEPORT \
    && (php /etc/memcache/mcseed.php ${REPLICATEHOST} ${REPLICATEPORT} 127.0.0.1 11211 )

  echo "Allowing incoming connections ($USR)...."
  iptables -D INPUT -p tcp -m tcp ! -d 127.0.0.1 --dport 11211 -j REJECT
```
