#!/bin/bash

###  invoke data collection modules

cd /opt/sensmon/sens-read.d
for mod in ./S*; do
	$mod
done

