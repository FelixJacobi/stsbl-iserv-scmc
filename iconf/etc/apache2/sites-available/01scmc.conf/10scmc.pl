#!/usr/bin/perl -CSDAL

use warnings;
use strict;
use utf8;
use IServ::DB;

my @ids = IServ::DB::SelectCol "SELECT ID FROM scmc_servers";
my $port = qx(dpkg-query --showformat='\${Status}' --show iserv-server-nginx 2> /dev/null) =~
    /install (hold|ok) (installed|unpacked)/ ? "980" : "80";

for (@ids)
{
  print "<VirtualHost *:$port>\n";
  print "  Include \"scmc/$_.conf\"\n";
  print "</VirtualHost>\n";
}
