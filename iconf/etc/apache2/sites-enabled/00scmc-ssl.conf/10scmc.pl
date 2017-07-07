#!/usr/bin/perl -CSDAL

use warnings;
use strict;
use utf8;
use IServ::DB;

my @ids = IServ::DB::SelectCol "SELECT ID FROM scmc_servers";

for (@ids)
{
  print "<VirtualHost *:443>\n";
  print "  SSLEngine on\n";
  print "  SSLProtocol all -SSLv2 -SSLv3\n";
  print "\n";
  print "  # Enable HSTS - stick to https after first successful https connect\n";
  print "  # http://en.wikipedia.org/wiki/HTTP_Strict_Transport_Security\n";
  print "  Header always set Strict-Transport-Security: \"max-age=15768000\"\n";
  print "\n";
  print "  Include \"scmc/$_.conf\"\n";
  print "</VirtualHost>\n";
}
