#!/usr/bin/perl -CSDAL

use warnings;
use strict;
use utf8;
use IServ::DB;

my @ids = IServ::DB::SelectCol "SELECT ID FROM scmc_servers";

for (@ids)
{
  print "<VirtualHost *:80>\n";
  print "  Include \"scmc/$_.conf\"\n";
  print "</VirtualHost>\n";
}
