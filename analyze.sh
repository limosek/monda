#!/bin/sh

(./gethistory.php -Hdonalisa -i'net.if.in\[eth0,bytes\]|net.if.out\[eth0,bytes\]|system.cpu.load|proc.num\[,,run\]|vfs.fs.size\[/,free\]|vfs.fs.size\[\/,used\]' -h-4hours -S; cat analyze.m) >donalisa.out
